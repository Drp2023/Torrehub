<?php
/**
 * Single guide (B-02). Other post types have their own templates (listings: classified-listing/).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) {
	the_post();
	get_template_part( 'post' === get_post_type() ? 'template-parts/guides/single' : 'template-parts/content/page' );
}
get_footer();
