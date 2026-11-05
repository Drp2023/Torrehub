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
} elseif ( 'cleanup' === $cmd ) {
	$saved = (array) get_option( TH_E2E_FEEDBACK, array() );
	$id    = (int) ( $saved['listing'] ?? 0 );
	if ( $id ) {
		$set_status( $id, 'pending' );
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
