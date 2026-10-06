<?php
/**
 * Which Form Builder fields are offered as archive filters, per category.
 *
 * Stored in the option `th_archive_filters`:
 *   [ 'groups' => [ category_slug => [ [ 'field' => 'select_x', 'label' => 'Fuel' ], … ] ], 'import' => summary ]
 * A group applies to its category and all descendants; the nearest configured ancestor wins. Without any
 * group, the fields flagged "filterable" in the category's form are used.
 *
 * The groups were imported once from the retired WPCode snippet "Filter Builder Active" (`lfb_filter_groups`,
 * see _dev/docs/WPCODE-AUDIT.md). The LFB option itself is never modified.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Archive;

defined( 'ABSPATH' ) || exit;

/**
 * Filter-group storage, LFB import and per-term resolution.
 */
final class FilterConfig {

	public const OPTION = 'th_archive_filters';

	/** Form Builder elements that can be filtered. */
	public const ELEMENTS = array( 'select', 'radio', 'checkbox', 'number', 'date', 'text' );

	/**
	 * Configured groups.
	 *
	 * @return array<string,array<int,array{field:string,label:string}>>
	 */
	public static function groups(): array {
		self::maybe_import();
		$opt = get_option( self::OPTION, array() );
		return is_array( $opt ) && isset( $opt['groups'] ) && is_array( $opt['groups'] ) ? $opt['groups'] : array();
	}

	/**
	 * Has the option been written (imported or saved) at least once?
	 */
	public static function exists(): bool {
		return is_array( get_option( self::OPTION, null ) );
	}

	/**
	 * Save groups (admin screen).
	 *
	 * @param array<string,array<int,array{field:string,label:string}>> $groups Groups.
	 */
	public static function save( array $groups ): void {
		$opt           = get_option( self::OPTION, array() );
		$opt           = is_array( $opt ) ? $opt : array();
		$opt['groups'] = $groups;
		update_option( self::OPTION, $opt, false );
	}

	/**
	 * Import summary of the one-time LFB import ([] when nothing was imported).
	 *
	 * @return array<string,mixed>
	 */
	public static function import_summary(): array {
		$opt = get_option( self::OPTION, array() );
		return is_array( $opt ) && isset( $opt['import'] ) ? (array) $opt['import'] : array();
	}

	/**
	 * First run: import the LFB groups when present, otherwise start empty (form "filterable" flags apply).
	 */
	public static function maybe_import(): void {
		if ( self::exists() ) {
			return;
		}
		$summary = self::import_lfb();
		update_option(
			self::OPTION,
			array(
				'groups' => $summary['groups'],
				'import' => array(
					'source'  => $summary['source'],
					'at'      => gmdate( 'c' ),
					'skipped' => $summary['skipped'],
				),
			),
			false
		);
	}

	/**
	 * Convert `lfb_filter_groups` into theme groups. Only fields that exist in the category's form are kept;
	 * price, location, category and "verified" entries map to the archive's common filters and are skipped.
	 *
	 * @return array{source:string,groups:array,skipped:array<int,string>}
	 */
	public static function import_lfb(): array {
		$lfb    = get_option( 'lfb_filter_groups', array() );
		$out    = array(
			'source'  => is_array( $lfb ) && $lfb ? 'lfb_filter_groups' : '',
			'groups'  => array(),
			'skipped' => array(),
		);
		$common = array( 'price', 'pricing', 'rtcl_location', 'address', 'rtcl_category', 'special_tax_category' );
		if ( ! is_array( $lfb ) ) {
			return $out;
		}

		foreach ( $lfb as $group ) {
			$slug = sanitize_title( (string) ( $group['category'] ?? '' ) );
			$term = $slug ? get_term_by( 'slug', $slug, 'rtcl_category' ) : false;
			if ( ! $term instanceof \WP_Term ) {
				$out['skipped'][] = sprintf( 'group "%s": unknown category', $slug );
				continue;
			}
			$filters = (array) ( $group['filters'] ?? array() );
			$types   = array_column( $filters, 'type' );
			if ( in_array( 'heading', $types, true ) ) {
				// Tabbed root group (Auto/Moto/Boats): its sub-categories carry their own groups; the root itself
				// keeps only the common filters (an empty group stops the "filterable flag" fallback).
				$out['groups'][ $slug ] = array();
				$out['skipped'][]       = sprintf( 'group "%s": tabbed group → common filters only; sub-categories use their own groups', $slug );
				continue;
			}
			$fields = self::form_fields_for_term( $term );
			$items  = array();
			foreach ( $filters as $f ) {
				$name = sanitize_key( (string) ( $f['uuid'] ?? '' ) );
				$type = (string) ( $f['type'] ?? '' );
				if ( in_array( $type, array( 'user_verified', 'user_online' ), true ) || in_array( $name, $common, true ) ) {
					continue;
				}
				if ( '' === $name || ! isset( $fields[ $name ] ) ) {
					$out['skipped'][] = sprintf( 'group "%s": "%s" (%s) is not a field of the category form', $slug, (string) ( $f['label'] ?? '' ), $name ? $name : 'no key' );
					continue;
				}
				$items[ $name ] = array(
					'field' => $name,
					'label' => sanitize_text_field( (string) ( $f['label'] ?? '' ) ),
				);
			}
			$out['groups'][ $slug ] = array_values( $items );
		}
		return $out;
	}

	/**
	 * The form attached to a category: its root's form from th_category_map() (filterable via `th_category_map`).
	 *
	 * @param \WP_Term $term Category.
	 */
	public static function form_id_for_term( \WP_Term $term ): int {
		$ancestors = get_ancestors( $term->term_id, 'rtcl_category', 'taxonomy' );
		$root      = $ancestors ? get_term( (int) end( $ancestors ), 'rtcl_category' ) : $term;
		$map       = th_category_map();
		return ( $root instanceof \WP_Term && isset( $map[ $root->slug ] ) ) ? (int) $map[ $root->slug ]['form_id'] : 0;
	}

	/**
	 * Filterable custom fields of a category's form, keyed by field name (meta key).
	 *
	 * @param \WP_Term $term Category.
	 * @return array<string,array<string,mixed>>
	 */
	public static function form_fields_for_term( \WP_Term $term ): array {
		$form_id = self::form_id_for_term( $term );
		return $form_id ? self::form_fields( $form_id ) : array();
	}

	/**
	 * Custom fields of a form that the archive can filter on.
	 *
	 * @param int $form_id RTCL form id.
	 * @return array<string,array<string,mixed>>
	 */
	public static function form_fields( int $form_id ): array {
		static $cache = array();
		if ( isset( $cache[ $form_id ] ) ) {
			return $cache[ $form_id ];
		}
		$cache[ $form_id ] = array();
		if ( ! class_exists( '\Rtcl\Models\Form\Form' ) ) {
			return array();
		}
		$form = \Rtcl\Models\Form\Form::query()->where( 'status', 'publish' )->find( $form_id );
		// `fields` is a magic property without __isset(), so empty( $form->fields ) is always true — read it first.
		$fields = $form ? (array) $form->fields : array();
		foreach ( $fields as $field ) {
			if ( ! empty( $field['preset'] ) || empty( $field['name'] ) || ! in_array( $field['element'] ?? '', self::ELEMENTS, true ) ) {
				continue;
			}
			$cache[ $form_id ][ (string) $field['name'] ] = $field;
		}
		return $cache[ $form_id ];
	}

	/**
	 * Resolve the filter fields for a category (nearest configured ancestor, else the form's filterable flags).
	 *
	 * @param \WP_Term|null $term Category or null (no category → no field filters).
	 * @return array<int,array{field:string,label:string,raw:array<string,mixed>}>
	 */
	public static function for_term( ?\WP_Term $term ): array {
		if ( ! $term ) {
			return array();
		}
		$fields = self::form_fields_for_term( $term );
		if ( ! $fields ) {
			return array();
		}
		$groups = self::groups();
		$chain  = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, 'rtcl_category', 'taxonomy' ) );
		$items  = null;
		foreach ( $chain as $term_id ) {
			$t = get_term( (int) $term_id, 'rtcl_category' );
			if ( $t instanceof \WP_Term && isset( $groups[ $t->slug ] ) ) {
				$items = $groups[ $t->slug ];
				break;
			}
		}
		if ( null === $items ) {
			$items = array();
			foreach ( $fields as $name => $raw ) {
				if ( ! empty( $raw['filterable'] ) ) {
					$items[] = array(
						'field' => $name,
						'label' => '',
					);
				}
			}
		}

		$out = array();
		foreach ( $items as $item ) {
			$name = (string) ( $item['field'] ?? '' );
			if ( isset( $fields[ $name ] ) ) {
				$out[] = array(
					'field' => $name,
					'label' => '' !== (string) ( $item['label'] ?? '' ) ? (string) $item['label'] : (string) ( $fields[ $name ]['label'] ?? $name ),
					'raw'   => $fields[ $name ],
				);
			}
		}

		/**
		 * Filter the archive filter fields of a category.
		 *
		 * @param array    $out  List of [ field, label, raw ].
		 * @param \WP_Term $term Category.
		 */
		return (array) apply_filters( 'th_archive_filter_fields', $out, $term );
	}
}
