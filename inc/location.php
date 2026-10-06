<?php
/**
 * Current town helpers (see Torrehub\Modules\Location).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

/**
 * The visitor's town: cookie → Customizer default → first town with listings.
 *
 * @return array{id:int,name:string,slug:string,url:string,count:int,image:int}|null
 */
function th_current_town(): ?array {
	static $town = false;
	if ( false !== $town ) {
		return $town;
	}
	$town = null;
	if ( ! th_has_rtcl() ) {
		return null;
	}
	$slug = isset( $_COOKIE['th_location'] ) ? sanitize_title( wp_unslash( $_COOKIE['th_location'] ) ) : '';
	if ( $slug ) {
		$town = Directory::town( $slug );
	}
	if ( ! $town ) {
		// DECISION: Torrevieja is the default town (design + RTCL map centre); editable in the Customizer.
		$town = Directory::town( (string) get_theme_mod( 'th_default_town', 'torrevieja' ) );
	}
	if ( ! $town ) {
		$top  = Directory::top_towns( 1 );
		$town = $top[0] ?? null;
	}
	return $town;
}

/**
 * Approximate town centres (WGS84) for the browser-side "use my current location" lookup.
 * DECISION: static table — rtcl_location terms carry no coordinates. Filterable.
 *
 * @return array<string,array{0:float,1:float}>
 */
function th_town_coordinates(): array {
	$coords = array(
		'alcoi'                => array( 38.698, -0.474 ),
		'algorfa'              => array( 38.063, -0.794 ),
		'alicante'             => array( 38.345, -0.481 ),
		'almoradi'             => array( 38.109, -0.790 ),
		'altea'                => array( 38.599, -0.051 ),
		'benidorm'             => array( 38.540, -0.131 ),
		'benijofar'            => array( 38.077, -0.738 ),
		'benissa'              => array( 38.714, 0.050 ),
		'cabo-roig'            => array( 37.913, -0.721 ),
		'calp'                 => array( 38.645, 0.045 ),
		'catral'               => array( 38.155, -0.804 ),
		'denia'                => array( 38.840, 0.106 ),
		'dolores'              => array( 38.141, -0.769 ),
		'el-campello'          => array( 38.428, -0.397 ),
		'elcheelx'             => array( 38.267, -0.698 ),
		'elda'                 => array( 38.478, -0.791 ),
		'finestrat'            => array( 38.567, -0.212 ),
		'guardamar-del-segura' => array( 38.090, -0.655 ),
		'la-vila-joiosa'       => array( 38.507, -0.233 ),
		'la-zenia'             => array( 37.927, -0.724 ),
		'mil-palmeras'         => array( 37.887, -0.765 ),
		'moraira'              => array( 38.687, 0.135 ),
		'orihuela'             => array( 38.085, -0.944 ),
		'orihuela-costa'       => array( 37.938, -0.739 ),
		'pilar-de-la-horadada' => array( 37.866, -0.792 ),
		'punta-prima'          => array( 37.942, -0.711 ),
		'quesada'              => array( 38.069, -0.722 ),
		'rojales'              => array( 38.088, -0.723 ),
		'santa-pola'           => array( 38.192, -0.565 ),
		'teulada'              => array( 38.729, 0.102 ),
		'torrevieja'           => array( 37.978, -0.683 ),
		'villamartin'          => array( 37.938, -0.762 ),
		'villena'              => array( 38.636, -0.866 ),
		'xabia'                => array( 38.789, 0.163 ),
	);
	/**
	 * Filter town coordinates (slug => [lat, lng]).
	 *
	 * @param array $coords Coordinates.
	 */
	return (array) apply_filters( 'th_town_coordinates', $coords );
}

/**
 * Great-circle distance in km between two [lat, lng] points.
 *
 * @param array{0:float,1:float} $a Point A.
 * @param array{0:float,1:float} $b Point B.
 */
function th_distance_km( array $a, array $b ): float {
	$rad  = M_PI / 180;
	$dlat = ( $b[0] - $a[0] ) * $rad;
	$dlng = ( $b[1] - $a[1] ) * $rad;
	$h    = sin( $dlat / 2 ) ** 2 + cos( $a[0] * $rad ) * cos( $b[0] * $rad ) * sin( $dlng / 2 ) ** 2;
	return 6371 * 2 * asin( min( 1, sqrt( $h ) ) );
}

/**
 * Towns within $km of a town centre, nearest first (the town itself included at 0 km).
 *
 * Used for the radius chips/fallbacks. The archive query itself measures from the centre to each listing's own pin
 * (phase 6 pin picker) and falls back to its town's centre (Archive\Module::geo_clauses). Towns without coordinates
 * only match themselves.
 *
 * @param string $slug Centre town slug.
 * @param int    $km   Radius in km.
 * @return array<string,float> slug => distance (km).
 */
function th_towns_within( string $slug, int $km ): array {
	$coords = th_town_coordinates();
	if ( ! isset( $coords[ $slug ] ) || $km <= 0 ) {
		return array( $slug => 0.0 );
	}
	$out = array();
	foreach ( $coords as $other => $point ) {
		$d = th_distance_km( $coords[ $slug ], $point );
		if ( $d <= $km ) {
			$out[ $other ] = round( $d, 1 );
		}
	}
	asort( $out );
	return $out;
}

/**
 * Next radius step for "Search within N km" (0 → 10 → 25 → 50; 0 when already at the widest).
 *
 * @param int $radius Current radius in km.
 */
function th_archive_wider_radius( int $radius ): int {
	foreach ( array( 10, 25, 50 ) as $step ) {
		if ( $step > $radius ) {
			return $step;
		}
	}
	return 0;
}
