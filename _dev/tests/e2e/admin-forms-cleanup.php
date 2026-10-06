<?php
/**
 * After _dev/tests/e2e/admin-forms.mjs (local only): put the Form Builder definitions back as they were.
 *
 *   wp eval-file _dev/tests/e2e/admin-forms-cleanup.php
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

$rows = get_option( 'th_e2e_forms_snapshot' );
if ( ! is_array( $rows ) ) {
	WP_CLI::error( 'No snapshot — run admin-forms-snapshot.php before the test.' );
}
global $wpdb;
foreach ( $rows as $row ) {
	$id = (int) $row['id'];
	unset( $row['id'] );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test helper.
	$wpdb->update( $wpdb->prefix . 'rtcl_forms', $row, array( 'id' => $id ) );
}
delete_option( 'th_e2e_forms_snapshot' );
wp_cache_flush();
WP_CLI::success( count( $rows ) . ' forms restored.' );
