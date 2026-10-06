<?php
/**
 * Auth form handlers (front-end POSTs via th_on_front_post()). On error the page re-renders with the messages and
 * the submitted values (Forms::result()); on success the handler redirects.
 *
 * Bot control without captcha: honeypot field + signed render time (a form sent < 3 s after rendering, or > 12 h
 * later, is refused). Brute force: 5 failed logins per IP or per account → 15-minute lockout.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Form handlers.
 */
final class Forms {

	private const MAX_FAILS = 5;
	private const LOCK      = 15 * MINUTE_IN_SECONDS;

	/**
	 * Result of a failed POST in this request: [ form, errors (field => message, '_' = general), values ].
	 *
	 * @var array{form:string,errors:array<string,string>,values:array<string,string>}|null
	 */
	private static ?array $result = null;

	/**
	 * Hooks.
	 */
	public function register(): void {
		th_on_front_post( 'th_login', array( $this, 'login' ) );
		th_on_front_post( 'th_register', array( $this, 'register_account' ) );
		th_on_front_post( 'th_lost', array( $this, 'lost' ) );
		th_on_front_post( 'th_reset', array( $this, 'reset' ) );
		th_on_front_post( 'th_resend', array( $this, 'resend' ) );
		add_action( 'template_redirect', array( $this, 'confirm_link' ), 1 );
	}

	/**
	 * Failed result for a form (null if that form wasn't submitted with errors).
	 *
	 * @param string $form Form key.
	 * @return array{form:string,errors:array<string,string>,values:array<string,string>}|null
	 */
	public static function result( string $form ): ?array {
		return self::$result && self::$result['form'] === $form ? self::$result : null;
	}

	/**
	 * Remember a failure for the template.
	 *
	 * @param string               $form   Form.
	 * @param array<string,string> $errors Errors.
	 * @param array<string,string> $values Values to refill (never passwords).
	 */
	private static function fail( string $form, array $errors, array $values = array() ): void {
		self::$result = array(
			'form'   => $form,
			'errors' => $errors,
			'values' => $values,
		);
	}

	/**
	 * Read a POST field.
	 *
	 * @param string $key  Field.
	 * @param bool   $raw  Don't sanitize (passwords).
	 */
	private static function field( string $key, bool $raw = false ): string {
		// Raw by design for passwords; sanitized below otherwise. Every handler verifies its nonce first.
		$value = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return $raw ? (string) $value : trim( sanitize_text_field( (string) $value ) );
	}

	/**
	 * Hashed client IP (rate-limit key).
	 */
	private static function ip_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( md5( $ip . wp_salt( 'nonce' ) ), 0, 16 );
	}

	/**
	 * Is the form a bot submission? (honeypot filled or impossible timing).
	 */
	private static function is_bot(): bool {
		if ( '' !== self::field( 'th_website' ) ) {
			return true;
		}
		$ts = self::field( 'th_ts' );
		if ( ! preg_match( '/^(\d+)\.([a-f0-9]{16})$/', $ts, $m ) || ! hash_equals( substr( wp_hash( 'th_ts' . $m[1] ), 0, 16 ), $m[2] ) ) {
			return true;
		}
		$age = time() - (int) $m[1];
		return $age < 3 || $age > 12 * HOUR_IN_SECONDS;
	}

	/**
	 * Hidden anti-bot fields for a form.
	 */
	public static function bot_fields(): void {
		$now = (string) time();
		printf(
			'<div class="th-hp" aria-hidden="true"><label>%s <input type="text" name="th_website" tabindex="-1" autocomplete="off"></label></div><input type="hidden" name="th_ts" value="%s">',
			esc_html__( 'Leave this empty', 'torrehub' ),
			esc_attr( $now . '.' . substr( wp_hash( 'th_ts' . $now ), 0, 16 ) )
		);
	}

	/* ------------------------------------------------------------------ login */

	/**
	 * Log in.
	 */
	public function login(): void {
		$login    = self::field( 'log' );
		$values   = array(
			'log'         => $login,
			'redirect_to' => self::field( 'redirect_to' ),
		);
		$nonce_ok = wp_verify_nonce( self::field( '_wpnonce' ), 'th_login' );
		if ( ! $nonce_ok ) {
			self::fail( 'login', array( '_' => __( 'The page expired. Please try again.', 'torrehub' ) ), $values );
			return;
		}

		$keys  = array( 'th_lf_ip_' . self::ip_key(), 'th_lf_u_' . md5( strtolower( $login ) ) );
		$fails = max( array_map( static fn( $k ) => (int) get_transient( $k ), $keys ) );
		if ( $fails >= self::MAX_FAILS ) {
			self::fail( 'login', array( '_' => __( 'Too many attempts. Please wait 15 minutes, or reset your password.', 'torrehub' ) ), $values );
			return;
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => self::field( 'pwd', true ),
				'remember'      => '' !== self::field( 'rememberme' ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			$code = $user->get_error_code();
			if ( str_starts_with( $code, 'th_' ) ) {
				// Correct password, but the account isn't active: no attempt counted.
				self::fail( 'login', array( '_status' => substr( $code, 3 ) ), $values );
				return;
			}
			foreach ( $keys as $k ) {
				set_transient( $k, (int) get_transient( $k ) + 1, self::LOCK );
			}
			$left = self::MAX_FAILS - ( $fails + 1 );
			self::fail(
				'login',
				array(
					'_' => $left > 0 && $left <= 2
						/* translators: %d: attempts left */
						? sprintf( _n( 'E-mail or password is incorrect. %d attempt left before a short lockout.', 'E-mail or password is incorrect. %d attempts left before a short lockout.', $left, 'torrehub' ), $left )
						: __( 'E-mail or password is incorrect.', 'torrehub' ),
				),
				$values
			);
			return;
		}

		foreach ( $keys as $k ) {
			delete_transient( $k );
		}
		$target = wp_validate_redirect( $values['redirect_to'], '' );
		if ( '' === $target || ( str_contains( $target, '/wp-admin' ) && ! user_can( $user, 'edit_others_posts' ) ) ) {
			$target = th_rtcl_page_url( 'myaccount', '/my-account/' );
		}
		wp_safe_redirect( $target );
		exit;
	}

	/* ------------------------------------------------------------------ register */

	/**
	 * Create an account.
	 */
	public function register_account(): void {
		$type   = sanitize_key( self::field( 'type' ) );
		$values = array(
			'type'       => $type,
			'first_name' => self::field( 'first_name' ),
			'last_name'  => self::field( 'last_name' ),
			'username'   => sanitize_user( self::field( 'username' ), true ),
			'email'      => sanitize_email( self::field( 'email' ) ),
			'company'    => self::field( 'company' ),
			'nif'        => Accounts::normalise_nif( self::field( 'nif' ) ),
		);
		$errors = array();

		if ( ! wp_verify_nonce( self::field( '_wpnonce' ), 'th_register' ) ) {
			self::fail( 'register', array( '_' => __( 'The page expired. Please try again.', 'torrehub' ) ), $values );
			return;
		}
		/**
		 * Is self-registration open? (The theme's registration ignores WP's "Anyone can register" setting, which
		 * would also open wp-login.php?action=register.)
		 *
		 * @param bool $open Open.
		 */
		if ( ! apply_filters( 'th_registration_open', true ) ) {
			self::fail( 'register', array( '_' => __( 'Registration is closed at the moment.', 'torrehub' ) ), $values );
			return;
		}
		if ( self::is_bot() ) {
			self::fail( 'register', array( '_' => __( 'Please try again — the form was sent too quickly.', 'torrehub' ) ), $values );
			return;
		}
		$ip_key = 'th_reg_' . self::ip_key();
		if ( (int) get_transient( $ip_key ) >= 5 ) {
			self::fail( 'register', array( '_' => __( 'Too many new accounts from this connection. Try again later.', 'torrehub' ) ), $values );
			return;
		}

		if ( ! isset( Accounts::TYPES[ $type ] ) ) {
			$errors['type'] = __( 'Choose an account type.', 'torrehub' );
		}
		if ( '' === $values['first_name'] ) {
			$errors['first_name'] = __( 'Enter your first name.', 'torrehub' );
		}
		if ( '' === $values['last_name'] ) {
			$errors['last_name'] = __( 'Enter your last name.', 'torrehub' );
		}
		$username_error = self::username_error( $values['username'] );
		if ( $username_error ) {
			$errors['username'] = $username_error;
		}
		if ( ! is_email( $values['email'] ) ) {
			$errors['email'] = __( 'Enter a valid e-mail address.', 'torrehub' );
		} elseif ( email_exists( $values['email'] ) ) {
			$errors['email'] = __( 'There’s already an account with this e-mail. Log in or reset your password.', 'torrehub' );
		}
		$password = self::field( 'password', true );
		$pw_error = self::password_error( $password, $values['username'], $values['email'] );
		if ( $pw_error ) {
			$errors['password'] = $pw_error;
		} elseif ( self::field( 'password2', true ) !== $password ) {
			$errors['password2'] = __( 'The passwords don’t match.', 'torrehub' );
		}
		if ( 'business' === $type ) {
			if ( '' === $values['nif'] ) {
				$errors['nif'] = __( 'Enter your NIF.', 'torrehub' );
			} elseif ( ! Accounts::valid_nif( $values['nif'] ) ) {
				$errors['nif'] = __( 'This NIF isn’t valid — check the number and the control letter or digit.', 'torrehub' );
			}
		}
		if ( '' === self::field( 'terms' ) ) {
			$errors['terms'] = __( 'Please accept the Terms & Conditions and the Privacy Policy.', 'torrehub' );
		}
		if ( $errors ) {
			self::fail( 'register', $errors, $values );
			return;
		}

		$display = 'business' === $type && '' !== $values['company'] ? $values['company'] : trim( $values['first_name'] . ' ' . $values['last_name'] );
		$user_id = wp_insert_user(
			array(
				'user_login'   => $values['username'],
				'user_email'   => $values['email'],
				'user_pass'    => $password,
				'first_name'   => $values['first_name'],
				'last_name'    => $values['last_name'],
				'display_name' => $display,
				'nickname'     => $values['username'],
				'role'         => Accounts::TYPES[ $type ],
			)
		);
		if ( is_wp_error( $user_id ) ) {
			self::fail( 'register', array( '_' => $user_id->get_error_message() ), $values );
			return;
		}
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );

		update_user_meta( $user_id, 'th_account_status', Accounts::UNCONFIRMED );
		update_user_meta( $user_id, 'th_terms_accepted', gmdate( 'c' ) );
		update_user_meta( $user_id, '_rtcl_user_type', 'member' === $type ? 'buyer' : 'seller' ); // Classified Listing's own buyer/seller flag.
		if ( 'business' === $type ) {
			update_user_meta( $user_id, 'custom_field_2', $values['nif'] );
			if ( '' !== $values['company'] ) {
				update_user_meta( $user_id, 'th_company', $values['company'] );
			}
		}
		Accounts::send_confirmation( get_userdata( $user_id ) );
		do_action( 'th_account_registered', $user_id, $type );

		wp_safe_redirect( add_query_arg( 'th_registered', rawurlencode( $values['email'] ), Pages::url( 'register' ) ) );
		exit;
	}

	/**
	 * Username problem or ''.
	 *
	 * @param string $username Sanitized username.
	 */
	public static function username_error( string $username ): string {
		if ( strlen( $username ) < 3 || strlen( $username ) > 30 || ! preg_match( '/^[a-z0-9._-]+$/i', $username ) ) {
			return __( '3–30 characters: letters, numbers, dot, dash or underscore.', 'torrehub' );
		}
		if ( username_exists( $username ) || ! validate_username( $username ) ) {
			/* translators: %s: suggested username */
			return sprintf( __( 'Already taken — try %s', 'torrehub' ), self::suggest_username( $username ) );
		}
		return '';
	}

	/**
	 * A free variant of a taken username.
	 *
	 * @param string $username Taken username.
	 */
	public static function suggest_username( string $username ): string {
		foreach ( array( '_tv', '_cb', '_es' ) as $suffix ) {
			if ( ! username_exists( $username . $suffix ) ) {
				return $username . $suffix;
			}
		}
		$n = 2;
		while ( username_exists( $username . $n ) ) {
			++$n;
		}
		return $username . $n;
	}

	/**
	 * Password problem or ''. Rules: ≥ 10 characters, not the username/e-mail, not a well-known password.
	 *
	 * @param string $password Password.
	 * @param string $username Username.
	 * @param string $email    E-mail.
	 */
	public static function password_error( string $password, string $username = '', string $email = '' ): string {
		if ( mb_strlen( $password ) < 10 ) {
			return __( 'Use at least 10 characters.', 'torrehub' );
		}
		$lower = strtolower( $password );
		$weak  = array( 'password12', 'password123', '1234567890', 'qwertyuiop', 'torrehub123', 'iloveyou12' );
		if ( in_array( $lower, $weak, true ) || ( $username && str_contains( $lower, strtolower( $username ) ) ) || ( $email && str_contains( $lower, strtolower( strtok( $email, '@' ) ) ) ) ) {
			return __( 'Choose a password that isn’t based on your name, e-mail or a common word.', 'torrehub' );
		}
		return '';
	}

	/* ------------------------------------------------------------------ confirmation link */

	/**
	 * /register/?th_confirm=ID&token=… → confirm, then redirect to a clean URL with the result.
	 */
	public function confirm_link(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token is the proof.
		if ( 'register' !== Pages::current() || empty( $_GET['th_confirm'] ) || empty( $_GET['token'] ) ) {
			return;
		}
		$result = Accounts::confirm( absint( $_GET['th_confirm'] ), sanitize_text_field( wp_unslash( $_GET['token'] ) ) );
		// phpcs:enable
		wp_safe_redirect( add_query_arg( 'th_confirmed', $result, Pages::url( 'register' ) ) );
		exit;
	}

	/**
	 * Resend the confirmation e-mail (same answer whether or not the address exists).
	 */
	public function resend(): void {
		$email = sanitize_email( self::field( 'email' ) );
		if ( wp_verify_nonce( self::field( '_wpnonce' ), 'th_resend' ) ) {
			$user = get_user_by( 'email', $email );
			$key  = 'th_resend_' . md5( strtolower( $email ) );
			if ( $user && Accounts::UNCONFIRMED === Accounts::status( $user->ID ) && (int) get_transient( $key ) < 3 ) {
				set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
				Accounts::send_confirmation( $user );
			}
		}
		wp_safe_redirect( add_query_arg( 'th_notice', 'resent', Pages::url( 'login' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ password reset */

	/**
	 * Request a reset link (same answer whether or not the account exists).
	 */
	public function lost(): void {
		$login = self::field( 'user_login' );
		if ( ! wp_verify_nonce( self::field( '_wpnonce' ), 'th_lost' ) ) {
			self::fail( 'lost', array( '_' => __( 'The page expired. Please try again.', 'torrehub' ) ), array( 'user_login' => $login ) );
			return;
		}
		$ip_key = 'th_lost_' . self::ip_key();
		if ( (int) get_transient( $ip_key ) < 10 ) {
			set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );
			$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
			if ( $user && Accounts::ACTIVE === Accounts::status( $user->ID ) ) {
				$key = get_password_reset_key( $user );
				if ( ! is_wp_error( $key ) ) {
					\Torrehub\Core\Mailer::send(
						$user->user_email,
						__( 'Reset your password', 'torrehub' ),
						__( 'Reset your password', 'torrehub' ),
						array( __( 'Someone asked to reset the password of your Torrehub account. If it was you, choose a new password with the button below.', 'torrehub' ) ),
						array(
							__( 'Choose a new password', 'torrehub' ),
							add_query_arg(
								array(
									'key'   => $key,
									'login' => rawurlencode( $user->user_login ),
								),
								Pages::url( 'lost' )
							),
						),
						__( 'If you didn’t ask for this, ignore this e-mail — your password stays the same. The link expires in 24 hours.', 'torrehub' )
					);
				}
			}
		}
		wp_safe_redirect( add_query_arg( 'th_sent', '1', Pages::url( 'lost' ) ) );
		exit;
	}

	/**
	 * Set the new password.
	 */
	public function reset(): void {
		$key   = self::field( 'key' );
		$login = self::field( 'login' );
		$back  = array(
			'key'   => $key,
			'login' => $login,
		);
		if ( ! wp_verify_nonce( self::field( '_wpnonce' ), 'th_reset' ) ) {
			self::fail( 'reset', array( '_' => __( 'The page expired. Please try again.', 'torrehub' ) ), $back );
			return;
		}
		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			self::fail( 'reset', array( '_' => __( 'This reset link is invalid or has expired. Ask for a new one.', 'torrehub' ) ), $back );
			return;
		}
		$password = self::field( 'password', true );
		$error    = self::password_error( $password, $user->user_login, $user->user_email );
		if ( $error ) {
			self::fail( 'reset', array( 'password' => $error ), $back );
			return;
		}
		if ( self::field( 'password2', true ) !== $password ) {
			self::fail( 'reset', array( 'password2' => __( 'The passwords don’t match.', 'torrehub' ) ), $back );
			return;
		}
		reset_password( $user, $password );
		wp_safe_redirect( add_query_arg( 'th_notice', 'password', Pages::url( 'login' ) ) );
		exit;
	}
}
