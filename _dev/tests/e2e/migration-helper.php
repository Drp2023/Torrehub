<?php
/**
 * Helper for _dev/tests/e2e/migration.mjs (local only).
 *
 *   wp eval-file _dev/tests/e2e/migration-helper.php setup    → pre-migration state: pages rolled back, demo content
 *                                                               out of the trash, one dummy NIE, no log / baseline
 *   wp eval-file _dev/tests/e2e/migration-helper.php cleanup  → back to the migrated state (as after bin/reset-db.sh)
 *
 * @package Torrehub
 */

defined( 'WP_CLI' ) || exit;

use Torrehub\Modules\Migration\Module as Migration;
use Torrehub\Modules\Migration\Steps;

$cmd = $args[0] ?? '';
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
	$tester = get_user_by( 'login', 'tester' );
	update_user_meta( $tester->ID, 'custom_field_1', 'E2E-NIE' );
	WP_CLI::line( count( $trashed ) . ' restored from the trash' );
} else {
	foreach ( array( 'migrate-pages', 'trash-demo', 'purge-nie' ) as $key ) {
		Steps::run( $key, 'apply' );
	}
	foreach ( get_users( array( 'fields' => 'ID' ) ) as $uid ) {
		delete_transient( 'th_migration_dry_' . $uid . '_purge-nie' );
	}
	WP_CLI::success( 'Migration test data removed.' );
}
