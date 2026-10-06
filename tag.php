<?php
/**
 * Guides index (B-01): a guide tag.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="th-main th-section" tabindex="-1">
	<?php get_template_part( 'template-parts/guides/index' ); ?>
</main>
<?php
get_footer();
