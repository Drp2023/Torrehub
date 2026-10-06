<?php
/**
 * JSON-LD for a listing (replaces review-schema-pro): the schema type follows the root category.
 *
 * Mapping:
 *   properties, auto-moto-boats, marketplace              → Product + Offer
 *   services, restaurants-nightlife, leisure-sport,
 *   public-information                                    → LocalBusiness (Restaurant for restaurants)
 *   tourist-attractions                                   → TouristAttraction
 *   events                                                → Event (only with a start date)
 *   jobs                                                  → none (JobPosting needs data the form lacks)
 *
 * AggregateRating is added when there are approved reviews. BreadcrumbList only when no SEO plugin prints one.
 * Filter: `th_listing_schema`.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Listing;

use Torrehub\Data\ListingFields;

defined( 'ABSPATH' ) || exit;

/**
 * Listing structured data.
 */
final class Schema {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'print' ), 30 );
	}

	/**
	 * Print the JSON-LD graph.
	 */
	public function print(): void {
		if ( ! is_singular( 'rtcl_listing' ) || 'publish' !== get_post_status() ) {
			return;
		}
		$graph = self::graph( new View( get_queried_object() ) );
		if ( $graph ) {
			printf(
				'<script type="application/ld+json">%s</script>' . PHP_EOL,
				wp_json_encode(
					array(
						'@context' => 'https://schema.org',
						'@graph'   => $graph,
					),
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
				)
			);
		}
	}

	/**
	 * Build the graph.
	 *
	 * @param View $v Listing view.
	 * @return array<int,array<string,mixed>>
	 */
	public static function graph( View $v ): array {
		$root  = $v->root ? $v->root->slug : '';
		$url   = (string) get_permalink( $v->id );
		$image = array_values( array_filter( array_map( static fn( $id ) => wp_get_attachment_image_url( $id, 'full' ), array_slice( $v->images, 0, 5 ) ) ) );
		$desc  = wp_html_excerpt( wp_strip_all_tags( (string) $v->post->post_content ), 300, '…' );
		$town  = $v->town ? html_entity_decode( $v->town->name, ENT_QUOTES ) : '';

		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $v->fields['address'],
				'addressLocality' => $town,
				'addressRegion'   => 'Alicante',
				'addressCountry'  => 'ES',
			)
		);

		$item = array(
			'@id'         => $url . '#listing',
			'name'        => $v->title,
			'url'         => $url,
			'description' => $desc,
			'image'       => $image,
		);

		switch ( $root ) {
			case 'properties':
			case 'auto-moto-boats':
			case 'marketplace':
				$item['@type'] = 'Product';
				$amount        = (float) get_post_meta( $v->id, 'price', true );
				if ( $amount > 0 ) {
					$item['offers'] = array(
						'@type'         => 'Offer',
						'price'         => $amount,
						'priceCurrency' => 'EUR',
						'availability'  => 'https://schema.org/InStock',
						'url'           => $url,
					);
				}
				break;
			case 'tourist-attractions':
				$item['@type']   = 'TouristAttraction';
				$item['address'] = $address;
				break;
			case 'events':
				$start = self::event_date( $v->id, 'start' );
				if ( ! $start ) {
					return self::breadcrumb( $v );
				}
				$item['@type']               = 'Event';
				$item['startDate']           = $start;
				$item['endDate']             = self::event_date( $v->id, 'end' );
				$item['eventStatus']         = 'https://schema.org/EventScheduled';
				$item['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
				$item['location']            = array_filter(
					array(
						'@type'   => 'Place',
						'name'    => $town,
						'address' => $address,
					)
				);
				break;
			case 'jobs':
				return self::breadcrumb( $v );
			default:
				$item['@type']   = 'restaurants-nightlife' === $root ? 'Restaurant' : 'LocalBusiness';
				$item['address'] = $address;
				$phone           = trim( (string) get_post_meta( $v->id, 'phone', true ) );
				if ( $phone ) {
					$item['telephone'] = $phone;
				}
				if ( $v->point && ! $v->point['approx'] ) {
					$item['geo'] = array(
						'@type'     => 'GeoCoordinates',
						'latitude'  => $v->point['lat'],
						'longitude' => $v->point['lng'],
					);
				}
		}

		if ( $v->reviews['count'] > 0 && 'Event' !== $item['@type'] ) {
			$item['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $v->reviews['average'],
				'reviewCount' => $v->reviews['count'],
				'bestRating'  => 5,
				'worstRating' => 1,
			);
		}

		/**
		 * Filter the listing's main schema item.
		 *
		 * @param array $item Item.
		 * @param View  $v    View model.
		 */
		$item = (array) apply_filters( 'th_listing_schema', array_filter( $item ), $v );
		return array_merge( array( $item ), self::breadcrumb( $v ) );
	}

	/**
	 * BreadcrumbList (skipped when an SEO plugin prints breadcrumbs).
	 *
	 * @param View $v Listing view.
	 * @return array<int,array<string,mixed>>
	 */
	private static function breadcrumb( View $v ): array {
		if ( th_seo_plugin_active() ) {
			return array();
		}
		$items = array();
		foreach ( $v->breadcrumb() as $i => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $crumb['label'],
				'item'     => $crumb['url'] ? $crumb['url'] : (string) get_permalink( $v->id ),
			);
		}
		return array(
			array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			),
		);
	}

	/**
	 * Event start/end as ISO 8601 (Europe/Madrid) from the Events form date fields.
	 *
	 * @param int    $listing_id Listing id.
	 * @param string $which      start|end.
	 */
	private static function event_date( int $listing_id, string $which ): string {
		foreach ( (array) get_post_meta( $listing_id ) as $key => $values ) {
			if ( ! str_starts_with( (string) $key, 'date_' ) ) {
				continue;
			}
			$label = '';
			$form  = rtcl()->factory->get_listing( $listing_id )?->getForm();
			foreach ( $form ? (array) $form->fields : array() as $f ) {
				if ( ( $f['name'] ?? '' ) === $key ) {
					$label = (string) ( $f['label'] ?? '' );
				}
			}
			if ( ! preg_match( 'start' === $which ? '/start/i' : '/end/i', $label ) || ListingFields::is_private( array( 'label' => $label ) ) ) {
				continue;
			}
			$raw = (string) ( $values[0] ?? '' );
			$dt  = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $raw, new \DateTimeZone( 'Europe/Madrid' ) );
			$dt  = $dt ? $dt : \DateTimeImmutable::createFromFormat( 'Y-m-d', $raw, new \DateTimeZone( 'Europe/Madrid' ) );
			if ( $dt ) {
				return $dt->format( 'c' );
			}
		}
		return '';
	}
}
