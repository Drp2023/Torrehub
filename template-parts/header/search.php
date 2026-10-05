<?php
/**
 * Header search: GET to the RTCL listings archive with `q` (+ the current town as `rtcl_location`).
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string $class       Extra classes.
 *     @type string $placeholder Placeholder text.
 *     @type string $variant     'header' (pill) or 'hero' (white pill with an inner accent button). Default 'header'.
 *     @type string $id          Input id (unique per page).
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'class'       => '',
		'placeholder' => __( 'Search Torrehub', 'torrehub' ),
		'variant'     => 'header',
		'id'          => '',
	)
);

$th_town  = th_current_town();
$input_id = $a['id'] ? $a['id'] : 'th-q-' . wp_unique_id();
$query    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- search term echo.
?>
<form class="<?php echo esc_attr( th_classes( 'th-search', 'th-search--' . $a['variant'], $a['class'] ) ); ?>" role="search" method="get" action="<?php echo esc_url( th_url_listings() ); ?>">
	<label class="th-sr-only" for="<?php echo esc_attr( $input_id ); ?>"><?php esc_html_e( 'Search listings', 'torrehub' ); ?></label>
	<?php
	th_icon(
		'search',
		array(
			'size'  => 18,
			'class' => 'th-search__icon',
		)
	);
	?>
	<input class="th-search__input" id="<?php echo esc_attr( $input_id ); ?>" type="search" name="q" value="<?php echo esc_attr( $query ); ?>" placeholder="<?php echo esc_attr( $a['placeholder'] ); ?>" autocomplete="off" enterkeyhint="search">
	<?php if ( $th_town ) : ?>
		<input type="hidden" name="rtcl_location" value="<?php echo esc_attr( $th_town['slug'] ); ?>">
	<?php endif; ?>
	<?php if ( 'hero' === $a['variant'] ) : ?>
		<button class="th-btn th-btn--accent th-search__submit" type="submit"><?php esc_html_e( 'Search', 'torrehub' ); ?></button>
	<?php else : ?>
		<button class="th-search__go" type="submit"><?php th_icon( 'search', array( 'size' => 18 ) ); ?><span class="th-sr-only"><?php esc_html_e( 'Search', 'torrehub' ); ?></span></button>
	<?php endif; ?>
</form>
