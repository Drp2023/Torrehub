<?php
/**
 * Listing lifetime and renewal (client decision 2026-10-08):
 * - A listing runs for a number of days set per account type (Private Seller 15, Business Seller 30; Appearance ›
 *   Torrehub › Listing lifetime), counted from when it goes live. Classified Listing's hourly cron ends it
 *   (status `rtcl-expired`); the theme only sets the dates.
 * - The owner renews an ended listing, or one ending within the reminder window, with one click in My listings or
 *   from the reminder e-mail's signed link: the same number of days again, free, as often as they like.
 * - A reminder e-mail goes out the given number of days (3) before the end, once per period.
 *
 * Everything is free (the quota module stays off, no payments). Paid packages later: `th_listing_lifetime_days`,
 * `th_listing_can_renew` and the `th_listing_renewed` action are the extension points.
 *
 * DECISION: listings of staff accounts (administrators, editors — not seller accounts) never expire by default
 * (site-owned information such as Public information / Tourist attractions); configurable.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Lifetime;

use Torrehub\Core\Mailer;
use Torrehub\Core\Module as BaseModule;
use Torrehub\Core\Settings;
use Torrehub\Modules\Auth\Accounts;

defined( 'ABSPATH' ) || exit;

/**
 * Lifetime module.
 */
final class Module extends BaseModule {

	public const OPTION = 'th_lifetime';
	public const CRON   = 'th_listing_expiry_notices';

	/** Expiry date the reminder was sent for (a renewal changes it, so the next period gets its own e-mail). */
	public const NOTICE = 'th_expiry_notice';

	/**
	 * Listing whose dates are being set in this request (Classified Listing's default-duration filter has no id).
	 *
	 * @var int
	 */
	private static int $context = 0;

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'lifetime';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Listing lifetime & renewal', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Listings run a set number of days per account type; owners renew them for free with one click; reminder e-mail before the end.', 'torrehub' );
	}

	/**
	 * Always on (client decision).
	 */
	public function optional(): bool {
		return false;
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

	/* ------------------------------------------------------------------ settings */

	/**
	 * Settings with defaults.
	 *
	 * @return array{private:int,business:int,staff:int,remind:int}
	 */
	public static function settings(): array {
		$saved = (array) get_option( self::OPTION, array() );
		return array(
			'private'  => isset( $saved['private'] ) ? absint( $saved['private'] ) : 15,
			'business' => isset( $saved['business'] ) ? absint( $saved['business'] ) : 30,
			'staff'    => isset( $saved['staff'] ) ? absint( $saved['staff'] ) : 0,
			'remind'   => isset( $saved['remind'] ) ? absint( $saved['remind'] ) : 3,
		);
	}

	/**
	 * Settings section on Appearance › Torrehub.
	 */
	public function always(): void {
		add_action(
			'admin_init',
			static function () {
				register_setting(
					Settings::PAGE,
					self::OPTION,
					array(
						'type'              => 'array',
						'default'           => array(),
						'sanitize_callback' => static function ( $v ) {
							$v = is_array( $v ) ? $v : array();
							return array(
								'private'  => min( 3650, absint( $v['private'] ?? 15 ) ),
								'business' => min( 3650, absint( $v['business'] ?? 30 ) ),
								'staff'    => min( 3650, absint( $v['staff'] ?? 0 ) ),
								'remind'   => min( 60, absint( $v['remind'] ?? 3 ) ),
							);
						},
					)
				);
			}
		);
		add_action( 'th_settings_sections', array( self::class, 'settings_section' ) );
	}

	/**
	 * The settings table.
	 */
	public static function settings_section(): void {
		$s      = self::settings();
		$delete = self::deleted_after_days();
		$rows   = array(
			'private'  => array( __( 'Private Seller (days)', 'torrehub' ), 1, '' ),
			'business' => array( __( 'Business Seller (days)', 'torrehub' ), 1, '' ),
			'staff'    => array( __( 'Staff accounts (days)', 'torrehub' ), 0, __( '0 = never expire (administrators’ own listings).', 'torrehub' ) ),
			'remind'   => array( __( 'Reminder e-mail (days before the end)', 'torrehub' ), 0, __( '0 = no e-mail. Listings ending within this many days can be renewed early.', 'torrehub' ) ),
		);
		?>
		<h2 id="th-lifetime"><?php esc_html_e( 'Listing lifetime & renewal', 'torrehub' ); ?></h2>
		<p>
			<?php esc_html_e( 'How long a listing stays online after it goes live, by the owner’s account type. Owners renew for free, for the same number of days, from My listings or the reminder e-mail. Changes apply when a listing next goes live or is renewed.', 'torrehub' ); ?>
			<?php
			if ( $delete > 0 ) {
				/* translators: %d: number of days */
				echo esc_html( sprintf( __( 'Ended listings are deleted %d days after they end (Classified Listing › Settings).', 'torrehub' ), $delete ) );
			}
			?>
		</p>
		<table class="form-table" role="presentation">
			<?php foreach ( $rows as $key => $row ) : ?>
				<tr>
					<th scope="row"><label for="th-lifetime-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $row[0] ); ?></label></th>
					<td>
						<input type="number" min="<?php echo esc_attr( (string) $row[1] ); ?>" max="3650" class="small-text" id="th-lifetime-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $s[ $key ] ); ?>">
						<?php if ( $row[2] ) : ?>
							<p class="description"><?php echo esc_html( $row[2] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Days after the end before Classified Listing deletes an ended listing (0 = never).
	 */
	public static function deleted_after_days(): int {
		$general = (array) get_option( 'rtcl_general_settings', array() );
		$emails  = (array) get_option( 'rtcl_email_templates_settings', array() );
		$delete  = absint( $general['delete_expired_listings'] ?? 0 );
		return $delete > 0 ? $delete + absint( $emails['renewal_reminder_threshold'] ?? 0 ) : 0;
	}

	/* ------------------------------------------------------------------ rules */

	/**
	 * Account type of a listing's owner: private|business|staff.
	 *
	 * @param int $listing_id Listing.
	 */
	public static function owner_type( int $listing_id ): string {
		$user = get_userdata( (int) get_post_field( 'post_author', $listing_id ) );
		$type = $user ? Accounts::type( $user ) : '';
		return 'business' === $type ? 'business' : ( in_array( $type, array( 'seller', 'member' ), true ) ? 'private' : 'staff' );
	}

	/**
	 * Lifetime of a listing in days (0 = never expires).
	 *
	 * @param int $listing_id Listing.
	 */
	public static function days( int $listing_id ): int {
		$type = self::owner_type( $listing_id );
		/**
		 * Lifetime of a listing in days (0 = never expires) — e.g. longer for a paid package.
		 *
		 * @param int    $days       Days from the settings.
		 * @param string $type       private|business|staff.
		 * @param int    $listing_id Listing.
		 */
		return max( 0, (int) apply_filters( 'th_listing_lifetime_days', self::settings()[ $type ], $type, $listing_id ) );
	}

	/**
	 * End of a listing (unix time; 0 = never expires or not set).
	 *
	 * @param int $listing_id Listing.
	 */
	public static function ends( int $listing_id ): int {
		if ( get_post_meta( $listing_id, 'never_expires', true ) ) {
			return 0;
		}
		$date = (string) get_post_meta( $listing_id, 'expiry_date', true );
		return '' !== $date ? (int) get_gmt_from_date( $date, 'U' ) : 0;
	}

	/**
	 * Can the listing be renewed now: ended, or live and ending within the reminder window?
	 *
	 * @param int $listing_id Listing.
	 */
	public static function renewable( int $listing_id ): bool {
		$status = get_post_status( $listing_id );
		$ends   = self::ends( $listing_id );
		$window = max( 1, self::settings()['remind'] ) * DAY_IN_SECONDS;
		$can    = self::days( $listing_id ) > 0
			&& ( 'rtcl-expired' === $status || ( 'publish' === $status && $ends && $ends - time() <= $window ) );
		/**
		 * May the listing be renewed (free) now?
		 *
		 * @param bool $can        Renewable by the rules above.
		 * @param int  $listing_id Listing.
		 */
		return (bool) apply_filters( 'th_listing_can_renew', $can, $listing_id );
	}

	/**
	 * Set the end date for a period starting at $from (local time; Classified Listing's cron compares local time).
	 *
	 * @param int $listing_id Listing.
	 * @param int $from       Unix time the period starts.
	 */
	private static function set_end( int $listing_id, int $from ): void {
		$days = self::days( $listing_id );
		delete_post_meta( $listing_id, 'deletion_date' );
		if ( $days <= 0 ) {
			update_post_meta( $listing_id, 'never_expires', 1 );
			delete_post_meta( $listing_id, 'expiry_date' );
			return;
		}
		delete_post_meta( $listing_id, 'never_expires' );
		update_post_meta( $listing_id, 'expiry_date', wp_date( 'Y-m-d H:i:s', $from + $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Renew: the same number of days again, from the current end if it is still ahead, else from now; ended
	 * listings go live again (no new review — the content didn't change).
	 *
	 * @param int    $listing_id Listing.
	 * @param string $via        my-listings|email.
	 */
	public static function renew( int $listing_id, string $via ): bool {
		if ( ! self::renewable( $listing_id ) ) {
			return false;
		}
		$ends          = self::ends( $listing_id );
		$from          = max( time(), $ends );
		self::$context = $listing_id;
		if ( 'rtcl-expired' === get_post_status( $listing_id ) ) {
			wp_update_post(
				array(
					'ID'          => $listing_id,
					'post_status' => 'publish',
				)
			);
		}
		self::set_end( $listing_id, $from );
		delete_post_meta( $listing_id, 'renewal_reminder_sent' );
		/**
		 * A listing was renewed.
		 *
		 * @param int    $listing_id Listing.
		 * @param int    $ends       New end (unix time).
		 * @param string $via        my-listings|email.
		 */
		do_action( 'th_listing_renewed', $listing_id, self::ends( $listing_id ), $via );
		return true;
	}

	/* ------------------------------------------------------------------ hooks */

	/**
	 * Hooks.
	 */
	public function register(): void {
		// Before Classified Listing's own default (priority 99), which then sees the date and leaves it.
		add_action( 'transition_post_status', array( $this, 'went_live' ), 5, 3 );
		add_filter( 'get_default_expired_duration_days', array( $this, 'default_days' ) );
		th_on_front_post( 'th_renew_listing', array( $this, 'renew_from_account' ) );
		add_action( 'template_redirect', array( $this, 'renew_link' ), 1 );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::CRON, array( $this, 'send_notices' ) );
		add_shortcode( 'torrehub_listing_lifetime', array( self::class, 'shortcode' ) );
	}

	/**
	 * A listing went live (approval, direct publish, renewal): its period starts now.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function went_live( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! $post instanceof \WP_Post || 'rtcl_listing' !== $post->post_type ) {
			return;
		}
		self::$context = (int) $post->ID;
		self::set_end( (int) $post->ID, time() );
	}

	/**
	 * Classified Listing's own default (new auto-published listing, its renew AJAX): the same days as went_live().
	 *
	 * @param int $days Its global setting.
	 */
	public function default_days( $days ): int {
		if ( self::$context ) {
			return self::days( self::$context );
		}
		$user = wp_get_current_user();
		$type = $user->exists() ? Accounts::type( $user ) : '';
		$key  = 'business' === $type ? 'business' : ( in_array( $type, array( 'seller', 'member' ), true ) ? 'private' : 'staff' );
		return self::settings()[ $key ];
	}

	/**
	 * My listings › Renew (form post, no JS needed).
	 */
	public function renew_from_account(): void {
		$id   = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified next.
		$back = \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' );
		check_admin_referer( 'th_renew_listing_' . $id );
		$own = is_user_logged_in() && 'rtcl_listing' === get_post_type( $id ) && get_current_user_id() === (int) get_post_field( 'post_author', $id );
		$ok  = $own && self::renew( $id, 'my-listings' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'th_renewed' => $ok ? $id : 0,
				),
				$back
			)
		);
		exit;
	}

	/* ------------------------------------------------------------------ e-mail link */

	/**
	 * Secret of a listing's renewal link (bound to the listing and its owner).
	 *
	 * @param int $listing_id Listing.
	 */
	public static function token( int $listing_id ): string {
		return substr( hash_hmac( 'sha256', 'th_renew|' . $listing_id . '|' . (int) get_post_field( 'post_author', $listing_id ), wp_salt( 'auth' ) ), 0, 32 );
	}

	/**
	 * Signed renewal link (opens a page with the Renew button — mail scanners only fetch, they don't post).
	 *
	 * @param int $listing_id Listing.
	 */
	public static function renew_url( int $listing_id ): string {
		return add_query_arg(
			array(
				'th_renew' => $listing_id,
				't'        => self::token( $listing_id ),
			),
			home_url( '/' )
		);
	}

	/**
	 * `?th_renew={id}&t={token}`: GET shows the listing and the button, POST renews.
	 */
	public function renew_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- the signed per-listing token is the authorisation.
		$id  = isset( $_GET['th_renew'] ) ? absint( $_GET['th_renew'] ) : 0;
		$tok = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : '';
		// phpcs:enable
		if ( ! $id ) {
			return;
		}
		$valid = 'rtcl_listing' === get_post_type( $id ) && in_array( get_post_status( $id ), array( 'publish', 'rtcl-expired' ), true ) && '' !== $tok && hash_equals( self::token( $id ), $tok );
		$state = 'invalid';
		if ( $valid ) {
			$state = self::renewable( $id ) ? 'ready' : 'running';
			if ( 'ready' === $state && 'post' === sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
				$state = self::renew( $id, 'email' ) ? 'renewed' : 'running';
			}
		}
		nocache_headers();
		status_header( $valid ? 200 : 404 );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		get_header();
		get_template_part(
			'template-parts/lifetime/renew',
			null,
			array(
				'listing_id' => $valid ? $id : 0,
				'state'      => $state,
			)
		);
		get_footer();
		exit;
	}

	/* ------------------------------------------------------------------ reminder */

	/**
	 * Hourly reminder check.
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', self::CRON );
		}
	}

	/**
	 * E-mail the owners of live listings ending within the reminder window (once per period).
	 */
	public function send_notices(): void {
		$days = self::settings()['remind'];
		if ( $days <= 0 ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'rtcl_listing',
				'post_status'    => 'publish',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- hourly cron batch, ids only.
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- hourly cron.
					array(
						'key'     => 'expiry_date',
						'value'   => array( wp_date( 'Y-m-d H:i:s' ), wp_date( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ) ),
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => 'never_expires',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			$id  = (int) $id;
			$end = (string) get_post_meta( $id, 'expiry_date', true );
			if ( (string) get_post_meta( $id, self::NOTICE, true ) === $end ) {
				continue;
			}
			if ( self::notify( $id ) ) {
				update_post_meta( $id, self::NOTICE, $end );
			}
		}
	}

	/**
	 * The reminder e-mail.
	 *
	 * @param int $listing_id Listing.
	 */
	public static function notify( int $listing_id ): bool {
		$owner = get_userdata( (int) get_post_field( 'post_author', $listing_id ) );
		if ( ! $owner || ! is_email( $owner->user_email ) ) {
			return false;
		}
		$title = html_entity_decode( get_the_title( $listing_id ), ENT_QUOTES );
		$date  = wp_date( get_option( 'date_format' ), self::ends( $listing_id ) );
		$days  = self::days( $listing_id );
		$card  = function_exists( 'th_listing_card_args' ) ? th_listing_card_args( get_post( $listing_id ) ) : array();
		return Mailer::send(
			$owner->user_email,
			/* translators: 1: listing title, 2: date */
			sprintf( __( '“%1$s” ends on %2$s', 'torrehub' ), $title, $date ),
			__( 'Your listing ends soon', 'torrehub' ),
			array(
				/* translators: 1: listing title, 2: date */
				sprintf( __( '“%1$s” runs until %2$s.', 'torrehub' ), $title, $date ),
				/* translators: %d: number of days */
				sprintf( _n( 'Renew it to keep it online for another %d day — it’s free, and you can renew as often as you like.', 'Renew it to keep it online for another %d days — it’s free, and you can renew as often as you like.', $days, 'torrehub' ), $days ),
			),
			/* translators: %d: number of days */
			array( sprintf( _n( 'Renew for %d day', 'Renew for %d days', $days, 'torrehub' ), $days ), self::renew_url( $listing_id ) ),
			__( 'Sold or no longer needed? Do nothing — the listing ends on its own.', 'torrehub' ),
			array(
				'items'  => array( array( $title, (string) ( $card['price'] ?? '' ), (string) get_permalink( $listing_id ) ) ),
				'footer' => array( __( 'My listings', 'torrehub' ), \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' ) ),
			)
		);
	}

	/* ------------------------------------------------------------------ wording */

	/**
	 * "Listings run 15 days (30 for businesses)" — follows the settings ('' when nothing expires).
	 */
	public static function duration_text(): string {
		$s = self::settings();
		if ( $s['private'] > 0 && $s['business'] > 0 && $s['private'] !== $s['business'] ) {
			/* translators: 1: days for private sellers, 2: days for businesses */
			return sprintf( __( 'Listings run %1$d days (%2$d for businesses)', 'torrehub' ), $s['private'], $s['business'] );
		}
		$days = max( $s['private'], $s['business'] );
		/* translators: %d: number of days */
		return $days > 0 ? sprintf( _n( 'Listings run %d day', 'Listings run %d days', $days, 'torrehub' ), $days ) : '';
	}

	/**
	 * `[torrehub_listing_lifetime show="duration|renewal"]` — FAQ answers that follow the settings.
	 *
	 * @param array|string $atts Attributes.
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'show' => 'duration' ), (array) $atts );
		$s    = self::settings();
		if ( 'renewal' === $atts['show'] ) {
			$text = $s['remind'] > 0
				/* translators: %d: number of days */
				? sprintf( _n( 'In My account › My listings, use “Renew” on a listing that has ended or ends within %d day — or the link in the reminder e-mail.', 'In My account › My listings, use “Renew” on a listing that has ended or ends within %d days — or the link in the reminder e-mail.', $s['remind'], 'torrehub' ), $s['remind'] )
				: __( 'In My account › My listings, use “Renew” on a listing that has ended.', 'torrehub' );
			$text .= ' ' . __( 'It runs for the same number of days again. Renewing is free and you can renew as often as you like.', 'torrehub' );
			$gone  = self::deleted_after_days();
			if ( $gone > 0 ) {
				/* translators: %d: number of days */
				$text .= ' ' . sprintf( _n( 'An ended listing can be renewed for %d day; after that it is deleted.', 'An ended listing can be renewed for %d days; after that it is deleted.', $gone, 'torrehub' ), $gone );
			}
			return esc_html( $text );
		}
		if ( $s['private'] > 0 && $s['business'] > 0 ) {
			/* translators: 1: days for Private Sellers, 2: days for Business Sellers */
			$text = sprintf( __( 'A listing of a Private Seller runs %1$d days, one of a Business Seller %2$d days, counted from when it goes live.', 'torrehub' ), $s['private'], $s['business'] );
		} elseif ( $s['private'] > 0 ) {
			/* translators: %d: number of days */
			$text = sprintf( __( 'A listing of a Private Seller runs %d days, counted from when it goes live; listings of Business Sellers don’t expire.', 'torrehub' ), $s['private'] );
		} elseif ( $s['business'] > 0 ) {
			/* translators: %d: number of days */
			$text = sprintf( __( 'A listing of a Business Seller runs %d days, counted from when it goes live; listings of Private Sellers don’t expire.', 'torrehub' ), $s['business'] );
		} else {
			return esc_html__( 'Listings don’t expire.', 'torrehub' );
		}
		if ( $s['remind'] > 0 ) {
			/* translators: %d: number of days */
			$text .= ' ' . sprintf( _n( 'We e-mail you %d day before the end.', 'We e-mail you %d days before the end.', $s['remind'], 'torrehub' ), $s['remind'] );
		}
		return esc_html( $text );
	}
}
