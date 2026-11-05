<?php
/**
 * Helper for _dev/tests/e2e/feedback.mjs (local only).
 *
 *   setup             → JSON: a pending listing of user54 to approve, a live Service listing of user54; originals
 *                       saved for cleanup (statuses, meta, the Service form, archive filter settings)
 *   pend ID           → back to pending
 *   publish ID        → live
 *   status ID         → post status
 *   rows ID KEY       → JSON: every meta value of KEY (checkboxes keep one row per choice)
 *   rtcl-published on|off → Classified Listing's own "Listing is approved/published" e-mail
 *   unmigrate         → the Service form and answers as before update 1.1.0 (U1), plus one "Yes" answer to map
 *   aliases           → JSON: two new live listings of user54 — A in Plumbing without the word "plumber", B in
 *                       Marketplace › Home & Garden mentioning a plumber; the Plumbing category id (its keywords saved)
 *   matcher Q         → JSON: listing ids a saved search for keyword Q finds
 *   review            → JSON: a new member (e2e_reviewer / reviewer-pass-1) and their pending 4-star review on a
 *                       live listing of user54
 *   verified ID       → 1|0
 *   bc-run ID [fail]  → one broadcast batch (fail: every send refused); JSON queue counts
 *   bc-stale ID       → one queued row of campaign ID looks interrupted mid-send
 *   bc-counts ID      → JSON queue counts
 *   bc-last           → id of the newest campaign
 *   favs LOGIN        → JSON: the user's favourites (rtcl_favourites); saved once for cleanup
 *   fav-clear LOGIN   → empty favourites
 *   cleanup           → everything back as it was
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

use Torrehub\Modules\Archive\FilterConfig;

const TH_E2E_FEEDBACK = 'th_e2e_feedback';
const TH_E2E_KEYS     = array( 'select_moa3gux0', 'radio_moa3cxr4' );
global $wpdb;
$cmd   = $args[0] ?? '';
$opts  = 'rtcl_email_notifications_settings';
$forms = $wpdb->prefix . 'rtcl_forms';

$service_form = static function () use ( $wpdb, $forms ) {
	return $wpdb->get_row( "SELECT id, fields FROM {$forms} WHERE fields LIKE '%moa3gux0%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};
$set_status   = static function ( int $id, string $status ) {
	wp_update_post(
		array(
			'ID'          => $id,
			'post_status' => $status,
		)
	);
};

if ( 'setup' === $cmd ) {
	$seller = get_user_by( 'login', 'user54' );
	$first  = static fn( array $q ) => (int) ( get_posts( array_merge( array( 'post_type' => 'rtcl_listing', 'author' => $seller->ID, 'numberposts' => 1, 'fields' => 'ids' ), $q ) )[0] ?? 0 );
	$id     = $first( array( 'post_status' => 'pending' ) );
	$status = 'pending';
	if ( ! $id ) { // Run on its own after reset-db.sh: borrow a live one (put back at cleanup).
		$id     = $first(
			array(
				'post_status' => 'publish',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- local test.
					array(
						'key'     => 'select_moa3gux0',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$status = 'publish';
		$set_status( $id, 'pending' );
	}
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- local test.
	$svc  = $first( array( 'post_status' => 'publish', 'meta_key' => 'select_moa3gux0' ) );
	$form = $service_form();
	update_option(
		TH_E2E_FEEDBACK,
		array(
			'listing' => $id,
			'meta'    => array_map( static fn( $k ) => get_post_meta( $id, $k, true ), array_combine( array( 'expiry_date', 'never_expires' ), array( 'expiry_date', 'never_expires' ) ) ),
			'notify'  => get_option( $opts ),
			'service' => $svc,
			'rows'    => array_combine( TH_E2E_KEYS, array_map( static fn( $k ) => get_post_meta( $svc, $k ), TH_E2E_KEYS ) ),
			'form'    => $form ? array( (int) $form->id, $form->fields ) : null,
			'filters' => get_option( FilterConfig::OPTION ),
			'status'  => $status,
		),
		false
	);
	echo wp_json_encode(
		array(
			'listing' => $id,
			'title'   => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
			'email'   => $seller->user_email,
			'service' => $svc,
			'svcUrl'  => get_permalink( $svc ),
		)
	);
} elseif ( 'pend' === $cmd ) {
	$set_status( (int) $args[1], 'pending' );
} elseif ( 'publish' === $cmd ) {
	$set_status( (int) $args[1], 'publish' );
} elseif ( 'status' === $cmd ) {
	echo esc_html( (string) get_post_status( (int) $args[1] ) );
} elseif ( 'rows' === $cmd ) {
	wp_cache_delete( (int) $args[1], 'post_meta' );
	echo wp_json_encode( array_values( get_post_meta( (int) $args[1], (string) $args[2] ) ) );
} elseif ( 'rtcl-published' === $cmd ) {
	$o                 = (array) get_option( $opts, array() );
	$users             = array_diff( (array) ( $o['notify_users'] ?? array() ), array( 'listing_published' ) );
	$o['notify_users'] = 'on' === ( $args[1] ?? '' ) ? array_merge( $users, array( 'listing_published' ) ) : array_values( $users );
	update_option( $opts, $o );
} elseif ( 'unmigrate' === $cmd ) {
	$form   = $service_form();
	$fields = json_decode( (string) $form->fields, true );
	$fields['moa3gux0']['element']     = 'select';
	$fields['moa3gux0']['placeholder'] = '- Select -';
	$fields['moa3cxr4']['label']       = 'Mobile Service';
	$fields['moa3cxr4']['options']     = array(
		array(
			'label'      => 'Yes',
			'value'      => 'yes',
			'icon_class' => '',
		),
		array(
			'label'      => 'No-comes to you',
			'value'      => 'no_comes_to_you',
			'icon_class' => '',
		),
	);
	$wpdb->update( $forms, array( 'fields' => wp_json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), array( 'id' => (int) $form->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$svc = (int) ( get_option( TH_E2E_FEEDBACK )['service'] ?? 0 );
	update_post_meta( $svc, 'radio_moa3cxr4', 'Yes' );
	$filters = get_option( FilterConfig::OPTION );
	foreach ( (array) ( $filters['groups'] ?? array() ) as $slug => $items ) {
		foreach ( (array) $items as $i => $item ) {
			if ( 'radio_moa3cxr4' === ( $item['field'] ?? '' ) ) {
				$filters['groups'][ $slug ][ $i ]['label'] = 'Mobile Service';
			}
		}
	}
	update_option( FilterConfig::OPTION, $filters, false );
} elseif ( 'aliases' === $cmd ) {
	$saved    = (array) get_option( TH_E2E_FEEDBACK, array() );
	$plumbing = get_term_by( 'slug', 'plumbing', 'rtcl_category' );
	$author   = get_user_by( 'login', 'user54' )->ID;
	$make     = static function ( string $title, string $content, array $cats ) use ( $author ) {
		$id = wp_insert_post(
			array(
				'post_type'    => 'rtcl_listing',
				'post_status'  => 'publish',
				'post_author'  => $author,
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
		wp_set_object_terms( $id, $cats, 'rtcl_category' );
		update_post_meta( $id, 'th_published_at', time() );
		return $id;
	};
	$a = $make( 'E2E Pipe Pro', 'Quick fixes around the house, same day.', array( 'services', 'home-maintenance', 'plumbing' ) );
	$b = $make( 'E2E Garden shed', 'Solid wooden shed, sold by a retired plumber.', array( 'marketplace', 'home-garden' ) );
	$saved['posts']    = array_merge( (array) ( $saved['posts'] ?? array() ), array( $a, $b ) );
	$saved['plumbing'] = array( (int) $plumbing->term_id, get_term_meta( $plumbing->term_id ) );
	update_option( TH_E2E_FEEDBACK, $saved, false );
	echo wp_json_encode(
		array(
			'a'        => get_permalink( $a ),
			'b'        => get_permalink( $b ),
			'aId'      => $a,
			'bId'      => $b,
			'plumbing' => (int) $plumbing->term_id,
		)
	);
} elseif ( 'matcher' === $cmd ) {
	$found = \Torrehub\Modules\SearchAlerts\Matcher::find( array( 'q' => (string) $args[1] ), 0, 0, 50 );
	echo wp_json_encode( array_map( static fn( $p ) => (int) $p->ID, $found['posts'] ) );
} elseif ( 'review' === $cmd ) {
	$saved = (array) get_option( TH_E2E_FEEDBACK, array() );
	$uid   = wp_insert_user(
		array(
			'user_login'   => 'e2e_reviewer',
			'user_pass'    => 'reviewer-pass-1',
			'user_email'   => 'e2e-reviewer@example.test',
			'display_name' => 'Rita Reviewer',
			'role'         => 'customer',
		)
	);
	$listing = (int) ( get_posts(
		array(
			'post_type'   => 'rtcl_listing',
			'post_status' => 'publish',
			'author'      => get_user_by( 'login', 'user54' )->ID,
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	)[0] ?? 0 );
	$comment = wp_insert_comment(
		array(
			'comment_post_ID'      => $listing,
			'comment_type'         => 'review',
			'comment_content'      => 'Great service, quick and friendly. Would book again.',
			'comment_author'       => 'Rita Reviewer',
			'comment_author_email' => 'e2e-reviewer@example.test',
			'user_id'              => $uid,
			'comment_approved'     => 0,
			'comment_meta'         => array(
				'rating'          => 4,
				'th_review_title' => 'Very happy',
			),
		)
	);
	$saved['reviewer'] = (int) $uid;
	$saved['verified'] = get_user_meta( get_user_by( 'login', 'user54' )->ID, 'rtcl_verified_seller', true );
	$saved['bc_max']   = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}th_broadcasts" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$saved['bc_opts']  = array( get_option( 'th_broadcast' ), get_option( 'th_account_notice' ) );
	update_option( TH_E2E_FEEDBACK, $saved, false );
	echo wp_json_encode(
		array(
			'reviewer' => (int) $uid,
			'comment'  => (int) $comment,
			'listing'  => $listing,
			'title'    => html_entity_decode( get_the_title( $listing ), ENT_QUOTES ),
			'url'      => get_permalink( $listing ),
			'seller'   => (int) get_user_by( 'login', 'user54' )->ID,
		)
	);
} elseif ( 'verified' === $cmd ) {
	echo '1' === (string) get_user_meta( (int) $args[1], 'rtcl_verified_seller', true ) ? '1' : '0';
} elseif ( in_array( $cmd, array( 'bc-run', 'bc-stale', 'bc-counts', 'bc-last' ), true ) ) {
	$id = (int) ( $args[1] ?? 0 );
	if ( 'bc-last' === $cmd ) {
		echo (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}th_broadcasts" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return;
	}
	if ( 'bc-run' === $cmd ) {
		if ( 'fail' === ( $args[2] ?? '' ) ) {
			add_filter( 'pre_wp_mail', '__return_false' );
		}
		\Torrehub\Modules\Broadcast\Queue::run( $id );
	} elseif ( 'bc-stale' === $cmd ) {
		$q = $wpdb->prefix . 'th_broadcast_queue';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$q} SET status = 'sending', updated_at = %s WHERE campaign_id = %d AND status = 'queued' ORDER BY user_id LIMIT 1", gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ), $id ) );
	}
	echo wp_json_encode( \Torrehub\Modules\Broadcast\Queue::counts( $id ) );
} elseif ( in_array( $cmd, array( 'favs', 'fav-clear' ), true ) ) {
	$user  = get_user_by( 'login', (string) $args[1] );
	$saved = (array) get_option( TH_E2E_FEEDBACK, array() );
	if ( ! isset( $saved['favs'][ $user->ID ] ) ) {
		$saved['favs'][ $user->ID ] = get_user_meta( $user->ID, 'rtcl_favourites', true );
		update_option( TH_E2E_FEEDBACK, $saved, false );
	}
	if ( 'fav-clear' === $cmd ) {
		delete_user_meta( $user->ID, 'rtcl_favourites' );
	}
	wp_cache_delete( $user->ID, 'user_meta' );
	echo wp_json_encode( array_values( array_map( 'intval', (array) get_user_meta( $user->ID, 'rtcl_favourites', true ) ) ) );
} elseif ( 'cleanup' === $cmd ) {
	$saved = (array) get_option( TH_E2E_FEEDBACK, array() );
	foreach ( (array) ( $saved['favs'] ?? array() ) as $uid => $value ) {
		'' === $value ? delete_user_meta( (int) $uid, 'rtcl_favourites' ) : update_user_meta( (int) $uid, 'rtcl_favourites', $value );
	}
	if ( ! empty( $saved['reviewer'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( get_comments( array( 'user_id' => (int) $saved['reviewer'] ) ) as $c ) {
			wp_delete_comment( (int) $c->comment_ID, true );
		}
		wp_delete_user( (int) $saved['reviewer'] ); // Also forgets the user's chats.
	}
	if ( array_key_exists( 'verified', $saved ) ) {
		$seller = get_user_by( 'login', 'user54' )->ID;
		'' === $saved['verified'] ? delete_user_meta( $seller, 'rtcl_verified_seller' ) : update_user_meta( $seller, 'rtcl_verified_seller', $saved['verified'] );
		delete_user_meta( $seller, \Torrehub\Modules\Broadcast\Notice::DISMISSED );
	}
	if ( array_key_exists( 'bc_max', $saved ) ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}th_broadcast_queue WHERE campaign_id > %d", $saved['bc_max'] ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}th_broadcasts WHERE id > %d", $saved['bc_max'] ) );
		// phpcs:enable
		wp_unschedule_hook( \Torrehub\Modules\Broadcast\Queue::HOOK );
		foreach ( array( 'th_broadcast', 'th_account_notice' ) as $i => $opt ) {
			false === $saved['bc_opts'][ $i ] ? delete_option( $opt ) : update_option( $opt, $saved['bc_opts'][ $i ], false );
		}
		delete_user_meta( get_user_by( 'login', 'dev' )->ID, 'th_broadcast_draft' );
	}
	foreach ( (array) ( $saved['posts'] ?? array() ) as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	if ( ! empty( $saved['plumbing'] ) ) {
		foreach ( (array) $saved['plumbing'][1] as $key => $values ) {
			if ( str_starts_with( (string) $key, 'th_alias_' ) ) {
				update_term_meta( (int) $saved['plumbing'][0], $key, $values[0] );
			}
		}
	}
	$id    = (int) ( $saved['listing'] ?? 0 );
	if ( $id ) {
		$set_status( $id, (string) ( $saved['status'] ?? 'pending' ) );
		foreach ( (array) ( $saved['meta'] ?? array() ) as $key => $value ) {
			'' === $value ? delete_post_meta( $id, $key ) : update_post_meta( $id, $key, $value );
		}
	}
	$svc = (int) ( $saved['service'] ?? 0 );
	if ( $svc ) {
		$set_status( $svc, 'publish' );
		foreach ( (array) ( $saved['rows'] ?? array() ) as $key => $values ) {
			delete_post_meta( $svc, $key );
			foreach ( (array) $values as $value ) {
				add_post_meta( $svc, $key, $value );
			}
		}
	}
	if ( ! empty( $saved['form'] ) ) {
		$wpdb->update( $forms, array( 'fields' => $saved['form'][1] ), array( 'id' => (int) $saved['form'][0] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( array_key_exists( 'filters', $saved ) ) {
		update_option( FilterConfig::OPTION, $saved['filters'], false );
	}
	if ( array_key_exists( 'notify', $saved ) ) {
		update_option( $opts, $saved['notify'] );
	}
	\Torrehub\Data\Directory::flush();
	delete_option( TH_E2E_FEEDBACK );
}
