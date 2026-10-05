<?php
/**
 * Autoloader + load order.
 *
 * Classes: Torrehub\Foo\Bar → inc/src/Foo/Bar.php
 * Procedural helpers (th_*) live in inc/*.php.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, 'Torrehub\\' ) ) {
			return;
		}
		$file = TH_DIR . '/inc/src/' . str_replace( '\\', '/', substr( $class_name, 9 ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once TH_DIR . '/inc/helpers.php';
require_once TH_DIR . '/inc/setup.php';
require_once TH_DIR . '/inc/assets.php';
require_once TH_DIR . '/inc/template-tags.php';
require_once TH_DIR . '/inc/category-map.php';
require_once TH_DIR . '/inc/links.php';
require_once TH_DIR . '/inc/location.php';
require_once TH_DIR . '/inc/languages.php';
require_once TH_DIR . '/inc/listing-card.php';
require_once TH_DIR . '/inc/customizer.php';
require_once TH_DIR . '/inc/seo.php';

Torrehub\Data\Directory::register_invalidation();

Torrehub\Core\Theme::instance()->boot();
