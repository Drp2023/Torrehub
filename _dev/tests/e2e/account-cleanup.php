<?php
/**
 * Undo _dev/tests/e2e/account.mjs (local only): un-verify user54, drop its verification record, restore listings
 * of user54 from the trash.
 *
 *   wp eval-file _dev/tests/e2e/account-cleanup.php
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

delete_user_meta( 54, 'rtcl_verified_seller' );
delete_user_meta( 54, 'th_verification' );
delete_user_meta( 54, 'th_verification_consent' );
$restored = 0;
foreach ( get_posts( array( 'post_type' => 'rtcl_listing', 'post_status' => 'trash', 'author' => 54, 'numberposts' => -1 ) ) as $post ) {
	wp_untrash_post( $post->ID );
	wp_update_post( array( 'ID' => $post->ID, 'post_status' => get_post_meta( $post->ID, '_wp_trash_meta_status', true ) ?: 'publish' ) );
	++$restored;
}
Torrehub\Data\Directory::flush();
WP_CLI::success( sprintf( 'user54 un-verified; %d listing(s) restored.', $restored ) );
