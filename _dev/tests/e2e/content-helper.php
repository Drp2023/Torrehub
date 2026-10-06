<?php
/**
 * Helper for _dev/tests/e2e/content.mjs (local only).
 *
 *   wp eval-file _dev/tests/e2e/content-helper.php setup    → a test guide with headings + related categories (prints its URL)
 *   wp eval-file _dev/tests/e2e/content-helper.php cleanup  → removes it and the contact-form rate limits
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

$cmd = $args[0] ?? '';
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'title' => 'E2E guide: NIE appointment', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $old ) {
	wp_delete_post( (int) $old, true );
}
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- test helper.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient%th\\_contact\\_%'" );

if ( 'setup' === $cmd ) {
	$content = '';
	foreach ( array( 'Booking the appointment', 'What to bring', 'On the day', 'Using a gestoría' ) as $h ) {
		$content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">{$h}</h2>\n<!-- /wp:heading -->\n\n";
		$content .= str_repeat( "<!-- wp:paragraph -->\n<p>" . str_repeat( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 12 ) . "</p>\n<!-- /wp:paragraph -->\n\n", 4 );
	}
	$content .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>The photocopy is the one people forget.</p>\n<!-- /wp:paragraph --></blockquote>\n<!-- /wp:quote -->";
	$id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => 'E2E guide: NIE appointment',
			'post_content'  => $content,
			'post_author'   => 1,
			'post_category' => array( (int) get_cat_ID( 'Tips in Spain' ) ),
			'post_date'     => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * 400 ), // Old: doesn't become the featured guide.
		)
	);
	$cats = array_filter( array( get_term_by( 'slug', 'services', 'rtcl_category' ), get_term_by( 'slug', 'public-information', 'rtcl_category' ) ) );
	update_post_meta( $id, Torrehub\Modules\Guides\Module::META_CATS, array_map( static fn( $t ) => (int) $t->term_id, $cats ) );
	WP_CLI::line( (string) get_permalink( $id ) );
} else {
	WP_CLI::success( 'Content test data removed.' );
}
