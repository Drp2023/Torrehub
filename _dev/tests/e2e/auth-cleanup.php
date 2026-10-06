<?php
/**
 * Remove the accounts created by _dev/tests/e2e/auth.mjs (local only): every user with an @e2e.test address.
 *
 *   wp eval-file _dev/tests/e2e/auth-cleanup.php
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

require_once ABSPATH . 'wp-admin/includes/user.php';
$n = 0;
foreach ( get_users( array( 'search' => '*@e2e.test', 'search_columns' => array( 'user_email' ) ) ) as $user ) {
	wp_delete_user( $user->ID );
	++$n;
}
WP_CLI::success( sprintf( '%d test account(s) removed.', $n ) );
