<?php
/**
 * Helper for _dev/tests/e2e/search-alerts.mjs (local only).
 *
 *   wp eval-file _dev/tests/e2e/search-alerts-helper.php publish "<title>" <price>   → publishes a Cars listing by user54
 *   wp eval-file _dev/tests/e2e/search-alerts-helper.php backdate                    → tester's alerts: last sent 2 days ago
 *   wp eval-file _dev/tests/e2e/search-alerts-helper.php cleanup                     → removes tester's alerts + "E2E alert" listings
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

$cmd    = $args[0] ?? '';
$tester = get_user_by( 'login', 'tester' );
$seller = get_user_by( 'login', 'user54' );
global $wpdb;
$table = Torrehub\Modules\SearchAlerts\Store::table();

if ( 'publish' === $cmd ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'rtcl_listing',
			'post_status'  => 'pending',
			'post_title'   => (string) ( $args[1] ?? 'E2E alert listing' ),
			'post_content' => 'Test listing for the search alert test.',
			'post_author'  => $seller->ID,
		)
	);
	wp_set_object_terms( $id, array( 'auto-moto-boats', 'vehicles-for-sale', 'cars' ), 'rtcl_category' );
	wp_set_object_terms( $id, 'torrevieja', 'rtcl_location' );
	update_post_meta( $id, 'price', (string) ( $args[2] ?? '5000' ) );
	update_post_meta( $id, 'ad_type', 'automotoboats' );
	update_post_meta( $id, '_rtcl_form_id', 5 );
	wp_update_post(
		array(
			'ID'          => $id,
			'post_status' => 'publish',
		)
	); // Like an admin approving it.
	WP_CLI::line( (string) $id );
} elseif ( 'backdate' === $cmd ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test helper.
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET last_sent = %s WHERE user_id = %d", gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS ), $tester->ID ) );
	WP_CLI::line( 'ok' );
} elseif ( 'cleanup' === $cmd ) {
	Torrehub\Modules\SearchAlerts\Store::forget_user( $tester->ID );
	foreach ( get_posts( array( 'post_type' => 'rtcl_listing', 'post_status' => 'any', 's' => 'E2E alert', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
	wp_clear_scheduled_hook( 'th_search_alerts_instant' );
	WP_CLI::success( 'Search alert test data removed.' );
}
