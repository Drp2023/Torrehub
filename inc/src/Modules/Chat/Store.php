<?php
/**
 * Chat storage: `{prefix}th_chat_threads` (one per listing + buyer) and `{prefix}th_chat_messages`.
 * Times are stored in GMT. Each side has an unread counter (badge) and a last-read message id ("Seen").
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Chat;

use Torrehub\Core\Installer;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom tables owned by this module: only the fixed table / column names are interpolated, every value is prepared.

/**
 * Chat threads and messages.
 */
final class Store {

	public const MAX_LENGTH = 2000;

	/**
	 * Table names.
	 *
	 * @return array{threads:string,messages:string}
	 */
	public static function tables(): array {
		return array(
			'threads'  => Installer::table( 'chat_threads' ),
			'messages' => Installer::table( 'chat_messages' ),
		);
	}

	/**
	 * One thread.
	 *
	 * @param int $id Thread id.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$t   = self::tables()['threads'];
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		return $row ? $row : null;
	}

	/**
	 * Existing thread for a listing + buyer.
	 *
	 * @param int $listing_id Listing.
	 * @param int $buyer_id   Buyer.
	 */
	public static function find( int $listing_id, int $buyer_id ): ?object {
		global $wpdb;
		$t   = self::tables()['threads'];
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE listing_id = %d AND buyer_id = %d", $listing_id, $buyer_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * The user's side of a thread.
	 *
	 * @param object $thread  Thread row.
	 * @param int    $user_id User.
	 * @return string buyer|seller|''
	 */
	public static function side( object $thread, int $user_id ): string {
		if ( $user_id && (int) $thread->buyer_id === $user_id ) {
			return 'buyer';
		}
		return $user_id && (int) $thread->seller_id === $user_id ? 'seller' : '';
	}

	/**
	 * Other participant.
	 *
	 * @param object $thread  Thread row.
	 * @param int    $user_id Me.
	 */
	public static function other( object $thread, int $user_id ): int {
		return 'buyer' === self::side( $thread, $user_id ) ? (int) $thread->seller_id : (int) $thread->buyer_id;
	}

	/**
	 * Clean a message body: plain text, trimmed, length-capped.
	 *
	 * @param string $body Raw body.
	 */
	public static function clean( string $body ): string {
		$body = trim( sanitize_textarea_field( $body ) );
		return mb_substr( $body, 0, self::MAX_LENGTH );
	}

	/**
	 * Start (or reuse) the buyer's thread about a listing and add the first message.
	 *
	 * @param int    $listing_id Listing.
	 * @param int    $buyer_id   Buyer.
	 * @param string $body       Message.
	 * @return array{thread:int,message:int}|\WP_Error
	 */
	public static function start( int $listing_id, int $buyer_id, string $body ) {
		$post = get_post( $listing_id );
		if ( ! $post || 'rtcl_listing' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'th_chat_listing', __( 'This listing isn’t available any more.', 'torrehub' ) );
		}
		$seller = (int) $post->post_author;
		if ( ! $seller || $seller === $buyer_id ) {
			return new \WP_Error( 'th_chat_self', __( 'You can’t message yourself.', 'torrehub' ) );
		}
		$thread = self::find( $listing_id, $buyer_id );
		if ( ! $thread ) {
			global $wpdb;
			$now = current_time( 'mysql', true );
			$ok  = $wpdb->insert(
				self::tables()['threads'],
				array(
					'listing_id' => $listing_id,
					'buyer_id'   => $buyer_id,
					'seller_id'  => $seller,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);
			if ( ! $ok ) {
				return new \WP_Error( 'th_chat_db', __( 'The message couldn’t be sent. Try again.', 'torrehub' ) );
			}
			$thread = self::get( (int) $wpdb->insert_id );
		}
		$message = self::send( (int) $thread->id, $buyer_id, $body );
		if ( is_wp_error( $message ) ) {
			return $message;
		}
		return array(
			'thread'  => (int) $thread->id,
			'message' => $message,
		);
	}

	/**
	 * Add a message to a thread.
	 *
	 * @param int    $thread_id Thread.
	 * @param int    $sender_id Sender (must be a participant).
	 * @param string $body      Message.
	 * @return int|\WP_Error Message id.
	 */
	public static function send( int $thread_id, int $sender_id, string $body ) {
		$thread = self::get( $thread_id );
		$side   = $thread ? self::side( $thread, $sender_id ) : '';
		if ( ! $side ) {
			return new \WP_Error( 'th_chat_forbidden', __( 'This conversation isn’t yours.', 'torrehub' ) );
		}
		$body = self::clean( $body );
		if ( '' === $body ) {
			return new \WP_Error( 'th_chat_empty', __( 'Write a message first.', 'torrehub' ) );
		}
		global $wpdb;
		$tables = self::tables();
		$now    = current_time( 'mysql', true );
		$ok     = $wpdb->insert(
			$tables['messages'],
			array(
				'thread_id'  => $thread_id,
				'sender_id'  => $sender_id,
				'body'       => $body,
				'created_at' => $now,
			),
			array( '%d', '%d', '%s', '%s' )
		);
		if ( ! $ok ) {
			return new \WP_Error( 'th_chat_db', __( 'The message couldn’t be sent. Try again.', 'torrehub' ) );
		}
		$id    = (int) $wpdb->insert_id;
		$other = 'buyer' === $side ? 'seller' : 'buyer';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table + column names are fixed strings.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tables['threads']} SET updated_at = %s, last_message_id = %d, {$other}_unread = {$other}_unread + 1, {$side}_read_id = %d, buyer_deleted = 0, seller_deleted = 0 WHERE id = %d",
				$now,
				$id,
				$id,
				$thread_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		/**
		 * A chat message was stored (e-mail notification, real-time push…).
		 *
		 * @param int    $id        Message id.
		 * @param object $thread    Thread row (before this message).
		 * @param int    $sender_id Sender.
		 */
		do_action( 'th_chat_message_sent', $id, $thread, $sender_id );
		return $id;
	}

	/**
	 * The user's threads, newest activity first.
	 *
	 * @param int $user_id User.
	 * @param int $limit   Max rows.
	 * @return array<int,object>
	 */
	public static function threads( int $user_id, int $limit = 50 ): array {
		global $wpdb;
		$t = self::tables();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names.
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT th.*, m.body AS last_body, m.sender_id AS last_sender, m.created_at AS last_at
				FROM {$t['threads']} th LEFT JOIN {$t['messages']} m ON m.id = th.last_message_id
				WHERE ( th.buyer_id = %d AND th.buyer_deleted = 0 ) OR ( th.seller_id = %d AND th.seller_deleted = 0 )
				ORDER BY th.updated_at DESC, th.id DESC LIMIT %d",
				$user_id,
				$user_id,
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Messages of a thread after an id (polling), oldest first.
	 *
	 * @param int $thread_id Thread.
	 * @param int $after     Last message id the client has.
	 * @param int $limit     Max rows (the newest $limit when $after = 0).
	 * @return array<int,object>
	 */
	public static function messages( int $thread_id, int $after = 0, int $limit = 100 ): array {
		global $wpdb;
		$m = self::tables()['messages'];
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		if ( $after ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$m} WHERE thread_id = %d AND id > %d ORDER BY id ASC LIMIT %d", $thread_id, $after, $limit ) );
		}
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$m} WHERE thread_id = %d ORDER BY id DESC LIMIT %d", $thread_id, $limit ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		return array_reverse( $rows );
	}

	/**
	 * Mark the thread read for one side.
	 *
	 * @param object $thread  Thread row.
	 * @param int    $user_id Reader.
	 */
	public static function mark_read( object $thread, int $user_id ): void {
		$side = self::side( $thread, $user_id );
		if ( ! $side ) {
			return;
		}
		global $wpdb;
		$t = self::tables()['threads'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table + column names are fixed strings.
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET {$side}_unread = 0, {$side}_read_id = last_message_id WHERE id = %d", (int) $thread->id ) );
	}

	/**
	 * Unread messages across the user's threads (header / bottom-nav badge, dashboard tile).
	 *
	 * @param int $user_id User.
	 */
	public static function unread( int $user_id ): int {
		if ( ! $user_id ) {
			return 0;
		}
		global $wpdb;
		$t = self::tables()['threads'];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN buyer_id = %d AND buyer_deleted = 0 THEN buyer_unread WHEN seller_id = %d AND seller_deleted = 0 THEN seller_unread ELSE 0 END), 0) FROM {$t} WHERE buyer_id = %d OR seller_id = %d", $user_id, $user_id, $user_id, $user_id ) );
	}

	/**
	 * Hide a thread for one side (it comes back when a new message arrives).
	 *
	 * @param object $thread  Thread row.
	 * @param int    $user_id User.
	 */
	public static function hide( object $thread, int $user_id ): void {
		$side = self::side( $thread, $user_id );
		if ( $side ) {
			global $wpdb;
			$wpdb->update(
				self::tables()['threads'],
				array(
					"{$side}_deleted" => 1,
					"{$side}_unread"  => 0,
				),
				array( 'id' => (int) $thread->id ),
				array( '%d', '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Remove everything of a deleted user (GDPR): their threads (both sides) and messages.
	 *
	 * @param int $user_id User.
	 */
	public static function forget_user( int $user_id ): void {
		global $wpdb;
		$t   = self::tables();
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['threads']} WHERE buyer_id = %d OR seller_id = %d", $user_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $ids ) {
			$in = implode( ',', array_map( 'absint', $ids ) ); // Integers only.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- absint list.
			$wpdb->query( "DELETE FROM {$t['messages']} WHERE thread_id IN ({$in})" );
			$wpdb->query( "DELETE FROM {$t['threads']} WHERE id IN ({$in})" );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Median first-reply time of a seller in seconds over the last 90 days (null below 3 samples).
	 * Used for "Typical reply within …" (hidden by default, Customizer).
	 *
	 * @param int $seller_id Seller.
	 */
	public static function typical_reply( int $seller_id ): ?int {
		global $wpdb;
		$t = self::tables();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT TIMESTAMPDIFF(SECOND, first_in.at, MIN(r.created_at))
				FROM ( SELECT m.thread_id, MIN(m.created_at) at FROM {$t['messages']} m INNER JOIN {$t['threads']} th ON th.id = m.thread_id
					WHERE th.seller_id = %d AND m.sender_id = th.buyer_id AND m.created_at > %s GROUP BY m.thread_id ) first_in
				INNER JOIN {$t['messages']} r ON r.thread_id = first_in.thread_id AND r.sender_id = %d AND r.created_at >= first_in.at
				GROUP BY first_in.thread_id, first_in.at",
				$seller_id,
				gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ),
				$seller_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$rows = array_map( 'intval', $rows );
		if ( count( $rows ) < 3 ) {
			return null;
		}
		sort( $rows );
		return $rows[ intdiv( count( $rows ), 2 ) ];
	}
}
