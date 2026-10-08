<?php
/**
 * Helper for _dev/tests/e2e/lifetime.mjs (local only).
 *
 *   setup    → three of user54's (business) live listings: A ends in 2 days, B ended (deletion in 10 days), C runs
 *              20 more days; new listings that go live for a private seller, user54 and an administrator; the FAQ
 *              page carries the lifetime questions. Prints JSON (ids, e-mail). Originals are kept for cleanup.
 *   info ID  → JSON: status, days left, never_expires, deletion_date, th_published_at of a listing
 *   expire ID → end date in the past (Classified Listing's hourly cron then ends it)
 *   cleanup  → everything back as it was
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

use Torrehub\Modules\Lifetime\Module as Lifetime;

const TH_E2E_LIFETIME = 'th_e2e_lifetime';
$cmd   = $args[0] ?? '';
$keys  = array( 'expiry_date', 'never_expires', 'deletion_date', Lifetime::NOTICE, 'th_published_at' );
$local = static fn( int $ts ) => wp_date( 'Y-m-d H:i:s', $ts );

if ( 'setup' === $cmd ) {
	delete_option( Lifetime::OPTION );
	$seller = get_user_by( 'login', 'user54' );
	$own    = get_posts(
		array(
			'post_type'   => 'rtcl_listing',
			'post_status' => 'publish',
			'author'      => $seller->ID,
			'numberposts' => 3,
			'orderby'     => 'date', // Newest: on the first page of My listings.
			'order'       => 'DESC',
			'fields'      => 'ids',
		)
	);
	$saved = array( 'listings' => array() );
	foreach ( $own as $id ) {
		$meta = array();
		foreach ( $keys as $key ) {
			$meta[ $key ] = metadata_exists( 'post', $id, $key ) ? get_post_meta( $id, $key, true ) : null;
		}
		$saved['listings'][ $id ] = array(
			'status' => get_post_status( $id ),
			'date'   => get_post_field( 'post_date', $id ),
			'gmt'    => get_post_field( 'post_date_gmt', $id ),
			'meta'   => $meta,
		);
	}
	list( $a, $b, $c ) = $own;
	global $wpdb;
	foreach ( array( $a, $b, $c ) as $i => $id ) {
		// Newest, so they are on the first page of My listings (user54 has many listings from other suites).
		$wpdb->update( $wpdb->posts, array( 'post_date' => $local( time() - $i * 60 ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - $i * 60 ) ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test fixture.
		clean_post_cache( $id );
	}
	foreach ( array( $a, $b, $c ) as $id ) {
		delete_post_meta( $id, 'never_expires' );
		delete_post_meta( $id, Lifetime::NOTICE );
	}
	update_post_meta( $a, 'expiry_date', $local( time() + 2 * DAY_IN_SECONDS ) );
	update_post_meta( $c, 'expiry_date', $local( time() + 20 * DAY_IN_SECONDS ) );
	// B: as Classified Listing's cron leaves an ended listing (no end date, a deletion date).
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'rtcl-expired' ), array( 'ID' => $b ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test fixture without side effects.
	clean_post_cache( $b );
	delete_post_meta( $b, 'expiry_date' );
	update_post_meta( $b, 'deletion_date', $local( time() + 10 * DAY_IN_SECONDS ) );
	update_post_meta( $b, 'th_published_at', 1700000000 ); // When it first went live; a renewal must keep it.

	// New listings going live: private seller, business seller, administrator.
	$private = get_user_by( 'login', 'e2eprivate' );
	$pid     = $private ? $private->ID : wp_insert_user(
		array(
			'user_login' => 'e2eprivate',
			'user_email' => 'e2eprivate@example.test',
			'user_pass'  => wp_generate_password(),
			'role'       => 'seller',
		)
	);
	$admin   = get_user_by( 'login', 'dev' );
	$new     = array();
	foreach ( array(
		'private'  => $pid,
		'business' => $seller->ID,
		'staff'    => $admin->ID,
	) as $type => $author ) {
		$id = wp_insert_post(
			array(
				'post_type'   => 'rtcl_listing',
				'post_status' => 'pending',
				'post_title'  => 'E2E lifetime ' . $type,
				'post_author' => $author,
			)
		);
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
		$new[ $type ] = $id;
	}
	$saved['new']  = $new;
	$saved['user'] = $private ? 0 : $pid;

	// FAQ with the lifetime questions (a FAQ migrated before them is migrated again).
	$faq = get_page_by_path( 'faq' );
	if ( $faq && ! str_contains( (string) $faq->post_content, 'torrehub_listing_lifetime' ) ) {
		Torrehub\Modules\Content\Migrator::rollback( $faq );
		Torrehub\Modules\Content\Migrator::migrate( get_post( $faq->ID ), Torrehub\Modules\Content\Migrator::plan()['faq'], true );
	}
	update_option( TH_E2E_LIFETIME, $saved, false );
	echo wp_json_encode(
		array(
			'a'     => $a,
			'b'     => $b,
			'c'     => $c,
			'new'   => $new,
			'email' => $seller->user_email,
			'title'  => get_the_title( $a ),
			'titleB' => get_the_title( $b ),
		)
	);
} elseif ( 'info' === $cmd ) {
	$id   = (int) ( $args[1] ?? 0 );
	$ends = Lifetime::ends( $id );
	echo wp_json_encode(
		array(
			'status'    => get_post_status( $id ),
			'days'      => $ends ? round( ( $ends - time() ) / DAY_IN_SECONDS, 2 ) : null,
			'never'     => (bool) get_post_meta( $id, 'never_expires', true ),
			'deletion'  => (string) get_post_meta( $id, 'deletion_date', true ),
			'published' => (string) get_post_meta( $id, 'th_published_at', true ),
			'notice'    => (string) get_post_meta( $id, Lifetime::NOTICE, true ),
		)
	);
} elseif ( 'expire' === $cmd ) {
	update_post_meta( (int) $args[1], 'expiry_date', $local( time() - HOUR_IN_SECONDS ) );
} else {
	$saved = (array) get_option( TH_E2E_LIFETIME, array() );
	foreach ( (array) ( $saved['listings'] ?? array() ) as $id => $orig ) {
		$wpdb = $GLOBALS['wpdb'];
		$wpdb->update( $wpdb->posts, array( 'post_status' => $orig['status'], 'post_date' => $orig['date'], 'post_date_gmt' => $orig['gmt'] ), array( 'ID' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- restore the fixture without side effects.
		clean_post_cache( (int) $id );
		foreach ( $orig['meta'] as $key => $value ) {
			null === $value ? delete_post_meta( (int) $id, $key ) : update_post_meta( (int) $id, $key, $value );
		}
	}
	foreach ( (array) ( $saved['new'] ?? array() ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
	if ( ! empty( $saved['user'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $saved['user'] );
	}
	delete_option( TH_E2E_LIFETIME );
	delete_option( Lifetime::OPTION );
	WP_CLI::success( 'Lifetime test data removed.' );
}
