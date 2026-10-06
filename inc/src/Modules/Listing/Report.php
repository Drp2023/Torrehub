<?php
/**
 * "Report this listing" — port of the WPCode snippet "Torrehub Listing Report System (TLRS)" (5643).
 *
 * Kept from TLRS: logged-in users only, one report per user, meta `tlrs_reported_users` (user ids) and
 * `tlrs_report_count`, option `tlrs_threshold` (live: 50; 0 = off), an admin column and a reports screen.
 * Changed: the reason is stored (TLRS collected it and dropped it) in `th_report_log`; the admin gets an e-mail;
 * at the threshold the listing goes to **pending** (back into the review queue) instead of draft (DECISION).
 * Classified Listing's own "Report abuse" modal isn't rendered by the theme's single template.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Listing;

defined( 'ABSPATH' ) || exit;

/**
 * Listing reports.
 */
final class Report {

	public const PAGE = 'th-listing-reports';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_th_report_listing', array( $this, 'ajax' ) );
		th_on_front_post( 'th_report_listing', array( $this, 'post' ) );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_post_th_reports_admin', array( $this, 'admin_action' ) );
			add_filter( 'manage_rtcl_listing_posts_columns', array( $this, 'column' ) );
			add_action( 'manage_rtcl_listing_posts_custom_column', array( $this, 'column_value' ), 10, 2 );
		}
	}

	/**
	 * Report reasons (value => label).
	 *
	 * @return array<string,string>
	 */
	public static function reasons(): array {
		return array(
			'scam'      => __( 'Scam or fraud', 'torrehub' ),
			'wrong'     => __( 'Wrong category or misleading', 'torrehub' ),
			'duplicate' => __( 'Duplicate listing', 'torrehub' ),
			'offensive' => __( 'Offensive or illegal content', 'torrehub' ),
			'gone'      => __( 'No longer available', 'torrehub' ),
			'other'     => __( 'Something else', 'torrehub' ),
		);
	}

	/**
	 * Has the current user reported this listing?
	 *
	 * @param int $listing_id Listing id.
	 */
	public static function reported_by_current_user( int $listing_id ): bool {
		$users = get_post_meta( $listing_id, 'tlrs_reported_users', true );
		return is_user_logged_in() && is_array( $users ) && in_array( get_current_user_id(), array_map( 'intval', $users ), true );
	}

	/**
	 * Record a report. Returns an error code or ''.
	 *
	 * @param int    $listing_id Listing id.
	 * @param string $reason     Reason key.
	 * @param string $message    Free text.
	 */
	private function record( int $listing_id, string $reason, string $message ): string {
		if ( ! is_user_logged_in() ) {
			return 'login';
		}
		if ( 'rtcl_listing' !== get_post_type( $listing_id ) || 'publish' !== get_post_status( $listing_id ) ) {
			return 'invalid';
		}
		if ( (int) get_post_field( 'post_author', $listing_id ) === get_current_user_id() ) {
			return 'own';
		}
		if ( ! isset( self::reasons()[ $reason ] ) || ( 'other' === $reason && mb_strlen( $message ) < 10 ) || mb_strlen( $message ) > 1000 ) {
			return 'reason';
		}
		if ( self::reported_by_current_user( $listing_id ) ) {
			return 'done';
		}

		$users   = get_post_meta( $listing_id, 'tlrs_reported_users', true );
		$users   = is_array( $users ) ? $users : array();
		$users[] = get_current_user_id();
		update_post_meta( $listing_id, 'tlrs_reported_users', $users );

		$count = (int) get_post_meta( $listing_id, 'tlrs_report_count', true ) + 1;
		update_post_meta( $listing_id, 'tlrs_report_count', $count );

		$log   = get_post_meta( $listing_id, 'th_report_log', true );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'user'    => get_current_user_id(),
			'reason'  => $reason,
			'message' => $message,
			'time'    => time(),
		);
		update_post_meta( $listing_id, 'th_report_log', $log );

		$threshold = (int) get_option( 'tlrs_threshold', 5 );
		$hidden    = false;
		if ( $threshold > 0 && $count >= $threshold ) {
			wp_update_post(
				array(
					'ID'          => $listing_id,
					'post_status' => 'pending',
				)
			);
			$hidden = true;
		}

		$this->notify( $listing_id, $reason, $message, $count, $hidden );
		return '';
	}

	/**
	 * E-mail the site admin.
	 *
	 * @param int    $listing_id Listing id.
	 * @param string $reason     Reason key.
	 * @param string $message    Message.
	 * @param int    $count      Reports so far.
	 * @param bool   $hidden     Moved to pending.
	 */
	private function notify( int $listing_id, string $reason, string $message, int $count, bool $hidden ): void {
		/* translators: %s: listing title */
		$subject = sprintf( __( '[Torrehub] Listing reported: %s', 'torrehub' ), get_the_title( $listing_id ) );
		$lines   = array(
			/* translators: %s: listing title */
			sprintf( __( 'Listing: %s', 'torrehub' ), get_the_title( $listing_id ) ),
			(string) get_permalink( $listing_id ),
			/* translators: %s: reason */
			sprintf( __( 'Reason: %s', 'torrehub' ), self::reasons()[ $reason ] ?? $reason ),
			'' !== $message ? $message : '',
			/* translators: %d: number of reports */
			sprintf( __( 'Reports so far: %d', 'torrehub' ), $count ),
			$hidden ? __( 'The report threshold was reached: the listing is now pending review.', 'torrehub' ) : '',
			admin_url( 'edit.php?post_type=rtcl_listing&page=' . self::PAGE ),
		);
		wp_mail( (string) get_option( 'admin_email' ), $subject, implode( "\n", array_filter( $lines ) ) );
	}

	/**
	 * AJAX submit.
	 */
	public function ajax(): void {
		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		check_ajax_referer( 'th_report_' . $listing_id );
		$error = $this->record(
			$listing_id,
			isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '',
			isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : ''
		);
		if ( $error ) {
			wp_send_json_error( array( 'message' => self::message( $error ) ), 'login' === $error ? 401 : 400 );
		}
		wp_send_json_success( array( 'message' => self::message( 'sent' ) ) );
	}

	/**
	 * No-JS submit (posted to the listing page).
	 */
	public function post(): void {
		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		$back       = $listing_id ? (string) get_permalink( $listing_id ) : home_url( '/' );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( th_url_login( $back ) );
			exit;
		}
		check_admin_referer( 'th_report_' . $listing_id );
		$error = $this->record(
			$listing_id,
			isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '',
			isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : ''
		);
		wp_safe_redirect( add_query_arg( 'th_report', $error ? $error : 'sent', $back ) );
		exit;
	}

	/**
	 * User-facing message for a result code.
	 *
	 * @param string $code Code.
	 */
	public static function message( string $code ): string {
		$messages = array(
			'sent'    => __( 'Thanks — your report was sent. Our team will take a look.', 'torrehub' ),
			'login'   => __( 'Log in to report a listing.', 'torrehub' ),
			'own'     => __( 'You can’t report your own listing.', 'torrehub' ),
			'done'    => __( 'You have already reported this listing.', 'torrehub' ),
			'reason'  => __( 'Choose a reason (and tell us a little more for “Something else”).', 'torrehub' ),
			'invalid' => __( 'This listing can’t be reported.', 'torrehub' ),
		);
		return $messages[ $code ] ?? $messages['invalid'];
	}

	/* ------------------------------------------------------------------ admin */

	/**
	 * Listings → Reports.
	 */
	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=rtcl_listing',
			__( 'Reported listings', 'torrehub' ),
			__( 'Reports', 'torrehub' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Column header.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public function column( $cols ) {
		$cols['tlrs_reports'] = __( 'Reports', 'torrehub' );
		return $cols;
	}

	/**
	 * Column value.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Listing id.
	 */
	public function column_value( $col, $post_id ): void {
		if ( 'tlrs_reports' === $col ) {
			echo (int) get_post_meta( (int) $post_id, 'tlrs_report_count', true );
		}
	}

	/**
	 * Clear reports / save threshold.
	 */
	public function admin_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'torrehub' ), 403 );
		}
		check_admin_referer( 'th_reports_admin' );
		if ( isset( $_POST['threshold'] ) ) {
			update_option( 'tlrs_threshold', min( 1000, absint( $_POST['threshold'] ) ) );
		}
		$clear = isset( $_POST['clear'] ) ? absint( $_POST['clear'] ) : 0;
		if ( $clear && 'rtcl_listing' === get_post_type( $clear ) ) {
			delete_post_meta( $clear, 'tlrs_report_count' );
			delete_post_meta( $clear, 'tlrs_reported_users' );
			delete_post_meta( $clear, 'th_report_log' );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=rtcl_listing&page=' . self::PAGE . '&updated=1' ) );
		exit;
	}

	/**
	 * Reports screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$query   = new \WP_Query(
			array(
				'post_type'      => 'rtcl_listing',
				'post_status'    => 'any',
				'posts_per_page' => 100, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- admin list of reported listings.
				'meta_key'       => 'tlrs_report_count', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin screen, small table.
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'tlrs_report_count',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$reasons = self::reasons();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reported listings', 'torrehub' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="th_reports_admin">
				<?php wp_nonce_field( 'th_reports_admin' ); ?>
				<p>
					<label for="th-threshold"><?php esc_html_e( 'Move a listing back to “Pending” after this many reports (0 = never):', 'torrehub' ); ?></label>
					<input type="number" class="small-text" id="th-threshold" name="threshold" min="0" max="1000" value="<?php echo esc_attr( (string) (int) get_option( 'tlrs_threshold', 5 ) ); ?>">
					<?php submit_button( __( 'Save', 'torrehub' ), 'secondary', 'submit', false ); ?>
				</p>
			</form>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Listing', 'torrehub' ); ?></th>
					<th><?php esc_html_e( 'Status', 'torrehub' ); ?></th>
					<th><?php esc_html_e( 'Reports', 'torrehub' ); ?></th>
					<th><?php esc_html_e( 'Latest reasons', 'torrehub' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $query->posts ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No reported listings.', 'torrehub' ); ?></td></tr>
				<?php endif; ?>
				<?php
				foreach ( $query->posts as $listing ) :
					$log = get_post_meta( $listing->ID, 'th_report_log', true );
					$log = is_array( $log ) ? array_slice( array_reverse( $log ), 0, 3 ) : array();
					?>
					<tr>
						<td><a href="<?php echo esc_url( (string) get_edit_post_link( $listing->ID ) ); ?>"><?php echo esc_html( get_the_title( $listing ) ); ?></a></td>
						<td><?php echo esc_html( (string) get_post_status( $listing ) ); ?></td>
						<td><?php echo (int) get_post_meta( $listing->ID, 'tlrs_report_count', true ); ?></td>
						<td>
							<?php if ( ! $log ) : ?>
								<em><?php esc_html_e( 'Reported before reasons were recorded.', 'torrehub' ); ?></em>
							<?php endif; ?>
							<?php foreach ( $log as $entry ) : ?>
								<div><strong><?php echo esc_html( $reasons[ $entry['reason'] ] ?? (string) $entry['reason'] ); ?></strong> · <?php echo esc_html( wp_date( get_option( 'date_format' ), (int) $entry['time'] ) ); ?><?php echo '' !== (string) $entry['message'] ? ' — ' . esc_html( (string) $entry['message'] ) : ''; ?></div>
							<?php endforeach; ?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="th_reports_admin">
								<input type="hidden" name="clear" value="<?php echo esc_attr( (string) $listing->ID ); ?>">
								<?php wp_nonce_field( 'th_reports_admin' ); ?>
								<button type="submit" class="button-link"><?php esc_html_e( 'Clear reports', 'torrehub' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
