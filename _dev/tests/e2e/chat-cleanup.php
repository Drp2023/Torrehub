<?php
/**
 * Undo the side effects of _dev/tests/e2e/chat.mjs (local only): conversations of "tester" and the rate limit.
 *
 *   wp eval-file _dev/tests/e2e/chat-cleanup.php
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

$user = get_user_by( 'login', 'tester' );
if ( ! $user ) {
	WP_CLI::success( 'Nothing to clean.' );
	return;
}
Torrehub\Modules\Chat\Store::forget_user( $user->ID );
delete_transient( 'th_chat_rate_' . $user->ID );
$seller = get_user_by( 'login', 'user54' );
if ( $seller ) {
	delete_transient( 'th_chat_rate_' . $seller->ID );
}
WP_CLI::success( 'Test conversations removed.' );
