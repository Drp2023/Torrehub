<?php
/**
 * Site header (G-01 desktop, G-08 mobile, G-02 logged in) + mega panel (G-04), drawer (G-09), location (G-05).
 *
 * One markup for all widths; CSS switches desktop/mobile parts at 900px.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

$th_town      = th_current_town();
$th_town_name = $th_town ? $th_town['name'] : __( 'Choose town', 'torrehub' );
$th_user      = wp_get_current_user();
$th_logged_in = $th_user->exists();
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

<header class="<?php echo esc_attr( th_classes( 'th-header', array( 'th-header--home' => is_front_page() ) ) ); ?>" data-th-header>
	<div class="th-container th-header__bar">
		<button type="button" class="th-btn th-btn--icon th-btn--white th-header__menu" data-th-dialog-open="th-drawer" aria-controls="th-drawer" aria-expanded="false" aria-haspopup="dialog">
			<?php th_icon( 'menu' ); ?><span class="th-sr-only"><?php esc_html_e( 'Menu', 'torrehub' ); ?></span>
		</button>

		<?php get_template_part( 'template-parts/header/logo' ); ?>

		<?php if ( th_has_rtcl() ) : ?>
			<button type="button" class="th-loc-pill th-header__desktop" data-th-dialog-open="th-location" aria-controls="th-location" aria-expanded="false" aria-haspopup="dialog">
				<?php th_icon( 'map-pin-dot', array( 'size' => 18 ) ); ?>
				<span><span class="th-sr-only"><?php esc_html_e( 'Your town:', 'torrehub' ); ?></span> <?php echo esc_html( $th_town_name ); ?></span>
				<?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?>
			</button>

			<?php get_template_part( 'template-parts/header/search', null, array( 'class' => 'th-header__desktop th-header__search' ) ); ?>
		<?php endif; ?>

		<nav class="th-header__nav th-header__desktop" aria-label="<?php esc_attr_e( 'Main', 'torrehub' ); ?>">
			<?php if ( th_has_rtcl() ) : ?>
				<button type="button" class="th-nav-link" aria-expanded="false" aria-controls="th-mega" data-th-mega-toggle>
					<?php esc_html_e( 'Explore', 'torrehub' ); ?> <?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?>
				</button>
			<?php endif; ?>
			<a class="th-nav-link" href="<?php echo esc_url( th_url_guides() ); ?>"<?php echo is_home() || is_singular( 'post' ) ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'Guides', 'torrehub' ); ?></a>
		</nav>

		<div class="th-header__actions">
			<?php get_template_part( 'template-parts/header/language-switcher', null, array( 'variant' => 'dropdown' ) ); ?>

			<?php if ( $th_logged_in ) : ?>
				<a class="th-header__avatar" href="<?php echo esc_url( th_url_account() ); ?>">
					<?php echo th_get_avatar( $th_user->ID, 38 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<span class="th-sr-only"><?php esc_html_e( 'My account', 'torrehub' ); ?></span>
				</a>
			<?php else : ?>
				<?php
				th_component(
					'button',
					array(
						'label'   => __( 'Log in', 'torrehub' ),
						'variant' => 'outline',
						'href'    => th_url_login(),
						'class'   => 'th-header__login',
					)
				);
				?>
			<?php endif; ?>

			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Post a listing', 'torrehub' ),
					'variant' => 'accent',
					'href'    => th_url_post_listing(),
					'class'   => 'th-header__desktop th-header__post',
				)
			);
			?>
		</div>
	</div>

	<?php if ( th_has_rtcl() ) : ?>
		<div class="th-container th-header__row th-header__mobile">
			<button type="button" class="th-loc-pill" data-th-dialog-open="th-location" aria-controls="th-location" aria-expanded="false" aria-haspopup="dialog">
				<?php th_icon( 'map-pin-dot', array( 'size' => 18 ) ); ?>
				<span><span class="th-sr-only"><?php esc_html_e( 'Your town:', 'torrehub' ); ?></span> <?php echo esc_html( $th_town_name ); ?></span>
				<?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?>
			</button>
			<?php get_template_part( 'template-parts/header/search', null, array( 'class' => 'th-search--mobile' ) ); ?>
		</div>

		<?php get_template_part( 'template-parts/header/mega-panel' ); ?>
	<?php endif; ?>
</header>

<?php
get_template_part( 'template-parts/header/drawer' );
if ( th_has_rtcl() ) {
	get_template_part( 'template-parts/header/location-dialog' );
}
