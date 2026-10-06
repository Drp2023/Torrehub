<?php
/**
 * Undo the side effects of _dev/tests/e2e/listing.mjs (local only):
 * reviews and reports made by the "tester" account on the test listing.
 *
 *   wp eval-file _dev/tests/e2e/listing-cleanup.php
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

$user    = get_user_by( 'login', 'tester' );
$listing = get_page_by_path( 'amrit-restaurant', OBJECT, 'rtcl_listing' );
if ( ! $user || ! $listing ) {
	WP_CLI::success( 'Nothing to clean.' );
	return;
}

foreach ( get_comments( array( 'post_id' => $listing->ID, 'user_id' => $user->ID, 'type' => 'review', 'status' => 'all' ) ) as $comment ) {
	wp_delete_comment( (int) $comment->comment_ID, true );
}

$users = array_values( array_diff( array_map( 'intval', (array) get_post_meta( $listing->ID, 'tlrs_reported_users', true ) ), array( $user->ID ) ) );
$log   = array_values( array_filter( (array) get_post_meta( $listing->ID, 'th_report_log', true ), static fn( $e ) => (int) ( $e['user'] ?? 0 ) !== $user->ID ) );
if ( $users ) {
	update_post_meta( $listing->ID, 'tlrs_reported_users', $users );
	update_post_meta( $listing->ID, 'tlrs_report_count', count( $users ) );
} else {
	delete_post_meta( $listing->ID, 'tlrs_reported_users' );
	delete_post_meta( $listing->ID, 'tlrs_report_count' );
}
$log ? update_post_meta( $listing->ID, 'th_report_log', $log ) : delete_post_meta( $listing->ID, 'th_report_log' );
if ( 'pending' === get_post_status( $listing ) ) {
	wp_update_post( array( 'ID' => $listing->ID, 'post_status' => 'publish' ) );
}
WP_CLI::success( 'Test review and report removed.' );
