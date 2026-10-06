<?php
/**
 * Default page: title + content.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) {
	the_post();
	get_template_part( 'template-parts/content/page' );
}
get_footer();
