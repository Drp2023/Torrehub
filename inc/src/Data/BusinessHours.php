<?php
/**
 * Business hours from Classified Listing's `_rtcl_bhs` meta, evaluated in Europe/Madrid time.
 *
 * Stored shape: [ 'active' => true, 'type' => 247 | 'selective', 'days' => [ 0..6 (0 = Sunday) => [ 'open' => bool,
 * 'times' => [ [ 'start' => 'H:i', 'end' => 'H:i' ], … ] ] ] ]. Missing day = closed. End before start = past midnight.
 *
 * DECISION (BUILD-PLAN v1 #9): the site timezone is UTC, but "Open now" must follow local time on the Costa Blanca,
 * so the theme evaluates hours in Europe/Madrid (filter `th_hours_timezone`). Special-day hours (`_rtcl_special_bhs`)
 * are not used: no listing has any.
 *
 * @package Torrehub
 */

namespace Torrehub\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Hours value object.
 */
final class BusinessHours {

	/**
	 * Parsed hours or null when the listing shows none.
	 *
	 * @param int $listing_id Listing id.
	 * @return array{always:bool,days:array<int,array<int,array{0:string,1:string}>>}|null
	 */
	public static function for_listing( int $listing_id ): ?array {
		$bhs = get_post_meta( $listing_id, '_rtcl_bhs', true );
		if ( ! is_array( $bhs ) || empty( $bhs['active'] ) ) {
			return null;
		}
		if ( 247 === (int) ( $bhs['type'] ?? 0 ) || '247' === (string) ( $bhs['type'] ?? '' ) ) {
			return array(
				'always' => true,
				'days'   => array(),
			);
		}
		$days = array();
		foreach ( (array) ( $bhs['days'] ?? array() ) as $day => $data ) {
			if ( empty( $data['open'] ) ) {
				continue;
			}
			foreach ( (array) ( $data['times'] ?? array() ) as $t ) {
				$start = self::time( (string) ( $t['start'] ?? '' ) );
				$end   = self::time( (string) ( $t['end'] ?? '' ) );
				if ( $start && $end ) {
					$days[ (int) $day ][] = array( $start, $end );
				}
			}
		}
		return $days ? array(
			'always' => false,
			'days'   => $days,
		) : null;
	}

	/**
	 * Normalise "9:00" / "09:00" → "09:00" ('' if invalid).
	 *
	 * @param string $t Time.
	 */
	private static function time( string $t ): string {
		return preg_match( '/^(\d{1,2}):(\d{2})/', trim( $t ), $m ) && (int) $m[1] < 25 && (int) $m[2] < 60 ? sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] ) : '';
	}

	/**
	 * Timezone the hours are read in.
	 */
	public static function timezone(): \DateTimeZone {
		return new \DateTimeZone( (string) apply_filters( 'th_hours_timezone', 'Europe/Madrid' ) );
	}

	/**
	 * Open right now? With the closing time of the current slot.
	 *
	 * @param array{always:bool,days:array} $hours Parsed hours.
	 * @param \DateTimeImmutable|null       $now   For tests.
	 * @return array{open:bool,until:string,next:string}
	 */
	public static function status( array $hours, ?\DateTimeImmutable $now = null ): array {
		if ( $hours['always'] ) {
			return array(
				'open'  => true,
				'until' => '',
				'next'  => '',
			);
		}
		$now  = ( $now ?? new \DateTimeImmutable( 'now' ) )->setTimezone( self::timezone() );
		$day  = (int) $now->format( 'w' );
		$hm   = $now->format( 'H:i' );
		$prev = ( $day + 6 ) % 7;

		foreach ( $hours['days'][ $day ] ?? array() as list( $start, $end ) ) {
			if ( $end > $start ? ( $hm >= $start && $hm < $end ) : ( $hm >= $start ) ) {
				return array(
					'open'  => true,
					'until' => $end,
					'next'  => '',
				);
			}
		}
		// A slot of yesterday that runs past midnight.
		foreach ( $hours['days'][ $prev ] ?? array() as list( $start, $end ) ) {
			if ( $end < $start && $hm < $end ) {
				return array(
					'open'  => true,
					'until' => $end,
					'next'  => '',
				);
			}
		}
		// Next opening today, else the first slot of the next open day.
		foreach ( $hours['days'][ $day ] ?? array() as list( $start ) ) {
			if ( $start > $hm ) {
				return array(
					'open'  => false,
					'until' => '',
					'next'  => $start,
				);
			}
		}
		return array(
			'open'  => false,
			'until' => '',
			'next'  => '',
		);
	}

	/**
	 * Week rows Monday → Sunday for display: [ day index, localized day name, "08:00 – 20:00" | "Closed", is today ].
	 *
	 * @param array{always:bool,days:array} $hours Parsed hours.
	 * @return array<int,array{day:int,name:string,text:string,today:bool}>
	 */
	public static function week( array $hours ): array {
		global $wp_locale;
		$today = (int) ( new \DateTimeImmutable( 'now', self::timezone() ) )->format( 'w' );
		$rows  = array();
		foreach ( array( 1, 2, 3, 4, 5, 6, 0 ) as $d ) {
			if ( $hours['always'] ) {
				$text = __( 'Open 24 hours', 'torrehub' );
			} elseif ( empty( $hours['days'][ $d ] ) ) {
				$text = __( 'Closed', 'torrehub' );
			} else {
				$text = implode( ', ', array_map( static fn( $s ) => $s[0] . ' – ' . $s[1], $hours['days'][ $d ] ) );
			}
			$rows[] = array(
				'day'   => $d,
				'name'  => $wp_locale ? $wp_locale->get_weekday( $d ) : (string) $d,
				'text'  => $text,
				'today' => $d === $today,
			);
		}
		return $rows;
	}
}
