<?php
/**
 * Document head + site header. Phase 1: minimal shell (full G-01…G-11 header arrives in phase 2).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="th-skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'torrehub' ); ?></a>

<header class="th-site-header" role="banner">
	<div class="th-container th-site-header__inner">
		<?php get_template_part( 'template-parts/header/logo' ); ?>
	</div>
</header>
