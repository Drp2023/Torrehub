<?php
/**
 * RTCL listing → listing-card component arguments.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Are favourites switched on in Classified Listing? (Off on the current site → the design's heart is hidden.)
 */
function th_favourites_enabled(): bool {
	return class_exists( '\Rtcl\Helpers\Functions' ) && \Rtcl\Helpers\Functions::is_enable_favourite();
}

/**
 * Is a listing in the current user's favourites (RTCL core user meta `rtcl_favourites`)?
 *
 * @param int $listing_id Listing id.
 */
function th_is_favourite( int $listing_id ): bool {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$favs = get_user_meta( get_current_user_id(), 'rtcl_favourites', true );
	return is_array( $favs ) && in_array( $listing_id, array_map( 'intval', $favs ), true );
}

/**
 * Price of a listing split for display: amount ("€12,450", "€45 – €60", "Free") + suffix ("/ h", "negotiable").
 *
 * RTCL's get_price_html() glues unit and type labels onto the amount ("€24,550total price(Fixed)"), so the
 * amount is formatted here with RTCL's own price functions and the labels are added separately.
 *
 * @param object $listing Rtcl\Models\Listing.
 * @return array{amount:string,suffix:string}
 */
function th_listing_price_parts( $listing ): array {
	$out = array(
		'amount' => '',
		'suffix' => '',
	);
	if ( ! class_exists( '\Rtcl\Helpers\Functions' ) || ! method_exists( $listing, 'get_price' ) ) {
		return $out;
	}
	$text = static fn( $html ) => trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
	$type = method_exists( $listing, 'get_price_type' ) ? (string) $listing->get_price_type() : '';

	if ( 'on_call' === $type ) {
		$out['amount'] = __( 'Price on call', 'torrehub' );
		return $out;
	}

	$min = $listing->get_price();
	if ( '' === $min || null === $min ) {
		return $out;
	}
	$max = method_exists( $listing, 'get_max_price' ) ? $listing->get_max_price() : '';
	if ( 0.0 === (float) $min && ( '' === $max || 0.0 === (float) $max ) ) {
		$out['amount'] = __( 'Free', 'torrehub' );
		return $out;
	}
	if ( method_exists( $listing, 'get_pricing_type' ) && 'range' === $listing->get_pricing_type() && '' !== $max && (float) $max !== (float) $min ) {
		$out['amount'] = $text( \Rtcl\Helpers\Functions::format_price_range( $min, $max, array( 'listing' => $listing ) ) );
	} else {
		$out['amount'] = $text( \Rtcl\Helpers\Functions::price( $min, false, array( 'listing' => $listing ) ) );
	}

	$suffix = array();
	$unit   = method_exists( $listing, 'get_price_unit' ) ? (string) $listing->get_price_unit() : '';
	$units  = array(
		'hour'  => __( '/ h', 'torrehub' ),
		'day'   => __( '/ day', 'torrehub' ),
		'week'  => __( '/ week', 'torrehub' ),
		'month' => __( '/ month', 'torrehub' ),
		'year'  => __( '/ year', 'torrehub' ),
		'sqft'  => __( '/ sq ft', 'torrehub' ),
		'total' => '', // One-off total price: no "per" suffix.
	);
	if ( $unit ) {
		if ( array_key_exists( $unit, $units ) ) {
			$label = $units[ $unit ];
		} elseif ( class_exists( '\Rtcl\Resources\Options' ) ) {
			$all   = \Rtcl\Resources\Options::get_price_unit_list();
			$label = isset( $all[ $unit ]['short'] ) ? $text( $all[ $unit ]['short'] ) : '';
		} else {
			$label = '';
		}
		if ( '' !== $label ) {
			$suffix[] = $label;
		}
	}
	if ( 'negotiable' === $type ) {
		$suffix[] = __( 'negotiable', 'torrehub' );
	}
	$out['suffix'] = implode( ' · ', $suffix );
	return $out;
}

/**
 * Short attribute strings from Form Builder fields flagged "show on archive" (max $limit).
 *
 * @param object $listing Rtcl\Models\Listing.
 * @param int    $limit   Max attributes.
 * @return array<int,string>
 */
function th_listing_card_attrs( $listing, int $limit = 3 ): array {
	if ( ! method_exists( $listing, 'getForm' ) || ! class_exists( '\Rtcl\Services\FormBuilder\FBField' ) ) {
		return array();
	}
	$form = $listing->getForm();
	if ( ! $form ) {
		return array();
	}
	$out = array();
	foreach ( (array) $form->getFields() as $raw ) {
		if ( ! empty( $raw['preset'] ) ) {
			continue;
		}
		$field = new \Rtcl\Services\FormBuilder\FBField( $raw );
		if ( ! $field->isArchiveViewAble() ) {
			continue;
		}
		$value = $field->getFormattedCustomFieldValue( (int) $listing->get_id() );
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
		}
		$value = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' !== $value ) {
			$out[] = $value;
		}
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * Card arguments for a listing.
 *
 * @param int|WP_Post         $post      Listing post or id.
 * @param array<string,mixed> $overrides Arguments merged last (variant, eager, heading…).
 * @return array<string,mixed>|null Null when the listing can't be loaded.
 */
function th_listing_card_args( $post, array $overrides = array() ): ?array {
	if ( ! th_has_rtcl() ) {
		return null;
	}
	$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
	$listing = rtcl()->factory->get_listing( $post_id );
	if ( ! $listing ) {
		return null;
	}

	$author   = (int) get_post_field( 'post_author', $post_id );
	$featured = (bool) get_post_meta( $post_id, 'featured', true );
	$towns    = get_the_terms( $post_id, 'rtcl_location' );
	$town     = ( $towns && ! is_wp_error( $towns ) ) ? html_entity_decode( $towns[0]->name, ENT_QUOTES ) : '';
	$images   = class_exists( '\Rtcl\Helpers\Functions' ) ? \Rtcl\Helpers\Functions::get_listing_images( $post_id ) : array();
	$image_id = $images ? (int) $images[0]->ID : (int) get_post_thumbnail_id( $post_id );
	$verified = '1' === (string) get_user_meta( $author, 'rtcl_verified_seller', true );
	$price    = th_listing_price_parts( $listing );

	$badges = array();
	if ( $featured ) {
		$badges[] = array(
			'label'   => __( 'Featured', 'torrehub' ),
			'variant' => 'featured',
		);
	}

	$args = array(
		'variant'      => $featured ? 'featured' : 'default',
		'title'        => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES ),
		'url'          => (string) get_permalink( $post_id ),
		'image'        => $image_id,
		'price'        => $price['amount'],
		'price_suffix' => $price['suffix'],
		'badges'       => $badges,
		'attrs'        => th_listing_card_attrs( $listing ),
		'location'     => $town,
		/* translators: %s: human-readable time difference, e.g. "2 days" */
		'age'          => sprintf( __( '%s ago', 'torrehub' ), human_time_diff( (int) get_post_time( 'U', true, $post_id ), time() ) ),
		'verified'     => $verified,
		'rating'       => (float) get_post_meta( $post_id, '_rtcl_average_rating', true ),
		'rating_count' => (int) get_post_meta( $post_id, '_rtcl_review_count', true ),
		'photo_count'  => count( $images ),
		'fav'          => th_favourites_enabled(),
		'fav_pressed'  => th_is_favourite( $post_id ),
		'listing_id'   => $post_id,
	);

	/**
	 * Filter listing card arguments.
	 *
	 * @param array  $args    Card args.
	 * @param object $listing Rtcl\Models\Listing.
	 */
	$args = (array) apply_filters( 'th_listing_card_args', $args, $listing );

	return array_merge( $args, $overrides );
}
