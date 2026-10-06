<?php
/**
 * Map positions for listings.
 *
 * DECISION: a listing's own coordinates (RTCL `latitude` / `longitude` meta, filled when the seller sets the map
 * pin) are used when present. Otherwise — today: every listing — the pin sits near its town centre, spread on a
 * small deterministic spiral (≈ 300–900 m) so listings of one town don't stack. Such pins are flagged
 * `approx` and the map says "Pins show the town, not the exact address".
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Archive;

defined( 'ABSPATH' ) || exit;

/**
 * Listing → map point.
 */
final class MapData {

	/**
	 * Point for one listing, or null when neither coordinates nor a known town exist.
	 *
	 * @param int $post_id Listing id.
	 * @return array{lat:float,lng:float,approx:bool}|null
	 */
	public static function point( int $post_id ): ?array {
		$lat = get_post_meta( $post_id, 'latitude', true );
		$lng = get_post_meta( $post_id, 'longitude', true );
		if ( is_numeric( $lat ) && is_numeric( $lng ) && ( 0.0 !== (float) $lat || 0.0 !== (float) $lng ) ) {
			return array(
				'lat'    => (float) $lat,
				'lng'    => (float) $lng,
				'approx' => false,
			);
		}
		$towns = get_the_terms( $post_id, 'rtcl_location' );
		if ( ! $towns || is_wp_error( $towns ) ) {
			return null;
		}
		$coords = th_town_coordinates();
		$slug   = $towns[0]->slug;
		if ( ! isset( $coords[ $slug ] ) ) {
			return null;
		}
		// Golden-angle spiral keyed on the post id: stable between requests, evenly spread.
		$angle = deg2rad( fmod( $post_id * 137.508, 360 ) );
		$r     = 0.003 + 0.006 * fmod( $post_id * 0.618034, 1 );
		return array(
			'lat'    => round( $coords[ $slug ][0] + $r * sin( $angle ), 5 ),
			'lng'    => round( $coords[ $slug ][1] + $r * cos( $angle ) * 1.27, 5 ), // ≈ 1/cos(38°): keep the spread round.
			'approx' => true,
		);
	}

	/**
	 * Pins for a list of card args (id, position, short price label).
	 *
	 * @param array<int,array<string,mixed>> $cards Card args (from th_listing_card_args()).
	 * @return array<int,array<string,mixed>>
	 */
	public static function pins( array $cards ): array {
		$pins = array();
		foreach ( $cards as $card ) {
			$point = self::point( (int) $card['listing_id'] );
			if ( ! $point ) {
				continue;
			}
			$pins[] = array(
				'id'       => (int) $card['listing_id'],
				'lat'      => $point['lat'],
				'lng'      => $point['lng'],
				'approx'   => $point['approx'],
				'label'    => self::short_price( (string) $card['price'] ),
				'featured' => 'featured' === ( $card['variant'] ?? '' ),
				'title'    => (string) $card['title'],
			);
		}
		return $pins;
	}

	/**
	 * Compact pin label: "€24,550" → "€24.6k", "Free" stays, empty → "•".
	 *
	 * @param string $price Formatted price.
	 */
	public static function short_price( string $price ): string {
		$price = trim( html_entity_decode( wp_strip_all_tags( $price ), ENT_QUOTES ) );
		if ( '' === $price ) {
			return '•';
		}
		if ( preg_match( '/^([^\d]*)([\d.,\s]+)(.*)$/u', $price, $m ) ) {
			$num = (float) preg_replace( '/[^\d]/', '', (string) preg_replace( '/[.,]\d{1,2}$/', '', trim( $m[2] ) ) ); // Drop cents first.
			if ( $num >= 1000000 ) {
				return $m[1] . rtrim( rtrim( number_format( $num / 1000000, 1 ), '0' ), '.' ) . 'M';
			}
			if ( $num >= 10000 ) {
				return $m[1] . round( $num / 1000 ) . 'k';
			}
		}
		return mb_strlen( $price ) > 10 ? mb_substr( $price, 0, 9 ) . '…' : $price;
	}
}
