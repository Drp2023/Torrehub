<?php
/**
 * Compare listings (client request 2026-11) — Classified Listing's Compare is Pro-only (the free plugin has the
 * button markup but no handler or page), so the theme does it.
 *
 * - Up to 3 published listings from the same main category (same form, so the same fields line up).
 * - "Compare" on the cards (next to the heart) and on the listing page (next to Save): a link to
 *   /compare/?toggle=ID — works without JS (adds and shows the comparison); compare.js toggles in place and
 *   updates the bar from the same URL (&format=json).
 * - The selection lives in a functional cookie (`th_compare`, ids only, set when the visitor presses Compare): no
 *   account needed, and every server-rendered card / AJAX result knows what is selected.
 * - A floating bar at the bottom shows the selection (thumbs, remove, Clear, Compare); /compare/ shows them side by
 *   side: photo, title, price, town, then the category and the listing's details row by row. noindex.
 *
 * DECISION: a listing from another main category is refused with a message (Clear starts a new comparison) rather
 * than silently replacing the selection; the 4th listing is refused the same way.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Compare;

use Torrehub\Core\Module as BaseModule;
use Torrehub\Modules\Listing\View;

defined( 'ABSPATH' ) || exit;

/**
 * Compare module.
 */
final class Module extends BaseModule {

	public const MAX     = 3;
	public const COOKIE  = 'th_compare';
	private const VAR    = 'th_compare';
	private const SLUG   = 'compare';
	private const FLUSH  = 'th_compare_rewrite';
	private const DAYS   = 30;
	private const NOTICE = 'th_compare_msg';

	/**
	 * Running (templates check before rendering a button).
	 *
	 * @var bool
	 */
	private static bool $active = false;

	/**
	 * Ids for this request (after a change in this request).
	 *
	 * @var array<int,int>|null
	 */
	private static ?array $ids = null;

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'compare';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Compare listings', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Visitors put up to 3 listings from the same category side by side (no account needed).', 'torrehub' );
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
	 * Is the module running?
	 */
	public static function active(): bool {
		return self::$active;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		self::$active = true;
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', static fn( $vars ) => array_merge( (array) $vars, array( self::VAR ) ) );
		add_action( 'parse_query', array( $this, 'not_the_blog' ) );
		add_action( 'template_redirect', array( $this, 'route' ), 5 );
		add_filter( 'th_listing_card_args', array( $this, 'card_args' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'bar' ), 5 );
		add_filter( 'th_page_css_bundles', array( $this, 'css_bundles' ) );
	}

	/* ------------------------------------------------------------------ state */

	/**
	 * /compare/ URL (optionally with an action).
	 *
	 * @param array<string,string|int> $query Query args.
	 */
	public static function url( array $query = array() ): string {
		return add_query_arg( $query, home_url( '/' . self::SLUG . '/' ) );
	}

	/**
	 * Selected listing ids (published, same main category, at most 3).
	 *
	 * @return array<int,int>
	 */
	public static function ids(): array {
		if ( null === self::$ids ) {
			$raw       = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
			self::$ids = self::valid( array_map( 'absint', explode( '.', $raw ) ) );
		}
		return self::$ids;
	}

	/**
	 * Keep published listings of the first one's main category, at most 3, no repeats.
	 *
	 * @param array<int,int> $ids Ids.
	 * @return array<int,int>
	 */
	private static function valid( array $ids ): array {
		$out  = array();
		$root = 0;
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			if ( 'rtcl_listing' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$root = $root ? $root : self::root( $id );
			if ( self::root( $id ) === $root && count( $out ) < self::MAX ) {
				$out[] = (int) $id;
			}
		}
		return $out;
	}

	/**
	 * Main (top-level) category of a listing, 0 when none.
	 *
	 * @param int $id Listing.
	 */
	public static function root( int $id ): int {
		$cats = get_the_terms( $id, 'rtcl_category' );
		if ( ! $cats || is_wp_error( $cats ) ) {
			return 0;
		}
		$anc = get_ancestors( $cats[0]->term_id, 'rtcl_category', 'taxonomy' );
		return $anc ? (int) end( $anc ) : (int) $cats[0]->term_id;
	}

	/**
	 * First photo of a listing (as on the cards), 0 when none.
	 *
	 * @param int $id Listing.
	 */
	public static function image( int $id ): int {
		$images = \Rtcl\Helpers\Functions::get_listing_images( $id );
		return $images ? (int) $images[0]->ID : (int) get_post_thumbnail_id( $id );
	}

	/**
	 * Main category of the selection.
	 */
	public static function category(): ?\WP_Term {
		$ids  = self::ids();
		$term = $ids ? get_term( self::root( $ids[0] ), 'rtcl_category' ) : null;
		return $term instanceof \WP_Term ? $term : null;
	}

	/**
	 * Is a listing selected?
	 *
	 * @param int $id Listing.
	 */
	public static function has( int $id ): bool {
		return in_array( $id, self::ids(), true );
	}

	/**
	 * Store the selection (cookie, for this request too).
	 *
	 * @param array<int,int> $ids Ids.
	 */
	private static function store( array $ids ): void {
		self::$ids = array_values( $ids );
		$value     = implode( '.', self::$ids );
		$expires   = $value ? time() + self::DAYS * DAY_IN_SECONDS : time() - YEAR_IN_SECONDS;
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				$value,
				array(
					'expires'  => $expires,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/**
	 * Add / remove / toggle / clear. Returns [ result code, message ].
	 *
	 * @param string $action add|remove|toggle|clear.
	 * @param int    $id     Listing.
	 * @return array{0:string,1:string}
	 */
	private static function change( string $action, int $id ): array {
		$ids = self::ids();
		if ( 'clear' === $action ) {
			self::store( array() );
			return array( 'cleared', __( 'Comparison cleared.', 'torrehub' ) );
		}
		$in = in_array( $id, $ids, true );
		if ( 'remove' === $action || ( 'toggle' === $action && $in ) ) {
			self::store( array_diff( $ids, array( $id ) ) );
			return array( 'removed', __( 'Removed from the comparison.', 'torrehub' ) );
		}
		if ( $in ) {
			return array( 'added', __( 'Added to the comparison.', 'torrehub' ) );
		}
		if ( 'rtcl_listing' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return array( 'invalid', __( 'This listing can’t be compared.', 'torrehub' ) );
		}
		$category = self::category();
		if ( $category && self::root( $id ) !== (int) $category->term_id ) {
			return array(
				'category',
				/* translators: %s: category name, e.g. "Services" */
				sprintf( __( 'You can only compare listings from one category: %s. Clear the comparison to start a new one.', 'torrehub' ), html_entity_decode( $category->name, ENT_QUOTES ) ),
			);
		}
		if ( count( $ids ) >= self::MAX ) {
			/* translators: %d: maximum number of listings */
			return array( 'full', sprintf( __( 'You can compare up to %d listings. Remove one first.', 'torrehub' ), self::MAX ) );
		}
		$ids[] = $id;
		self::store( $ids );
		return array( 'added', __( 'Added to the comparison.', 'torrehub' ) );
	}

	/* ------------------------------------------------------------------ routing */

	/**
	 * /compare/ → index.php?th_compare=1.
	 */
	public function rewrite(): void {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?' . self::VAR . '=1', 'top' );
		if ( '1' !== get_option( self::FLUSH ) ) {
			flush_rewrite_rules( false );
			update_option( self::FLUSH, '1' );
		}
	}

	/**
	 * /compare/ only carries the query var: without this WordPress would treat it as the posts index (Guides).
	 *
	 * @param \WP_Query $q Query.
	 */
	public function not_the_blog( $q ): void {
		if ( $q->is_main_query() && '1' === (string) $q->get( self::VAR ) ) {
			$q->is_home = false;
			$q->set( 'posts_per_page', 1 );
			$q->set( 'no_found_rows', true );
		}
	}

	/**
	 * Is /compare/ being viewed?
	 */
	public static function is_page(): bool {
		return '1' === (string) get_query_var( self::VAR );
	}

	/**
	 * Changes (?toggle=, ?add=, ?remove=, ?clear=1) and the page.
	 */
	public function route(): void {
		if ( ! self::is_page() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- changes only the visitor's own compare cookie.
		$action = '';
		$id     = 0;
		foreach ( array( 'toggle', 'add', 'remove' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$action = $key;
				$id     = absint( $_GET[ $key ] );
			}
		}
		if ( isset( $_GET['clear'] ) ) {
			$action = 'clear';
		}
		$json = isset( $_GET['format'] ) && 'json' === $_GET['format'];
		$code = isset( $_GET[ self::NOTICE ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE ] ) ) : '';
		// phpcs:enable

		if ( $action ) {
			nocache_headers();
			list( $result, $message ) = self::change( $action, $id );
			if ( $json ) {
				wp_send_json(
					array(
						'result'  => $result,
						'message' => $message,
						'ok'      => in_array( $result, array( 'added', 'removed', 'cleared' ), true ),
						'ids'     => self::ids(),
						'bar'     => self::bar_html(),
					)
				);
			}
			wp_safe_redirect( self::url( $result ? array( self::NOTICE => $result ) : array() ) );
			exit;
		}

		nocache_headers();
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter(
			'document_title_parts',
			static function ( $parts ) {
				$parts['title'] = __( 'Compare listings', 'torrehub' );
				return $parts;
			},
			30
		);
		status_header( 200 );
		get_header();
		get_template_part(
			'template-parts/compare/page',
			null,
			array(
				'views'  => array_map( static fn( $listing_id ) => new View( get_post( $listing_id ) ), self::ids() ),
				'notice' => self::notice( $code ),
			)
		);
		get_footer();
		exit;
	}

	/**
	 * Message for a result code after a no-JS change.
	 *
	 * @param string $code Result code.
	 * @return array{text:string,type:string}|null
	 */
	private static function notice( string $code ): ?array {
		$category = self::category();
		$texts    = array(
			'added'    => array( __( 'Added to the comparison.', 'torrehub' ), 'success' ),
			'removed'  => array( __( 'Removed from the comparison.', 'torrehub' ), 'success' ),
			'cleared'  => array( __( 'Comparison cleared.', 'torrehub' ), 'success' ),
			/* translators: %d: maximum number of listings */
			'full'     => array( sprintf( __( 'You can compare up to %d listings. Remove one first.', 'torrehub' ), self::MAX ), 'attention' ),
			'category' => array(
				/* translators: %s: category name */
				sprintf( __( 'You can only compare listings from one category: %s. Clear the comparison to start a new one.', 'torrehub' ), $category ? html_entity_decode( $category->name, ENT_QUOTES ) : '' ),
				'attention',
			),
			'invalid'  => array( __( 'This listing can’t be compared.', 'torrehub' ), 'attention' ),
		);
		return isset( $texts[ $code ] ) ? array(
			'text' => $texts[ $code ][0],
			'type' => $texts[ $code ][1],
		) : null;
	}

	/* ------------------------------------------------------------------ output */

	/**
	 * Compare button on public listing cards.
	 *
	 * @param array  $args    Card args.
	 * @param object $listing Rtcl\Models\Listing.
	 * @return array
	 */
	public function card_args( $args, $listing ): array {
		$args = (array) $args;
		$id   = (int) ( $args['listing_id'] ?? 0 );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$args['compare']         = true;
			$args['compare_pressed'] = self::has( $id );
		}
		return $args;
	}

	/**
	 * The floating bar (every front page except /compare/; empty until something is selected).
	 */
	public function bar(): void {
		if ( ! is_admin() && ! self::is_page() ) {
			echo self::bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output.
		}
	}

	/**
	 * Bar markup ('' when nothing is selected — compare.js adds it on the first Compare).
	 */
	public static function bar_html(): string {
		$ids = self::ids();
		if ( ! $ids ) {
			return '';
		}
		ob_start();
		get_template_part(
			'template-parts/compare/bar',
			null,
			array(
				'ids'      => $ids,
				'category' => self::category(),
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Compare page CSS.
	 *
	 * @param array<int,string> $bundles Bundles.
	 * @return array<int,string>
	 */
	public function css_bundles( $bundles ): array {
		$bundles = (array) $bundles;
		if ( self::is_page() ) {
			$bundles[] = 'compare';
		}
		return $bundles;
	}
}
