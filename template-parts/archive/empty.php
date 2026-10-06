<?php
/**
 * No results (P-10): explain, then offer the widest useful next step.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Archive\Search $search }
 */

defined( 'ABSPATH' ) || exit;

$th_search = $args['search'];
$actions   = array();
$wider     = $th_search->town ? th_archive_wider_radius( $th_search->radius ) : 0;

if ( $wider ) {
	$actions[] = array(
		/* translators: %s: radius in km */
		'label' => sprintf( __( 'Search within %s km', 'torrehub' ), number_format_i18n( $wider ) ),
		'href'  => $th_search->url( array( 'radius' => $wider ) ),
		'block' => true,
	);
}
if ( $th_search->has_refinements() || $th_search->town || '' !== $th_search->q ) {
	$actions[] = array(
		'label'   => __( 'Clear all filters', 'torrehub' ),
		'href'    => $th_search->category ? (string) get_term_link( $th_search->category ) : th_url_listings(),
		'variant' => $actions ? 'outline' : 'primary',
		'block'   => true,
	);
} else {
	$actions[] = array(
		'label' => __( 'Browse all listings', 'torrehub' ),
		'href'  => th_url_listings(),
		'block' => true,
	);
}

$where = $th_search->town ? html_entity_decode( $th_search->town->name, ENT_QUOTES ) : '';
$count = $th_search->active_count() - ( $th_search->category ? 1 : 0 ) - ( $th_search->town ? 1 : 0 ); // Refinements only.
if ( $where && $count > 0 ) {
	/* translators: 1: town, 2: number of filters */
	$text = sprintf( _n( 'Nothing in %1$s matches %2$s filter. Try a wider radius or drop it.', 'Nothing in %1$s matches all %2$s filters. Try a wider radius or drop a filter.', $count, 'torrehub' ), $where, number_format_i18n( $count ) );
} elseif ( $where ) {
	/* translators: %s: town */
	$text = sprintf( __( 'Nothing in %s yet. Try a wider radius.', 'torrehub' ), $where );
} elseif ( $th_search->has_refinements() || '' !== $th_search->q ) {
	$text = __( 'Nothing matches these filters. Try removing one.', 'torrehub' );
} else {
	$text = __( 'No listings here yet — check back soon.', 'torrehub' );
}
?>
<div class="th-archive__empty">
	<?php
	th_component(
		'empty-state',
		array(
			'title'   => __( 'No results here', 'torrehub' ),
			'text'    => $text,
			'actions' => $actions,
		)
	);
	?>
</div>
