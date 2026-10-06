<?php
/**
 * Saved searches: `{prefix}th_search_alerts`.
 *
 * One row per saved search: the canonical archive parameters (Archive\Search::params(), JSON), how often to send
 * (instant | daily | weekly | off), the last send and the newest listing already announced, and a random token for
 * the unsubscribe link. user_id 0 = an e-mail-only alert taken over from rtcl-search-alert (guests could save
 * there); new alerts need an account.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\SearchAlerts;

use Torrehub\Core\Installer;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table owned by this module: only the fixed table name is interpolated, every value is prepared.

/**
 * Saved-search storage.
 */
final class Store {

	public const FREQUENCIES  = array( 'instant', 'daily', 'weekly', 'off' );
	public const MAX_PER_USER = 20;

	/**
	 * Table name.
	 */
	public static function table(): string {
		return Installer::table( 'search_alerts' );
	}

	/**
	 * Stable fingerprint of a parameter set (one alert per search per user).
	 *
	 * @param array<string,mixed> $params Parameters.
	 */
	public static function hash( array $params ): string {
		unset( $params['orderby'], $params['view'], $params['page'] );
		self::ksort_deep( $params );
		return md5( (string) wp_json_encode( $params ) );
	}

	/**
	 * Recursive ksort.
	 *
	 * @param array<string,mixed> $a Array.
	 */
	private static function ksort_deep( array &$a ): void {
		ksort( $a );
		foreach ( $a as &$v ) {
			if ( is_array( $v ) ) {
				self::ksort_deep( $v );
			}
		}
	}

	/**
	 * One alert.
	 *
	 * @param int $id Alert id.
	 */
	public static function get( int $id ): ?object {
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) );
		return $row ? $row : null;
	}

	/**
	 * A user's alert for a parameter set.
	 *
	 * @param int    $user_id User.
	 * @param string $hash    Fingerprint.
	 */
	public static function find( int $user_id, string $hash ): ?object {
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d AND hash = %s", $user_id, $hash ) );
		return $row ? $row : null;
	}

	/**
	 * A user's alerts, newest first.
	 *
	 * @param int $user_id User.
	 * @return array<int,object>
	 */
	public static function for_user( int $user_id ): array {
		global $wpdb;
		$t = self::table();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d ORDER BY id DESC", $user_id ) );
	}

	/**
	 * Count of a user's alerts.
	 *
	 * @param int $user_id User.
	 */
	public static function count( int $user_id ): int {
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE user_id = %d", $user_id ) );
	}

	/**
	 * Active alerts of a frequency.
	 *
	 * @param string $frequency instant|daily|weekly.
	 * @return array<int,object>
	 */
	public static function active( string $frequency ): array {
		global $wpdb;
		$t = self::table();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE frequency = %s ORDER BY id ASC", $frequency ) );
	}

	/**
	 * Create an alert.
	 *
	 * @param array<string,mixed> $data user_id, email, label, params, frequency.
	 * @return int Alert id (0 on failure).
	 */
	public static function create( array $data ): int {
		global $wpdb;
		$params = (array) ( $data['params'] ?? array() );
		$now    = current_time( 'mysql', true );
		$ok     = $wpdb->insert(
			self::table(),
			array(
				'user_id'      => (int) ( $data['user_id'] ?? 0 ),
				'email'        => (string) ( $data['email'] ?? '' ),
				'label'        => mb_substr( (string) ( $data['label'] ?? '' ), 0, 190 ),
				'params'       => (string) wp_json_encode( $params ),
				'hash'         => self::hash( $params ),
				'frequency'    => in_array( $data['frequency'] ?? '', self::FREQUENCIES, true ) ? $data['frequency'] : 'daily',
				'last_sent'    => $data['last_sent'] ?? $now,
				'last_post_id' => (int) ( $data['last_post_id'] ?? self::newest_listing_id() ),
				'token'        => bin2hex( random_bytes( 16 ) ),
				'created_at'   => $data['created_at'] ?? $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update columns of an alert.
	 *
	 * @param int                 $id   Alert id.
	 * @param array<string,mixed> $data Columns.
	 */
	public static function update( int $id, array $data ): void {
		global $wpdb;
		$wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Delete an alert.
	 *
	 * @param int $id Alert id.
	 */
	public static function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Delete a user's alerts (account deleted — GDPR).
	 *
	 * @param int $user_id User.
	 */
	public static function forget_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'user_id' => $user_id ), array( '%d' ) );
	}

	/**
	 * Highest published listing id (a new alert only announces what comes after it).
	 */
	public static function newest_listing_id(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'rtcl_listing'" );
	}

	/**
	 * Decoded parameters of an alert.
	 *
	 * @param object $alert Alert row.
	 * @return array<string,mixed>
	 */
	public static function params( object $alert ): array {
		$params = json_decode( (string) $alert->params, true );
		return is_array( $params ) ? $params : array();
	}

	/**
	 * Where to send: the account's address, else the stored one (imported guest alerts).
	 *
	 * @param object $alert Alert row.
	 */
	public static function recipient( object $alert ): string {
		$user = $alert->user_id ? get_userdata( (int) $alert->user_id ) : null;
		return $user ? (string) $user->user_email : (string) $alert->email;
	}
}
