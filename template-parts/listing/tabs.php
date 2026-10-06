<?php
/**
 * Sticky section tabs (anchor links; listing.js marks the section in view with aria-current).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

$view         = $args['view'];
$section_tabs = array( 'details' => __( 'Details', 'torrehub' ) );
if ( '' !== trim( (string) $view->post->post_content ) ) {
	$section_tabs['about'] = __( 'About', 'torrehub' );
}
if ( $view->fields['features'] ) {
	$section_tabs['features'] = __( 'Amenities', 'torrehub' );
}
if ( $view->hours ) {
	$section_tabs['hours'] = __( 'Hours', 'torrehub' );
}
if ( $view->point ) {
	$section_tabs['location'] = __( 'Location', 'torrehub' );
}
if ( Torrehub\Modules\Reviews\Module::active() ) {
	$section_tabs['reviews'] = $view->reviews['count']
		/* translators: %s: number of reviews */
		? sprintf( __( 'Reviews · %s', 'torrehub' ), number_format_i18n( $view->reviews['count'] ) )
		: __( 'Reviews', 'torrehub' );
}
if ( count( $section_tabs ) < 3 ) {
	return;
}
?>
<nav class="th-tabs th-listing__tabs" aria-label="<?php esc_attr_e( 'Listing sections', 'torrehub' ); ?>" data-th-tabs>
	<?php foreach ( $section_tabs as $anchor => $label ) : ?>
		<a href="#<?php echo esc_attr( $anchor ); ?>"<?php echo 'details' === $anchor ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>
