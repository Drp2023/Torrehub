<?php
/**
 * Saved searches + e-mail alerts — replaces the rtcl-search-alert add-on.
 *
 * - "Save this search" next to the archive heading (`th_archive_head_actions`): name + how often (instant, daily,
 *   weekly). Needs an account (guests go to the login page and come back).
 * - Account › Saved searches: frequency (or off), open the results, delete.
 * - A listing going live is stamped (`th_published_at`) — approval keeps the original post date. Instant alerts run
 *   a minute later (single cron event), daily / weekly digests from an hourly cron check (≤ 10 listings + "See all").
 * - Every e-mail has a signed one-click unsubscribe link (also as List-Unsubscribe / -Post headers).
 * - Own listings are never announced to their author. Deleting an account deletes its alerts (GDPR).
 *
 * DECISION: at most 20 saved searches per account; daily/weekly digests go out at the hour the alert was saved
 * (rounded to the hourly cron). Real system cron recommended (runbook).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\SearchAlerts;

use Torrehub\Core\Mailer;
use Torrehub\Core\Module as BaseModule;
use Torrehub\Modules\Archive\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Search alerts module.
 */
final class Module extends BaseModule {

	public const ENDPOINT = 'alerts';
	private const CRON    = 'th_search_alerts_digest';
	private const INSTANT = 'th_search_alerts_instant';

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'search-alerts';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Saved searches & alerts', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Members save a search and get new matching listings by e-mail (instantly, daily or weekly).', 'torrehub' );
	}

	/**
	 * Needs Classified Listing.
	 */
	public function requirements_met(): bool {
		return th_has_rtcl();
	}

	/**
	 * Requirement message.
	 */
	public function requirement_message(): string {
		return __( 'Requires the Classified Listing plugin.', 'torrehub' );
	}

	/**
	 * Table.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array(
			'search_alerts' => 'CREATE TABLE {table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				email varchar(190) NOT NULL DEFAULT \'\',
				label varchar(190) NOT NULL DEFAULT \'\',
				params longtext NOT NULL,
				hash char(32) NOT NULL,
				frequency varchar(10) NOT NULL DEFAULT \'daily\',
				last_sent datetime NULL DEFAULT NULL,
				last_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				token char(32) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY user_hash (user_id,hash),
				KEY frequency (frequency)
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
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'th_archive_head_actions', array( $this, 'button' ) );
		th_on_front_post( 'th_save_search', array( $this, 'save' ) );
		th_on_front_post( 'th_alert_update', array( $this, 'update' ) );
		th_on_front_post( 'th_alert_delete', array( $this, 'delete' ) );

		add_filter( 'rtcl_my_account_endpoint', array( $this, 'endpoint' ) );
		add_filter( 'th_account_sections', array( $this, 'section' ) );
		add_action( 'rtcl_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_filter( 'th_account_lean_sections', static fn( $keys ) => array_merge( (array) $keys, array( self::ENDPOINT ) ) );

		add_action( 'transition_post_status', array( $this, 'stamp_published' ), 10, 3 );
		add_action( self::INSTANT, array( $this, 'run_instant' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::CRON, array( $this, 'run_digests' ) );

		add_action( 'template_redirect', array( $this, 'unsubscribe' ), 1 );
		add_action( 'delete_user', array( Store::class, 'forget_user' ) );
	}

	/* ------------------------------------------------------------------ urls */

	/**
	 * Account › Saved searches URL.
	 */
	public static function url(): string {
		return \Rtcl\Helpers\Link::get_account_endpoint_url( self::ENDPOINT );
	}

	/**
	 * Signed unsubscribe (or re-subscribe) link.
	 *
	 * @param object $alert Alert row.
	 * @param string $what  off|on.
	 */
	public static function token_url( object $alert, string $what = 'off' ): string {
		return add_query_arg(
			array(
				'th_alert_' . ( 'on' === $what ? 'on' : 'off' ) => (int) $alert->id,
				't' => (string) $alert->token,
			),
			home_url( '/' )
		);
	}

	/**
	 * Results URL of an alert (newest first).
	 *
	 * @param object $alert Alert row.
	 */
	public static function results_url( object $alert ): string {
		return Search::build_url( Store::params( $alert ) );
	}

	/* ------------------------------------------------------------------ archive button */

	/**
	 * "Save this search" next to the archive heading.
	 *
	 * @param Search $search Current search.
	 */
	public function button( $search ): void {
		if ( ! $search instanceof Search ) {
			return;
		}
		$params = self::clean_params( $search->params() );
		get_template_part(
			'template-parts/archive/save-search',
			null,
			array(
				'search'   => $search,
				'params'   => $params,
				'existing' => is_user_logged_in() ? Store::find( get_current_user_id(), Store::hash( $params ) ) : null,
			)
		);
	}

	/**
	 * Parameters worth saving (no page, view or order).
	 *
	 * @param array<string,mixed> $params Search parameters.
	 * @return array<string,mixed>
	 */
	public static function clean_params( array $params ): array {
		unset( $params['page'], $params['view'], $params['orderby'] );
		return $params;
	}

	/**
	 * Return address after a POST (same site only).
	 *
	 * @param string $fallback Fallback URL.
	 */
	private static function back( string $fallback ): string {
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce first.
		return wp_validate_redirect( $back, $fallback );
	}

	/**
	 * POST: save the current search.
	 */
	public function save(): void {
		check_admin_referer( 'th_save_search' );
		$back = self::back( th_url_listings() );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( th_url_login( $back ) );
			exit;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$raw       = isset( $_POST['params'] ) ? json_decode( wp_unslash( (string) $_POST['params'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- re-parsed by Search::from_array().
		$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : 'daily';
		$label     = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		// phpcs:enable
		$params    = self::clean_params( Search::from_array( is_array( $raw ) ? $raw : array() )->params() );
		$frequency = in_array( $frequency, array( 'instant', 'daily', 'weekly' ), true ) ? $frequency : 'daily';
		$label     = '' !== $label ? $label : Matcher::summary( $params );
		$user      = get_current_user_id();
		$existing  = Store::find( $user, Store::hash( $params ) );
		if ( $existing ) {
			Store::update(
				(int) $existing->id,
				array(
					'label'     => mb_substr( $label, 0, 190 ),
					'frequency' => $frequency,
				)
			);
		} elseif ( Store::count( $user ) >= Store::MAX_PER_USER ) {
			wp_safe_redirect( add_query_arg( 'th_alert', 'limit', $back ) );
			exit;
		} else {
			Store::create(
				array(
					'user_id'   => $user,
					'label'     => $label,
					'params'    => $params,
					'frequency' => $frequency,
				)
			);
		}
		wp_safe_redirect( add_query_arg( 'th_alert', 'saved', remove_query_arg( 'th_save', $back ) ) );
		exit;
	}

	/**
	 * Alert of the current user from a POST, or exit.
	 *
	 * @param string $nonce Nonce action prefix.
	 */
	private static function own_alert( string $nonce ): object {
		$id = isset( $_POST['alert'] ) ? absint( $_POST['alert'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified next.
		check_admin_referer( $nonce . $id );
		$alert = Store::get( $id );
		if ( ! $alert || ! is_user_logged_in() || get_current_user_id() !== (int) $alert->user_id ) {
			wp_safe_redirect( add_query_arg( 'th_alert', 'missing', self::url() ) );
			exit;
		}
		return $alert;
	}

	/**
	 * POST: change frequency / label.
	 */
	public function update(): void {
		$alert = self::own_alert( 'th_alert_update_' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in own_alert().
		$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : (string) $alert->frequency;
		$label     = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : (string) $alert->label;
		// phpcs:enable
		$data = array(
			'frequency' => in_array( $frequency, Store::FREQUENCIES, true ) ? $frequency : (string) $alert->frequency,
			'label'     => mb_substr( '' !== $label ? $label : (string) $alert->label, 0, 190 ),
		);
		// Switching back on: don't announce what went live while it was off.
		if ( 'off' === $alert->frequency && 'off' !== $data['frequency'] ) {
			$data['last_sent']    = current_time( 'mysql', true );
			$data['last_post_id'] = Store::newest_listing_id();
		}
		Store::update( (int) $alert->id, $data );
		wp_safe_redirect( add_query_arg( 'th_alert', 'updated', self::url() ) );
		exit;
	}

	/**
	 * POST: delete.
	 */
	public function delete(): void {
		$alert = self::own_alert( 'th_alert_delete_' );
		Store::delete( (int) $alert->id );
		wp_safe_redirect( add_query_arg( 'th_alert', 'deleted', self::url() ) );
		exit;
	}

	/* ------------------------------------------------------------------ account */

	/**
	 * Register the account endpoint.
	 *
	 * @param array<string,string> $endpoints Endpoints.
	 * @return array<string,string>
	 */
	public function endpoint( $endpoints ) {
		$endpoints                   = (array) $endpoints;
		$endpoints[ self::ENDPOINT ] = self::ENDPOINT;
		return $endpoints;
	}

	/**
	 * Account navigation entry.
	 *
	 * @param array<string,array> $sections Sections.
	 * @return array<string,array>
	 */
	public function section( $sections ) {
		$sections                   = (array) $sections;
		$sections[ self::ENDPOINT ] = array( __( 'Saved searches', 'torrehub' ), 'bell' );
		return $sections;
	}

	/**
	 * Flush rewrite rules once after the endpoint was added.
	 */
	public function maybe_flush_rewrites(): void {
		if ( '1' !== get_option( 'th_alerts_rewrite' ) ) {
			flush_rewrite_rules( false );
			update_option( 'th_alerts_rewrite', '1' );
		}
	}

	/**
	 * The account section.
	 */
	public function render(): void {
		get_template_part( 'template-parts/account/alerts', null, array( 'alerts' => Store::for_user( get_current_user_id() ) ) );
	}

	/* ------------------------------------------------------------------ sending */

	/**
	 * Stamp a listing when it goes live; queue the instant alerts.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function stamp_published( $new_status, $old_status, $post ): void {
		// DECISION: a renewed listing (ended → live) isn't new — free, unlimited renewals mustn't re-announce it.
		if ( 'publish' !== $new_status || in_array( $old_status, array( 'publish', 'rtcl-expired' ), true ) || ! $post instanceof \WP_Post || 'rtcl_listing' !== $post->post_type ) {
			return;
		}
		update_post_meta( $post->ID, Matcher::PUBLISHED, time() );
		// A minute later: terms and meta of the listing are saved by then.
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::INSTANT, array( (int) $post->ID ) );
	}

	/**
	 * Hourly digest check.
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON );
		}
	}

	/**
	 * Instant alerts for one new listing.
	 *
	 * @param int $post_id Listing.
	 */
	public function run_instant( $post_id ): void {
		$post_id = (int) $post_id;
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}
		foreach ( Store::active( 'instant' ) as $alert ) {
			if ( $post_id <= (int) $alert->last_post_id ) {
				continue;
			}
			$found = Matcher::find( Store::params( $alert ), 0, (int) $alert->last_post_id, 1, array( $post_id ) );
			$posts = self::not_own( $found['posts'], (int) $alert->user_id );
			if ( $posts && self::send( $alert, $posts, count( $posts ) ) ) {
				Store::update(
					(int) $alert->id,
					array(
						'last_sent'    => current_time( 'mysql', true ),
						'last_post_id' => $post_id,
					)
				);
			}
		}
	}

	/**
	 * Daily and weekly digests that are due.
	 */
	public function run_digests(): void {
		$intervals = array(
			'daily'  => DAY_IN_SECONDS,
			'weekly' => WEEK_IN_SECONDS,
		);
		foreach ( $intervals as $frequency => $interval ) {
			foreach ( Store::active( $frequency ) as $alert ) {
				$last = $alert->last_sent ? (int) strtotime( $alert->last_sent . ' UTC' ) : (int) strtotime( $alert->created_at . ' UTC' );
				// Five minutes' slack: the hourly cron never fires at exactly the same second.
				if ( time() - $last < $interval - 5 * MINUTE_IN_SECONDS ) {
					continue;
				}
				self::digest( $alert, $last );
			}
		}
	}

	/**
	 * Send one digest (if anything is new) and move the window on.
	 *
	 * @param object $alert Alert row.
	 * @param int    $since Unix time of the previous window.
	 */
	public static function digest( object $alert, int $since ): bool {
		$found = Matcher::find( Store::params( $alert ), $since, (int) $alert->last_post_id, 10 );
		$posts = self::not_own( $found['posts'], (int) $alert->user_id );
		$sent  = $posts && self::send( $alert, $posts, max( count( $posts ), $found['total'] ) );
		$data  = array( 'last_sent' => current_time( 'mysql', true ) );
		if ( $posts ) {
			$data['last_post_id'] = max( (int) $alert->last_post_id, max( array_map( static fn( $p ) => (int) $p->ID, $posts ) ) );
		}
		Store::update( (int) $alert->id, $data );
		return $sent;
	}

	/**
	 * Drop the alert owner's own listings.
	 *
	 * @param array<int,\WP_Post> $posts   Listings.
	 * @param int                 $user_id Alert owner.
	 * @return array<int,\WP_Post>
	 */
	private static function not_own( array $posts, int $user_id ): array {
		return array_values( array_filter( $posts, static fn( $p ) => ! $user_id || (int) $p->post_author !== $user_id ) );
	}

	/**
	 * The alert e-mail.
	 *
	 * @param object              $alert Alert row.
	 * @param array<int,\WP_Post> $posts Listings (≤ 10).
	 * @param int                 $total All matches.
	 */
	public static function send( object $alert, array $posts, int $total ): bool {
		$to = Store::recipient( $alert );
		if ( ! is_email( $to ) ) {
			return false;
		}
		$label = '' !== (string) $alert->label ? (string) $alert->label : Matcher::summary( Store::params( $alert ) );
		$items = array();
		foreach ( $posts as $post ) {
			$card    = th_listing_card_args( $post );
			$meta    = array_filter( array( $card ? trim( $card['price'] . ' ' . $card['price_suffix'] ) : '', $card ? (string) $card['location'] : '' ) );
			$items[] = array( html_entity_decode( get_the_title( $post ), ENT_QUOTES ), implode( ' · ', $meta ), (string) get_permalink( $post ) );
		}
		$subject = 1 === $total
			/* translators: %s: saved search name */
			? sprintf( __( 'New listing for “%s”', 'torrehub' ), $label )
			/* translators: 1: number of listings, 2: saved search name */
			: sprintf( __( '%1$s new listings for “%2$s”', 'torrehub' ), number_format_i18n( $total ), $label );
		$often = array(
			'instant' => __( 'You get an e-mail as soon as a matching listing goes live.', 'torrehub' ),
			'daily'   => __( 'You get at most one e-mail a day for this search.', 'torrehub' ),
			'weekly'  => __( 'You get at most one e-mail a week for this search.', 'torrehub' ),
		);
		$off   = self::token_url( $alert );
		return Mailer::send(
			$to,
			$subject,
			$subject,
			array( (string) ( $often[ $alert->frequency ] ?? '' ) ),
			array( __( 'See all results', 'torrehub' ), self::results_url( $alert ) ),
			$alert->user_id ? __( 'Change how often you get these in your account, under Saved searches.', 'torrehub' ) : '',
			array(
				'items'   => $items,
				'footer'  => array( __( 'Stop these e-mails', 'torrehub' ), $off ),
				'headers' => array( 'List-Unsubscribe: <' . esc_url_raw( $off ) . '>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click' ),
			)
		);
	}

	/* ------------------------------------------------------------------ unsubscribe */

	/**
	 * `?th_alert_off={id}&t={token}` (link or one-click POST) and `?th_alert_on=…` (undo).
	 */
	public function unsubscribe(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- the per-alert secret token is the authorisation.
		$off = isset( $_GET['th_alert_off'] ) ? absint( $_GET['th_alert_off'] ) : 0;
		$on  = isset( $_GET['th_alert_on'] ) ? absint( $_GET['th_alert_on'] ) : 0;
		$tok = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : '';
		// phpcs:enable
		$id = $off ? $off : $on;
		if ( ! $id ) {
			return;
		}
		$alert = Store::get( $id );
		$valid = $alert && '' !== $tok && hash_equals( (string) $alert->token, $tok );
		$post  = 'post' === sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );
		if ( $valid && $off ) {
			Store::update( $id, array( 'frequency' => 'off' ) );
		} elseif ( $valid && $on && 'off' === $alert->frequency && $post ) {
			Store::update(
				$id,
				array(
					'frequency'    => 'daily',
					'last_sent'    => current_time( 'mysql', true ),
					'last_post_id' => Store::newest_listing_id(),
				)
			);
		}
		if ( $post && $off ) {
			// RFC 8058 one-click: the mail client only needs a 2xx.
			status_header( $valid ? 200 : 404 );
			exit;
		}
		nocache_headers();
		status_header( $valid ? 200 : 404 );
		get_header();
		get_template_part(
			'template-parts/alerts/unsubscribed',
			null,
			array(
				'alert' => $valid ? Store::get( $id ) : null,
				'state' => $valid ? ( $off ? 'off' : 'on' ) : 'invalid',
			)
		);
		get_footer();
		exit;
	}
}
