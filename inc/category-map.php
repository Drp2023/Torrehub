<?php
/**
 * The 10 root listing categories → icon, tint and Classified Listing form.
 *
 * Keyed by term slug (stable across environments). Form IDs/slugs come from the rtcl_forms table;
 * the listing form is chosen with `?_fb={form_slug}` (RTCL picks forms, not categories — one form per root).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Root category slug → icon, tint, form.
 *
 * @return array<string,array{icon:string,tint:string,form_id:int,form_slug:string}>
 */
function th_category_map(): array {
	$map = array(
		'services'              => array(
			'icon'      => 'cat-services',
			'tint'      => 'blue',
			'form_id'   => 3,
			'form_slug' => 'service',
		),
		'properties'            => array(
			'icon'      => 'cat-properties',
			'tint'      => 'blue',
			'form_id'   => 7,
			'form_slug' => 'property-for-rent-or-sale',
		),
		'auto-moto-boats'       => array(
			'icon'      => 'cat-auto-moto-boats',
			'tint'      => 'blue',
			'form_id'   => 5,
			'form_slug' => 'moto-boats-cars',
		),
		'restaurants-nightlife' => array(
			'icon'      => 'cat-restaurants-nightlife',
			'tint'      => 'clay',
			'form_id'   => 8,
			'form_slug' => 'restaurants-nithtlife',
		),
		'events'                => array(
			'icon'      => 'cat-events',
			'tint'      => 'clay',
			'form_id'   => 6,
			'form_slug' => 'submit-an-event',
		),
		'jobs'                  => array(
			'icon'      => 'cat-jobs',
			'tint'      => 'grey',
			'form_id'   => 2,
			'form_slug' => 'post-a-job',
		),
		'public-information'    => array(
			'icon'      => 'cat-public-information',
			'tint'      => 'grey',
			'form_id'   => 4,
			'form_slug' => 'public-information',
		),
		'marketplace'           => array(
			'icon'      => 'cat-marketplace',
			'tint'      => 'amber',
			'form_id'   => 9,
			'form_slug' => 'marketplace',
		),
		'tourist-attractions'   => array(
			'icon'      => 'cat-tourist-attractions',
			'tint'      => 'amber',
			'form_id'   => 11,
			'form_slug' => 'tourist-attraction',
		),
		'leisure-sport'         => array(
			'icon'      => 'cat-leisure-sport',
			'tint'      => 'green',
			'form_id'   => 10,
			'form_slug' => 'leisure-sports',
		),
	);

	/**
	 * Filter the root category map (e.g. after adding a root category).
	 *
	 * @param array $map Root category map.
	 */
	return (array) apply_filters( 'th_category_map', $map );
}

/**
 * Map entry for a category term or any of its descendants (resolved to the root ancestor).
 *
 * @param WP_Term|int|string $term Term object, ID or slug (taxonomy rtcl_category).
 * @return array{slug:string,icon:string,tint:string,form_id:int,form_slug:string}|null
 */
function th_category_meta( $term ): ?array {
	if ( ! $term instanceof WP_Term ) {
		$term = is_numeric( $term ) ? get_term( (int) $term, 'rtcl_category' ) : get_term_by( 'slug', (string) $term, 'rtcl_category' );
	}
	if ( ! $term instanceof WP_Term ) {
		return null;
	}
	$root      = $term;
	$ancestors = get_ancestors( $term->term_id, 'rtcl_category', 'taxonomy' );
	if ( $ancestors ) {
		$top = get_term( (int) end( $ancestors ), 'rtcl_category' );
		if ( $top instanceof WP_Term ) {
			$root = $top;
		}
	}
	$map = th_category_map();
	return isset( $map[ $root->slug ] ) ? array( 'slug' => $root->slug ) + $map[ $root->slug ] : null;
}
