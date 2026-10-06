<?php
/**
 * Undo the side effects of _dev/tests/e2e/listing-form.mjs (local only): listings and drafts titled "E2E …"
 * by user54, with their photos and files.
 *
 *   wp eval-file _dev/tests/e2e/listing-form-cleanup.php
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

$user = get_user_by( 'login', 'user54' );
if ( ! $user ) {
	WP_CLI::success( 'Nothing to clean.' );
	return;
}
$ids = get_posts(
	array(
		'post_type'        => 'rtcl_listing',
		'post_status'      => 'any',
		'author'           => $user->ID,
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	)
);
$temp = get_posts(
	array(
		'post_type'        => 'rtcl_listing',
		'post_status'      => array( 'rtcl-temp', 'trash' ),
		'author'           => $user->ID,
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	)
);
$n = 0;
foreach ( array_unique( array_merge( $ids, $temp ) ) as $id ) {
	$title = get_the_title( $id );
	$draft = (string) get_post_meta( $id, 'th_draft', true );
	if ( str_starts_with( $title, 'E2E' ) || str_contains( $draft, 'E2E' ) || 'RTCL Auto Temp' === $title || ( 'rtcl-temp' === get_post_status( $id ) ) ) {
		Torrehub\Modules\ListingForm\Drafts::delete( (int) $id );
		++$n;
	}
}
WP_CLI::success( "Removed {$n} test listings/drafts." );
