<?php
/**
 * End of results (last page): "That's all N" + a way to widen the search.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Archive\Search $search, @type int $total }
 */

defined( 'ABSPATH' ) || exit;

$th_search  = $args['search'];
$wider      = $th_search->town ? th_archive_wider_radius( $th_search->radius ) : 0;
$end_action = array();
$text       = '';

if ( $wider ) {
	$text       = __( 'Widen the radius to see listings in nearby towns too.', 'torrehub' );
	$end_action = array(
		/* translators: %s: radius in km */
		'label' => sprintf( __( 'Search within %s km', 'torrehub' ), number_format_i18n( $wider ) ),
		'href'  => $th_search->url( array( 'radius' => $wider ) ),
	);
} elseif ( $th_search->has_refinements() ) {
	$text       = __( 'Fewer filters will show more listings.', 'torrehub' );
	$end_action = array(
		'label' => __( 'Clear filters', 'torrehub' ),
		'href'  => $th_search->url_cleared(),
	);
} elseif ( $th_search->category ) {
	$text = __( 'New listings appear here as soon as they’re approved.', 'torrehub' );
}

th_component(
	'end-of-results',
	array(
		'total'  => (int) $args['total'],
		'text'   => $text,
		'action' => $end_action,
	)
);
