<?php
/**
 * Form Builder field definitions → what the theme's listing form needs: support check, rules, options, values.
 *
 * The definitions come straight from Classified Listing's `rtcl_forms` table (Form::$fields); nothing is copied.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\ListingForm;

defined( 'ABSPATH' ) || exit;

/**
 * Static helpers for one field definition (array as stored by the Form Builder).
 */
final class Fields {

	/**
	 * Elements the workspace sets itself (category from the drill-in, listing type from the root category) or
	 * never shows (reCAPTCHA: not configured; view count: admin only).
	 */
	public const SKIP = array( 'category', 'listing_type', 'recaptcha', 'view_count' );

	/**
	 * Elements rendered by template-parts/listing-form/field.php.
	 */
	public const SUPPORTED = array(
		'title',
		'description',
		'excerpt',
		'text',
		'textarea',
		'number',
		'email',
		'url',
		'website',
		'phone',
		'whatsapp',
		'telegram',
		'zipcode',
		'address',
		'select',
		'radio',
		'checkbox',
		'date',
		'pricing',
		'location',
		'map',
		'images',
		'file',
		'social_profiles',
		'video_urls',
		'business_hours',
		'repeater',
		'terms_and_condition',
		'html',
	);

	/**
	 * Elements that take the full width of the two-column grid.
	 */
	private const WIDE = array( 'title', 'description', 'excerpt', 'textarea', 'address', 'checkbox', 'pricing', 'map', 'images', 'file', 'social_profiles', 'video_urls', 'business_hours', 'repeater', 'terms_and_condition', 'html' );

	/**
	 * Is the field shown on the front-end form?
	 *
	 * @param array<string,mixed> $field Field definition.
	 */
	public static function shown( array $field ): bool {
		$element = (string) ( $field['element'] ?? '' );
		return '' !== $element
			&& ! empty( $field['name'] )
			&& empty( $field['admin_use_only'] )
			&& ! in_array( $element, self::SKIP, true )
			&& in_array( $element, self::SUPPORTED, true );
	}

	/**
	 * Full-width field?
	 *
	 * @param array<string,mixed> $field Field definition.
	 */
	public static function wide( array $field ): bool {
		if ( in_array( (string) $field['element'], self::WIDE, true ) ) {
			return true;
		}
		return 'radio' === $field['element'] && count( self::options( $field ) ) > 3;
	}

	/**
	 * DOM id for the field's main control.
	 *
	 * @param array<string,mixed> $field Field definition.
	 */
	public static function dom_id( array $field ): string {
		return 'th-lf-' . sanitize_html_class( (string) $field['uuid'] );
	}

	/**
	 * Truthy Form Builder flag ("true", 1, true …).
	 *
	 * @param mixed $value Stored flag.
	 */
	public static function flag( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'yes' === $value;
	}

	/**
	 * Required?
	 *
	 * @param array<string,mixed> $field Field definition.
	 */
	public static function required( array $field ): bool {
		return self::flag( $field['validation']['required']['value'] ?? false );
	}

	/**
	 * Conditional logic, normalised; null when off.
	 *
	 * @param mixed $logics Stored logics.
	 * @return array{relation:string,conditions:array<int,array{fieldId:string,operator:string,value:string}>}|null
	 */
	public static function logics( $logics ): ?array {
		if ( ! is_array( $logics ) || ! self::flag( $logics['status'] ?? false ) || empty( $logics['conditions'] ) ) {
			return null;
		}
		$conditions = array();
		foreach ( (array) $logics['conditions'] as $c ) {
			if ( ! empty( $c['fieldId'] ) && ! empty( $c['operator'] ) ) {
				$conditions[] = array(
					'fieldId'  => (string) $c['fieldId'],
					'operator' => (string) $c['operator'],
					'value'    => is_scalar( $c['value'] ?? '' ) ? (string) ( $c['value'] ?? '' ) : '',
				);
			}
		}
		if ( ! $conditions ) {
			return null;
		}
		return array(
			'relation'   => 'or' === strtolower( (string) ( $logics['relation'] ?? 'and' ) ) ? 'or' : 'and',
			'conditions' => $conditions,
		);
	}

	/**
	 * Select / radio / checkbox options: value => label.
	 *
	 * @param array<string,mixed> $field Field definition.
	 * @return array<string,string>
	 */
	public static function options( array $field ): array {
		$out = array();
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			if ( is_array( $option ) && isset( $option['value'] ) && '' !== (string) $option['value'] ) {
				$out[ (string) $option['value'] ] = html_entity_decode( (string) ( $option['label'] ?? $option['value'] ), ENT_QUOTES );
			}
		}
		return $out;
	}

	/**
	 * Current value for a field.
	 *
	 * @param array<string,mixed> $values Form values keyed by field name.
	 * @param array<string,mixed> $field  Field definition.
	 * @return mixed
	 */
	public static function value( array $values, array $field ) {
		$name = (string) $field['name'];
		if ( array_key_exists( $name, $values ) && null !== $values[ $name ] ) {
			return $values[ $name ];
		}
		return $field['default_value'] ?? '';
	}

	/**
	 * Client-side rules (mirror of FBHelper::isValidateField; the server validates again on save).
	 *
	 * @param array<string,mixed> $field Field definition.
	 * @return array<string,array{value:mixed,message:string}>
	 */
	public static function rules( array $field ): array {
		$out = array();
		foreach ( (array) ( $field['validation'] ?? array() ) as $key => $rule ) {
			if ( ! is_array( $rule ) || ! in_array( $key, array( 'required', 'min', 'max', 'email', 'url', 'regex', 'numeric' ), true ) ) {
				continue;
			}
			$value = $rule['value'] ?? false;
			if ( false === $value || 'false' === $value || '' === $value || null === $value ) {
				continue;
			}
			$message     = (string) ( $rule['message'] ?? '' );
			$out[ $key ] = array(
				'value'   => is_numeric( $value ) ? $value + 0 : $value,
				'message' => $message ? str_replace( '{value}', is_scalar( $value ) ? (string) $value : '', $message ) : '',
			);
		}
		return $out;
	}

	/**
	 * Field ids used by section-level conditions (e.g. "Item type" switches Car / Motorcycle / Boat).
	 * Selects among them render as choice cards.
	 *
	 * @param array<int,array<string,mixed>> $sections Form sections.
	 * @return array<int,string>
	 */
	public static function section_switches( array $sections ): array {
		$ids = array();
		foreach ( $sections as $section ) {
			$logics = self::logics( $section['logics'] ?? null );
			foreach ( $logics['conditions'] ?? array() as $c ) {
				$ids[] = $c['fieldId'];
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Icon for a choice card option (item types).
	 *
	 * @param string $value Option value.
	 */
	public static function choice_icon( string $value ): string {
		$value = strtolower( $value );
		$map   = array(
			'car'   => 'cat-auto-moto-boats',
			'moto'  => 'motorbike',
			'bike'  => 'motorbike',
			'boat'  => 'boat',
			'yacht' => 'boat',
		);
		foreach ( $map as $needle => $icon ) {
			if ( str_contains( $value, $needle ) ) {
				return $icon;
			}
		}
		return 'check';
	}

	/**
	 * Term id from a stored taxonomy value (WP_Term, ['term_id' => …] or a plain id).
	 *
	 * @param mixed $item Value item.
	 */
	public static function term_id( $item ): int {
		if ( $item instanceof \WP_Term ) {
			return (int) $item->term_id;
		}
		if ( is_array( $item ) ) {
			return absint( $item['term_id'] ?? 0 );
		}
		return absint( $item );
	}

	/**
	 * Date field: PHP format → native input type (`date` or `datetime-local`).
	 *
	 * @param array<string,mixed> $field Field definition.
	 */
	public static function date_input_type( array $field ): string {
		$format = (string) ( $field['date_format'] ?? 'Y-m-d' );
		return ( str_contains( $format, 'H' ) || str_contains( $format, 'h' ) || str_contains( $format, 'G' ) || str_contains( $format, 'g' ) ) ? 'datetime-local' : 'date';
	}

	/**
	 * Stored/posted date string (in the field's format) → native input value.
	 *
	 * @param array<string,mixed> $field Field definition.
	 * @param mixed               $value Value in the field's date format.
	 */
	public static function date_native( array $field, $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		$format = (string) ( $field['date_format'] ?? 'Y-m-d' );
		$date   = \DateTime::createFromFormat( $format, $value );
		if ( ! $date ) {
			$time = strtotime( $value );
			$date = $time ? ( new \DateTime() )->setTimestamp( $time ) : null;
		}
		if ( ! $date ) {
			return '';
		}
		return 'datetime-local' === self::date_input_type( $field ) ? $date->format( 'Y-m-d\TH:i' ) : $date->format( 'Y-m-d' );
	}

	/**
	 * Description from the WP editor (HTML) → plain text for the textarea. Paragraphs and line breaks survive;
	 * the listing page adds paragraphs back (wpautop).
	 *
	 * @param mixed $html Stored content.
	 */
	public static function plain_text( $html ): string {
		$text = (string) $html;
		if ( '' === $text || ! str_contains( $text, '<' ) ) {
			return html_entity_decode( $text, ENT_QUOTES );
		}
		$text = preg_replace( '#<br\s*/?>#i', "\n", $text );
		$text = preg_replace( '#</(p|div|h[1-6]|li|ul|ol|blockquote)>#i', "\n\n", (string) $text );
		$text = preg_replace( '#<li[^>]*>#i', '• ', (string) $text );
		$text = wp_strip_all_tags( (string) $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		return trim( html_entity_decode( (string) $text, ENT_QUOTES ) );
	}
}
