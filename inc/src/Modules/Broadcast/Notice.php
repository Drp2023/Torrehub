<?php
/**
 * Optional notice for logged-in users in the account sidebar ("Terms updated on 5 Nov 2026. Read the terms"),
 * dismissible per user (a plain form post — no JS needed). Set up on Users › Notify all users. Changing the text,
 * date or link shows it again to everyone (the dismissal is stored per version).
 *
 * Built from the design's alert component (info) — placement in the account sidebar: client decision 2026-11.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Broadcast;

defined( 'ABSPATH' ) || exit;

/**
 * Account notice.
 */
final class Notice {

	public const OPTION    = 'th_account_notice';
	public const DISMISSED = 'th_notice_dismissed';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'th_account_sidebar', array( $this, 'render' ) );
		th_on_front_post( 'th_notice_dismiss', array( $this, 'dismiss' ) );
	}

	/**
	 * Settings with defaults.
	 *
	 * @return array{enabled:bool,title:string,text:string,date:string,label:string,url:string,version:string}
	 */
	public static function settings(): array {
		$s              = (array) get_option( self::OPTION, array() );
		$terms          = get_page_by_path( 'terms-conditions' );
		$out            = array(
			'enabled' => ! empty( $s['enabled'] ),
			'title'   => (string) ( $s['title'] ?? '' ),
			'text'    => (string) ( $s['text'] ?? '' ),
			'date'    => (string) ( $s['date'] ?? '' ),
			'label'   => (string) ( $s['label'] ?? '' ),
			'url'     => (string) ( $s['url'] ?? '' ),
		);
		$out['title']   = '' !== $out['title'] ? $out['title'] : __( 'Terms updated', 'torrehub' );
		$out['text']    = '' !== $out['text'] ? $out['text'] : __( 'We updated our Terms & Conditions on {date}.', 'torrehub' );
		$out['date']    = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $out['date'] ) ? $out['date'] : wp_date( 'Y-m-d' );
		$out['label']   = '' !== $out['label'] ? $out['label'] : __( 'Read the terms', 'torrehub' );
		$out['url']     = '' !== $out['url'] ? $out['url'] : ( $terms ? (string) get_permalink( $terms ) : '' );
		$out['version'] = md5( $out['title'] . '|' . $out['text'] . '|' . $out['date'] . '|' . $out['label'] . '|' . $out['url'] );
		return $out;
	}

	/**
	 * Save (from the admin page).
	 *
	 * @param array<string,mixed> $values Values.
	 */
	public static function save( array $values ): void {
		update_option( self::OPTION, $values, false );
	}

	/**
	 * The notice text with the date filled in.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	public static function text( array $s ): string {
		$ts = strtotime( $s['date'] . ' 12:00:00' );
		return str_replace( '{date}', wp_date( get_option( 'date_format' ), $ts ? $ts : time() ), (string) $s['text'] );
	}

	/**
	 * Should this user see it?
	 *
	 * @param int $user_id User.
	 */
	public static function visible( int $user_id ): bool {
		$s = self::settings();
		return $user_id && $s['enabled'] && get_user_meta( $user_id, self::DISMISSED, true ) !== $s['version'];
	}

	/**
	 * Account sidebar.
	 */
	public function render(): void {
		$user = get_current_user_id();
		if ( ! self::visible( $user ) ) {
			return;
		}
		$s       = self::settings();
		$actions = array();
		if ( '' !== $s['url'] ) {
			$actions[] = array(
				'label'   => $s['label'],
				'variant' => 'white',
				'href'    => $s['url'],
			);
		}
		$actions[] = array(
			'label'   => __( 'Got it', 'torrehub' ),
			'variant' => 'text',
			'type'    => 'submit',
			'attrs'   => array( 'form' => 'th-notice-dismiss' ),
		);
		?>
		<aside class="th-account-notice" aria-label="<?php esc_attr_e( 'Notice', 'torrehub' ); ?>">
			<?php
			th_component(
				'alert',
				array(
					'variant' => 'info',
					'title'   => $s['title'],
					'text'    => self::text( $s ),
					'actions' => $actions,
				)
			);
			?>
			<form id="th-notice-dismiss" method="post" action="<?php echo esc_url( th_url_account() ); ?>" hidden>
				<input type="hidden" name="th_action" value="th_notice_dismiss">
				<input type="hidden" name="version" value="<?php echo esc_attr( $s['version'] ); ?>">
				<input type="hidden" name="back" value="<?php echo esc_attr( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw. ?>">
				<?php wp_nonce_field( 'th_notice_dismiss' ); ?>
			</form>
		</aside>
		<?php
	}

	/**
	 * Dismiss (this version) for the current user.
	 */
	public function dismiss(): void {
		check_admin_referer( 'th_notice_dismiss' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$version = isset( $_POST['version'] ) ? sanitize_key( wp_unslash( $_POST['version'] ) ) : '';
		$back    = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : th_url_account();
		// phpcs:enable
		if ( is_user_logged_in() && '' !== $version ) {
			update_user_meta( get_current_user_id(), self::DISMISSED, $version );
		}
		wp_safe_redirect( $back ? $back : th_url_account() );
		exit;
	}

	/**
	 * Settings form on Users › Notify all users.
	 */
	public static function settings_form(): void {
		$s   = self::settings();
		$raw = (array) get_option( self::OPTION, array() );
		?>
		<h2><?php esc_html_e( 'Notice in the account sidebar', 'torrehub' ); ?></h2>
		<p><?php esc_html_e( 'Logged-in users see it next to their account menu until they dismiss it. Changing the text, date or link shows it again to everyone.', 'torrehub' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="th_broadcast">
			<input type="hidden" name="do" value="notice">
			<?php wp_nonce_field( 'th_broadcast' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Show the notice', 'torrehub' ); ?></th>
					<td><label><input type="checkbox" name="notice_enabled" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'On', 'torrehub' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="th-notice-title"><?php esc_html_e( 'Title', 'torrehub' ); ?></label></th>
					<td><input type="text" class="regular-text" id="th-notice-title" name="notice_title" value="<?php echo esc_attr( (string) ( $raw['title'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $s['title'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="th-notice-text"><?php esc_html_e( 'Text', 'torrehub' ); ?></label></th>
					<td>
						<input type="text" class="large-text" id="th-notice-text" name="notice_text" value="<?php echo esc_attr( (string) ( $raw['text'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $s['text'] ); ?>">
						<p class="description"><?php esc_html_e( '{date} becomes the date below.', 'torrehub' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="th-notice-date"><?php esc_html_e( 'Date', 'torrehub' ); ?></label></th>
					<td><input type="date" id="th-notice-date" name="notice_date" value="<?php echo esc_attr( $s['date'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="th-notice-label"><?php esc_html_e( 'Link', 'torrehub' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="th-notice-label" name="notice_label" value="<?php echo esc_attr( (string) ( $raw['label'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $s['label'] ); ?>">
						<input type="url" class="regular-text" name="notice_url" value="<?php echo esc_attr( (string) ( $raw['url'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $s['url'] ); ?>" aria-label="<?php esc_attr_e( 'Link address', 'torrehub' ); ?>">
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save notice', 'torrehub' ), 'secondary' ); ?>
		</form>
		<?php
	}
}
