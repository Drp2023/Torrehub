<?php
/**
 * Login, registration (Member / Private Seller / Business Seller + NIF), e-mail confirmation, admin approval and
 * password reset — all in the theme (replaces wppb, Pro's e-mail verification and the WPCode snippets 7263, 7280).
 *
 * - Pages: Pages (login 4999, register 5014, lost-password created). Templates: template-parts/auth/*.
 * - Classified Listing's own login/registration/lost-password handlers and AJAX are switched off; its logged-out
 *   account page redirects to /login/.
 * - Non-staff users can't open wp-admin (port of WPCode 7280) and don't see the admin bar.
 * - wp-login.php keeps working for staff; its register / lost-password actions forward to the theme pages.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Auth;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Auth module.
 */
final class Module extends BaseModule {

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'auth';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Accounts: login & registration', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Login, registration with account types, e-mail confirmation, admin approval and password reset.', 'torrehub' );
	}

	/**
	 * Core building block.
	 */
	public function optional(): bool {
		return false;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		( new Forms() )->register();
		if ( is_admin() ) {
			( new UsersAdmin() )->register();
			add_action( 'admin_init', array( Pages::class, 'ensure' ) );
		}

		add_filter( 'template_include', array( $this, 'template' ), 100 );
		add_action( 'template_redirect', array( $this, 'redirects' ), 5 );
		add_filter( 'login_url', array( $this, 'login_url' ), 20, 2 );
		add_filter( 'register_url', static fn() => Pages::url( 'register' ), 20 );
		add_filter( 'lostpassword_url', array( $this, 'lostpassword_url' ), 20, 2 );
		add_action( 'login_init', array( $this, 'wp_login_forward' ) );
		add_filter( 'authenticate', array( $this, 'check_status' ), 99, 1 );
		add_action( 'admin_init', array( $this, 'restrict_admin' ), 1 );
		add_filter( 'show_admin_bar', array( $this, 'admin_bar' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter(
			'th_page_css_bundles',
			static function ( $bundles ) {
				$bundles = (array) $bundles;
				if ( '' !== Pages::current() ) {
					$bundles[] = 'auth';
				}
				return $bundles;
			}
		);

		// Classified Listing: no own login / registration.
		add_action( 'init', array( $this, 'disable_rtcl_auth' ), 20 );
		add_filter( 'option_rtcl_account_settings', array( $this, 'rtcl_settings' ) );
	}

	/**
	 * Render the auth pages with the theme's templates (ignores their stored content).
	 *
	 * @param string $template Template.
	 */
	public function template( $template ) {
		$page = Pages::current();
		if ( '' !== $page ) {
			nocache_headers(); // Forms carry nonces and per-visitor state: never page-cache these.
			$file = locate_template( 'template-parts/auth/page.php' );
			return $file ? $file : $template;
		}
		return $template;
	}

	/**
	 * Logged-in users don't need login/register; logged-out users can't use the RTCL account page.
	 */
	public function redirects(): void {
		$page = Pages::current();
		if ( is_user_logged_in() && in_array( $page, array( 'login', 'register' ), true ) && ! isset( $_GET['th_confirmed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state.
			wp_safe_redirect( th_rtcl_page_url( 'myaccount', '/my-account/' ) );
			exit;
		}
		$account = (int) ( get_option( 'rtcl_advanced_settings', array() )['myaccount'] ?? 0 );
		if ( ! is_user_logged_in() && $account && is_page( $account ) ) {
			$here = home_url( add_query_arg( array() ) );
			if ( str_contains( $here, '/registration' ) ) {
				wp_safe_redirect( Pages::url( 'register' ) );
			} elseif ( str_contains( $here, '/lost-password' ) ) {
				wp_safe_redirect( Pages::url( 'lost' ) );
			} else {
				wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( $here ), Pages::url( 'login' ) ) );
			}
			exit;
		}
	}

	/**
	 * Front-end login links → /login/ (wp-admin keeps wp-login.php so staff can always get in).
	 *
	 * @param string $url      Login URL.
	 * @param string $redirect Redirect target.
	 */
	public function login_url( $url, $redirect = '' ) {
		if ( is_admin() || ! Pages::id( 'login' ) ) {
			return $url;
		}
		$login = Pages::url( 'login' );
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $login ) : $login;
	}

	/**
	 * Lost-password links → /lost-password/.
	 *
	 * @param string $url      URL.
	 * @param string $redirect Redirect.
	 */
	public function lostpassword_url( $url, $redirect = '' ) {
		return Pages::id( 'lost' ) ? Pages::url( 'lost' ) : $url;
	}

	/**
	 * Forward wp-login.php?action=register|lostpassword to the theme pages.
	 */
	public function wp_login_forward(): void {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		if ( 'register' === $action && Pages::id( 'register' ) ) {
			wp_safe_redirect( Pages::url( 'register' ) );
			exit;
		}
		if ( in_array( $action, array( 'lostpassword', 'retrievepassword' ), true ) && Pages::id( 'lost' ) ) {
			wp_safe_redirect( Pages::url( 'lost' ) );
			exit;
		}
	}

	/**
	 * Accounts that aren't active can't log in (staff exempt). Runs after the password check.
	 *
	 * @param \WP_User|\WP_Error|null $user Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public function check_status( $user ) {
		if ( ! $user instanceof \WP_User || user_can( $user, 'edit_others_posts' ) ) {
			return $user;
		}
		$status = Accounts::status( $user->ID );
		$texts  = array(
			Accounts::UNCONFIRMED => __( 'Please confirm your e-mail address first — we sent you a link.', 'torrehub' ),
			Accounts::PENDING     => __( 'Your account is awaiting approval.', 'torrehub' ),
			Accounts::REJECTED    => __( 'This account wasn’t approved.', 'torrehub' ),
		);
		return isset( $texts[ $status ] ) ? new \WP_Error( 'th_' . $status, $texts[ $status ] ) : $user;
	}

	/**
	 * Only staff (editors, admins) use wp-admin. AJAX, admin-post and uploads stay open. (WPCode 7280.)
	 */
	public function restrict_admin(): void {
		if ( ! is_user_logged_in() || wp_doing_ajax() || current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( in_array( $script, array( 'admin-post.php', 'admin-ajax.php', 'async-upload.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( th_rtcl_page_url( 'myaccount', '/my-account/' ) );
		exit;
	}

	/**
	 * Admin bar for staff only.
	 *
	 * @param bool $show Show.
	 */
	public function admin_bar( $show ) {
		return $show && current_user_can( 'edit_others_posts' );
	}

	/**
	 * Auth pages are noindex.
	 *
	 * @param array<string,bool|string> $robots Directives.
	 * @return array<string,bool|string>
	 */
	public function robots( $robots ) {
		if ( '' !== Pages::current() ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * Remove Classified Listing's login/registration/lost-password handlers (forms, AJAX).
	 */
	public function disable_rtcl_auth(): void {
		foreach ( array( 'process_login', 'process_registration', 'process_lost_password', 'process_reset_password' ) as $method ) {
			remove_action( 'wp_loaded', array( \Rtcl\Controllers\FormHandler::class, $method ), 20 );
		}
		global $wp_filter;
		foreach ( array( 'wp_ajax_nopriv_rtcl_login_request', 'wp_ajax_nopriv_rtcl_registration_request' ) as $hook ) {
			unset( $wp_filter[ $hook ] );
		}
	}

	/**
	 * Classified Listing account settings as the theme needs them: no RTCL registration, no own e-mail verification.
	 *
	 * @param mixed $value Settings.
	 * @return mixed
	 */
	public function rtcl_settings( $value ) {
		if ( is_array( $value ) ) {
			$value['enable_myaccount_registration'] = 'no';
			$value['separate_registration_form']    = '';
			$value['user_verification']             = '';
		}
		return $value;
	}
}
