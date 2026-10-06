<?php
/**
 * WP-CLI commands shipped with the theme (loaded only under WP-CLI).
 *
 * Commands: wp torrehub fix-option-values [--apply]
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
