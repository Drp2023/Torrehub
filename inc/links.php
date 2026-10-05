<?php
/**
 * Canonical URLs used across templates (one place to change when auth/account pages move in phase 5).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classified Listing page permalink (listings, listing_form, myaccount, checkout…), with a fallback.
 *
 * @param string $page     RTCL page key.
 * @param string $fallback Path used when RTCL or the page is missing.
 */
function th_rtcl_page_url( string $page, string $fallback ): string {
	if ( class_exists( '\Rtcl\Helpers\Link' ) ) {
		$url = \Rtcl\Helpers\Link::get_page_permalink( $page );
		if ( $url && ! is_wp_error( $url ) ) {
			return (string) $url;
		}
	}
	return home_url( $fallback );
}

/**
 * Listings archive URL, optionally with RTCL query args (q, rtcl_location, rtcl_category …).
 *
 * @param array<string,string> $args Query args.
 */
function th_url_listings( array $args = array() ): string {
	$url = th_rtcl_page_url( 'listings', '/listings/' );
	return $args ? add_query_arg( array_map( 'rawurlencode', array_filter( $args, 'strlen' ) ), $url ) : $url;
}

/**
 * Login URL with a return target.
 *
 * DECISION: phase 5 replaces this with the theme's /login/ page.
 *
 * @param string $redirect_to Where to return after login.
 */
function th_url_login( string $redirect_to = '' ): string {
	return wp_login_url( $redirect_to ? $redirect_to : home_url( add_query_arg( array() ) ) );
}

/**
 * Account dashboard (logged in) or login (guest).
 */
function th_url_account(): string {
	return is_user_logged_in() ? th_rtcl_page_url( 'myaccount', '/my-account/' ) : th_url_login( th_rtcl_page_url( 'myaccount', '/my-account/' ) );
}

/**
 * Can the current user post listings? Sellers, businesses and admins/editors can; members (customer) cannot.
 */
function th_user_can_post(): bool {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$user = wp_get_current_user();
	return (bool) array_intersect( array( 'seller', 'business', 'administrator', 'editor' ), (array) $user->roles );
}

/**
 * "Post a listing" target: the listing form for sellers, login for guests (returning to the form),
 * the account page for members (phase 5 adds the 403 "you need a seller account" view).
 */
function th_url_post_listing(): string {
	$form = th_rtcl_page_url( 'listing_form', '/listing-form/' );
	if ( ! is_user_logged_in() ) {
		return th_url_login( $form );
	}
	return th_user_can_post() ? $form : th_url_account();
}

/**
 * Guides index (posts page), or home when no posts page is set yet (open question in BUILD-PLAN).
 */
function th_url_guides(): string {
	$page = (int) get_option( 'page_for_posts' );
	return $page ? (string) get_permalink( $page ) : home_url( '/' );
}
