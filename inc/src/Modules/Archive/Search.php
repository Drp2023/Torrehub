<?php
/**
 * The archive's search state: parsed from the request, turned into query args, chips and URLs.
 *
 * URL parameters (all optional):
 *   q                 keyword (RTCL → `s`)
 *   rtcl_category     category slug (or the category archive itself)
 *   rtcl_location     town slug (or the town archive itself)
 *   radius            km around the town: 10|25|50 (0 = the town only); listing pin, else its town centre
 *   min_price, max_price
 *   verified=1        verified sellers only
 *   f[field]          Form Builder fields: f[select_x][]=a, f[number_y][min]=…, f[date_z]=Y-m-d
 *   orderby           date-desc (default) | price-asc | price-desc | views-desc | nearest
 *   view              grid (default) | list | map
 *   page              RTCL's page parameter
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Archive;

use Torrehub\Data\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable-ish value object for one archive request.
 */
final class Search {

	public const RADII  = array( 10, 25, 50 );
	public const VIEWS  = array( 'grid', 'list', 'map' );
	public const ORDERS = array( 'date-desc', 'price-asc', 'price-desc', 'views-desc', 'nearest' );

	/** Hard cap of pins on the map view. */
	public const MAP_LIMIT = 300;

	/**
	 * Category (archive term or ?rtcl_category).
	 *
	 * @var \WP_Term|null
	 */
	public ?\WP_Term $category = null;

	/**
	 * Town (archive term or ?rtcl_location).
	 *
	 * @var \WP_Term|null
	 */
	public ?\WP_Term $town = null;

	/**
	 * Keyword.
	 *
	 * @var string
	 */
	public string $q = '';

	/**
	 * Radius in km around the town (0 = the town only).
	 *
	 * @var int
	 */
	public int $radius = 0;

	/**
	 * Lower price bound.
	 *
	 * @var int|null
	 */
	public ?int $price_min = null;

	/**
	 * Upper price bound.
	 *
	 * @var int|null
	 */
	public ?int $price_max = null;

	/**
	 * One seller's listings (?seller=user_nicename).
	 *
	 * @var \WP_User|null
	 */
	public ?\WP_User $seller = null;

	/**
	 * Verified sellers only.
	 *
	 * @var bool
	 */
	public bool $verified = false;

	/**
	 * Sort key ('' = default).
	 *
	 * @var string
	 */
	public string $orderby = '';

	/**
	 * View: grid, list or map.
	 *
	 * @var string
	 */
	public string $view = 'grid';

	/**
	 * Page number (RTCL ?page=).
	 *
	 * @var int
	 */
	public int $page = 1;

	/**
	 * Field values: name => list<string> (choices) | ['min'=>?int,'max'=>?int] (number) | ['day'=>'Y-m-d'] (date).
	 *
	 * @var array<string,array>
	 */
	public array $fields = array();

	/**
	 * Filter definitions for the category (memo).
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private ?array $defs = null;

	/**
	 * State of the current request (memo).
	 *
	 * @var Search|null
	 */
	private static ?Search $current = null;

	/**
	 * State of the current request.
	 */
	public static function current(): self {
		if ( null === self::$current ) {
			$category = null;
			$town     = null;
			if ( is_tax( 'rtcl_category' ) ) {
				$category = get_queried_object();
			}
			if ( is_tax( 'rtcl_location' ) ) {
				$town = get_queried_object();
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET search.
			self::$current = self::from_array( wp_unslash( $_GET ), $category instanceof \WP_Term ? $category : null, $town instanceof \WP_Term ? $town : null );
		}
		return self::$current;
	}

	/**
	 * Build from a parameter array (GET), with an optional category/town taken from the archive itself.
	 *
	 * @param array<string,mixed> $get      Raw (unslashed) parameters.
	 * @param \WP_Term|null       $category Category of the archive page.
	 * @param \WP_Term|null       $town     Town of the archive page.
	 */
	public static function from_array( array $get, ?\WP_Term $category = null, ?\WP_Term $town = null ): self {
		$s = new self();

		$s->category = $category;
		if ( ! $s->category && ! empty( $get['rtcl_category'] ) && is_string( $get['rtcl_category'] ) ) {
			$term        = get_term_by( 'slug', sanitize_title( $get['rtcl_category'] ), 'rtcl_category' );
			$s->category = $term instanceof \WP_Term ? $term : null;
		}
		$s->town = $town;
		if ( ! $s->town && ! empty( $get['rtcl_location'] ) && is_string( $get['rtcl_location'] ) ) {
			$term    = get_term_by( 'slug', sanitize_title( $get['rtcl_location'] ), 'rtcl_location' );
			$s->town = $term instanceof \WP_Term ? $term : null;
		}

		$s->q = isset( $get['q'] ) && is_string( $get['q'] ) ? trim( sanitize_text_field( $get['q'] ) ) : '';

		$radius = isset( $get['radius'] ) && is_scalar( $get['radius'] ) ? (string) $get['radius'] : '';
		if ( 'all' === $radius ) {
			$s->town = null;
		} elseif ( $s->town && in_array( (int) $radius, self::RADII, true ) ) {
			$s->radius = (int) $radius;
		}

		$s->price_min = self::int_or_null( $get['min_price'] ?? null );
		$s->price_max = self::int_or_null( $get['max_price'] ?? null );
		if ( null !== $s->price_min && null !== $s->price_max && $s->price_min > $s->price_max ) {
			list( $s->price_min, $s->price_max ) = array( $s->price_max, $s->price_min );
		}

		// A slider left at its end means "no limit" (range inputs always submit a value).
		$bounds = ( null !== $s->price_min || null !== $s->price_max ) ? $s->price_bounds() : null;
		if ( null !== $s->price_min && $s->price_min <= 0 ) {
			$s->price_min = null;
		}
		if ( $bounds && null !== $s->price_max && $s->price_max >= $bounds['max'] ) {
			$s->price_max = null;
		}

		$s->verified = ! empty( $get['verified'] );

		if ( ! empty( $get['seller'] ) && is_string( $get['seller'] ) ) {
			$user      = get_user_by( 'slug', sanitize_title( $get['seller'] ) );
			$s->seller = $user instanceof \WP_User ? $user : null;
		}

		$orderby    = isset( $get['orderby'] ) && is_string( $get['orderby'] ) ? sanitize_key( $get['orderby'] ) : '';
		$s->orderby = in_array( $orderby, self::ORDERS, true ) ? $orderby : '';
		// Newest is the default, so it only needs to be explicit when a radius makes "Nearest" the default.
		if ( ( 'nearest' === $s->orderby || 'date-desc' === $s->orderby ) && ! $s->radius ) {
			$s->orderby = '';
		}

		$view    = isset( $get['view'] ) && is_string( $get['view'] ) ? sanitize_key( $get['view'] ) : '';
		$s->view = in_array( $view, self::VIEWS, true ) ? $view : 'grid';

		$s->page = max( 1, isset( $get['page'] ) && is_scalar( $get['page'] ) ? absint( $get['page'] ) : 1 );

		// Field values are validated against the category's definitions (unknown fields/options are dropped).
		$raw = isset( $get['f'] ) && is_array( $get['f'] ) ? $get['f'] : array();
		foreach ( $s->definitions() as $def ) {
			$name = $def['name'];
			if ( ! isset( $raw[ $name ] ) ) {
				continue;
			}
			$value = $raw[ $name ];
			switch ( $def['ui'] ) {
				case 'range':
					$min = self::int_or_null( is_array( $value ) ? ( $value['min'] ?? null ) : null );
					$max = self::int_or_null( is_array( $value ) ? ( $value['max'] ?? null ) : null );
					$min = null !== $min && $min <= $def['min'] ? null : $min;
					$max = null !== $max && $max >= $def['max'] ? null : $max;
					if ( null !== $min || null !== $max ) {
						$s->fields[ $name ] = array(
							'min' => $min,
							'max' => $max,
						);
					}
					break;
				case 'date':
					$day = is_string( $value ) ? $value : '';
					$dt  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $day );
					if ( $dt && $dt->format( 'Y-m-d' ) === $day ) {
						$s->fields[ $name ] = array( 'day' => $day );
					}
					break;
				default:
					$values = array_values(
						array_intersect(
							array_map( 'strval', array_keys( $def['options'] ) ),
							array_map( static fn( $v ) => is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '', (array) $value )
						)
					);
					if ( $values ) {
						$s->fields[ $name ] = $values;
					}
			}
		}
		return $s;
	}

	/**
	 * Positive int or null ('' / junk / negative → null).
	 *
	 * @param mixed $value Raw value.
	 */
	private static function int_or_null( $value ): ?int {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$value = preg_replace( '/[^0-9]/', '', (string) $value );
		return '' === $value ? null : (int) $value;
	}

	/* ------------------------------------------------------------------ Definitions */

	/**
	 * Filter definitions of the current category's form fields.
	 *
	 * @return array<int,array{name:string,label:string,element:string,ui:string,options:array<string,string>,min:?float,max:?float}>
	 */
	public function definitions(): array {
		if ( null !== $this->defs ) {
			return $this->defs;
		}
		$this->defs = array();
		foreach ( FilterConfig::for_term( $this->category ) as $item ) {
			$raw     = $item['raw'];
			$element = (string) ( $raw['element'] ?? '' );
			$options = array();
			foreach ( (array) ( $raw['options'] ?? array() ) as $opt ) {
				$value = is_array( $opt ) ? (string) ( $opt['value'] ?? '' ) : (string) $opt;
				$label = is_array( $opt ) ? (string) ( $opt['label'] ?? $value ) : (string) $opt;
				if ( '' !== trim( $value ) ) { // Options with an empty value can't be matched (WPCODE-AUDIT §6510.7).
					$options[ $value ] = '' !== trim( $label ) ? $label : $value;
				}
			}
			$def = array(
				'name'    => $item['field'],
				'label'   => $item['label'],
				'element' => $element,
				'ui'      => '',
				'options' => $options,
				'min'     => null,
				'max'     => null,
			);
			switch ( $element ) {
				case 'number':
					$bounds = Directory::meta_bounds( $item['field'] );
					if ( ! $bounds || $bounds['max'] <= $bounds['min'] ) {
						continue 2; // Nothing to narrow down (no values, or all listings share one value).
					}
					$def['ui']  = 'range';
					$def['min'] = floor( $bounds['min'] );
					$def['max'] = ceil( $bounds['max'] );
					break;
				case 'date':
					$def['ui'] = 'date';
					break;
				case 'text':
					$values = Directory::meta_values( $item['field'] );
					if ( ! $values ) {
						continue 2;
					}
					$def['options'] = array_combine( $values, $values );
					$def['ui']      = count( $values ) > 8 ? 'select' : 'chips';
					break;
				case 'checkbox':
					if ( ! $options ) {
						continue 2;
					}
					$def['ui'] = 1 === count( $options ) ? 'toggle' : 'chips';
					break;
				case 'radio':
					if ( ! $options ) {
						continue 2;
					}
					$def['ui'] = count( $options ) <= 3 ? 'segmented' : 'chips';
					break;
				default: // select.
					if ( ! $options ) {
						continue 2;
					}
					$def['ui'] = count( $options ) > 8 ? 'select' : 'chips';
			}
			$this->defs[] = $def;
		}
		return $this->defs;
	}

	/**
	 * Definition by field name.
	 *
	 * @param string $name Field name.
	 * @return array<string,mixed>|null
	 */
	public function definition( string $name ): ?array {
		foreach ( $this->definitions() as $def ) {
			if ( $def['name'] === $name ) {
				return $def;
			}
		}
		return null;
	}

	/**
	 * Price slider bounds for the current category scope (null: no priced listings).
	 *
	 * @return array{min:int,max:int}|null
	 */
	public function price_bounds(): ?array {
		$ids = array();
		if ( $this->category ) {
			$ids = array_merge( array( $this->category->term_id ), array_map( 'intval', (array) get_term_children( $this->category->term_id, 'rtcl_category' ) ) );
		}
		$b = Directory::meta_bounds( 'price', $ids );
		if ( ! $b || $b['max'] <= 0 ) {
			return null;
		}
		// Round the top up to a "nice" number so the slider ends on a readable value.
		$max  = (float) $b['max'];
		$step = 10 ** max( 0, (int) floor( log10( max( 1, $max ) ) ) - 1 );
		return array(
			'min' => 0,
			'max' => (int) ( ceil( $max / $step ) * $step ),
		);
	}

	/* ------------------------------------------------------------------ Query */

	/**
	 * Apply the state to RTCL's main listing query (runs on `rtcl_listing_query`, after RTCL set q/orderby/taxonomies).
	 *
	 * @param \WP_Query $q Main query.
	 */
	public function apply( \WP_Query $q ): void {
		$add = $this->meta_clauses();
		if ( $add ) {
			// RTCL's own meta query (e.g. the OR EXISTS/NOT EXISTS pair behind "price" ordering) stays a nested
			// group, first — WP orders meta_value_num by the first clause.
			$existing = (array) $q->get( 'meta_query' );
			$has_own  = (bool) array_diff_key( $existing, array( 'relation' => true ) );
			$q->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), $has_own ? array( $existing ) : array(), $add ) );
		}

		$authors = null;
		if ( $this->verified ) {
			$authors = $this->verified_authors();
		}
		if ( $this->seller ) {
			$authors = null === $authors ? array( $this->seller->ID ) : ( in_array( $this->seller->ID, $authors, true ) ? array( $this->seller->ID ) : array( 0 ) );
		}
		if ( null !== $authors ) {
			$q->set( 'author__in', $authors );
		}

		$centre = $this->town ? ( th_town_coordinates()[ $this->town->slug ] ?? null ) : null;
		if ( $this->town && $this->radius ) {
			$within = th_towns_within( $this->town->slug, $this->radius );
			// Drop the single-town constraint (query var + RTCL clause): the radius replaces it.
			$q->set( 'rtcl_location', '' );
			$tax = array_filter(
				(array) $q->get( 'tax_query' ),
				static fn( $clause ) => ! is_array( $clause ) || 'rtcl_location' !== ( $clause['taxonomy'] ?? '' )
			);
			if ( $centre ) {
				// Listings with their own pin: distance from the town centre; without: their town's centre
				// (Archive\Module::geo_clauses).
				$q->set(
					'th_geo',
					array(
						'lat'   => (float) $centre[0],
						'lng'   => (float) $centre[1],
						'km'    => $this->radius,
						'towns' => array_keys( $within ),
					)
				);
			} else {
				$tax[] = array(
					'taxonomy' => 'rtcl_location',
					'field'    => 'slug',
					'terms'    => array_keys( $within ),
				);
			}
			$q->set( 'tax_query', $tax );
		}

		if ( 'map' === $this->view ) {
			$q->set( 'posts_per_page', self::MAP_LIMIT );
			$q->set( 'paged', 1 );
			$q->set( 'no_found_rows', false );
		} else {
			$q->set( 'posts_per_page', self::per_page() );
		}

		if ( 'nearest' === $this->orderby || ( ! $this->orderby && $this->radius ) ) {
			if ( $centre ) {
				$q->set(
					'th_geo_order',
					array(
						'lat' => (float) $centre[0],
						'lng' => (float) $centre[1],
					)
				);
			} else {
				$q->set( 'th_nearest', array_keys( th_towns_within( (string) $this->town?->slug, $this->radius ) ) );
			}
		}
	}

	/**
	 * Listings per page (Customizer; DECISION: 12 = 4 rows of 3 on desktop, RTCL's own setting is 7).
	 */
	public static function per_page(): int {
		return max( 3, (int) th_mod( 'th_archive_per_page' ) );
	}

	/**
	 * Meta-query clauses for price and form fields.
	 *
	 * @return array<int,array>
	 */
	public function meta_clauses(): array {
		$out = array();
		if ( null !== $this->price_min ) {
			$out[] = array(
				'key'     => 'price',
				'value'   => $this->price_min,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			);
		}
		if ( null !== $this->price_max ) {
			$out[] = array(
				'key'     => 'price',
				'value'   => $this->price_max,
				'type'    => 'NUMERIC',
				'compare' => '<=',
			);
		}
		foreach ( $this->fields as $name => $value ) {
			$def = $this->definition( $name );
			if ( ! $def ) {
				continue;
			}
			if ( 'range' === $def['ui'] ) {
				if ( null !== $value['min'] && null !== $value['max'] ) {
					$out[] = array(
						'key'     => $name,
						'value'   => array( $value['min'], $value['max'] ),
						'type'    => 'NUMERIC',
						'compare' => 'BETWEEN',
					);
				} else {
					$out[] = array(
						'key'     => $name,
						'value'   => $value['min'] ?? $value['max'],
						'type'    => 'NUMERIC',
						'compare' => null !== $value['min'] ? '>=' : '<=',
					);
				}
			} elseif ( 'date' === $def['ui'] ) {
				// Stored as "Y-m-d H:i:s" (or "Y-m-d"): match the whole day.
				$out[] = array(
					'key'     => $name,
					'value'   => array( $value['day'] . ' 00:00:00', $value['day'] . ' 23:59:59' ),
					'type'    => 'DATETIME',
					'compare' => 'BETWEEN',
				);
			} else {
				// Choice fields: any of the selected values (checkbox values are stored as one row each).
				$out[] = array(
					'key'     => $name,
					'value'   => $value,
					'compare' => 'IN',
				);
			}
		}
		return $out;
	}

	/**
	 * User ids of verified sellers ([0] when none, so the query returns nothing).
	 *
	 * @return array<int,int>
	 */
	private function verified_authors(): array {
		$ids = get_users(
			array(
				'fields'     => 'ID',
				'meta_key'   => 'rtcl_verified_seller', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small user table, request-cached by WP.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return $ids ? array_map( 'intval', $ids ) : array( 0 );
	}

	/* ------------------------------------------------------------------ URLs */

	/**
	 * Query parameters of this state (without base-path parts).
	 *
	 * @return array<string,mixed>
	 */
	public function params(): array {
		$p = array();
		if ( '' !== $this->q ) {
			$p['q'] = $this->q;
		}
		if ( $this->category ) {
			$p['rtcl_category'] = $this->category->slug;
		}
		if ( $this->town ) {
			$p['rtcl_location'] = $this->town->slug;
			if ( $this->radius ) {
				$p['radius'] = $this->radius;
			}
		}
		if ( null !== $this->price_min ) {
			$p['min_price'] = $this->price_min;
		}
		if ( null !== $this->price_max ) {
			$p['max_price'] = $this->price_max;
		}
		if ( $this->verified ) {
			$p['verified'] = 1;
		}
		if ( $this->seller ) {
			$p['seller'] = $this->seller->user_nicename;
		}
		foreach ( $this->fields as $name => $value ) {
			if ( isset( $value['day'] ) ) {
				$p['f'][ $name ] = $value['day'];
			} elseif ( array_key_exists( 'min', $value ) ) {
				$p['f'][ $name ] = array_filter(
					array(
						'min' => $value['min'],
						'max' => $value['max'],
					),
					static fn( $v ) => null !== $v
				);
			} else {
				$p['f'][ $name ] = array_values( $value );
			}
		}
		if ( $this->orderby ) {
			$p['orderby'] = $this->orderby;
		}
		if ( 'grid' !== $this->view ) {
			$p['view'] = $this->view;
		}
		if ( $this->page > 1 ) {
			$p['page'] = $this->page;
		}
		return $p;
	}

	/**
	 * Canonical URL of a state: the category archive (or town archive) as the path, the rest as query.
	 *
	 * @param array<string,mixed> $p Parameters (from params()).
	 */
	public static function build_url( array $p ): string {
		$base = th_url_listings();
		if ( ! empty( $p['rtcl_category'] ) ) {
			$link = get_term_link( (string) $p['rtcl_category'], 'rtcl_category' );
			if ( ! is_wp_error( $link ) ) {
				$base = $link;
				unset( $p['rtcl_category'] );
			}
		} elseif ( ! empty( $p['rtcl_location'] ) && empty( $p['radius'] ) ) {
			$link = get_term_link( (string) $p['rtcl_location'], 'rtcl_location' );
			if ( ! is_wp_error( $link ) ) {
				$base = $link;
				unset( $p['rtcl_location'] );
			}
		}
		// Numbered list keys become empty brackets: shorter URLs, same meaning for PHP.
		$query = preg_replace( '/%5B\d+%5D=/', '%5B%5D=', http_build_query( $p, '', '&', PHP_QUERY_RFC3986 ) );
		return $query ? $base . ( str_contains( $base, '?' ) ? '&' : '?' ) . $query : $base;
	}

	/**
	 * URL of this state with changes. `null` removes a key; for f[] use with_field()/without_field().
	 *
	 * @param array<string,mixed> $changes Parameter changes.
	 */
	public function url( array $changes = array() ): string {
		$p = $this->params();
		unset( $p['page'] ); // Any change starts again on page 1 unless the caller sets it.
		foreach ( $changes as $key => $value ) {
			if ( null === $value ) {
				unset( $p[ $key ] );
			} else {
				$p[ $key ] = $value;
			}
		}
		if ( empty( $p['rtcl_location'] ) ) {
			unset( $p['radius'] );
			if ( isset( $p['orderby'] ) && 'nearest' === $p['orderby'] ) {
				unset( $p['orderby'] );
			}
		}
		return self::build_url( $p );
	}

	/**
	 * URL without one field value (or the whole field when $value is null).
	 *
	 * @param string      $name  Field name.
	 * @param string|null $value Value to drop.
	 */
	public function url_without_field( string $name, ?string $value = null ): string {
		$p = $this->params();
		unset( $p['page'] );
		if ( null === $value || ! isset( $p['f'][ $name ] ) || ! is_array( $p['f'][ $name ] ) || ! array_is_list( $p['f'][ $name ] ) ) {
			unset( $p['f'][ $name ] );
		} else {
			$p['f'][ $name ] = array_values( array_diff( $p['f'][ $name ], array( $value ) ) );
			if ( ! $p['f'][ $name ] ) {
				unset( $p['f'][ $name ] );
			}
		}
		if ( empty( $p['f'] ) ) {
			unset( $p['f'] );
		}
		return self::build_url( $p );
	}

	/**
	 * URL keeping only keyword, category and town (Clear all).
	 */
	public function url_cleared(): string {
		$p = array_intersect_key( $this->params(), array_flip( array( 'q', 'rtcl_category', 'rtcl_location', 'radius', 'view', 'seller' ) ) );
		return self::build_url( $p );
	}

	/**
	 * Category switch keeps keyword, town, radius, price, verified and view; field filters belong to the old form.
	 *
	 * @param string $slug Category slug ('' = all categories).
	 */
	public function url_for_category( string $slug ): string {
		$p = $this->params();
		unset( $p['f'], $p['page'] );
		if ( '' === $slug ) {
			unset( $p['rtcl_category'] );
		} else {
			$p['rtcl_category'] = $slug;
		}
		return self::build_url( $p );
	}

	/**
	 * A GET form for a state: [ action URL without query, hidden params ].
	 *
	 * @param array<string,mixed> $p Parameters to carry as hidden inputs.
	 * @return array{0:string,1:array<string,mixed>}
	 */
	public static function form_target( array $p ): array {
		$url   = self::build_url( $p );
		$parts = explode( '?', $url, 2 );
		$query = array();
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}
		return array( $parts[0], $query );
	}

	/**
	 * Echo hidden inputs for nested parameters (f[name][] …).
	 *
	 * @param array<string,mixed> $params Parameters.
	 * @param string              $prefix Name prefix (recursion).
	 */
	public static function hidden_inputs( array $params, string $prefix = '' ): void {
		foreach ( $params as $key => $value ) {
			$name = '' === $prefix ? (string) $key : $prefix . '[' . ( is_int( $key ) ? '' : $key ) . ']';
			if ( is_array( $value ) ) {
				self::hidden_inputs( $value, $name );
			} else {
				printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( (string) $value ) );
			}
		}
	}

	/* ------------------------------------------------------------------ Presentation */

	/**
	 * Removable chips for the active filters (keyword, category and town are part of the heading, but still removable).
	 *
	 * @return array<int,array{label:string,url:string,variant:string}>
	 */
	public function chips(): array {
		$chips = array();
		if ( $this->category ) {
			$chips[] = array(
				'label'   => html_entity_decode( $this->category->name, ENT_QUOTES ),
				'url'     => $this->url_for_category( '' ),
				'variant' => '',
			);
		}
		if ( $this->town ) {
			$chips[] = array(
				'label'   => $this->radius
					/* translators: 1: town, 2: radius in km */
					? sprintf( __( '%1$s + %2$s km', 'torrehub' ), html_entity_decode( $this->town->name, ENT_QUOTES ), number_format_i18n( $this->radius ) )
					: html_entity_decode( $this->town->name, ENT_QUOTES ),
				'url'     => $this->url( array( 'rtcl_location' => null ) ),
				'variant' => '',
			);
		}
		if ( '' !== $this->q ) {
			$chips[] = array(
				/* translators: %s: search keyword */
				'label'   => sprintf( __( '“%s”', 'torrehub' ), $this->q ),
				'url'     => $this->url( array( 'q' => null ) ),
				'variant' => '',
			);
		}
		if ( null !== $this->price_min || null !== $this->price_max ) {
			$chips[] = array(
				'label'   => $this->range_label( $this->price_min, $this->price_max, '€' ),
				'url'     => $this->url(
					array(
						'min_price' => null,
						'max_price' => null,
					)
				),
				'variant' => '',
			);
		}
		foreach ( $this->fields as $name => $value ) {
			$def = $this->definition( $name );
			if ( ! $def ) {
				continue;
			}
			if ( 'range' === $def['ui'] ) {
				$chips[] = array(
					'label'   => $def['label'] . ' ' . $this->range_label( $value['min'], $value['max'] ),
					'url'     => $this->url_without_field( $name ),
					'variant' => '',
				);
			} elseif ( 'date' === $def['ui'] ) {
				$chips[] = array(
					'label'   => $def['label'] . ': ' . wp_date( get_option( 'date_format' ), (int) strtotime( $value['day'] . ' 12:00:00' ) ),
					'url'     => $this->url_without_field( $name ),
					'variant' => '',
				);
			} else {
				foreach ( $value as $v ) {
					$chips[] = array(
						'label'   => 'toggle' === $def['ui'] ? $def['label'] : (string) ( $def['options'][ $v ] ?? $v ),
						'url'     => $this->url_without_field( $name, $v ),
						'variant' => '',
					);
				}
			}
		}
		if ( $this->seller ) {
			$chips[] = array(
				/* translators: %s: seller name */
				'label'   => sprintf( __( 'By %s', 'torrehub' ), $this->seller->display_name ),
				'url'     => $this->url( array( 'seller' => null ) ),
				'variant' => '',
			);
		}
		if ( $this->verified ) {
			$chips[] = array(
				'label'   => __( 'Verified only', 'torrehub' ),
				'url'     => $this->url( array( 'verified' => null ) ),
				'variant' => 'success',
			);
		}
		return $chips;
	}

	/**
	 * Number of active refinements counted on the "Filters · N" button (keyword excluded).
	 */
	public function active_count(): int {
		return count( $this->chips() ) - ( '' !== $this->q ? 1 : 0 );
	}

	/**
	 * Does anything beyond category/town/keyword narrow the results?
	 */
	public function has_refinements(): bool {
		return $this->fields || null !== $this->price_min || null !== $this->price_max || $this->verified || $this->radius || $this->seller;
	}

	/**
	 * "€0 – €120", "2010 – 2020", "from 5", "up to 9".
	 *
	 * @param int|null $min    Lower bound.
	 * @param int|null $max    Upper bound.
	 * @param string   $prefix Currency prefix.
	 */
	private function range_label( ?int $min, ?int $max, string $prefix = '' ): string {
		// Prices get thousands separators; other numbers (years, sizes) are shown as entered.
		$fmt = static fn( int $v ) => '' !== $prefix ? $prefix . number_format_i18n( $v ) : (string) $v;
		if ( null !== $min && null !== $max ) {
			return $fmt( $min ) . ' – ' . $fmt( $max );
		}
		if ( null !== $min ) {
			/* translators: %s: lower bound */
			return sprintf( __( 'from %s', 'torrehub' ), $fmt( $min ) );
		}
		/* translators: %s: upper bound */
		return sprintf( __( 'up to %s', 'torrehub' ), $fmt( (int) $max ) );
	}

	/**
	 * Page heading: "Plumbing in Torrevieja", "Listings in Torrevieja", "Search: plumber", "All listings".
	 */
	public function heading(): string {
		$cat  = $this->category ? html_entity_decode( $this->category->name, ENT_QUOTES ) : '';
		$town = $this->town ? html_entity_decode( $this->town->name, ENT_QUOTES ) : '';
		if ( $cat && $town ) {
			/* translators: 1: category, 2: town */
			return sprintf( __( '%1$s in %2$s', 'torrehub' ), $cat, $town );
		}
		if ( $cat ) {
			return $cat;
		}
		if ( $town ) {
			/* translators: %s: town */
			return sprintf( __( 'Listings in %s', 'torrehub' ), $town );
		}
		if ( $this->seller ) {
			/* translators: %s: seller name */
			return sprintf( __( 'Listings by %s', 'torrehub' ), $this->seller->display_name );
		}
		if ( '' !== $this->q ) {
			/* translators: %s: search keyword */
			return sprintf( __( 'Results for “%s”', 'torrehub' ), $this->q );
		}
		return __( 'All listings', 'torrehub' );
	}

	/**
	 * Breadcrumb items: Home / Listings / root … / category.
	 *
	 * @return array<int,array{label:string,url:string}>
	 */
	public function breadcrumb(): array {
		$items = array(
			array(
				'label' => __( 'Home', 'torrehub' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => __( 'Listings', 'torrehub' ),
				'url'   => th_url_listings(),
			),
		);
		if ( $this->category ) {
			$chain = array_reverse( get_ancestors( $this->category->term_id, 'rtcl_category', 'taxonomy' ) );
			foreach ( $chain as $id ) {
				$t = get_term( (int) $id, 'rtcl_category' );
				if ( $t instanceof \WP_Term ) {
					$items[] = array(
						'label' => html_entity_decode( $t->name, ENT_QUOTES ),
						'url'   => (string) get_term_link( $t ),
					);
				}
			}
			$items[] = array(
				'label' => $this->town ? $this->heading() : html_entity_decode( $this->category->name, ENT_QUOTES ),
				'url'   => '',
			);
		} elseif ( $this->town ) {
			$items[] = array(
				'label' => html_entity_decode( $this->town->name, ENT_QUOTES ),
				'url'   => '',
			);
		} else {
			$items[1]['url'] = '';
		}
		return $items;
	}
}
