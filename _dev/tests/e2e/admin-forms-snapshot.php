<?php
/**
 * Before _dev/tests/e2e/admin-forms.mjs (local only): snapshot the Form Builder definitions it edits.
 * Restore with admin-forms-cleanup.php.
 *
 *   wp eval-file _dev/tests/e2e/admin-forms-snapshot.php
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test helper.
$rows = $wpdb->get_results( "SELECT id, fields, sections, settings, single_layout FROM {$wpdb->prefix}rtcl_forms", ARRAY_A );
update_option( 'th_e2e_forms_snapshot', $rows, false );
WP_CLI::success( count( $rows ) . ' forms saved.' );
