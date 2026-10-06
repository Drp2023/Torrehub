<?php
/**
 * Helper for _dev/tests/e2e/consent.mjs (local only).
 *
 *   wp eval-file _dev/tests/e2e/consent-helper.php setup    → test snippets in Statistics + Marketing, a guide with a YouTube embed (prints its URL)
 *   wp eval-file _dev/tests/e2e/consent-helper.php cleanup  → back to no snippets, guide removed
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

$cmd = $args[0] ?? '';
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'title' => 'E2E consent embed', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $old ) {
	wp_delete_post( (int) $old, true );
}
if ( 'setup' === $cmd ) {
	$s                          = Torrehub\Modules\Consent\Module::settings();
	$s['scripts']['statistics'] = "<script>window.thStat = (window.thStat || 0) + 1;</script>\n<noscript><img src=\"https://example.com/pixel.gif\" alt=\"\"></noscript>";
	$s['scripts']['marketing']  = '<script type="text/javascript">window.thMkt = 1;</script>';
	++$s['version'];
	update_option( Torrehub\Modules\Consent\Module::OPTION, $s );
	$id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'E2E consent embed',
			'post_content' => "<!-- wp:paragraph -->\n<p>Video below.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:embed {\"url\":\"https://www.youtube.com/watch?v=dQw4w9WgXcQ\",\"type\":\"video\",\"providerNameSlug\":\"youtube\"} -->\n<figure class=\"wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube\"><div class=\"wp-block-embed__wrapper\">\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\n</div></figure>\n<!-- /wp:embed -->",
			'post_date'    => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * 500 ),
		)
	);
	WP_CLI::line( (string) get_permalink( $id ) );
} else {
	$s            = Torrehub\Modules\Consent\Module::settings();
	$s['scripts'] = array_fill_keys( Torrehub\Modules\Consent\Module::CATEGORIES, '' );
	update_option( Torrehub\Modules\Consent\Module::OPTION, $s );
	WP_CLI::success( 'Consent test data removed.' );
}
