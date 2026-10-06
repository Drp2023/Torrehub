<?php
/**
 * Theme kernel: module registry, settings, installer.
 *
 * @package Torrehub
 */

namespace Torrehub\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Boots every module whose requirements are met and which is enabled.
 */
final class Theme {

	/**
	 * Singleton instance.
	 *
	 * @var Theme|null
	 */
	private static ?Theme $instance = null;

	/**
	 * Loaded modules keyed by id.
	 *
	 * @var array<string,Module>
	 */
	private array $modules = array();

	/**
	 * Ids of modules skipped for unmet requirements.
	 *
	 * @var array<int,string>
	 */
	private array $skipped = array();

	/**
	 * Shared instance.
	 */
	public static function instance(): Theme {
		self::$instance ??= new self();
		return self::$instance;
	}

	/**
	 * Module classes, in load order. Each phase adds its modules here.
	 *
	 * @return array<int,class-string<Module>>
	 */
	private function module_classes(): array {
		$classes = array(
			\Torrehub\Modules\RtclCompat\Module::class,
			\Torrehub\Modules\Location\Module::class,
			\Torrehub\Modules\Languages\Module::class,
			\Torrehub\Modules\Archive\Module::class,
			\Torrehub\Modules\Listing\Module::class,
			\Torrehub\Modules\Reviews\Module::class,
			\Torrehub\Modules\Styleguide\Module::class,
		);

		/**
		 * Filter the module list (e.g. to add or replace a module from a child theme).
		 *
		 * @param array<int,class-string<Module>> $classes Module class names.
		 */
		return (array) apply_filters( 'th_module_classes', $classes );
	}

	/**
	 * Instantiate modules, check requirements, register enabled ones.
	 */
	public function boot(): void {
		foreach ( $this->module_classes() as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}
			$module = new $class_name();
			if ( ! $module instanceof Module ) {
				continue;
			}
			$this->modules[ $module->id() ] = $module;
		}

		// Plugins (Classified Listing) are loaded before the theme, so requirements can be checked right away.
		foreach ( $this->modules as $id => $module ) {
			if ( ! $module->requirements_met() ) {
				$this->skipped[] = $id;
				continue;
			}
			if ( Settings::module_enabled( $module ) ) {
				$module->register();
			}
		}

		( new Installer( $this->modules ) )->register();
		( new Settings( $this ) )->register();
	}

	/**
	 * All loaded modules.
	 *
	 * @return array<string,Module>
	 */
	public function modules(): array {
		return $this->modules;
	}

	/**
	 * Ids of modules skipped for unmet requirements.
	 *
	 * @return array<int,string>
	 */
	public function skipped(): array {
		return $this->skipped;
	}

	/**
	 * A module by id.
	 *
	 * @param string $id Module id.
	 */
	public function module( string $id ): ?Module {
		return $this->modules[ $id ] ?? null;
	}
}
