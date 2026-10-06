<?php
/**
 * WP-CLI commands shipped with the theme (loaded only under WP-CLI).
 *
 * Commands: wp torrehub fix-option-values · purge-nie · purge-old-verification-docs · import-chat (each with [--apply])
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

/**
 * Import Classified Listing Pro's chat (`{prefix}rtcl_conversations` + `rtcl_conversation_messages`) into the
 * theme's chat tables. Run once at go-live while the Pro tables still exist (runbook item). Conversations whose
 * listing + buyer already have a theme thread are skipped, so a second run imports nothing twice. Text messages only
 * (attachments / system messages are skipped). Pro stored local server time; it is taken as GMT. Dry run without
 * --apply.
 *
 * ## OPTIONS
 *
 * [--apply]
 * : Write the threads and messages (default: report only).
 *
 * @param array<int,string>    $args       Positional args.
 * @param array<string,string> $assoc_args Flags.
 */
WP_CLI::add_command(
	'torrehub import-chat',
	static function ( $args, $assoc_args ) {
		global $wpdb;
		$apply = ! empty( $assoc_args['apply'] );
		$cons  = $wpdb->prefix . 'rtcl_conversations';
		$msgs  = $wpdb->prefix . 'rtcl_conversation_messages';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- CLI migration; table names are fixed.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cons ) ) !== $cons ) {
			WP_CLI::success( 'No Classified Listing Pro chat tables — nothing to import.' );
			return;
		}
		$tables = Torrehub\Modules\Chat\Store::tables();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables['threads'] ) ) !== $tables['threads'] ) {
			WP_CLI::error( 'The theme chat tables are missing: open wp-admin once (installer) or enable the Chat module.' );
		}
		$rows     = $wpdb->get_results( "SELECT * FROM {$cons} ORDER BY con_id ASC" );
		$threads  = 0;
		$messages = 0;
		$skipped  = 0;
		foreach ( $rows as $con ) {
			$buyer  = (int) $con->sender_id;
			$seller = (int) $con->recipient_id;
			if ( ! get_post( (int) $con->listing_id ) || ! get_userdata( $buyer ) || ! get_userdata( $seller ) || Torrehub\Modules\Chat\Store::find( (int) $con->listing_id, $buyer ) ) {
				++$skipped;
				continue;
			}
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$msgs} WHERE con_id = %d AND type = 'text' ORDER BY message_id ASC", (int) $con->con_id ) );
			++$threads;
			$messages += count( $items );
			if ( ! $apply ) {
				continue;
			}
			$wpdb->insert(
				$tables['threads'],
				array(
					'listing_id'     => (int) $con->listing_id,
					'buyer_id'       => $buyer,
					'seller_id'      => $seller,
					'created_at'     => (string) $con->created_at,
					'updated_at'     => (string) $con->updated_at,
					'buyer_deleted'  => (int) $con->sender_delete,
					'seller_deleted' => (int) $con->recipient_delete,
				)
			);
			$thread_id = (int) $wpdb->insert_id;
			$last      = 0;
			$read      = array(
				'buyer'  => 0,
				'seller' => 0,
			);
			$unread    = array(
				'buyer'  => 0,
				'seller' => 0,
			);
			foreach ( $items as $item ) {
				$wpdb->insert(
					$tables['messages'],
					array(
						'thread_id'  => $thread_id,
						'sender_id'  => (int) $item->source_id,
						'body'       => Torrehub\Modules\Chat\Store::clean( (string) $item->message ),
						'created_at' => (string) $item->created_at,
					)
				);
				$last          = (int) $wpdb->insert_id;
				$to            = (int) $item->source_id === $buyer ? 'seller' : 'buyer';
				$from          = 'seller' === $to ? 'buyer' : 'seller';
				$read[ $from ] = $last; // A sender has read everything up to their own message.
				if ( (int) $item->is_read ) {
					$read[ $to ] = $last;
				} else {
					++$unread[ $to ];
				}
			}
			$wpdb->update(
				$tables['threads'],
				array(
					'last_message_id' => $last,
					'buyer_read_id'   => $read['buyer'],
					'seller_read_id'  => $read['seller'],
					'buyer_unread'    => $unread['buyer'],
					'seller_unread'   => $unread['seller'],
				),
				array( 'id' => $thread_id )
			);
		}
		// phpcs:enable
		$verb = $apply ? 'Imported' : 'Would import';
		WP_CLI::success( "{$verb} {$threads} conversations with {$messages} messages ({$skipped} skipped: already imported, or the listing/user is gone)." );
	}
);
