<?php
/**
 * Base class for feature modules (inc/src/Modules/<Name>/Module.php).
 *
 * @package Torrehub
 */

namespace Torrehub\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A self-contained feature (auth, chat, reviews…). Registered by Theme, switchable in Settings.
 */
abstract class Module {

	/**
	 * Stable id used in settings (`th_modules[<id>]`).
	 */
	abstract public function id(): string;

	/**
	 * Human label for the settings screen.
	 */
	abstract public function label(): string;

	/**
	 * Hook everything up. Called once, only when enabled and requirements are met.
	 */
	abstract public function register(): void;

	/**
	 * Short description for the settings screen.
	 */
	public function description(): string {
		return '';
	}

	/**
	 * Can the site owner switch it off? Core building blocks return false.
	 */
	public function optional(): bool {
		return true;
	}

	/**
	 * Default state on a fresh install.
	 */
	public function default_enabled(): bool {
		return true;
	}

	/**
	 * Requirements check — e.g. the free Classified Listing plugin.
	 *
	 * @return string|null Translated reason when unmet, null when fine.
	 */
	public function unmet_requirement(): ?string {
		return null;
	}

	/**
	 * Custom tables owned by this module.
	 *
	 * Format: [ 'suffix' => 'CREATE TABLE {table} ( … ) {charset}' ]; the Installer fills the placeholders.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array();
	}

	/**
	 * Bump to re-run dbDelta for this module's tables.
	 */
	public function schema_version(): int {
		return 0;
	}
}
