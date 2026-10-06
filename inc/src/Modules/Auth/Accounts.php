<?php
/**
 * Account lifecycle: unconfirmed → (e-mail link, 48 h) → pending → (admin) → active | rejected.
 *
 * User meta: `th_account_status` (missing = active: every account created before the theme), `th_confirm_token`
 * (hash) + `th_confirm_expires`, `th_reject_reason`. Account type = role: member → customer, seller → seller,
 * business → business. Business NIF: `custom_field_2` (kept from the old registration form).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Auth;

use Torrehub\Core\Mailer;

defined( 'ABSPATH' ) || exit;

/**
 * Account helpers.
 */
final class Accounts {

	public const UNCONFIRMED = 'unconfirmed';
	public const PENDING     = 'pending';
	public const ACTIVE      = 'active';
	public const REJECTED    = 'rejected';

	/** Account types → roles. */
	public const TYPES = array(
		'member'   => 'customer',
		'seller'   => 'seller',
		'business' => 'business',
	);

	/**
	 * Status of a user.
	 *
	 * @param int $user_id User id.
	 */
	public static function status( int $user_id ): string {
		$status = (string) get_user_meta( $user_id, 'th_account_status', true );
		return in_array( $status, array( self::UNCONFIRMED, self::PENDING, self::REJECTED ), true ) ? $status : self::ACTIVE;
	}

	/**
	 * Account type key of a user (member|seller|business), '' for staff.
	 *
	 * @param \WP_User $user User.
	 */
	public static function type( \WP_User $user ): string {
		foreach ( self::TYPES as $type => $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return $type;
			}
		}
		return '';
	}

	/**
	 * Translated account type label.
	 *
	 * @param string $type Type key.
	 */
	public static function type_label( string $type ): string {
		$labels = array(
			'member'   => __( 'Member', 'torrehub' ),
			'seller'   => __( 'Private Seller', 'torrehub' ),
			'business' => __( 'Business Seller', 'torrehub' ),
		);
		return $labels[ $type ] ?? '';
	}

	/* ------------------------------------------------------------------ NIF */

	/**
	 * Normalise a NIF: uppercase, no spaces/dots/dashes, optional "ES" prefix removed.
	 *
	 * @param string $nif Raw input.
	 */
	public static function normalise_nif( string $nif ): string {
		$nif = strtoupper( (string) preg_replace( '/[\s.\-]/', '', $nif ) );
		return str_starts_with( $nif, 'ES' ) && strlen( $nif ) === 11 ? substr( $nif, 2 ) : $nif;
	}

	/**
	 * Valid Spanish tax id for a business: DNI (8 digits + letter), NIE-form NIF (X/Y/Z + 7 digits + letter, used by
	 * self-employed foreigners) or CIF (company letter + 7 digits + control digit/letter). Check characters verified.
	 *
	 * @param string $nif Normalised NIF.
	 */
	public static function valid_nif( string $nif ): bool {
		$letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
		if ( preg_match( '/^(\d{8})([A-Z])$/', $nif, $m ) ) {
			return $letters[ (int) $m[1] % 23 ] === $m[2];
		}
		if ( preg_match( '/^([XYZ])(\d{7})([A-Z])$/', $nif, $m ) ) {
			return $letters[ (int) ( strpos( 'XYZ', $m[1] ) . $m[2] ) % 23 ] === $m[3];
		}
		if ( preg_match( '/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-J])$/', $nif, $m ) ) {
			$sum = 0;
			foreach ( str_split( $m[2] ) as $i => $d ) {
				$d = (int) $d;
				if ( 0 === $i % 2 ) { // Odd positions (1st, 3rd …): double, add the digits.
					$d *= 2;
					$d  = intdiv( $d, 10 ) + $d % 10;
				}
				$sum += $d;
			}
			$digit  = ( 10 - $sum % 10 ) % 10;
			$letter = 'JABCDEFGHI'[ $digit ];
			if ( str_contains( 'PQRSNW', $m[1] ) ) {
				return $m[3] === $letter;
			}
			if ( str_contains( 'ABEH', $m[1] ) ) {
				return $m[3] === (string) $digit;
			}
			return $m[3] === (string) $digit || $m[3] === $letter;
		}
		return false;
	}

	/* ------------------------------------------------------------------ confirmation */

	/**
	 * New confirmation token (stored hashed, valid 48 h) → confirmation URL.
	 *
	 * @param \WP_User $user User.
	 */
	public static function confirmation_url( \WP_User $user ): string {
		$token = wp_generate_password( 32, false );
		update_user_meta( $user->ID, 'th_confirm_token', wp_hash_password( $token ) );
		update_user_meta( $user->ID, 'th_confirm_expires', time() + 2 * DAY_IN_SECONDS );
		return add_query_arg(
			array(
				'th_confirm' => $user->ID,
				'token'      => $token,
			),
			Pages::url( 'register' )
		);
	}

	/**
	 * Check a confirmation link; on success the account moves to "pending" and the admin is told.
	 *
	 * @param int    $user_id User id.
	 * @param string $token   Token from the link.
	 * @return string ok|expired|invalid|done
	 */
	public static function confirm( int $user_id, string $token ): string {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return 'invalid';
		}
		if ( self::UNCONFIRMED !== self::status( $user_id ) ) {
			return 'done';
		}
		$hash = (string) get_user_meta( $user_id, 'th_confirm_token', true );
		if ( '' === $hash || ! wp_check_password( $token, $hash ) ) {
			return 'invalid';
		}
		if ( (int) get_user_meta( $user_id, 'th_confirm_expires', true ) < time() ) {
			return 'expired';
		}
		delete_user_meta( $user_id, 'th_confirm_token' );
		delete_user_meta( $user_id, 'th_confirm_expires' );
		update_user_meta( $user_id, 'th_account_status', self::PENDING );
		self::notify_admin_new( $user );
		return 'ok';
	}

	/**
	 * Send (or resend) the confirmation e-mail.
	 *
	 * @param \WP_User $user User.
	 */
	public static function send_confirmation( \WP_User $user ): void {
		Mailer::send(
			$user->user_email,
			__( 'Confirm your e-mail address', 'torrehub' ),
			/* translators: %s: first name */
			sprintf( __( 'Welcome, %s!', 'torrehub' ), $user->first_name ? $user->first_name : $user->display_name ),
			array(
				__( 'Please confirm your e-mail address to finish creating your Torrehub account.', 'torrehub' ),
				__( 'After that, a person reviews every new account — usually within 48 hours. We’ll e-mail you as soon as it’s approved.', 'torrehub' ),
			),
			array( __( 'Confirm my e-mail', 'torrehub' ), self::confirmation_url( $user ) ),
			__( 'The link is valid for 48 hours. If you didn’t create an account, ignore this e-mail.', 'torrehub' )
		);
	}

	/* ------------------------------------------------------------------ approval */

	/**
	 * Approve an account.
	 *
	 * @param int $user_id User id.
	 */
	public static function approve( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		update_user_meta( $user_id, 'th_account_status', self::ACTIVE );
		delete_user_meta( $user_id, 'th_reject_reason' );
		delete_user_meta( $user_id, 'th_confirm_token' );
		delete_user_meta( $user_id, 'th_confirm_expires' );
		Mailer::send(
			$user->user_email,
			__( 'Your account is approved', 'torrehub' ),
			__( 'You’re in!', 'torrehub' ),
			array(
				/* translators: %s: account type */
				sprintf( __( 'Your %s account on Torrehub has been approved. You can log in now.', 'torrehub' ), self::type_label( self::type( $user ) ) ),
			),
			array( __( 'Log in', 'torrehub' ), Pages::url( 'login' ) )
		);
		do_action( 'th_account_approved', $user );
	}

	/**
	 * Reject an account.
	 *
	 * @param int    $user_id User id.
	 * @param string $reason  Reason shown to the user (optional).
	 */
	public static function reject( int $user_id, string $reason = '' ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		update_user_meta( $user_id, 'th_account_status', self::REJECTED );
		if ( '' !== $reason ) {
			update_user_meta( $user_id, 'th_reject_reason', $reason );
		}
		Mailer::send(
			$user->user_email,
			__( 'About your Torrehub account', 'torrehub' ),
			__( 'We couldn’t approve your account', 'torrehub' ),
			array_filter(
				array(
					__( 'Thanks for registering. After reviewing your details we couldn’t approve the account.', 'torrehub' ),
					/* translators: %s: reason */
					'' !== $reason ? sprintf( __( 'Reason: %s', 'torrehub' ), $reason ) : '',
					__( 'If you think this is a mistake, reply to this e-mail.', 'torrehub' ),
				)
			)
		);
		do_action( 'th_account_rejected', $user, $reason );
	}

	/**
	 * Tell the site admin a confirmed account waits for approval.
	 *
	 * @param \WP_User $user User.
	 */
	public static function notify_admin_new( \WP_User $user ): void {
		Mailer::send(
			(string) get_option( 'admin_email' ),
			__( 'New account awaiting approval', 'torrehub' ),
			/* translators: %s: account type */
			sprintf( __( 'New %s account', 'torrehub' ), self::type_label( self::type( $user ) ) ),
			array(
				/* translators: 1: name, 2: e-mail */
				sprintf( __( '%1$s (%2$s) confirmed their e-mail address and is waiting for approval.', 'torrehub' ), $user->display_name, $user->user_email ),
			),
			array( __( 'Review accounts', 'torrehub' ), admin_url( 'users.php?th_status=pending' ) )
		);
	}
}
