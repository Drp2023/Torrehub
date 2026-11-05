<?php
/**
 * Campaign storage and the batched sender.
 *
 * - A campaign snapshots its recipients when it starts: one queue row per user (primary key campaign + user — a
 *   user can't be queued twice).
 * - Each WP-Cron run takes the lock, claims up to "batch size" queued rows one by one (queued → sending, only if
 *   still queued), sends, and marks them sent / failed; then schedules the next run after "interval" minutes.
 * - Nobody gets a campaign twice: a row is claimed before its e-mail goes out. A row left in "sending" (the request
 *   died mid-send) becomes "unknown" — it is never resent automatically, as it may have arrived. "Retry failed"
 *   only re-queues rows wp_mail() refused (they were not handed over).
 * - Paused or interrupted campaigns continue where they stopped (Resume, or the next run).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Broadcast;

use Torrehub\Core\Installer;
use Torrehub\Core\Mailer;
use Torrehub\Modules\Auth\Accounts;

defined( 'ABSPATH' ) || exit;

/**
 * Broadcast queue.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own tables, fixed names.
 */
final class Queue {

	public const HOOK   = 'th_broadcast_batch';
	public const LOCK   = 'th_broadcast_lock';
	private const STALE = 15 * MINUTE_IN_SECONDS; // A row still "sending" after this was interrupted.

	/**
	 * Table names.
	 *
	 * @return array{campaigns:string,queue:string}
	 */
	public static function tables(): array {
		return array(
			'campaigns' => Installer::table( 'broadcasts' ),
			'queue'     => Installer::table( 'broadcast_queue' ),
		);
	}

	/* ------------------------------------------------------------------ recipients */

	/**
	 * Users a campaign goes to: the chosen roles (none = everyone), accounts that can log in (not unconfirmed,
	 * awaiting approval or rejected).
	 *
	 * @param array<int,string> $roles Role slugs.
	 * @return array<int,int> User ids.
	 */
	public static function recipients( array $roles ): array {
		$args = array(
			'fields'  => 'ID',
			'orderby' => 'ID',
		);
		if ( $roles ) {
			$args['role__in'] = $roles;
		}
		$ids = array_map( 'intval', get_users( $args ) );
		return array_values( array_filter( $ids, static fn( $id ) => Accounts::ACTIVE === Accounts::status( $id ) ) );
	}

	/* ------------------------------------------------------------------ campaigns */

	/**
	 * Create a campaign, queue its recipients and schedule the first batch.
	 *
	 * @param string            $subject Subject.
	 * @param string            $body    Message (plain text).
	 * @param array<int,string> $roles   Roles (none = everyone).
	 * @return int Campaign id (0 = nothing to send).
	 */
	public static function create( string $subject, string $body, array $roles ): int {
		global $wpdb;
		$users = self::recipients( $roles );
		if ( ! $users ) {
			return 0;
		}
		$t   = self::tables();
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$t['campaigns'],
			array(
				'subject'    => $subject,
				'body'       => $body,
				'roles'      => implode( ',', $roles ),
				'status'     => 'running',
				'total'      => count( $users ),
				'created_by' => get_current_user_id(),
				'created_at' => $now,
				'log'        => '[]',
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( ! $id ) {
			return 0;
		}
		foreach ( array_chunk( $users, 500 ) as $chunk ) {
			$values = implode( ',', array_map( static fn( $u ) => $wpdb->prepare( '(%d,%d,%s,%s)', $id, $u, 'queued', $now ), $chunk ) );
			$wpdb->query( "INSERT IGNORE INTO {$t['queue']} (campaign_id,user_id,status,updated_at) VALUES {$values}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- values prepared above.
		}
		/* translators: 1: number of users, 2: user name */
		self::log( $id, sprintf( __( 'Started for %1$d users by %2$s.', 'torrehub' ), count( $users ), wp_get_current_user()->display_name ) );
		self::schedule( $id, 0 );
		return $id;
	}

	/**
	 * One campaign.
	 *
	 * @param int $id Campaign.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$t   = self::tables()['campaigns'];
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
		return $row ? $row : null;
	}

	/**
	 * Newest campaigns.
	 *
	 * @param int $limit Rows.
	 * @return array<int,object>
	 */
	public static function all( int $limit = 20 ): array {
		global $wpdb;
		$t = self::tables()['campaigns'];
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d", $limit ) );
	}

	/**
	 * Queue rows per status.
	 *
	 * @param int $id Campaign.
	 * @return array<string,int>
	 */
	public static function counts( int $id ): array {
		global $wpdb;
		$out = array_fill_keys( array( 'queued', 'sending', 'sent', 'failed', 'unknown', 'skipped' ), 0 );
		$t   = self::tables()['queue'];
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) n FROM {$t} WHERE campaign_id = %d GROUP BY status", $id ) ) as $row ) {
			$out[ $row->status ] = (int) $row->n;
		}
		return $out;
	}

	/**
	 * Rows that need attention (failed / unknown / skipped).
	 *
	 * @param int $id Campaign.
	 * @return array<int,object>
	 */
	public static function problems( int $id ): array {
		global $wpdb;
		$t = self::tables()['queue'];
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, status, error, updated_at FROM {$t} WHERE campaign_id = %d AND status IN ('failed','unknown','skipped') ORDER BY updated_at DESC LIMIT 200", $id ) );
	}

	/**
	 * Set the status.
	 *
	 * @param int    $id     Campaign.
	 * @param string $status running|paused|cancelled|done.
	 */
	public static function set_status( int $id, string $status ): void {
		global $wpdb;
		$data = array( 'status' => $status );
		if ( in_array( $status, array( 'done', 'cancelled' ), true ) ) {
			$data['finished_at'] = current_time( 'mysql', true );
		}
		$wpdb->update( self::tables()['campaigns'], $data, array( 'id' => $id ) );
		if ( 'running' === $status ) {
			self::schedule( $id, 0 );
		} else {
			wp_clear_scheduled_hook( self::HOOK, array( $id ) );
		}
	}

	/**
	 * Put the failed rows (wp_mail refused them) back in the queue.
	 *
	 * @param int $id Campaign.
	 */
	public static function retry_failed( int $id ): int {
		global $wpdb;
		$t = self::tables()['queue'];
		$n = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'queued', error = '' WHERE campaign_id = %d AND status = 'failed'", $id ) );
		if ( $n ) {
			/* translators: %d: number of e-mails */
			self::log( $id, sprintf( _n( '%d failed e-mail queued again.', '%d failed e-mails queued again.', $n, 'torrehub' ), $n ) );
			self::set_status( $id, 'running' );
		}
		return $n;
	}

	/**
	 * Add a log line (newest kept: 100).
	 *
	 * @param int    $id   Campaign.
	 * @param string $text Text.
	 */
	public static function log( int $id, string $text ): void {
		global $wpdb;
		$c   = self::get( $id );
		$log = $c ? (array) json_decode( (string) $c->log, true ) : array();
		array_unshift( $log, array( time(), $text ) );
		$wpdb->update( self::tables()['campaigns'], array( 'log' => wp_json_encode( array_slice( $log, 0, 100 ) ) ), array( 'id' => $id ) );
	}

	/**
	 * Next run in $minutes (0 = now).
	 *
	 * @param int $id      Campaign.
	 * @param int $seconds Delay.
	 */
	public static function schedule( int $id, int $seconds ): void {
		wp_clear_scheduled_hook( self::HOOK, array( $id ) );
		wp_schedule_single_event( time() + $seconds, self::HOOK, array( $id ) );
	}

	/* ------------------------------------------------------------------ sending */

	/**
	 * Take the lock (one batch at a time, site-wide). A lock older than 10 minutes is a crashed run's.
	 */
	private static function lock(): bool {
		if ( add_option( self::LOCK, time(), '', false ) ) {
			return true;
		}
		if ( (int) get_option( self::LOCK ) < time() - 10 * MINUTE_IN_SECONDS ) {
			delete_option( self::LOCK );
			return add_option( self::LOCK, time(), '', false );
		}
		return false;
	}

	/**
	 * One batch (WP-Cron, or "Send next batch now").
	 *
	 * @param int $id Campaign.
	 * @return array{sent:int,failed:int,left:int}|null Null when it didn't run (busy, not running).
	 */
	public static function run( int $id ): ?array {
		global $wpdb;
		$c = self::get( $id );
		if ( ! $c || 'running' !== $c->status ) {
			return null;
		}
		$settings = Module::settings();
		if ( ! self::lock() ) {
			self::schedule( $id, MINUTE_IN_SECONDS ); // Another batch is running: try again shortly.
			return null;
		}
		$t    = self::tables();
		$now  = current_time( 'mysql', true );
		$sent = 0;
		$fail = 0;
		try {
			// Interrupted rows: may have been delivered — never resent.
			$stale = (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$t['queue']} SET status = 'unknown', error = %s, updated_at = %s WHERE campaign_id = %d AND status = 'sending' AND updated_at < %s",
					__( 'Interrupted while sending — not resent, as it may have arrived.', 'torrehub' ),
					$now,
					$id,
					gmdate( 'Y-m-d H:i:s', time() - self::STALE )
				)
			);
			if ( $stale ) {
				/* translators: %d: number of e-mails */
				self::log( $id, sprintf( _n( '%d e-mail was interrupted while sending (not resent).', '%d e-mails were interrupted while sending (not resent).', $stale, 'torrehub' ), $stale ) );
			}
			$users = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$t['queue']} WHERE campaign_id = %d AND status = 'queued' ORDER BY user_id LIMIT %d", $id, $settings['batch'] ) ) );
			foreach ( $users as $user_id ) {
				$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$t['queue']} SET status = 'sending', updated_at = %s WHERE campaign_id = %d AND user_id = %d AND status = 'queued'", current_time( 'mysql', true ), $id, $user_id ) );
				if ( 1 !== (int) $claimed ) {
					continue; // Someone else took it.
				}
				$user = get_userdata( $user_id );
				if ( ! $user || ! is_email( $user->user_email ) ) {
					self::mark( $id, $user_id, 'skipped', __( 'User deleted or without a valid e-mail address.', 'torrehub' ) );
					continue;
				}
				if ( Module::send( $user, (string) $c->subject, (string) $c->body ) ) {
					self::mark( $id, $user_id, 'sent' );
					++$sent;
				} else {
					self::mark( $id, $user_id, 'failed', Mailer::last_error() ? Mailer::last_error() : __( 'The mail server refused the message.', 'torrehub' ) );
					++$fail;
				}
			}
		} finally {
			delete_option( self::LOCK );
		}

		$counts = self::counts( $id );
		$wpdb->update(
			$t['campaigns'],
			array(
				'sent'   => $counts['sent'],
				'failed' => $counts['failed'] + $counts['unknown'] + $counts['skipped'],
			),
			array( 'id' => $id )
		);
		if ( $sent || $fail ) {
			/* translators: 1: sent, 2: failed, 3: still queued */
			self::log( $id, sprintf( __( 'Batch: %1$d sent, %2$d failed, %3$d left.', 'torrehub' ), $sent, $fail, $counts['queued'] ) );
		}
		$current = self::get( $id );
		if ( $counts['queued'] > 0 ) {
			if ( $current && 'running' === $current->status ) {
				self::schedule( $id, $settings['interval'] * MINUTE_IN_SECONDS );
			}
		} elseif ( $current && 'running' === $current->status ) {
			self::set_status( $id, 'done' );
			self::log( $id, __( 'Finished.', 'torrehub' ) );
		}
		return array(
			'sent'   => $sent,
			'failed' => $fail,
			'left'   => $counts['queued'],
		);
	}

	/**
	 * Mark a row.
	 *
	 * @param int    $id      Campaign.
	 * @param int    $user_id User.
	 * @param string $status  sent|failed|skipped.
	 * @param string $error   Reason.
	 */
	private static function mark( int $id, int $user_id, string $status, string $error = '' ): void {
		global $wpdb;
		$wpdb->update(
			self::tables()['queue'],
			array(
				'status'     => $status,
				'error'      => mb_substr( $error, 0, 250 ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'campaign_id' => $id,
				'user_id'     => $user_id,
			)
		);
	}
}
