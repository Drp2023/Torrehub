<?php
/**
 * Asset loading.
 *
 * CSS: assets/css/bundle.json defines the order. SCRIPT_DEBUG → source files one by one; otherwise
 * the built bundle assets/css/build/<bundle>.css (node _dev/tools/build-assets.mjs).
 * JS: native ES modules via the Script Modules API; no jQuery in theme code.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/**
 * CSS bundles from assets/css/bundle.json.
 *
 * @return array<string,array<int,string>>
 */
function th_css_bundles(): array {
	static $bundles = null;
	if ( null === $bundles ) {
		$json    = file_get_contents( TH_DIR . '/assets/css/bundle.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$bundles = $json ? (array) json_decode( $json, true ) : array();
	}
	return $bundles;
}

/**
 * Enqueue a CSS bundle (dev: sources, prod: build).
 *
 * @param string        $bundle Key in bundle.json.
 * @param array<string> $deps   Style handle dependencies.
 * @return string Handle of the last stylesheet (to depend on).
 */
function th_enqueue_css_bundle( string $bundle, array $deps = array() ): string {
	$files = th_css_bundles()[ $bundle ] ?? array();
	$build = "assets/css/build/{$bundle}.css";

	if ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ! is_readable( TH_DIR . '/' . $build ) ) {
		$prev = $deps;
		$last = '';
		foreach ( $files as $file ) {
			$path   = 'assets/css/' . $file;
			$handle = 'th-' . $bundle . '-' . sanitize_title( str_replace( array( '/', '.css' ), array( '-', '' ), $file ) );
			wp_enqueue_style( $handle, th_asset( $path ), $prev, th_asset_version( $path ) );
			$prev = array( $handle );
			$last = $handle;
		}
		return $last;
	}

	$handle = 'th-' . $bundle;
	wp_enqueue_style( $handle, th_asset( $build ), $deps, th_asset_version( $build ) );
	return $handle;
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		th_enqueue_css_bundle( 'theme' );

		wp_register_script_module( 'th-app', th_asset( 'assets/js/app.js' ), array(), th_asset_version( 'assets/js/app.js' ) );
		wp_enqueue_script_module( 'th-app' );

		// Global block styles are not needed on the front end (classic theme, no block layouts besides content).
		wp_dequeue_style( 'classic-theme-styles' );
	},
	20
);

/* Preload the two primary latin font files (brief §3.2). */
add_action(
	'wp_head',
	static function () {
		foreach ( array( 'figtree-latin-var.woff2', 'space-grotesk-latin-var.woff2' ) as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( th_asset( 'assets/fonts/' . $font ) )
			);
		}
	},
	1
);

/* Front-end JS config for modules (ajax/rest endpoints, i18n strings shared by components). */
add_filter(
	'script_module_data_th-app',
	static function ( array $data ): array {
		return array_merge(
			$data,
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => esc_url_raw( rest_url() ),
				'sprite'  => th_sprite_url(),
				'i18n'    => array(
					'close'   => __( 'Close', 'torrehub' ),
					'loading' => __( 'Loading…', 'torrehub' ),
				),
			)
		);
	}
);
