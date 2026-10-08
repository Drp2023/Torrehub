<?php
/**
 * Go-live migration steps (GO-LIVE-RUNBOOK.md, sections C and D). Each step runs as a dry run (report only) or
 * applied; the same code serves `wp torrehub <command> [--apply]` and Tools › Torrehub migration, so a host without
 * SSH can follow the runbook from wp-admin. Every step is idempotent.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Step registry and implementations.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off migrations on fixed table names.
 */
final class Steps {

	/**
	 * Steps in runbook order: key => definition.
	 *
	 * @return array<string,array{code:string,title:string,help:string,destructive:bool,rollback:bool,run:callable,confirm?:string}>
	 */
	public static function all(): array {
		return array(
			'fix-option-values'           => array(
				'code'        => 'C1',
				'title'       => __( 'Form Builder: empty option values', 'torrehub' ),
				'help'        => __( 'Options without a stored value (e.g. “€” in Restaurants → Price Range) get their label as value, so they can be saved and filtered. Touches only the Form Builder forms.', 'torrehub' ),
				'destructive' => false,
				'rollback'    => false,
				'run'         => array( self::class, 'fix_option_values' ),
			),
			'migrate-pages'               => array(
				'code'        => 'C2',
				'title'       => __( 'Content pages: Elementor → blocks', 'torrehub' ),
				'help'        => __( 'About, Contact, FAQ, Privacy, Terms and Legal notice move to the block editor and the theme’s page templates (from the HTML copy Elementor keeps of each page). The originals are kept (post meta + revision); “Roll back” restores them. Run before Elementor is removed.', 'torrehub' ),
				'destructive' => false,
				'rollback'    => true,
				'run'         => array( self::class, 'migrate_pages' ),
			),
			'import-chat'                 => array(
				'code'        => 'C3',
				'title'       => __( 'Messages: import the old chat', 'torrehub' ),
				'help'        => __( 'Copies Classified Listing Pro’s conversations (text messages) into the theme’s chat. Already imported conversations are skipped. Run while the old chat tables still exist.', 'torrehub' ),
				'destructive' => false,
				'rollback'    => false,
				'run'         => array( self::class, 'import_chat' ),
			),
			'import-search-alerts'        => array(
				'code'        => 'C4',
				'title'       => __( 'Saved searches: import the old alerts', 'torrehub' ),
				'help'        => __( 'Copies the rtcl-search-alert add-on’s saved searches (keyword, category, town). Already imported ones are skipped.', 'torrehub' ),
				'destructive' => false,
				'rollback'    => false,
				'run'         => array( self::class, 'import_search_alerts' ),
			),
			'trash-demo'                  => array(
				'code'        => 'C5',
				'title'       => __( 'Demo posts and pages to the trash', 'torrehub' ),
				'help'        => __( 'The old theme’s 8 demo posts and 11 demo pages go to the trash (restorable). The front page and the posts page are never touched.', 'torrehub' ),
				'destructive' => false,
				'rollback'    => false,
				'run'         => array( self::class, 'trash_demo' ),
			),
			'purge-nie'                   => array(
				'code'        => 'C6',
				'title'       => __( 'GDPR: delete stored NIE numbers', 'torrehub' ),
				'help'        => __( 'Deletes every private seller NIE (user meta custom_field_1 and nif_nie). Business NIFs stay. Cannot be undone without the database backup.', 'torrehub' ),
				'destructive' => true,
				'rollback'    => false,
				'run'         => array( self::class, 'purge_nie' ),
			),
			'trash-listings'              => array(
				'code'        => 'D1b',
				'title'       => __( 'Test data: every listing to the trash', 'torrehub' ),
				'help'        => __( 'Client decision: the existing listings are test data. Every listing (live, pending, ended, drafts) goes to the trash — restorable for 30 days. Their photos are deleted together with them when the trash is emptied (automatically after 30 days, or Listings › Trash › Empty Trash). Run after the R0 check (D1).', 'torrehub' ),
				'destructive' => true,
				'confirm'     => __( 'Every listing goes to the trash (restorable for 30 days). Continue?', 'torrehub' ),
				'rollback'    => false,
				'run'         => array( self::class, 'trash_listings' ),
			),
			'purge-old-verification-docs' => array(
				'code'        => 'D4',
				'title'       => __( 'GDPR: delete the old verification documents', 'torrehub' ),
				'help'        => __( 'The old seller-verification add-on kept ID documents as public Media Library files. They and their references are deleted; verified sellers keep their badge. Run after the add-on is switched off. Cannot be undone without the backup.', 'torrehub' ),
				'destructive' => true,
				'rollback'    => false,
				'run'         => array( self::class, 'purge_old_verification_docs' ),
			),
		);
	}

	/**
	 * Why a step shouldn't be applied yet ('' = fine). Dry runs are always allowed.
	 *
	 * @param string $key Step key.
	 */
	public static function blocker( string $key ): string {
		if ( 'purge-old-verification-docs' === $key && in_array( 'rtcl-seller-verification/rtcl-seller-verification.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			return __( 'Switch off the “Seller Verification” plugin first (runbook D4).', 'torrehub' );
		}
		return '';
	}

	/**
	 * Run a step.
	 *
	 * @param string $key  Step key.
	 * @param string $mode dry|apply|rollback.
	 */
	public static function run( string $key, string $mode ): Report {
		$report = new Report();
		$steps  = self::all();
		if ( ! isset( $steps[ $key ] ) ) {
			$report->fail( __( 'Unknown step.', 'torrehub' ) );
			return $report;
		}
		if ( 'rollback' === $mode && ! $steps[ $key ]['rollback'] ) {
			$report->fail( __( 'This step has no rollback.', 'torrehub' ) );
			return $report;
		}
		if ( 'apply' === $mode && '' !== self::blocker( $key ) ) {
			$report->fail( self::blocker( $key ) );
			return $report;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- long one-off migration.
		}
		call_user_func( $steps[ $key ]['run'], $report, $mode );
		return $report;
	}

	/**
	 * C1 — Form Builder choice options whose value is empty get their label as value (unique within the field).
	 *
	 * @param Report $r    Report.
	 * @param string $mode dry|apply.
	 */
	public static function fix_option_values( Report $r, string $mode ): void {
		global $wpdb;
		$apply = 'apply' === $mode;
		$table = $wpdb->prefix . 'rtcl_forms';
		$rows  = $wpdb->get_results( "SELECT id, title, fields FROM {$table}" );
		$total = 0;
		foreach ( (array) $rows as $row ) {
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
						/* translators: 1: form id, 2: form title, 3: field name, 4: option position */
						$r->warning( sprintf( __( 'Form %1$d “%2$s”, field %3$s: option %4$d has neither value nor label — left alone.', 'torrehub' ), $row->id, $row->title, $field['name'] ?? $uuid, $i ) );
						continue;
					}
					$value = $label;
					$n     = 2;
					while ( in_array( $value, $used, true ) ) {
						$value = $label . '-' . $n;
						++$n;
					}
					$used[] = $value;
					/* translators: 1: form id, 2: form title, 3: field label, 4: option label, 5: new value */
					$r->line( sprintf( __( 'Form %1$d “%2$s” · %3$s: option “%4$s” → value “%5$s”', 'torrehub' ), $row->id, $row->title, $field['label'] ?? '', $label, $value ) );
					$fields[ $uuid ]['options'][ $i ]['value'] = $value;
					$changed                                   = true;
					++$total;
				}
			}
			if ( $changed && $apply ) {
				$wpdb->update( $table, array( 'fields' => wp_json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), array( 'id' => (int) $row->id ) );
			}
		}
		if ( $apply && $total && class_exists( '\Torrehub\Data\Directory' ) ) {
			\Torrehub\Data\Directory::flush(); // Archive filters list the options.
		}
		$r->done(
			$total
				/* translators: %d: number of option values */
				? sprintf( $apply ? _n( '%d option value fixed.', '%d option values fixed.', $total, 'torrehub' ) : _n( '%d option value would be fixed.', '%d option values would be fixed.', $total, 'torrehub' ), $total )
				: __( 'No empty option values.', 'torrehub' ),
			$total
		);
	}

	/**
	 * C2 — content pages from Elementor to blocks + theme templates (or back with rollback).
	 *
	 * @param Report $r    Report.
	 * @param string $mode dry|apply|rollback.
	 */
	public static function migrate_pages( Report $r, string $mode ): void {
		$n = 0;
		foreach ( \Torrehub\Modules\Content\Migrator::plan() as $slug => $conf ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				/* translators: %s: page slug */
				$r->warning( sprintf( __( '%s: page not found.', 'torrehub' ), $slug ) );
				continue;
			}
			if ( 'rollback' === $mode ) {
				$done = \Torrehub\Modules\Content\Migrator::rollback( $page );
				$n   += $done ? 1 : 0;
				/* translators: %s: page slug */
				$r->line( sprintf( $done ? __( '%s: restored', 'torrehub' ) : __( '%s: not migrated', 'torrehub' ), $slug ) );
				continue;
			}
			$res = \Torrehub\Modules\Content\Migrator::migrate( $page, $conf, 'apply' === $mode );
			if ( $res['skipped'] ) {
				$r->line( $slug . ': ' . $res['skipped'] );
				continue;
			}
			++$n;
			/* translators: 1: page slug, 2: template file, 3: migration mode, 4: blocks, 5: words before, 6: words after */
			$r->line( sprintf( __( '%1$s → %2$s (%3$s): %4$d blocks, %5$d → %6$d words', 'torrehub' ), $slug, basename( $conf['template'] ), $conf['mode'], $res['blocks'], $res['words_before'], $res['words_after'] ) );
		}
		if ( 'rollback' === $mode ) {
			/* translators: %d: number of pages */
			$r->done( sprintf( _n( '%d page restored.', '%d pages restored.', $n, 'torrehub' ), $n ), $n );
		} else {
			/* translators: %d: number of pages */
			$r->done( sprintf( 'apply' === $mode ? _n( '%d page migrated.', '%d pages migrated.', $n, 'torrehub' ) : _n( '%d page would be migrated.', '%d pages would be migrated.', $n, 'torrehub' ), $n ), $n );
		}
	}

	/**
	 * Does a table exist?
	 *
	 * @param string $table Full table name.
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
	}

	/**
	 * C3 — Classified Listing Pro's chat into the theme's chat tables (text messages; Pro's local time taken as GMT).
	 *
	 * @param Report $r    Report.
	 * @param string $mode dry|apply.
	 */
	public static function import_chat( Report $r, string $mode ): void {
		global $wpdb;
		$apply = 'apply' === $mode;
		$cons  = $wpdb->prefix . 'rtcl_conversations';
		$msgs  = $wpdb->prefix . 'rtcl_conversation_messages';
		if ( ! self::table_exists( $cons ) ) {
			$r->done( __( 'No Classified Listing Pro chat tables — nothing to import.', 'torrehub' ) );
			return;
		}
		$tables = \Torrehub\Modules\Chat\Store::tables();
		if ( ! self::table_exists( $tables['threads'] ) ) {
			$r->fail( __( 'The theme chat tables are missing: open any wp-admin page once (installer) or switch the Chat module on.', 'torrehub' ) );
			return;
		}
		$rows     = $wpdb->get_results( "SELECT * FROM {$cons} ORDER BY con_id ASC" );
		$threads  = 0;
		$messages = 0;
		$skipped  = 0;
		foreach ( (array) $rows as $con ) {
			$buyer  = (int) $con->sender_id;
			$seller = (int) $con->recipient_id;
			if ( ! get_post( (int) $con->listing_id ) || ! get_userdata( $buyer ) || ! get_userdata( $seller ) || \Torrehub\Modules\Chat\Store::find( (int) $con->listing_id, $buyer ) ) {
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
						'body'       => \Torrehub\Modules\Chat\Store::clean( (string) $item->message ),
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
		$r->done(
			sprintf(
				$apply
					/* translators: 1: conversations, 2: messages, 3: skipped conversations */
					? __( 'Imported %1$d conversations with %2$d messages (%3$d skipped: already imported, or the listing / user is gone).', 'torrehub' )
					/* translators: 1: conversations, 2: messages, 3: skipped conversations */
					: __( 'Would import %1$d conversations with %2$d messages (%3$d skipped: already imported, or the listing / user is gone).', 'torrehub' ),
				$threads,
				$messages,
				$skipped
			),
			$threads
		);
	}

	/**
	 * C4 — rtcl-search-alert's saved searches into the theme's alerts ("monthly" → weekly, inactive → off;
	 * alerts without an account stay e-mail-only).
	 *
	 * @param Report $r    Report.
	 * @param string $mode dry|apply.
	 */
	public static function import_search_alerts( Report $r, string $mode ): void {
		global $wpdb;
		$apply = 'apply' === $mode;
		$old   = $wpdb->prefix . 'rtcl_search_alerts';
		if ( ! self::table_exists( $old ) ) {
			$r->done( __( 'No rtcl-search-alert table — nothing to import.', 'torrehub' ) );
			return;
		}
		$store = \Torrehub\Modules\SearchAlerts\Store::class;
		$new   = $store::table();
		if ( ! self::table_exists( $new ) ) {
			$r->fail( __( 'The theme alerts table is missing: open any wp-admin page once (installer) or switch the module on.', 'torrehub' ) );
			return;
		}
		$rows       = $wpdb->get_results( "SELECT * FROM {$old} ORDER BY id ASC" );
		$import     = 0;
		$skip       = 0;
		$first_term = static function ( $ids, string $tax ): string {
			$best  = '';
			$depth = -1;
			foreach ( array_map( 'absint', (array) $ids ) as $id ) {
				$term = get_term( $id, $tax );
				if ( $term instanceof \WP_Term ) {
					$d = count( get_ancestors( $term->term_id, $tax, 'taxonomy' ) );
					if ( $d > $depth ) {
						$depth = $d;
						$best  = $term->slug;
					}
				}
			}
			return $best;
		};
		foreach ( (array) $rows as $row ) {
			$filter = json_decode( (string) $row->filter, true );
			$p      = is_array( $filter ) ? (array) ( $filter['params'] ?? $filter ) : array();
			$params = array();
			if ( ! empty( $p['q'] ) && is_string( $p['q'] ) ) {
				$params['q'] = sanitize_text_field( $p['q'] );
			}
			$cat = $first_term( $p['filter_category'] ?? array(), 'rtcl_category' );
			if ( $cat ) {
				$params['rtcl_category'] = $cat;
			}
			$loc = $first_term( $p['filter_location'] ?? array(), 'rtcl_location' );
			if ( $loc ) {
				$params['rtcl_location'] = $loc;
			}
			$params = \Torrehub\Modules\Archive\Search::from_array( $params )->params();
			$user   = (int) $row->user_id;
			if ( ! $user && is_email( (string) $row->email ) ) {
				$match = get_user_by( 'email', (string) $row->email );
				$user  = $match ? (int) $match->ID : 0;
			}
			$hash = $store::hash( $params );
			$dupe = $user
				? $store::find( $user, $hash )
				: $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$new} WHERE user_id = 0 AND email = %s AND hash = %s", (string) $row->email, $hash ) );
			if ( $dupe || ( ! $user && ! is_email( (string) $row->email ) ) ) {
				++$skip;
				continue;
			}
			++$import;
			if ( $apply ) {
				$freq = array(
					'daily'   => 'daily',
					'weekly'  => 'weekly',
					'monthly' => 'weekly',
				);
				$store::create(
					array(
						'user_id'   => $user,
						'email'     => $user ? '' : sanitize_email( (string) $row->email ),
						'label'     => sanitize_text_field( (string) $row->title ),
						'params'    => $params,
						'frequency' => 'active' === (string) $row->status ? ( $freq[ (string) $row->scheduler_type ] ?? 'daily' ) : 'off',
					)
				);
			}
		}
		$r->done(
			sprintf(
				$apply
					/* translators: 1: imported searches, 2: skipped */
					? __( 'Imported %1$d saved searches (%2$d skipped: already imported or no address).', 'torrehub' )
					/* translators: 1: searches to import, 2: skipped */
					: __( 'Would import %1$d saved searches (%2$d skipped: already imported or no address).', 'torrehub' ),
				$import,
				$skip
			),
			$import
		);
	}

	/**
	 * C5 — the old theme's demo posts and pages to the trash (client decision, phase 8).
	 *
	 * @param Report $r    Report.
	 * @param string $mode dry|apply.
	 */
	public static function trash_demo( Report $r, string $mode ): void {
		$apply = 'apply' === $mode;
		$sets  = array(
			'post' => array(
				'the-restaurant-has-a-fine-italian-kitchen-2',
				'dinner-at-a-restaurant-in-attleborough',
				'music-blares-out-from-every-cafeteria',
				'best-shopping-mall-at-the-main-branch',
				'the-restaurant-has-a-fine-italian-kitchen',
				'the-cafe-was-divided-up-by-glass-partitions',
				'restaurant-often-caters-for-large-banquets-copy',
				'restaurant-often-caters-for-large-banquets',
			),
			// CLDirectory demo pages + pages of features that no longer exist (Pro compare, CLDirectory map template).
			'page' => array( 'home-one', 'home-two', 'home-three', 'home-four', 'home-new', 'about-us-2', 'pricing', 'practice', 'blog', 'compare', 'listing-map' ),
		);
		$keep = array_filter( array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ) );
		$n    = 0;
		foreach ( $sets as $type => $slugs ) {
			foreach ( $slugs as $slug ) {
				$found = get_posts(
					array(
						'post_type'      => $type,
						'name'           => $slug,
						'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
						'posts_per_page' => 1,
					)
				);
				if ( ! $found ) {
					continue;
				}
				$item = $found[0];
				if ( in_array( (int) $item->ID, $keep, true ) ) {
					/* translators: 1: post type, 2: slug */
					$r->warning( sprintf( __( '%1$s %2$s: is the front page / posts page — kept.', 'torrehub' ), $type, $slug ) );
					continue;
				}
				++$n;
				$r->line( sprintf( '%s %s: “%s”%s', $type, $slug, html_entity_decode( $item->post_title, ENT_QUOTES ), $apply ? ' → ' . __( 'trash', 'torrehub' ) : '' ) );
				if ( $apply ) {
					wp_trash_post( (int) $item->ID );
				}
			}
		}
		/* translators: %d: number of posts and pages */
		$r->done( sprintf( $apply ? __( 'Moved to the trash: %d', 'torrehub' ) : __( 'Would move to the trash: %d', 'torrehub' ), $n ), $n );
	}

	/**
	 * D1b — client decision 2026-10-08: all existing listings are test data and go to the trash (any status). The
	 * photos are attached to their listing; Classified Listing deletes them when the listing is deleted for good
	 * (emptying the trash), so they leave together.
	 *
	 * @param Report $r    Report.
	 * @param string $mode dry|apply.
	 */
	public static function trash_listings( Report $r, string $mode ): void {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT ID, post_status, post_title FROM {$wpdb->posts} WHERE post_type = 'rtcl_listing' AND post_status NOT IN ('trash', 'auto-draft', 'inherit') ORDER BY ID" );
		$ids  = array_map( static fn( $row ) => (int) $row->ID, (array) $rows );
		$n    = count( $ids );
		if ( ! $n ) {
			$r->done( __( 'No listings outside the trash.', 'torrehub' ) );
			return;
		}
		$photos = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} a JOIN {$wpdb->posts} p ON p.ID = a.post_parent WHERE a.post_type = 'attachment' AND p.post_type = 'rtcl_listing' AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit')" );
		$by     = array_count_values( array_map( static fn( $row ) => (string) $row->post_status, (array) $rows ) );
		ksort( $by );
		foreach ( $by as $status => $count ) {
			/* translators: 1: post status, 2: number of listings */
			$r->line( sprintf( __( 'Status %1$s: %2$d', 'torrehub' ), $status, $count ) );
		}
		foreach ( array_slice( (array) $rows, 0, 40 ) as $row ) {
			$r->line( sprintf( '#%d %s', $row->ID, html_entity_decode( (string) $row->post_title, ENT_QUOTES ) ) );
		}
		if ( $n > 40 ) {
			/* translators: %d: number of further listings */
			$r->line( sprintf( __( '… and %d more', 'torrehub' ), $n - 40 ) );
		}
		if ( 'apply' === $mode ) {
			foreach ( $ids as $id ) {
				wp_trash_post( $id );
			}
		}
		$r->done(
			sprintf(
				'apply' === $mode
					/* translators: 1: listings, 2: photos, 3: days */
					? __( 'Moved %1$d listings to the trash. Their %2$d photos are deleted when the trash is emptied (automatically after %3$d days).', 'torrehub' )
					/* translators: 1: listings, 2: photos, 3: days */
					: __( 'Would move %1$d listings to the trash. Their %2$d photos are deleted when the trash is emptied (automatically after %3$d days).', 'torrehub' ),
				$n,
				$photos,
				(int) EMPTY_TRASH_DAYS
			),
			$n
		);
	}

	/**
	 * C6 — GDPR (client decision 2026-10-06): every stored NIE goes; business NIFs (custom_field_2) stay.
	 *
	 * @param Report $r    Report.
	 * @param string $mode dry|apply.
	 */
	public static function purge_nie( Report $r, string $mode ): void {
		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key IN ('custom_field_1','nif_nie')" );
		if ( 'apply' !== $mode ) {
			/* translators: %d: number of meta rows */
			$r->done( sprintf( _n( '%d NIE entry (custom_field_1, nif_nie) would be deleted.', '%d NIE entries (custom_field_1, nif_nie) would be deleted.', $count, 'torrehub' ), $count ), $count );
			return;
		}
		delete_metadata( 'user', 0, 'custom_field_1', '', true );
		delete_metadata( 'user', 0, 'nif_nie', '', true );
		/* translators: %d: number of meta rows */
		$r->done( sprintf( _n( '%d NIE entry deleted.', '%d NIE entries deleted.', $count, 'torrehub' ), $count ), $count );
	}

	/**
	 * D4 — GDPR: ID documents the old verification add-on stored as public attachments (user meta photo_id,
	 * other_document_id) and their references; the verified badge (rtcl_verified_seller) stays.
	 *
	 * @param Report $r    Report.
	 * @param string $mode dry|apply.
	 */
	public static function purge_old_verification_docs( Report $r, string $mode ): void {
		global $wpdb;
		$ids = array_unique( array_map( 'intval', $wpdb->get_col( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('photo_id','other_document_id') AND meta_value REGEXP '^[0-9]+$'" ) ) );
		$ids = array_values( array_filter( $ids, static fn( $id ) => $id && 'attachment' === get_post_type( $id ) ) );
		$n   = count( $ids );
		if ( 'apply' !== $mode ) {
			/* translators: %d: number of files */
			$r->done( sprintf( _n( '%d document file would be deleted.', '%d document files would be deleted.', $n, 'torrehub' ), $n ), $n );
			return;
		}
		foreach ( $ids as $id ) {
			wp_delete_attachment( $id, true );
		}
		delete_metadata( 'user', 0, 'photo_id', '', true );
		delete_metadata( 'user', 0, 'other_document_id', '', true );
		/* translators: %d: number of files */
		$r->done( sprintf( _n( '%d document file deleted; references removed.', '%d document files deleted; references removed.', $n, 'torrehub' ), $n ), $n );
	}

	/**
	 * Aggregate counts for the before/after data check (runbook A6, G1) — numbers only, no personal data.
	 *
	 * @return array<string,int> label => count
	 */
	public static function data_counts(): array {
		global $wpdb;
		$out = array();
		foreach ( array( 'rtcl_listing', 'post', 'page', 'attachment' ) as $type ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_status, COUNT(*) n FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_status ORDER BY post_status", $type ) );
			foreach ( (array) $rows as $row ) {
				$out[ "{$type} · {$row->post_status}" ] = (int) $row->n;
			}
		}
		$out['rtcl_listing · meta rows']         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = 'rtcl_listing'" );
		$out['rtcl_listing · photos (attached)'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} a JOIN {$wpdb->posts} p ON p.ID = a.post_parent WHERE a.post_type = 'attachment' AND p.post_type = 'rtcl_listing'" );
		foreach ( array( 'rtcl_category', 'rtcl_location', 'category' ) as $tax ) {
			$out[ "terms · {$tax}" ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax ) );
		}
		$users              = count_users();
		$out['users · all'] = (int) $users['total_users'];
		foreach ( (array) $users['avail_roles'] as $role => $n ) {
			if ( $n ) {
				$out[ "users · {$role}" ] = (int) $n;
			}
		}
		foreach ( array( 'custom_field_1', 'nif_nie', 'custom_field_2', 'th_verification', 'photo_id', 'other_document_id' ) as $key ) {
			$out[ "user meta · {$key}" ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", $key ) );
		}
		$tables = array(
			'rtcl_forms'                 => 'Form Builder forms',
			'rtcl_conversations'         => 'old chat · conversations',
			'rtcl_conversation_messages' => 'old chat · messages',
			'rtcl_search_alerts'         => 'old saved searches',
			'th_chat_threads'            => 'chat · conversations',
			'th_chat_messages'           => 'chat · messages',
			'th_search_alerts'           => 'saved searches',
		);
		foreach ( $tables as $table => $label ) {
			if ( self::table_exists( $wpdb->prefix . $table ) ) {
				$out[ $label ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table}" );
			}
		}
		return $out;
	}
}
