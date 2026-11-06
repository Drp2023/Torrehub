<?php
/**
 * Helper for _dev/tests/e2e/migration.mjs (local only).
 *
 *   wp eval-file _dev/tests/e2e/migration-helper.php setup    → pre-migration state: pages rolled back, demo content
 *                                                               out of the trash, one dummy NIE, no log / baseline
 *   wp eval-file _dev/tests/e2e/migration-helper.php cleanup  → back to the migrated state (as after bin/reset-db.sh);
 *                                                               listings the test moved to the trash come back with
 *                                                               their status (publish hooks detached: no new dates)
 *   wp eval-file _dev/tests/e2e/migration-helper.php modules-set '{"chat":1}'|-  → th_modules as given ('-' = none
 *                                                               saved, as on a site where the settings were never
 *                                                               saved); the original comes back at cleanup
 *   wp eval-file _dev/tests/e2e/migration-helper.php modules-state → JSON: every optional module => on (effective)
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

use Torrehub\Modules\Migration\Module as Migration;
use Torrehub\Modules\Migration\Steps;

$cmd = $args[0] ?? '';

// Module switches (no effect on the migration log below).
if ( 'modules-set' === $cmd ) {
	if ( false === get_option( 'th_e2e_migration_modules' ) ) {
		$was = get_option( Torrehub\Core\Settings::OPTION );
		update_option( 'th_e2e_migration_modules', false === $was ? '-' : $was, false );
	}
	'-' === ( $args[1] ?? '-' ) ? delete_option( Torrehub\Core\Settings::OPTION ) : update_option( Torrehub\Core\Settings::OPTION, (array) json_decode( $args[1], true ) );
	return;
}
if ( 'modules-state' === $cmd ) {
	$state = array();
	foreach ( Torrehub\Core\Theme::instance()->modules() as $id => $module ) {
		if ( $module->optional() ) {
			$state[ $id ] = Torrehub\Core\Settings::module_enabled( $module );
		}
	}
	echo wp_json_encode( $state );
	return;
}

delete_option( Migration::LOG );
delete_option( Migration::BASELINE );
Migration::set_maintenance( false );

if ( 'setup' === $cmd ) {
	foreach ( array_keys( Torrehub\Modules\Content\Migrator::plan() ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			Torrehub\Modules\Content\Migrator::rollback( $page );
		}
	}
	$trashed = get_posts(
		array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'trash',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $trashed as $id ) {
		wp_untrash_post( (int) $id );
	}
	update_option(
		'th_e2e_migration_listings',
		get_posts(
			array(
				'post_type'      => 'rtcl_listing',
				'post_status'    => array_diff( array_keys( get_post_stati() ), array( 'trash', 'auto-draft', 'inherit' ) ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		),
		false
	);
	$tester = get_user_by( 'login', 'tester' );
	update_user_meta( $tester->ID, 'custom_field_1', 'E2E-NIE' );
	WP_CLI::line( count( $trashed ) . ' restored from the trash' );
} else {
	$listings = (array) get_option( 'th_e2e_migration_listings', array() );
	if ( $listings ) {
		remove_all_actions( 'transition_post_status' );
		add_filter( 'wp_untrash_post_status', static fn( $new_status, $id, $previous ) => $previous, 10, 3 );
		foreach ( $listings as $id ) {
			if ( 'trash' === get_post_status( (int) $id ) ) {
				wp_untrash_post( (int) $id );
			}
		}
	}
	delete_option( 'th_e2e_migration_listings' );
	$modules = get_option( 'th_e2e_migration_modules' );
	if ( false !== $modules ) {
		'-' === $modules ? delete_option( Torrehub\Core\Settings::OPTION ) : update_option( Torrehub\Core\Settings::OPTION, $modules );
		delete_option( 'th_e2e_migration_modules' );
	}
	foreach ( array( 'migrate-pages', 'trash-demo', 'purge-nie' ) as $key ) {
		Steps::run( $key, 'apply' );
	}
	foreach ( get_users( array( 'fields' => 'ID' ) ) as $uid ) {
		delete_transient( 'th_migration_dry_' . $uid . '_purge-nie' );
	}
	WP_CLI::success( 'Migration test data removed.' );
}
