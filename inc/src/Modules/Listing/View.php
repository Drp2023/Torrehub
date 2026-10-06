<?php
/**
 * Everything the single-listing templates need, gathered once (view model).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Listing;

use Torrehub\Data\BusinessHours;
use Torrehub\Data\ListingFields;
use Torrehub\Modules\Archive\MapData;
use Torrehub\Modules\Reviews\Module as Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * Single listing view model.
 */
final class View {

	/**
	 * Listing id.
	 *
	 * @var int
	 */
	public int $id;
	/**
	 * Listing post.
	 *
	 * @var \WP_Post
	 */
	public \WP_Post $post;
	/**
	 * Rtcl\Models\Listing.
	 *
	 * @var object
	 */
	public object $listing;
	/**
	 * Title (decoded).
	 *
	 * @var string
	 */
	public string $title;
	/**
	 * Post status.
	 *
	 * @var string
	 */
	public string $status;
	/**
	 * Viewer is the author.
	 *
	 * @var bool
	 */
	public bool $is_owner;
	/**
	 * Featured flag.
	 *
	 * @var bool
	 */
	public bool $featured;
	/**
	 * Author is a verified seller.
	 *
	 * @var bool
	 */
	public bool $verified;
	/**
	 * Price parts [amount, suffix].
	 *
	 * @var array
	 */
	public array $price;
	/**
	 * Gallery attachment ids.
	 *
	 * @var array
	 */
	public array $images;
	/**
	 * Display fields (ListingFields).
	 *
	 * @var array
	 */
	public array $fields;
	/**
	 * Deepest category.
	 *
	 * @var \WP_Term|null
	 */
	public ?\WP_Term $category = null;
	/**
	 * Root category.
	 *
	 * @var \WP_Term|null
	 */
	public ?\WP_Term $root = null;
	/**
	 * Town (rtcl_location).
	 *
	 * @var \WP_Term|null
	 */
	public ?\WP_Term $town = null;
	/**
	 * Parsed business hours.
	 *
	 * @var array|null
	 */
	public ?array $hours = null;
	/**
	 * Open-now status.
	 *
	 * @var array|null
	 */
	public ?array $open = null;
	/**
	 * Map point.
	 *
	 * @var array|null
	 */
	public ?array $point = null;
	/**
	 * Review summary.
	 *
	 * @var array
	 */
	public array $reviews;
	/**
	 * Seller card data.
	 *
	 * @var array
	 */
	public array $seller;
	/**
	 * Which contact channels exist.
	 *
	 * @var array
	 */
	public array $contact;
	/**
	 * Video URLs.
	 *
	 * @var array
	 */
	public array $videos;
	/**
	 * Social profile URLs by network.
	 *
	 * @var array
	 */
	public array $socials;
	/**
	 * Website URL.
	 *
	 * @var string
	 */
	public string $website;
	/**
	 * "Listed 2 days ago".
	 *
	 * @var string
	 */
	public string $age;
	/**
	 * View count.
	 *
	 * @var int
	 */
	public int $views;

	/**
	 * Build for a listing post.
	 *
	 * @param \WP_Post $post Listing.
	 */
	public function __construct( \WP_Post $post ) {
		$this->id       = (int) $post->ID;
		$this->post     = $post;
		$this->listing  = rtcl()->factory->get_listing( $this->id );
		$this->title    = html_entity_decode( get_the_title( $post ), ENT_QUOTES );
		$this->status   = (string) get_post_status( $post );
		$author         = (int) $post->post_author;
		$this->is_owner = is_user_logged_in() && get_current_user_id() === $author;
		$this->featured = (bool) get_post_meta( $this->id, 'featured', true );
		$this->verified = '1' === (string) get_user_meta( $author, 'rtcl_verified_seller', true );
		$this->price    = th_listing_price_parts( $this->listing );
		$this->fields   = ListingFields::for_listing( $this->id );
		$this->views    = (int) get_post_meta( $this->id, '_views', true );
		/* translators: %s: human time difference */
		$this->age = sprintf( __( 'Listed %s ago', 'torrehub' ), human_time_diff( (int) get_post_time( 'U', true, $post ), time() ) );

		// Deepest category (the listing may sit in a sub-sub-category) and its root.
		$cats = get_the_terms( $post, 'rtcl_category' );
		if ( $cats && ! is_wp_error( $cats ) ) {
			usort( $cats, static fn( $a, $b ) => count( get_ancestors( $b->term_id, 'rtcl_category' ) ) <=> count( get_ancestors( $a->term_id, 'rtcl_category' ) ) );
			$this->category = $cats[0];
			$anc            = get_ancestors( $cats[0]->term_id, 'rtcl_category', 'taxonomy' );
			$root           = $anc ? get_term( (int) end( $anc ), 'rtcl_category' ) : $cats[0];
			$this->root     = $root instanceof \WP_Term ? $root : null;
		}
		$towns      = get_the_terms( $post, 'rtcl_location' );
		$this->town = ( $towns && ! is_wp_error( $towns ) ) ? $towns[0] : null;

		// Gallery: the listing's images; the form's cover image (Service) leads when present.
		$this->images = array();
		foreach ( \Rtcl\Helpers\Functions::get_listing_images( $this->id ) as $att ) {
			$this->images[] = (int) $att->ID;
		}
		if ( ! $this->images && has_post_thumbnail( $post ) ) {
			$this->images[] = (int) get_post_thumbnail_id( $post );
		}

		$this->hours = BusinessHours::for_listing( $this->id );
		$this->open  = $this->hours ? BusinessHours::status( $this->hours ) : null;
		$this->point = '1' === (string) get_post_meta( $this->id, 'hide_map', true ) ? null : MapData::point( $this->id );

		$this->reviews = class_exists( Reviews::class ) && Reviews::active() ? Reviews::summary( $this->id ) : array(
			'average' => 0,
			'count'   => 0,
			'bars'    => array(),
		);

		$this->seller  = self::seller( $author, $this->id );
		$this->contact = array(
			'phone'    => '' !== trim( (string) get_post_meta( $this->id, 'phone', true ) ),
			'whatsapp' => '' !== trim( (string) get_post_meta( $this->id, '_rtcl_whatsapp_number', true ) ),
			'email'    => true,
		);

		$videos       = get_post_meta( $this->id, '_rtcl_video_urls', true );
		$this->videos = array_values( array_filter( array_map( 'esc_url_raw', is_array( $videos ) ? $videos : array() ) ) );

		$socials       = get_post_meta( $this->id, '_rtcl_social_profiles', true );
		$this->socials = array_filter( array_map( 'esc_url_raw', is_array( $socials ) ? $socials : array() ) );
		$this->website = esc_url_raw( (string) get_post_meta( $this->id, 'website', true ) );
	}

	/**
	 * Seller card data.
	 *
	 * @param int $user_id    Author.
	 * @param int $listing_id Listing (for the business logo field).
	 * @return array<string,mixed>
	 */
	private static function seller( int $user_id, int $listing_id ): array {
		$user  = get_userdata( $user_id );
		$roles = $user ? (array) $user->roles : array();
		$count = (int) ( new \WP_Query(
			array(
				'post_type'      => 'rtcl_listing',
				'post_status'    => 'publish',
				'author'         => $user_id,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		) )->found_posts;
		return array(
			'id'       => $user_id,
			'name'     => $user ? $user->display_name : __( 'Seller', 'torrehub' ),
			'since'    => $user ? wp_date( 'F Y', strtotime( $user->user_registered ) ) : '',
			'listings' => $count,
			'business' => in_array( 'business', $roles, true ),
			'nif'      => in_array( 'business', $roles, true ) && '' !== (string) get_user_meta( $user_id, 'custom_field_2', true ),
			'verified' => '1' === (string) get_user_meta( $user_id, 'rtcl_verified_seller', true ),
			'logo'     => (int) ( ListingFields::for_listing( $listing_id )['logo'] ?? 0 ),
			'url'      => $user ? add_query_arg( 'seller', $user->user_nicename, th_url_listings() ) : '',
		);
	}

	/**
	 * Badges for the header: Featured, Verified seller, Open now · until 20:00 / Closed.
	 *
	 * @return array<int,array{label:string,variant:string}>
	 */
	public function badges(): array {
		$out = array();
		if ( $this->featured ) {
			$out[] = array(
				'label'   => __( 'Featured', 'torrehub' ),
				'variant' => 'featured',
			);
		}
		if ( $this->verified ) {
			$out[] = array(
				'label'   => __( 'Verified seller', 'torrehub' ),
				'variant' => 'verified',
			);
		}
		if ( $this->open ) {
			if ( $this->open['open'] ) {
				$out[] = array(
					/* translators: %s: closing time */
					'label'   => $this->open['until'] ? sprintf( __( 'Open now · until %s', 'torrehub' ), $this->open['until'] ) : __( 'Open now', 'torrehub' ),
					'variant' => 'success',
				);
			} else {
				$out[] = array(
					/* translators: %s: opening time */
					'label'   => $this->open['next'] ? sprintf( __( 'Closed · opens %s', 'torrehub' ), $this->open['next'] ) : __( 'Closed now', 'torrehub' ),
					'variant' => 'neutral',
				);
			}
		}
		return $out;
	}

	/**
	 * Detail tiles, the category first (blue).
	 *
	 * @return array<int,array{label:string,value:string,tone:string}>
	 */
	public function details(): array {
		$tiles = array();
		if ( $this->category ) {
			$tiles[] = array(
				'label' => __( 'Category', 'torrehub' ),
				'value' => $this->category_path(),
				'tone'  => 'blue',
			);
		}
		return array_merge( $tiles, $this->fields['details'] );
	}

	/**
	 * "Home & Maintenance / Plumbing" (root excluded when deeper).
	 */
	public function category_path(): string {
		if ( ! $this->category ) {
			return '';
		}
		$names = array( html_entity_decode( $this->category->name, ENT_QUOTES ) );
		$anc   = get_ancestors( $this->category->term_id, 'rtcl_category', 'taxonomy' );
		if ( count( $anc ) > 1 ) {
			$parent = get_term( (int) $anc[0], 'rtcl_category' );
			if ( $parent instanceof \WP_Term ) {
				array_unshift( $names, html_entity_decode( $parent->name, ENT_QUOTES ) );
			}
		}
		return implode( ' / ', $names );
	}

	/**
	 * Breadcrumb: Home / root … / category / title.
	 *
	 * @return array<int,array{label:string,url:string}>
	 */
	public function breadcrumb(): array {
		$items = array(
			array(
				'label' => __( 'Home', 'torrehub' ),
				'url'   => home_url( '/' ),
			),
		);
		if ( $this->category ) {
			foreach ( array_reverse( get_ancestors( $this->category->term_id, 'rtcl_category', 'taxonomy' ) ) as $id ) {
				$t = get_term( (int) $id, 'rtcl_category' );
				if ( $t instanceof \WP_Term ) {
					$items[] = array(
						'label' => html_entity_decode( $t->name, ENT_QUOTES ),
						'url'   => (string) get_term_link( $t ),
					);
				}
			}
			$items[] = array(
				'label' => html_entity_decode( $this->category->name, ENT_QUOTES ),
				'url'   => (string) get_term_link( $this->category ),
			);
		}
		$items[] = array(
			'label' => $this->title,
			'url'   => '',
		);
		return $items;
	}

	/**
	 * Related listings: same category (then root), same town first, newest; excluding this one.
	 *
	 * @param int $limit Max.
	 * @return array<int,int>
	 */
	public function related( int $limit = 4 ): array {
		$term = $this->category ? $this->category : $this->root;
		if ( ! $term ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'        => 'rtcl_listing',
				'post_status'      => 'publish',
				'posts_per_page'   => $limit,
				'fields'           => 'ids',
				'post__not_in'     => array( $this->id ),
				'suppress_filters' => false,
				'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- small, cached by WP.
					array(
						'taxonomy' => 'rtcl_category',
						'terms'    => array( $this->root ? $this->root->term_id : $term->term_id ),
					),
				),
			)
		);
		// Closest category first: same leaf category before the rest of the root.
		usort(
			$ids,
			fn( $a, $b ) => (int) has_term( $term->term_id, 'rtcl_category', $b ) <=> (int) has_term( $term->term_id, 'rtcl_category', $a )
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Is the listing publicly visible?
	 */
	public function is_public(): bool {
		return 'publish' === $this->status;
	}
}
