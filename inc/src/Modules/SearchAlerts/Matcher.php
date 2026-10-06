<?php
/**
 * Runs a saved search the way the archive does (Archive\Search: category subtree, town or radius, price, form-field
 * filters, verified sellers, seller) and returns the listings that went live after a given moment.
 *
 * DECISION: the keyword uses WordPress's own search (title + description); the archive's keyword search is
 * Classified Listing's, which also matches a few meta fields. An alert can therefore miss a listing whose keyword is
 * only in, say, the address — it never sends one that doesn't match.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\SearchAlerts;

use Torrehub\Modules\Archive\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Saved-search matcher.
 */
final class Matcher {

	/**
	 * Meta stamped when a listing goes live (Module::stamp_published): approval keeps the original post date.
	 */
	public const PUBLISHED = 'th_published_at';

	/**
	 * Listings matching the parameters, published after $since (unix time) and newer than $after_id.
	 *
	 * @param array<string,mixed> $params   Saved parameters.
	 * @param int                 $since    Unix time.
	 * @param int                 $after_id Only listing ids above this (already announced).
	 * @param int                 $limit    Max listings.
	 * @param array<int,int>      $only     Restrict to these ids (instant alerts: the one new listing).
	 * @return array{posts:array<int,\WP_Post>,total:int}
	 */
	public static function find( array $params, int $since, int $after_id = 0, int $limit = 10, array $only = array() ): array {
		$search = Search::from_array( $params );
		$q      = new \WP_Query();
		$args   = array(
			'post_type'           => 'rtcl_listing',
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
			'orderby'             => 'ID',
			'order'               => 'DESC',
			'tax_query'           => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- the saved search.
		);
		if ( ! empty( $params['q'] ) && is_string( $params['q'] ) ) {
			$args['s'] = $params['q'];
		}
		if ( $search->category ) {
			$args['tax_query'][] = array(
				'taxonomy'         => 'rtcl_category',
				'field'            => 'term_id',
				'terms'            => array( (int) $search->category->term_id ),
				'include_children' => true,
			);
		}
		if ( $search->town && ! $search->radius ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'rtcl_location',
				'field'    => 'term_id',
				'terms'    => array( (int) $search->town->term_id ),
			);
		}
		$q->parse_query( $args );
		$search->apply( $q );

		$meta   = (array) $q->get( 'meta_query' );
		$meta[] = array(
			'key'     => self::PUBLISHED,
			'value'   => $since,
			'compare' => '>',
			'type'    => 'NUMERIC',
		);
		$q->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), array_filter( $meta, static fn( $k ) => 'relation' !== $k, ARRAY_FILTER_USE_KEY ) ) );
		$q->set( 'posts_per_page', $limit );
		$q->set( 'paged', 1 );
		$q->set( 'orderby', 'ID' );
		$q->set( 'order', 'DESC' );
		$q->set( 'th_nearest', null );
		$q->set( 'th_geo_order', null );
		if ( $only ) {
			$q->set( 'post__in', array_map( 'intval', $only ) );
		}
		$posts = array_values(
			array_filter(
				(array) $q->get_posts(),
				static fn( $p ) => $p instanceof \WP_Post && $p->ID > $after_id
			)
		);
		return array(
			'posts' => $posts,
			'total' => max( count( $posts ), (int) $q->found_posts - ( count( (array) $q->posts ) - count( $posts ) ) ),
		);
	}

	/**
	 * Short summary of the parameters ("Plumbing in Torrevieja · within 10 km · up to €60").
	 *
	 * @param array<string,mixed> $params Parameters.
	 */
	public static function summary( array $params ): string {
		$search = Search::from_array( $params );
		$parts  = array();
		foreach ( $search->chips() as $chip ) {
			$label = trim( wp_strip_all_tags( (string) ( $chip['label'] ?? '' ) ) );
			if ( '' !== $label && ! in_array( $label, $parts, true ) ) {
				$parts[] = $label;
			}
		}
		return $parts ? implode( ' · ', array_slice( $parts, 0, 5 ) ) : $search->heading();
	}
}
