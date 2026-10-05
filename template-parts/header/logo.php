<?php
/**
 * Site logo: Customizer custom logo, else the bundled PNG (SVG pending — open question).
 *
 * @package Torrehub
 *
 * @var array $args { @type string $variant 'default'|'light' (light = white on dark, e.g. footer) }
 */

defined( 'ABSPATH' ) || exit;

$variant = $args['variant'] ?? 'default';
$home    = home_url( '/' );
$name    = get_bloginfo( 'name' );
?>
<a class="<?php echo esc_attr( th_classes( 'th-logo', 'th-logo--' . $variant ) ); ?>" href="<?php echo esc_url( $home ); ?>" rel="home">
	<?php
	// DECISION: the brand logo ships with the theme. WordPress mirrors the site-wide `site_logo` option into every
	// theme's custom_logo, so the old site's logo would silently win; a custom logo is used only when switched on.
	$logo_id = th_mod( 'th_use_custom_logo' ) ? (int) get_theme_mod( 'custom_logo' ) : 0;
	if ( $logo_id ) {
		echo wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'class'         => 'th-logo__img',
				'alt'           => $name,
				'loading'       => 'eager',
				'fetchpriority' => 'high',
				'decoding'      => 'async',
			)
		);
	} else {
		// Cut from the brand sheet by _dev/tools/make-logo.php (276×96 = 3× the 32px header height).
		printf(
			'<img class="th-logo__img" src="%s" alt="%s" width="276" height="96" decoding="async" fetchpriority="high">',
			esc_url( th_asset( 'assets/img/torrehub-logo.webp' ) ),
			esc_attr( $name )
		);
	}
	?>
</a>
