<?php
/**
 * Read-only directory data for templates: category tree with real counts, towns, stats, upcoming events.
 *
 * Everything heavy is cached in transients (1 h) and flushed when listings or terms change.
 *
 * @package Torrehub
 */

namespace Torrehub\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Directory data service (static, request-memoised + transient-cached).
 */
final class Directory {

	public const POST_TYPE = 'rtcl_listing';
	public const TAX_CAT   = 'rtcl_category';
	public const TAX_LOC   = 'rtcl_location';

	private const CACHE_PREFIX = 'th_dir_';
	private const CACHE_TTL    = HOUR_IN_SECONDS;

	/**
	 * Request-level memo.
	 *
	 * @var array<string,mixed>
	 */
	private static array $memo = array();

	/**
	 * Flush all directory caches when content changes.
	 */
	public static function register_invalidation(): void {
		$flush = array( self::class, 'flush' );
		add_action( 'save_post_' . self::POST_TYPE, $flush );
		add_action(
			'transition_post_status',
			static function ( $new_status, $old_status, $post ) {
				if ( $post instanceof \WP_Post && self::POST_TYPE === $post->post_type && $new_status !== $old_status ) {
					self::flush();
				}
			},
			10,
			3
		);
		add_action(
			'deleted_post',
			static function ( $post_id, $post = null ) {
				if ( $post instanceof \WP_Post && self::POST_TYPE === $post->post_type ) {
					self::flush();
				}
			},
			10,
			2
		);
		add_action(
			'saved_term',
			static function ( $term_id, $tt_id, $taxonomy ) {
				if ( in_array( $taxonomy, array( self::TAX_CAT, self::TAX_LOC ), true ) ) {
					self::flush();
				}
			},
			10,
			3
		);
		add_action(
			'delete_term',
			static function ( $term_id, $tt_id, $taxonomy ) {
				if ( in_array( $taxonomy, array( self::TAX_CAT, self::TAX_LOC ), true ) ) {
					self::flush();
				}
			},
			10,
			3
		);
	}

	/**
	 * Drop every cached value.
	 */
	public static function flush(): void {
		foreach ( array( 'tree', 'towns', 'stats' ) as $key ) {
			delete_transient( self::CACHE_PREFIX . $key );
		}
		// Per-town values use a version key instead of enumerating transients.
		update_option( self::CACHE_PREFIX . 'v', time(), false );
		self::$memo = array();
	}

	/**
	 * Cache helper.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $producer Builds the value on miss.
	 * @return mixed
	 */
	private static function remember( string $key, callable $producer ) {
		if ( array_key_exists( $key, self::$memo ) ) {
			return self::$memo[ $key ];
		}
		$value = get_transient( self::CACHE_PREFIX . $key );
		if ( false === $value ) {
			$value = $producer();
			set_transient( self::CACHE_PREFIX . $key, $value, self::CACHE_TTL );
		}
		self::$memo[ $key ] = $value;
		return $value;
	}

	/**
	 * Versioned key for per-town caches.
	 *
	 * @param string $key Base key.
	 */
	private static function vkey( string $key ): string {
		return $key . '_' . (int) get_option( self::CACHE_PREFIX . 'v', 1 );
	}

	/**
	 * Category tree: root terms with children (and grandchildren) and distinct published-listing counts
	 * across each subtree.
	 *
	 * @return array<int,array{id:int,name:string,slug:string,url:string,count:int,descendants:int,children:array}>
	 */
	public static function category_tree(): array {
		return self::remember(
			'tree',
			static function () {
				$terms = get_terms(
					array(
						'taxonomy'   => self::TAX_CAT,
						'hide_empty' => false,
						'orderby'    => 'name',
					)
				);
				if ( is_wp_error( $terms ) || ! $terms ) {
					return array();
				}
				$by_parent = array();
				foreach ( $terms as $t ) {
					$by_parent[ (int) $t->parent ][] = $t;
				}
				$build = static function ( \WP_Term $t ) use ( &$build, $by_parent ): array {
					$children = array_map( $build, $by_parent[ $t->term_id ] ?? array() );
					$desc     = count( $children ) + array_sum( array_column( $children, 'descendants' ) );
					return array(
						'id'          => (int) $t->term_id,
						'name'        => html_entity_decode( $t->name, ENT_QUOTES ),
						'slug'        => $t->slug,
						'url'         => (string) get_term_link( $t ),
						'count'       => self::subtree_listing_count( (int) $t->term_id ),
						'descendants' => $desc,
						'children'    => $children,
					);
				};
				$roots = array_map( $build, $by_parent[0] ?? array() );

				// Order roots as in the design (category map order), unknown roots last.
				$order = array_flip( array_keys( th_category_map() ) );
				usort( $roots, static fn( $a, $b ) => ( $order[ $a['slug'] ] ?? 99 ) <=> ( $order[ $b['slug'] ] ?? 99 ) );
				return $roots;
			}
		);
	}

	/**
	 * Distinct published listings in a category subtree.
	 *
	 * @param int $term_id Category term id.
	 */
	private static function subtree_listing_count( int $term_id ): int {
		global $wpdb;
		$ids = array_merge( array( $term_id ), (array) get_term_children( $term_id, self::TAX_CAT ) );
		$in  = implode( ',', array_map( 'absint', $ids ) ); // Integers only — safe to inline.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- cached by caller; $in is absint()-ed.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
				WHERE p.post_type = %s AND p.post_status = 'publish' AND tt.term_id IN ($in)",
				self::TAX_CAT,
				self::POST_TYPE
			)
		);
		// phpcs:enable
		return $count;
	}

	/**
	 * One root by slug from the tree.
	 *
	 * @param string $slug Root slug.
	 * @return array<string,mixed>|null
	 */
	public static function root( string $slug ): ?array {
		foreach ( self::category_tree() as $root ) {
			if ( $root['slug'] === $slug ) {
				return $root;
			}
		}
		return null;
	}

	/**
	 * Towns (rtcl_location) with listing counts and image ids, alphabetical.
	 *
	 * @return array<int,array{id:int,name:string,slug:string,url:string,count:int,image:int}>
	 */
	public static function towns(): array {
		return self::remember(
			'towns',
			static function () {
				$terms = get_terms(
					array(
						'taxonomy'   => self::TAX_LOC,
						'hide_empty' => false,
						'orderby'    => 'name',
					)
				);
				if ( is_wp_error( $terms ) ) {
					return array();
				}
				return array_map(
					static fn( \WP_Term $t ) => array(
						'id'    => (int) $t->term_id,
						'name'  => html_entity_decode( $t->name, ENT_QUOTES ),
						'slug'  => $t->slug,
						'url'   => (string) get_term_link( $t ),
						'count' => (int) $t->count,
						'image' => (int) get_term_meta( $t->term_id, '_rtcl_image', true ),
					),
					$terms
				);
			}
		);
	}

	/**
	 * Towns sorted by listing count (ties: alphabetical).
	 *
	 * @param int $limit Max towns.
	 * @return array<int,array<string,mixed>>
	 */
	public static function top_towns( int $limit = 5 ): array {
		$towns = self::towns();
		usort( $towns, static fn( $a, $b ) => array( $b['count'], $a['name'] ) <=> array( $a['count'], $b['name'] ) );
		return array_slice( $towns, 0, $limit );
	}

	/**
	 * A town by slug.
	 *
	 * @param string $slug Town slug.
	 * @return array<string,mixed>|null
	 */
	public static function town( string $slug ): ?array {
		foreach ( self::towns() as $town ) {
			if ( $town['slug'] === $slug ) {
				return $town;
			}
		}
		return null;
	}

	/**
	 * Site stats: published listings, verified sellers, categories, towns.
	 *
	 * @return array{listings:int,verified:int,categories:int,towns:int}
	 */
	public static function stats(): array {
		return self::remember(
			'stats',
			static function () {
				global $wpdb;
				$counts = wp_count_posts( self::POST_TYPE );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- cached.
				$verified = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '1'", 'rtcl_verified_seller' ) );
				return array(
					'listings'   => (int) ( $counts->publish ?? 0 ),
					'verified'   => $verified,
					'categories' => (int) wp_count_terms(
						array(
							'taxonomy'   => self::TAX_CAT,
							'hide_empty' => false,
						)
					),
					'towns'      => (int) wp_count_terms(
						array(
							'taxonomy'   => self::TAX_LOC,
							'hide_empty' => false,
						)
					),
				);
			}
		);
	}

	/**
	 * Published Events listings happening on the coming weekend (Sat 00:00 – Sun 23:59:59, Europe/Madrid),
	 * optionally in a town. "Happening" = starts before the weekend ends AND (ends after it starts, or — without an
	 * end date — starts inside it). Event dates are Form Builder date fields stored as "Y-m-d H:i:s"; their meta
	 * keys are resolved from the Events form (1st date field = start, 2nd = end) so form edits don't break this.
	 *
	 * @param string                  $town_slug Town slug or ''.
	 * @param \DateTimeImmutable|null $now       Reference time (tests); default now.
	 * @return array{count:int,from:\DateTimeImmutable,to:\DateTimeImmutable}
	 */
	public static function weekend_events( string $town_slug = '', ?\DateTimeImmutable $now = null ): array {
		$tz   = new \DateTimeZone( 'Europe/Madrid' );
		$now  = $now ? $now->setTimezone( $tz ) : new \DateTimeImmutable( 'now', $tz );
		$dow  = (int) $now->format( 'N' ); // 1 = Mon … 7 = Sun.
		$from = ( 6 === $dow ? $now : ( 7 === $dow ? $now->modify( '-1 day' ) : $now->modify( 'next saturday' ) ) )->setTime( 0, 0 );
		$to   = $from->modify( '+1 day' )->setTime( 23, 59, 59 );

		$count = self::remember(
			self::vkey( 'weekend_' . $from->format( 'Ymd' ) . '_' . $town_slug ),
			static function () use ( $from, $to, $town_slug ) {
				list( $start_key, $end_key ) = self::event_date_meta_keys();
				if ( ! $start_key ) {
					return 0;
				}
				$f   = $from->format( 'Y-m-d H:i:s' );
				$t   = $to->format( 'Y-m-d H:i:s' );
				$tax = array(
					array(
						'taxonomy' => self::TAX_CAT,
						'field'    => 'slug',
						'terms'    => 'events',
					),
				);
				if ( $town_slug ) {
					$tax[] = array(
						'taxonomy' => self::TAX_LOC,
						'field'    => 'slug',
						'terms'    => $town_slug,
					);
				}
				$starts_before_end = array(
					'key'     => $start_key,
					'value'   => $t,
					'compare' => '<=',
					'type'    => 'CHAR',
				);
				$overlap           = array(
					'relation' => 'OR',
					array(
						'key'     => $start_key,
						'value'   => array( $f, $t ),
						'compare' => 'BETWEEN',
						'type'    => 'CHAR',
					),
				);
				if ( $end_key ) {
					$overlap[] = array(
						'key'     => $end_key,
						'value'   => $f,
						'compare' => '>=',
						'type'    => 'CHAR',
					);
				}
				$q = new \WP_Query(
					array(
						'post_type'      => self::POST_TYPE,
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'tax_query'      => $tax, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'AND',
							$starts_before_end,
							$overlap,
						),
					)
				);
				return (int) $q->found_posts;
			}
		);

		return array(
			'count' => (int) $count,
			'from'  => $from,
			'to'    => $to,
		);
	}

	/**
	 * Meta keys of the Events form's date fields: [start, end] ('' when missing).
	 *
	 * @return array{0:string,1:string}
	 */
	private static function event_date_meta_keys(): array {
		$map  = th_category_map();
		$form = (int) ( $map['events']['form_id'] ?? 0 );
		if ( ! $form || ! class_exists( '\Rtcl\Services\FormBuilder\FBHelper' ) ) {
			return array( '', '' );
		}
		$form_obj = \Rtcl\Services\FormBuilder\FBHelper::getFormById( $form );
		$keys     = array();
		foreach ( $form_obj ? (array) $form_obj->getFields() : array() as $field ) {
			if ( 'date' === ( $field['element'] ?? '' ) && ! empty( $field['name'] ) ) {
				$keys[] = (string) $field['name'];
			}
		}
		return array( $keys[0] ?? '', $keys[1] ?? '' );
	}
}
