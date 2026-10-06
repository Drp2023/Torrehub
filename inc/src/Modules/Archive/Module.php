<?php
/**
 * Listing archive: filters (Form Builder fields, price, radius, verified), sort, grid/list/map views,
 * load more, live result count. Replaces the Pro filter widget and the "Filter Builder Active" WPCode snippet.
 *
 * Templates: classified-listing/{archive-rtcl_listing,taxonomy-rtcl_*}.php → template-parts/archive/*.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Archive;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Archive module.
 */
final class Module extends BaseModule {

	/**
	 * Registered (enabled + requirements met) in this request.
	 *
	 * @var bool
	 */
	private static bool $active = false;

	/**
	 * Is the module running? The RTCL template overrides fall back to the plugin's templates when not.
	 */
	public static function active(): bool {
		return self::$active;
	}

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'archive';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Listing archive & filters', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Category, town and search results with filters, sorting, list/map views and live result counts.', 'torrehub' );
	}

	/**
	 * Needs Classified Listing.
	 */
	public function requirements_met(): bool {
		return th_has_rtcl();
	}

	/**
	 * Requirement message.
	 */
	public function requirement_message(): string {
		return __( 'Requires the Classified Listing plugin.', 'torrehub' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		self::$active = true;
		add_action( 'rtcl_listing_query', array( $this, 'apply_search' ) );
		add_filter( 'posts_clauses', array( $this, 'nearest_order' ), 10, 2 );
		add_filter( 'posts_clauses', array( $this, 'geo_clauses' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'count_endpoint' ), 1 );
		add_action( 'template_redirect', array( $this, 'canonical_redirect' ), 5 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'th_rtcl_assets_needed', array( $this, 'no_rtcl_assets' ) );
		add_filter( 'th_page_css_bundles', array( $this, 'css_bundles' ) );
		add_action( 'wp_head', array( $this, 'preload_lcp_image' ), 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'drop_block_styles' ), 100 );
		add_filter( 'script_module_data_th-app', array( $this, 'module_data' ) );
		add_filter( 'document_title_parts', array( $this, 'title' ), 20 );
		add_filter( 'pre_get_document_title', array( $this, 'drop_rtcl_title' ), 5 );

		if ( is_admin() ) {
			( new AdminScreen() )->register();
			( new CardFields() )->register();
		}
	}

	/**
	 * Is the current request a listing archive rendered by this module?
	 */
	public static function is_archive(): bool {
		return is_post_type_archive( 'rtcl_listing' )
			|| is_tax( array( 'rtcl_category', 'rtcl_location', 'rtcl_tag' ) )
			|| ( class_exists( '\Rtcl\Helpers\Functions' ) && \Rtcl\Helpers\Functions::is_listings() );
	}

	/**
	 * Apply the search state to RTCL's main listing query.
	 *
	 * @param \WP_Query $q Main query.
	 */
	public function apply_search( $q ): void {
		if ( $q instanceof \WP_Query && $q->is_main_query() ) {
			Search::current()->apply( $q );
		}
	}

	/**
	 * "Nearest" ordering: rank by the distance of each listing's town from the chosen town.
	 *
	 * @param array<string,string> $clauses SQL clauses.
	 * @param \WP_Query            $q       Query.
	 * @return array<string,string>
	 */
	public function nearest_order( $clauses, $q ) {
		$slugs = $q instanceof \WP_Query ? $q->get( 'th_nearest' ) : null;
		if ( ! $slugs || ! is_array( $slugs ) ) {
			return $clauses;
		}
		$ids = array();
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', (string) $slug, 'rtcl_location' );
			if ( $term instanceof \WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		if ( ! $ids ) {
			return $clauses;
		}
		global $wpdb;
		$list               = implode( ',', $ids ); // Integers only.
		$clauses['join']   .= " LEFT JOIN (SELECT tr.object_id, MIN(FIELD(tt.term_id, {$list})) th_rank FROM {$wpdb->term_relationships} tr"
			. " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'rtcl_location' AND tt.term_id IN ({$list})"
			. " GROUP BY tr.object_id) th_near ON th_near.object_id = {$wpdb->posts}.ID";
		$clauses['orderby'] = 'COALESCE(th_near.th_rank, 9999) ASC, ' . ( $clauses['orderby'] ? $clauses['orderby'] : "{$wpdb->posts}.post_date DESC" );
		return $clauses;
	}

	/**
	 * Radius filter and "Nearest" ordering on real coordinates (phase 6 pin picker).
	 *
	 * Distance per listing = Haversine from the centre to its own pin (`latitude` / `longitude` meta), else to its
	 * town's centre (th_town_coordinates(), precomputed here). Radius: distance ≤ km. Nearest: distance ascending.
	 *
	 * @param array<string,string> $clauses SQL clauses.
	 * @param \WP_Query            $q       Query.
	 * @return array<string,string>
	 */
	public function geo_clauses( $clauses, $q ) {
		if ( ! $q instanceof \WP_Query ) {
			return $clauses;
		}
		$geo   = $q->get( 'th_geo' );
		$order = $q->get( 'th_geo_order' );
		$geo   = is_array( $geo ) && isset( $geo['lat'], $geo['lng'], $geo['km'] ) ? $geo : null;
		$order = is_array( $order ) && isset( $order['lat'], $order['lng'] ) ? $order : null;
		$at    = $geo ? $geo : $order;
		if ( ! $at ) {
			return $clauses;
		}
		global $wpdb;
		$lat = (float) $at['lat'];
		$lng = (float) $at['lng'];

		// Town centre distances as a CASE over term ids (towns without coordinates stay NULL).
		$coords = th_town_coordinates();
		$cases  = '';
		foreach ( \Torrehub\Data\Directory::towns() as $town ) {
			if ( isset( $coords[ $town['slug'] ] ) ) {
				$cases .= sprintf( ' WHEN %d THEN %.3F', (int) $town['id'], th_distance_km( array( $lat, $lng ), $coords[ $town['slug'] ] ) );
			}
		}
		$town_join = $cases
			? " LEFT JOIN (SELECT tr.object_id, MIN(CASE tt.term_id{$cases} END) km FROM {$wpdb->term_relationships} tr"
				. " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'rtcl_location'"
				. " GROUP BY tr.object_id) th_town ON th_town.object_id = {$wpdb->posts}.ID"
			: '';

		$plat = "CAST(NULLIF(th_lat.meta_value, '') AS DECIMAL(10,6))";
		$plng = "CAST(NULLIF(th_lng.meta_value, '') AS DECIMAL(10,6))";
		$own  = sprintf(
			'(6371 * 2 * ASIN(LEAST(1, SQRT(POW(SIN(RADIANS(%1$s - %3$.6F) / 2), 2) + COS(RADIANS(%3$.6F)) * COS(RADIANS(%1$s)) * POW(SIN(RADIANS(%2$s - %4$.6F) / 2), 2)))))',
			$plat,
			$plng,
			$lat,
			$lng
		);
		$dist = $cases ? "COALESCE({$own}, th_town.km)" : $own;

		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} th_lat ON th_lat.post_id = {$wpdb->posts}.ID AND th_lat.meta_key = 'latitude'"
			. " LEFT JOIN {$wpdb->postmeta} th_lng ON th_lng.post_id = {$wpdb->posts}.ID AND th_lng.meta_key = 'longitude'"
			. $town_join;
		if ( $geo ) {
			$clauses['where'] .= sprintf( ' AND %s <= %.3F', $dist, (float) $geo['km'] );
		}
		if ( $order ) {
			$clauses['orderby'] = "{$dist} ASC, " . ( $clauses['orderby'] ? $clauses['orderby'] : "{$wpdb->posts}.post_date DESC" );
		}
		// The postmeta joins could duplicate rows if a listing had two latitude rows.
		if ( ! str_contains( (string) $clauses['groupby'], "{$wpdb->posts}.ID" ) ) {
			$clauses['groupby'] = $clauses['groupby'] ? $clauses['groupby'] . ", {$wpdb->posts}.ID" : "{$wpdb->posts}.ID";
		}
		return $clauses;
	}

	/**
	 * `?th_count=1` on any archive URL → `{"count":N}` (the filter sheet's live "Show N results").
	 * Same main query as the page itself, so the number always matches.
	 */
	public function count_endpoint(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only count.
		if ( ! isset( $_GET['th_count'] ) || ! self::is_archive() ) {
			return;
		}
		global $wp_query;
		$count = (int) $wp_query->found_posts;
		nocache_headers();
		wp_send_json(
			array(
				'count' => $count,
				/* translators: %s: number of results */
				'label' => 0 === $count ? __( 'No results', 'torrehub' ) : sprintf( _n( 'Show %s result', 'Show %s results', $count, 'torrehub' ), number_format_i18n( $count ) ),
			)
		);
	}

	/**
	 * One URL per search: /listings/?rtcl_category=x → /listing-category/x/, empty parameters dropped
	 * (GET forms submit every field). Unknown parameters (utm_*, gclid…) are kept.
	 */
	public function canonical_redirect(): void {
		if ( ! self::is_archive() || is_feed() || 'get' !== sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) || isset( $_GET['th_count'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		$search = Search::current();
		$known  = array( 'q', 'rtcl_category', 'rtcl_location', 'radius', 'min_price', 'max_price', 'verified', 'seller', 'f', 'orderby', 'view', 'page' );
		$get    = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- compared, never stored.
		$extra  = array_diff_key( $get, array_flip( $known ) );
		$target = Search::build_url( $search->params() );
		if ( $extra ) {
			$target .= ( str_contains( $target, '?' ) ? '&' : '?' ) . http_build_query( $extra, '', '&', PHP_QUERY_RFC3986 );
		}

		$current = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
		if ( self::same_url( $current, $target ) ) {
			return;
		}
		wp_safe_redirect( $target, 302, 'Torrehub' );
		exit;
	}

	/**
	 * Compare two URLs by path and parsed query (order-insensitive).
	 *
	 * @param string $a URL A.
	 * @param string $b URL B.
	 */
	private static function same_url( string $a, string $b ): bool {
		$norm = static function ( string $url ): array {
			$parts = wp_parse_url( $url );
			$query = array();
			if ( ! empty( $parts['query'] ) ) {
				parse_str( $parts['query'], $query );
			}
			$sort = static function ( &$arr ) use ( &$sort ) {
				if ( is_array( $arr ) ) {
					if ( ! array_is_list( $arr ) ) {
						ksort( $arr );
					}
					foreach ( $arr as &$v ) {
						$sort( $v );
					}
				}
			};
			$sort( $query );
			return array( untrailingslashit( (string) ( $parts['path'] ?? '/' ) ), $query );
		};
		return $norm( $a ) == $norm( $b ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- array equality after normalisation ('1' vs 1).
	}

	/**
	 * Filtered / sorted / paged variants are noindex,follow; the plain category, town and listings pages are indexable.
	 *
	 * @param array<string,bool|string> $robots Robots directives.
	 * @return array<string,bool|string>
	 */
	public function robots( $robots ) {
		if ( ! self::is_archive() ) {
			return $robots;
		}
		$s = Search::current();
		if ( $s->has_refinements() || '' !== $s->q || $s->orderby || 'grid' !== $s->view || $s->page > 1 || ( $s->category && $s->town ) ) {
			$robots['noindex'] = true;
			if ( empty( $robots['nofollow'] ) ) { // "Discourage search engines" already set nofollow.
				$robots['follow'] = true;
			}
		}
		return $robots;
	}

	/**
	 * The archive renders its own markup: Classified Listing's front-end kit isn't needed there.
	 *
	 * @param bool $needed Needed so far.
	 */
	public function no_rtcl_assets( $needed ): bool {
		return self::is_archive() ? false : (bool) $needed;
	}

	/**
	 * Preload the first card image (the archive's LCP element) so the browser fetches it with the CSS,
	 * not after parsing the header. Same srcset/sizes as the card, so the preloaded file is the one used.
	 */
	public function preload_lcp_image(): void {
		global $wp_query;
		if ( ! self::is_archive() || 'grid' !== Search::current()->view || Search::current()->page > 1 || empty( $wp_query->posts ) ) {
			return;
		}
		$card = th_listing_card_args( $wp_query->posts[0] );
		if ( ! $card || ! is_numeric( $card['image'] ) || (int) $card['image'] <= 0 ) {
			return;
		}
		$src    = wp_get_attachment_image_src( (int) $card['image'], 'th-card' );
		$srcset = wp_get_attachment_image_srcset( (int) $card['image'], 'th-card' );
		if ( ! $src ) {
			return;
		}
		printf(
			'<link rel="preload" as="image" href="%s"%s imagesizes="%s" fetchpriority="high">' . PHP_EOL,
			esc_url( $src[0] ),
			$srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : '',
			esc_attr( '(min-width: 1200px) 440px, (min-width: 600px) 50vw, 100vw' )
		);
	}

	/**
	 * Archives render no block content: the block library and theme.json global styles (≈ 19 KB inline) go.
	 */
	public function drop_block_styles(): void {
		if ( self::is_archive() ) {
			remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
			foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles' ) as $handle ) {
				wp_dequeue_style( $handle );
			}
		}
	}

	/**
	 * Archive CSS bundle (inlined with the theme bundle, see inc/assets.php).
	 *
	 * @param array<int,string> $bundles Page bundles.
	 * @return array<int,string>
	 */
	public function css_bundles( $bundles ): array {
		$bundles = (array) $bundles;
		if ( self::is_archive() ) {
			$bundles[] = 'archive';
		}
		return $bundles;
	}

	/**
	 * Strings + endpoints for archive.js / map.js / favourites.
	 *
	 * @param array<string,mixed> $data Module data.
	 * @return array<string,mixed>
	 */
	public function module_data( array $data ): array {
		$data['archive'] = array(
			'leaflet' => array(
				'js'  => th_asset( 'assets/vendor/leaflet/leaflet.js' ) . '?ver=1.9.4',
				'css' => th_asset( 'assets/vendor/leaflet/leaflet.css' ) . '?ver=1.9.4',
			),
			/**
			 * Map tiles. DECISION: OpenStreetMap standard tiles (no key, attribution required). Filterable, e.g. to a
			 * paid provider for heavy traffic (OSM's tile policy asks for moderate use).
			 *
			 * @param array $tiles { url, attribution, maxZoom }.
			 */
			'tiles'   => apply_filters(
				'th_map_tiles',
				array(
					'url'         => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
					'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
					'maxZoom'     => 18,
				)
			),
			'i18n'    => array(
				'loading'  => __( 'Loading more listings…', 'torrehub' ),
				'loaded'   => __( 'More listings loaded.', 'torrehub' ),
				'error'    => __( 'Couldn’t load more. Check your connection and try again.', 'torrehub' ),
				'counting' => __( 'Counting…', 'torrehub' ),
				'zoomIn'   => __( 'Zoom in', 'torrehub' ),
				'zoomOut'  => __( 'Zoom out', 'torrehub' ),
				'locate'   => __( 'Show my location', 'torrehub' ),
				'mapLabel' => __( 'Map of the results', 'torrehub' ),
			),
		);
		$data['loginUrl'] = wp_login_url(); // DECISION: phase 5 points this at the theme's /login/ page.
		if ( th_favourites_enabled() && is_user_logged_in() && function_exists( 'rtcl' ) ) {
			$data['favourites'] = array(
				'action'  => 'rtcl_public_add_remove_favorites',
				'nonceId' => rtcl()->nonceId,
				'nonce'   => wp_create_nonce( rtcl()->nonceText ),
				'added'   => __( 'Saved to your favourites.', 'torrehub' ),
				'removed' => __( 'Removed from your favourites.', 'torrehub' ),
			);
		}
		return $data;
	}

	/**
	 * Classified Listing replaces the whole document title on archives; the theme sets its own heading instead.
	 *
	 * @param string $title Pre-computed title.
	 */
	public function drop_rtcl_title( $title ) {
		if ( self::is_archive() ) {
			remove_filter( 'pre_get_document_title', array( \Rtcl\Controllers\Hooks\FilterHooks::class, 'listing_archive_site_title' ) );
		}
		return $title;
	}

	/**
	 * Document title = the archive heading ("Plumbing in Torrevieja").
	 *
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public function title( $parts ) {
		if ( self::is_archive() && is_array( $parts ) ) {
			$parts['title'] = Search::current()->heading();
		}
		return $parts;
	}
}
