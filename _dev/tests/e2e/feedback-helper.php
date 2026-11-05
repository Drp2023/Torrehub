<?php
/**
 * Helper for _dev/tests/e2e/feedback.mjs (local only).
 *
 *   setup             → JSON: a pending listing of user54 to approve; originals saved for cleanup
 *   pend ID           → back to pending
 *   status ID         → post status
 *   rtcl-published on|off → Classified Listing's own "Listing is approved/published" e-mail
 *   cleanup           → everything back as it was
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

const TH_E2E_FEEDBACK = 'th_e2e_feedback';
$cmd  = $args[0] ?? '';
$opts = 'rtcl_email_notifications_settings';

if ( 'setup' === $cmd ) {
	$seller  = get_user_by( 'login', 'user54' );
	$pending = get_posts(
		array(
			'post_type'   => 'rtcl_listing',
			'post_status' => 'pending',
			'author'      => $seller->ID,
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	$id = (int) ( $pending[0] ?? 0 );
	update_option(
		TH_E2E_FEEDBACK,
		array(
			'listing' => $id,
			'meta'    => array_map( static fn( $k ) => get_post_meta( $id, $k, true ), array_combine( array( 'expiry_date', 'never_expires' ), array( 'expiry_date', 'never_expires' ) ) ),
			'notify'  => get_option( $opts ),
		),
		false
	);
	echo wp_json_encode(
		array(
			'listing' => $id,
			'title'   => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
			'email'   => $seller->user_email,
		)
	);
} elseif ( 'pend' === $cmd ) {
	wp_update_post(
		array(
			'ID'          => (int) $args[1],
			'post_status' => 'pending',
		)
	);
} elseif ( 'status' === $cmd ) {
	echo esc_html( (string) get_post_status( (int) $args[1] ) );
} elseif ( 'rtcl-published' === $cmd ) {
	$o                 = (array) get_option( $opts, array() );
	$users             = array_diff( (array) ( $o['notify_users'] ?? array() ), array( 'listing_published' ) );
	$o['notify_users'] = 'on' === ( $args[1] ?? '' ) ? array_merge( $users, array( 'listing_published' ) ) : array_values( $users );
	update_option( $opts, $o );
} elseif ( 'cleanup' === $cmd ) {
	$saved = (array) get_option( TH_E2E_FEEDBACK, array() );
	$id    = (int) ( $saved['listing'] ?? 0 );
	if ( $id ) {
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'pending',
			)
		);
		foreach ( (array) ( $saved['meta'] ?? array() ) as $key => $value ) {
			'' === $value ? delete_post_meta( $id, $key ) : update_post_meta( $id, $key, $value );
		}
	}
	if ( array_key_exists( 'notify', $saved ) ) {
		update_option( $opts, $saved['notify'] );
	}
	delete_option( TH_E2E_FEEDBACK );
}
