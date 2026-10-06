<?php
/**
 * WP-CLI commands shipped with the theme (loaded only under WP-CLI).
 *
 * Commands: wp torrehub fix-option-values · purge-nie · purge-old-verification-docs (each with [--apply])
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Data fix: Form Builder choice options whose *value* is empty (e.g. "€" in Restaurants → Price Range and
 * Leisure → Price) can't be saved or filtered. Each empty value gets its label as value — the same convention as
 * the options that kept theirs (stored listings use "€€", "€€€"). Idempotent; dry run without --apply.
 * Go-live runbook item (BUILD-PLAN §15). Touches only `{prefix}rtcl_forms.fields`.
 *
 * ## OPTIONS
 *
 * [--apply]
 * : Write the changes (default: report only).
 *
 * @param array<int,string>    $args       Positional args.
 * @param array<string,string> $assoc_args Flags.
 */
WP_CLI::add_command(
	'torrehub fix-option-values',
	static function ( $args, $assoc_args ) {
		global $wpdb;
		$apply = ! empty( $assoc_args['apply'] );
		$table = $wpdb->prefix . 'rtcl_forms';
		$rows  = $wpdb->get_results( "SELECT id, title, fields FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- CLI maintenance script.
		$total = 0;

		foreach ( $rows as $row ) {
			$fields = json_decode( (string) $row->fields, true );
			if ( ! is_array( $fields ) ) {
				continue;
			}
			$changed = false;
			foreach ( $fields as $uuid => $field ) {
				if ( empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
					continue;
				}
				$used = array();
				foreach ( $field['options'] as $opt ) {
					if ( is_array( $opt ) && '' !== trim( (string) ( $opt['value'] ?? '' ) ) ) {
						$used[] = (string) $opt['value'];
					}
				}
				foreach ( $field['options'] as $i => $opt ) {
					if ( ! is_array( $opt ) || '' !== trim( (string) ( $opt['value'] ?? '' ) ) ) {
						continue;
					}
					$label = trim( (string) ( $opt['label'] ?? '' ) );
					if ( '' === $label ) {
						WP_CLI::warning( sprintf( 'form %d "%s" field %s: option %d has neither value nor label — left alone', $row->id, $row->title, $field['name'] ?? $uuid, $i ) );
						continue;
					}
					$value = $label;
					$n     = 2;
					while ( in_array( $value, $used, true ) ) {
						$value = $label . '-' . $n;
						++$n;
					}
					$used[] = $value;
					WP_CLI::log( sprintf( 'form %d "%s" · %s (%s): option "%s" → value "%s"', $row->id, $row->title, $field['label'] ?? '', $field['name'] ?? $uuid, $label, $value ) );
					$fields[ $uuid ]['options'][ $i ]['value'] = $value;
					$changed                                   = true;
					++$total;
				}
			}
			if ( $changed && $apply ) {
				$wpdb->update( $table, array( 'fields' => wp_json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), array( 'id' => (int) $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}

		if ( $apply && $total ) {
			// Theme caches that list form options (archive filters).
			if ( class_exists( 'Torrehub\Data\Directory' ) ) {
				Torrehub\Data\Directory::flush();
			}
			WP_CLI::success( sprintf( '%d option value(s) fixed.', $total ) );
		} elseif ( $total ) {
			WP_CLI::log( sprintf( '%d option value(s) would be fixed. Run again with --apply.', $total ) );
		} else {
			WP_CLI::success( 'No empty option values.' );
		}
	}
);

/**
 * GDPR (client decision 2026-10-06): private sellers' NIE numbers are not kept. Deletes every stored NIE —
 * user meta `custom_field_1` (old registration form) and `nif_nie` (WPCode snippet 7263, mixed NIE/NIF field).
 * Business NIFs (`custom_field_2`) stay. Dry run without --apply. Go-live runbook item.
 *
 * ## OPTIONS
 *
 * [--apply]
 * : Delete (default: count only).
 *
 * @param array<int,string>    $args       Positional args.
 * @param array<string,string> $assoc_args Flags.
 */
WP_CLI::add_command(
	'torrehub purge-nie',
	static function ( $args, $assoc_args ) {
		global $wpdb;
		$keys  = array( 'custom_field_1', 'nif_nie' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key IN ('custom_field_1','nif_nie')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- CLI maintenance.
		if ( empty( $assoc_args['apply'] ) ) {
			WP_CLI::log( sprintf( '%d NIE meta row(s) (%s) would be deleted. Run again with --apply.', $count, implode( ', ', $keys ) ) );
			return;
		}
		foreach ( $keys as $key ) {
			delete_metadata( 'user', 0, $key, '', true );
		}
		WP_CLI::success( sprintf( '%d NIE meta row(s) deleted.', $count ) );
	}
);

/**
 * GDPR: the old rtcl-seller-verification plugin stored ID documents as public Media Library attachments
 * (user meta `photo_id`, `other_document_id`). Already-verified sellers keep their badge (`rtcl_verified_seller`);
 * the documents and references are deleted. Dry run without --apply. Go-live runbook item, after the plugin is gone.
 *
 * ## OPTIONS
 *
 * [--apply]
 * : Delete (default: count only).
 *
 * @param array<int,string>    $args       Positional args.
 * @param array<string,string> $assoc_args Flags.
 */
WP_CLI::add_command(
	'torrehub purge-old-verification-docs',
	static function ( $args, $assoc_args ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- CLI maintenance.
		$ids = array_unique( array_map( 'intval', $wpdb->get_col( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('photo_id','other_document_id') AND meta_value REGEXP '^[0-9]+$'" ) ) );
		$ids = array_filter( $ids, static fn( $id ) => $id && 'attachment' === get_post_type( $id ) );
		if ( empty( $assoc_args['apply'] ) ) {
			WP_CLI::log( sprintf( '%d document attachment(s) would be deleted. Run again with --apply.', count( $ids ) ) );
			return;
		}
		foreach ( $ids as $id ) {
			wp_delete_attachment( $id, true );
		}
		delete_metadata( 'user', 0, 'photo_id', '', true );
		delete_metadata( 'user', 0, 'other_document_id', '', true );
		WP_CLI::success( sprintf( '%d document attachment(s) deleted; references removed.', count( $ids ) ) );
	}
);
