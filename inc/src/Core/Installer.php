<?php
/**
 * Creates/updates module tables with dbDelta, keyed by a per-module schema version.
 *
 * @package Torrehub
 */

namespace Torrehub\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on theme switch and (cheaply) on admin_init, so a theme update migrates without re-activation.
 */
final class Installer {

	private const OPTION = 'th_schema_versions';

	/**
	 * Set up with the loaded modules.
	 *
	 * @param array<string,Module> $modules Loaded modules keyed by id.
	 */
	public function __construct( private array $modules ) {}

	/**
	 * Hook the install check.
	 */
	public function register(): void {
		add_action( 'after_switch_theme', array( $this, 'maybe_install' ) );
		add_action( 'admin_init', array( $this, 'maybe_install' ) );
	}

	/**
	 * Install/upgrade tables for modules whose schema version is newer than the stored one.
	 */
	public function maybe_install(): void {
		$versions = (array) get_option( self::OPTION, array() );
		$changed  = false;

		foreach ( $this->modules as $id => $module ) {
			$target = $module->schema_version();
			if ( ! $target || ( (int) ( $versions[ $id ] ?? 0 ) ) >= $target ) {
				continue;
			}
			$this->install_tables( $module );
			$versions[ $id ] = $target;
			$changed         = true;
		}

		if ( $changed ) {
			update_option( self::OPTION, $versions, false );
		}
	}

	/**
	 * Run dbDelta for one module's tables.
	 *
	 * @param Module $module Module.
	 */
	private function install_tables( Module $module ): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		foreach ( $module->tables() as $suffix => $sql ) {
			dbDelta(
				strtr(
					$sql,
					array(
						'{table}'   => self::table( $suffix ),
						'{charset}' => $charset,
					)
				)
			);
		}
	}

	/**
	 * Full table name for a module table suffix.
	 *
	 * @param string $suffix Table suffix, e.g. 'chat_messages'.
	 */
	public static function table( string $suffix ): string {
		global $wpdb;
		return $wpdb->prefix . 'th_' . $suffix;
	}
}
