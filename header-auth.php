<?php
/**
 * Focused header for the auth pages (A-01 … A-09): logo + one link (Log in / Create an account).
 *
 * @package Torrehub
 *
 * @var array $args { @type string $page login|register|lost }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Auth\Pages;

$auth_page = $args['page'] ?? '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'th-auth-body' ); ?>>
<?php wp_body_open(); ?>
<a class="th-skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'torrehub' ); ?></a>

<header class="th-auth-header">
	<div class="th-container th-auth-header__bar">
		<?php get_template_part( 'template-parts/header/logo' ); ?>
		<p class="th-auth-header__switch">
			<?php if ( 'login' === $auth_page ) : ?>
				<span class="th-auth-header__hint"><?php esc_html_e( 'New to Torrehub?', 'torrehub' ); ?></span>
				<a href="<?php echo esc_url( Pages::url( 'register' ) ); ?>"><?php esc_html_e( 'Create an account', 'torrehub' ); ?></a>
			<?php else : ?>
				<span class="th-auth-header__hint"><?php esc_html_e( 'Already registered?', 'torrehub' ); ?></span>
				<a href="<?php echo esc_url( Pages::url( 'login' ) ); ?>"><?php esc_html_e( 'Log in', 'torrehub' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</header>
