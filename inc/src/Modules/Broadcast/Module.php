<?php
/**
 * Messages to all users (client request 2026-11): Users › Notify all users — subject and message, optional roles,
 * preview, test to yourself; sent in batches by WP-Cron (batch size and interval set on the page), with progress and
 * errors logged, resumable after an interruption, never twice to the same person (see Queue). Plus an optional,
 * dismissible notice in the account sidebar for logged-in users ("Terms updated on …", see Notice).
 *
 * DECISION: only accounts that can log in get the e-mail (not "e-mail not confirmed", "awaiting approval",
 * "rejected"). The message is plain text (paragraphs, line breaks); {name} becomes the user's display name.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Broadcast;

use Torrehub\Core\Mailer;
use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Broadcast module.
 */
final class Module extends BaseModule {

	public const OPTION = 'th_broadcast';

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'broadcast';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Messages to all users', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Users › Notify all users: e-mail to every user (or chosen roles), sent in batches; optional notice in the account sidebar.', 'torrehub' );
	}

	/**
	 * Tables.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array(
			'broadcasts'      => 'CREATE TABLE {table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				subject varchar(200) NOT NULL,
				body text NOT NULL,
				roles varchar(255) NOT NULL DEFAULT \'\',
				status varchar(20) NOT NULL,
				total int(10) unsigned NOT NULL DEFAULT 0,
				sent int(10) unsigned NOT NULL DEFAULT 0,
				failed int(10) unsigned NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				finished_at datetime NULL DEFAULT NULL,
				log longtext NOT NULL,
				PRIMARY KEY  (id)
			) {charset};',
			'broadcast_queue' => 'CREATE TABLE {table} (
				campaign_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				status varchar(10) NOT NULL,
				error varchar(255) NOT NULL DEFAULT \'\',
				updated_at datetime NOT NULL,
				PRIMARY KEY  (campaign_id,user_id),
				KEY campaign_status (campaign_id,status)
			) {charset};',
		);
	}

	/**
	 * Schema version.
	 */
	public function schema_version(): int {
		return 1;
	}

	/**
	 * Settings with defaults: batch size, minutes between batches.
	 *
	 * @return array{batch:int,interval:int}
	 */
	public static function settings(): array {
		$s = (array) get_option( self::OPTION, array() );
		return array(
			'batch'    => max( 1, min( 500, (int) ( $s['batch'] ?? 25 ) ) ),
			'interval' => max( 1, min( 60, (int) ( $s['interval'] ?? 5 ) ) ),
		);
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( Queue::HOOK, array( Queue::class, 'run' ) );
		( new Notice() )->register();
		if ( is_admin() ) {
			( new Admin() )->register();
		}
	}

	/**
	 * Message parts (shared by the e-mail, the preview and the test).
	 *
	 * @param string $subject Subject.
	 * @param string $body    Plain text.
	 * @param string $name    Recipient's name ({name}).
	 * @return array{0:string,1:array<int,string>,2:array<int,string>|null,3:string,4:array<string,mixed>}
	 */
	public static function parts( string $subject, string $body, string $name ): array {
		$body  = str_replace( '{name}', $name, str_replace( "\r", '', $body ) );
		$split = preg_split( '/\n\s*\n/', $body );
		$paras = array_values( array_filter( array_map( 'trim', is_array( $split ) ? $split : array() ), static fn( $p ) => '' !== $p ) );
		/* translators: %s: site name */
		$why = sprintf( __( 'You receive this because you have an account on %s.', 'torrehub' ), wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
		return array( $subject, $paras, null, $why, array( 'footer' => array( __( 'My account', 'torrehub' ), th_rtcl_page_url( 'myaccount', '/my-account/' ) ) ) );
	}

	/**
	 * Send to one user.
	 *
	 * @param \WP_User $user    Recipient.
	 * @param string   $subject Subject.
	 * @param string   $body    Message.
	 */
	public static function send( \WP_User $user, string $subject, string $body ): bool {
		list( $heading, $paras, $button, $foot, $extra ) = self::parts( $subject, $body, $user->display_name );
		return Mailer::send( $user->user_email, $subject, $heading, $paras, $button, $foot, $extra );
	}
}
