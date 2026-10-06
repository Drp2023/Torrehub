<?php
/**
 * Free-listing allowance ("5 free listings every 30 days") — replaces the paid Store add-on's posting limit.
 *
 * DECISION (BUILD-PLAN v1 #9): off by default; while off nothing is shown or enforced. Switch it on and set the
 * numbers in Appearance › Torrehub.
 *
 * - Counted from the seller's own listings created in the window (any status except temporary drafts — deleted
 *   listings in the trash still count, so deleting doesn't free a slot). Staff (edit_others_posts) are exempt.
 * - Enforced on Classified Listing's save (`rtcl_fb_extra_form_validation`) for new listings only; edits are free.
 * - Shown in the listing form rail and on the account dashboard (S-04 / G-03 allowance bar).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Quota;

use Torrehub\Core\Module as BaseModule;
use Torrehub\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Quota module.
 */
final class Module extends BaseModule {

	public const OPTION = 'th_quota';

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'quota';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Free listing allowance', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Limits how many listings a seller can post in a rolling window (default 5 every 30 days). Off by default.', 'torrehub' );
	}

	/**
	 * DECISION: off until the client switches it on.
	 */
	public function default_enabled(): bool {
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

	/**
	 * Hooks (only while enabled). The settings fields are registered regardless, see Module::settings().
	 */
	public function register(): void {
		add_filter( 'rtcl_fb_extra_form_validation', array( $this, 'validate' ), 10, 2 );
		add_filter( 'th_listing_form_blocked', array( $this, 'blocked' ), 10, 2 );
		add_action( 'th_listing_form_rail', array( $this, 'rail' ) );
		add_action( 'th_account_dashboard_cards', array( $this, 'dashboard' ), 5 );
	}

	/**
	 * Limit and window, sanitised.
	 *
	 * @return array{limit:int,days:int}
	 */
	public static function settings(): array {
		$saved = (array) get_option( self::OPTION, array() );
		return array(
			'limit' => max( 1, (int) ( $saved['limit'] ?? 5 ) ),
			'days'  => max( 1, (int) ( $saved['days'] ?? 30 ) ),
		);
	}

	/**
	 * Usage for a user.
	 *
	 * @param int $user_id User.
	 * @return array{used:int,limit:int,left:int,days:int,resets:int,exempt:bool}
	 */
	public static function usage( int $user_id ): array {
		$s      = self::settings();
		$exempt = user_can( $user_id, 'edit_others_posts' );
		$since  = time() - $s['days'] * DAY_IN_SECONDS;
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one small per-user query.
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				// post_date (site time): pending posts have no post_date_gmt until they are published.
				"SELECT post_date FROM {$wpdb->posts} WHERE post_type = 'rtcl_listing' AND post_author = %d AND post_status NOT IN ('rtcl-temp', 'auto-draft') AND post_date >= %s ORDER BY post_date ASC",
				$user_id,
				wp_date( 'Y-m-d H:i:s', $since )
			)
		);
		// phpcs:enable
		$used = count( $dates );
		// When the window drops below the limit again: the (used − limit + 1)-th oldest listing ages out.
		$key    = $used >= $s['limit'] ? $used - $s['limit'] : 0;
		$resets = $dates ? ( new \DateTimeImmutable( (string) $dates[ $key ], wp_timezone() ) )->getTimestamp() + $s['days'] * DAY_IN_SECONDS : 0;
		return array(
			'used'   => $used,
			'limit'  => $s['limit'],
			'left'   => $exempt ? PHP_INT_MAX : max( 0, $s['limit'] - $used ),
			'days'   => $s['days'],
			'resets' => $resets,
			'exempt' => $exempt,
		);
	}

	/**
	 * "Resets 14 Sept · 15-day expiry"-style note.
	 *
	 * @param array<string,int|bool> $usage Usage.
	 */
	private static function reset_note( array $usage ): string {
		if ( ! $usage['resets'] ) {
			/* translators: 1: number of listings, 2: days */
			return sprintf( __( '%1$s free listings every %2$s days', 'torrehub' ), number_format_i18n( (int) $usage['limit'] ), number_format_i18n( (int) $usage['days'] ) );
		}
		/* translators: %s: date */
		return sprintf( __( 'Next slot frees up %s', 'torrehub' ), wp_date( 'j M', (int) $usage['resets'] ) );
	}

	/**
	 * Is this save a new listing (not an edit)?
	 */
	private static function is_new_listing(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Classified Listing verified the nonce.
		$id = isset( $_POST['listingId'] ) ? absint( $_POST['listingId'] ) : 0;
		return ! $id || 'rtcl-temp' === get_post_status( $id );
	}

	/**
	 * Server-side enforcement on Classified Listing's save.
	 *
	 * @param \WP_Error $errors Errors.
	 * @return \WP_Error
	 */
	public function validate( $errors ) {
		if ( ! $errors instanceof \WP_Error || ! self::is_new_listing() ) {
			return $errors;
		}
		$message = $this->blocked( '', get_current_user_id() );
		if ( $message ) {
			$errors->add( 'th_quota', $message );
		}
		return $errors;
	}

	/**
	 * Message when no allowance is left ('' = allowed).
	 *
	 * @param string $message Previous message.
	 * @param int    $user_id User.
	 */
	public function blocked( $message, $user_id ): string {
		if ( $message ) {
			return (string) $message;
		}
		$usage = self::usage( (int) $user_id );
		if ( $usage['left'] > 0 ) {
			return '';
		}
		return sprintf(
			/* translators: 1: number of listings, 2: days, 3: date */
			__( 'You’ve used your %1$s free listings for the last %2$s days. You can post again on %3$s.', 'torrehub' ),
			number_format_i18n( (int) $usage['limit'] ),
			number_format_i18n( (int) $usage['days'] ),
			wp_date( (string) get_option( 'date_format' ), (int) $usage['resets'] )
		);
	}

	/**
	 * Allowance bar markup.
	 *
	 * @param array<string,int|bool> $usage Usage.
	 */
	private static function bar( array $usage ): void {
		$pct = min( 100, (int) round( (int) $usage['used'] / max( 1, (int) $usage['limit'] ) * 100 ) );
		?>
		<div class="th-allowance">
			<p class="th-allowance__row">
				<span><?php esc_html_e( 'Free listings', 'torrehub' ); ?></span>
				<span>
					<?php
					/* translators: 1: used, 2: limit */
					echo esc_html( sprintf( __( '%1$s / %2$s', 'torrehub' ), number_format_i18n( (int) $usage['used'] ), number_format_i18n( (int) $usage['limit'] ) ) );
					?>
				</span>
			</p>
			<span class="th-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $usage['limit'] ); ?>" aria-valuenow="<?php echo esc_attr( (string) $usage['used'] ); ?>" aria-label="<?php esc_attr_e( 'Free listings used', 'torrehub' ); ?>"><span class="th-progress__fill" style="<?php echo esc_attr( '--pct:' . $pct . '%' ); ?>"></span></span>
			<span><?php echo esc_html( self::reset_note( $usage ) ); ?></span>
		</div>
		<?php
	}

	/**
	 * Listing form rail card (new listings only).
	 *
	 * @param \Torrehub\Modules\ListingForm\Workspace $ws Workspace.
	 */
	public function rail( $ws ): void {
		if ( 'edit' === $ws->mode ) {
			return;
		}
		$usage = self::usage( get_current_user_id() );
		if ( ! $usage['exempt'] ) {
			self::bar( $usage );
		}
	}

	/**
	 * Dashboard card for sellers.
	 *
	 * @param \WP_User $user User.
	 */
	public function dashboard( $user ): void {
		if ( ! $user instanceof \WP_User || ! array_intersect( array( 'seller', 'business' ), (array) $user->roles ) ) {
			return;
		}
		$usage = self::usage( $user->ID );
		if ( ! $usage['exempt'] ) {
			self::bar( $usage );
		}
	}

	/**
	 * Settings section on Appearance › Torrehub (registered even while the module is off).
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
						'sanitize_callback' => static fn( $v ) => array(
							'limit' => max( 1, absint( is_array( $v ) ? ( $v['limit'] ?? 5 ) : 5 ) ),
							'days'  => max( 1, absint( is_array( $v ) ? ( $v['days'] ?? 30 ) : 30 ) ),
						),
					)
				);
			}
		);
		add_action(
			'th_settings_sections',
			static function () {
				$s = self::settings();
				?>
				<h2><?php esc_html_e( 'Free listing allowance', 'torrehub' ); ?></h2>
				<p><?php esc_html_e( 'Used only while the “Free listing allowance” module is enabled above.', 'torrehub' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="th-quota-limit"><?php esc_html_e( 'Listings per window', 'torrehub' ); ?></label></th>
						<td><input type="number" min="1" class="small-text" id="th-quota-limit" name="<?php echo esc_attr( self::OPTION ); ?>[limit]" value="<?php echo esc_attr( (string) $s['limit'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="th-quota-days"><?php esc_html_e( 'Window (days)', 'torrehub' ); ?></label></th>
						<td><input type="number" min="1" class="small-text" id="th-quota-days" name="<?php echo esc_attr( self::OPTION ); ?>[days]" value="<?php echo esc_attr( (string) $s['days'] ); ?>"></td>
					</tr>
				</table>
				<?php
			}
		);
	}
}
