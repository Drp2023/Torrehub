<?php
/**
 * A listing's Form Builder fields, ready to display.
 *
 * Classified Listing's FBField::getFormattedCustomFieldValue() returns stored *values* ("full_time",
 * "private_owner", ["yes"]); this maps them to the option *labels* and sorts every field into a display group:
 *
 *   details   label/value tiles (select, radio, number, date, short text)
 *   features  ✓ chips: single "Yes" checkboxes (by field label), multi-checkbox options, repeater rows
 *   links     url fields and uploaded files (PDF floor plan …)
 *   address   the first text field labelled address/location (shown in the header and on the map card)
 *   logo      a file field labelled logo (attachment id)
 *
 * DECISION: identity numbers are never shown publicly even though the forms mark them "show on single"
 * (NIF/NIE/DNI/CIF, VIN, licence plate). Tourist-licence numbers (VUT, RAICV) stay visible — Valencian rules
 * require them in rental adverts. Filter: `th_listing_private_field`.
 *
 * @package Torrehub
 */

namespace Torrehub\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Field formatter.
 */
final class ListingFields {

	/**
	 * Display groups for a listing (memoised per request).
	 *
	 * @param int $listing_id Listing id.
	 * @return array{details:array<int,array{name:string,label:string,value:string,tone:string}>,features:array<int,string>,links:array<int,array{label:string,url:string,kind:string}>,address:string,logo:int}
	 */
	public static function for_listing( int $listing_id ): array {
		static $memo = array();
		if ( isset( $memo[ $listing_id ] ) ) {
			return $memo[ $listing_id ];
		}
		$out                 = array(
			'details'  => array(),
			'features' => array(),
			'links'    => array(),
			'address'  => '',
			'logo'     => 0,
		);
		$memo[ $listing_id ] = $out;
		if ( ! th_has_rtcl() || ! class_exists( \Rtcl\Services\FormBuilder\FBField::class ) ) {
			return $out;
		}
		$listing = rtcl()->factory->get_listing( $listing_id );
		$form    = $listing ? $listing->getForm() : null;
		if ( ! $form ) {
			return $out;
		}
		$fields = \Rtcl\Services\FormBuilder\FBHelper::reOrderCustomField( $form->getFieldAsGroup( \Rtcl\Services\FormBuilder\FBField::CUSTOM ) );

		foreach ( $fields as $raw ) {
			$field = new \Rtcl\Services\FormBuilder\FBField( $raw );
			if ( ! $field->isSingleViewAble() || self::is_private( $raw ) ) {
				continue;
			}
			$value = $field->getFormattedCustomFieldValue( $listing_id );
			if ( self::is_empty( $value ) ) {
				continue;
			}
			$label   = trim( (string) $field->getLabel() );
			$element = (string) $field->getElement();

			switch ( $element ) {
				case 'checkbox':
					$labels = self::option_labels( $raw, (array) $value );
					if ( 1 === count( (array) ( $raw['options'] ?? array() ) ) ) {
						$out['features'][] = $label; // "Outdoor Seating ✓".
					} else {
						foreach ( $labels as $l ) {
							$out['features'][] = $l;
						}
					}
					break;

				case 'repeater':
					foreach ( (array) $value as $row ) {
						$text = trim( implode( ' · ', array_filter( array_map( static fn( $v ) => is_scalar( $v ) ? trim( (string) $v ) : '', (array) $row ) ) ) );
						if ( '' !== $text ) {
							$out['features'][] = $text;
						}
					}
					break;

				case 'url':
					$out['links'][] = array(
						'label' => $label,
						'url'   => esc_url_raw( (string) $value ),
						'kind'  => 'url',
					);
					break;

				case 'file':
					foreach ( (array) $value as $file ) {
						$id  = is_array( $file ) ? (int) ( $file['uid'] ?? 0 ) : 0;
						$url = is_array( $file ) ? (string) ( $file['url'] ?? '' ) : '';
						if ( ! $out['logo'] && preg_match( '/logo/i', $label ) && $id && wp_attachment_is_image( $id ) ) {
							$out['logo'] = $id;
							continue;
						}
						if ( preg_match( '/cover/i', $label ) ) {
							continue; // Cover images belong to the gallery, not to a download list.
						}
						if ( $url ) {
							$out['links'][] = array(
								'label' => $label,
								'url'   => esc_url_raw( $url ),
								'kind'  => 'file',
							);
						}
					}
					break;

				case 'textarea':
				case 'custom_html':
					break; // Long text lives in the description.

				default:
					$text = self::display_value( $raw, $value );
					if ( '' === $text ) {
						break;
					}
					if ( 'text' === $element && '' === $out['address'] && preg_match( '/address|location/i', $label ) && ! preg_match( '/pickup|online/i', $label ) ) {
						$out['address'] = $text;
						break;
					}
					$out['details'][] = array(
						'name'  => (string) $field->getName(),
						'label' => $label,
						'value' => $text,
						'tone'  => self::tone( $raw, $value ),
					);
			}
		}
		$out['features'] = array_values( array_unique( $out['features'] ) );

		/**
		 * Filter a listing's display fields.
		 *
		 * @param array $out        Groups.
		 * @param int   $listing_id Listing id.
		 */
		$memo[ $listing_id ] = (array) apply_filters( 'th_listing_fields', $out, $listing_id );
		return $memo[ $listing_id ];
	}

	/**
	 * One field's value as text ("Full time", "Yes", "12/07/2026 17:30").
	 *
	 * @param array<string,mixed> $raw   Field definition.
	 * @param mixed               $value Stored value.
	 */
	public static function display_value( array $raw, $value ): string {
		if ( is_array( $value ) ) {
			return implode( ', ', self::option_labels( $raw, $value ) );
		}
		$value = trim( (string) $value );
		if ( in_array( $raw['element'] ?? '', array( 'select', 'radio' ), true ) ) {
			$labels = self::option_labels( $raw, array( $value ) );
			return $labels[0] ?? $value;
		}
		return $value;
	}

	/**
	 * Map stored option values to their labels (unknown values are humanised: "full_time" → "Full time").
	 *
	 * @param array<string,mixed> $raw    Field definition.
	 * @param array<int,mixed>    $values Stored values.
	 * @return array<int,string>
	 */
	public static function option_labels( array $raw, array $values ): array {
		$map = array();
		foreach ( (array) ( $raw['options'] ?? array() ) as $opt ) {
			if ( is_array( $opt ) && isset( $opt['value'] ) ) {
				$map[ (string) $opt['value'] ] = trim( (string) ( $opt['label'] ?? $opt['value'] ) );
			}
		}
		$out = array();
		foreach ( $values as $v ) {
			if ( ! is_scalar( $v ) || '' === trim( (string) $v ) ) {
				continue;
			}
			$v     = (string) $v;
			$out[] = isset( $map[ $v ] ) && '' !== $map[ $v ] ? $map[ $v ] : self::humanise( $v );
		}
		return $out;
	}

	/**
	 * "full_time" → "Full time"; values with capitals or spaces are left alone.
	 *
	 * @param string $value Raw value.
	 */
	private static function humanise( string $value ): string {
		if ( preg_match( '/^[a-z0-9_]+$/', $value ) && str_contains( $value, '_' ) ) {
			return ucfirst( str_replace( '_', ' ', $value ) );
		}
		return $value;
	}

	/**
	 * Tile colour: green for "yes"-like values, grey otherwise (the category tile is blue, added by the template).
	 *
	 * @param array<string,mixed> $raw   Field definition.
	 * @param mixed               $value Stored value.
	 */
	private static function tone( array $raw, $value ): string {
		$v = strtolower( is_array( $value ) ? (string) reset( $value ) : (string) $value );
		return in_array( $v, array( 'yes', '247_available', 'free', 'new' ), true ) ? 'green' : 'grey';
	}

	/**
	 * Is the value empty (incl. arrays of empties)?
	 *
	 * @param mixed $value Value.
	 */
	private static function is_empty( $value ): bool {
		if ( is_array( $value ) ) {
			return ! array_filter( $value, static fn( $v ) => is_array( $v ) ? (bool) array_filter( $v ) : '' !== trim( (string) $v ) );
		}
		return '' === trim( (string) $value );
	}

	/**
	 * Identity numbers stay private (see class doc).
	 *
	 * @param array<string,mixed> $raw Field definition.
	 */
	public static function is_private( array $raw ): bool {
		$label   = (string) ( $raw['label'] ?? '' );
		$private = (bool) preg_match( '/\b(NIF|NIE|DNI|CIF|VIN|licen[cs]e\s*plate|matr[ií]cula)\b/i', $label );

		/**
		 * Filter whether a field is kept off the public listing page.
		 *
		 * @param bool  $private Private.
		 * @param array $raw     Field definition.
		 */
		return (bool) apply_filters( 'th_listing_private_field', $private, $raw );
	}
}
