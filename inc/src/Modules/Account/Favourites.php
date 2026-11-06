<?php
/**
 * Favourites (client request 2026-11) — Classified Listing's own feature, not a copy: the `rtcl_favourites` user
 * meta, its AJAX toggle and its "favourites" account endpoint (switched on by Migration step U3).
 *
 * - Hearts on cards and on the listing page: template-parts/components/fav-button.php + favourites.js.
 * - Guests: the heart links to the login page with `redirect_to` = this page + a signed `th_fav`; after logging in
 *   the listing is saved here and the page comes back without the parameters (`th_faved` → "Saved" toast).
 * - Account › Favourites for every role: classified-listing/myaccount/favourite-listings.php (cards, as in the
 *   archive). Saved searches are off since 1.1.0 (SearchAlerts module, U4).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Favourites.
 */
final class Favourites {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'save_after_login' ), 0 );
		add_filter( 'th_account_lean_sections', static fn( $sections ) => array_merge( (array) $sections, array( 'favourites' ) ) );
	}

	/**
	 * Back from the login page with `?th_fav={id}&th_fav_t={signature}`: save it, then the clean URL.
	 */
	public function save_after_login(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- signed link (th_fav_token), adds a favourite only.
		if ( ! isset( $_GET['th_fav'] ) || ! is_user_logged_in() ) {
			return;
		}
		$id    = absint( $_GET['th_fav'] );
		$token = isset( $_GET['th_fav_t'] ) ? sanitize_key( wp_unslash( $_GET['th_fav_t'] ) ) : '';
		// phpcs:enable
		$back = remove_query_arg( array( 'th_fav', 'th_fav_t' ) );
		if ( th_favourites_enabled() && $id && hash_equals( th_fav_token( $id ), $token ) && 'rtcl_listing' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
			self::add( get_current_user_id(), $id );
			$back = add_query_arg( 'th_faved', $id, $back );
		}
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Add to a user's favourites (the same list Classified Listing's AJAX toggles).
	 *
	 * @param int $user_id    User.
	 * @param int $listing_id Listing.
	 */
	public static function add( int $user_id, int $listing_id ): void {
		$favs = array_values( array_filter( array_map( 'intval', (array) get_user_meta( $user_id, 'rtcl_favourites', true ) ) ) );
		if ( ! in_array( $listing_id, $favs, true ) ) {
			$favs[] = $listing_id;
			update_user_meta( $user_id, 'rtcl_favourites', $favs );
		}
	}
}
