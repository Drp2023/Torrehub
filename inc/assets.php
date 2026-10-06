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
		/**
		 * Page-specific CSS bundles for this request (the Archive module adds 'archive').
		 *
		 * @param array<int,string> $bundles Bundle names from bundle.json.
		 */
		$page_css = (array) apply_filters( 'th_page_css_bundles', is_front_page() ? array( 'home' ) : array() );

		// Entry pages (home, listing archives) get their CSS inlined: brief §7, one render-blocking round trip less.
		/**
		 * Inline the CSS of pages with their own bundle (default) or link it (cacheable across page views).
		 *
		 * @param bool              $inline   Inline.
		 * @param array<int,string> $page_css Page bundles.
		 */
		$inline = (bool) apply_filters( 'th_inline_page_css', (bool) $page_css, $page_css );
		if ( ! $inline || ! th_inline_css_bundles( array_merge( array( 'theme' ), $page_css ) ) ) {
			$theme_css = th_enqueue_css_bundle( 'theme' );
			foreach ( $page_css as $bundle ) {
				th_enqueue_css_bundle( $bundle, array( $theme_css ) );
			}
		}

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

/**
 * Inline built CSS bundles as one <style> (front page). Returns false when a build file is missing or in
 * SCRIPT_DEBUG, so the caller falls back to <link> tags.
 *
 * @param array<int,string> $bundles Bundle names in order.
 */
function th_inline_css_bundles( array $bundles ): bool {
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		return false;
	}
	$css = '';
	foreach ( $bundles as $bundle ) {
		$file = TH_DIR . "/assets/css/build/{$bundle}.css";
		if ( ! is_readable( $file ) ) {
			return false;
		}
		$css .= (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	// Build files reference assets relative to assets/css/build/ — make them absolute for inline use.
	$css = str_replace( 'url("../../', 'url("' . TH_URI . '/assets/', $css );
	wp_register_style( 'th-theme', false, array(), TH_VERSION );
	wp_enqueue_style( 'th-theme' );
	wp_add_inline_style( 'th-theme', $css );
	return true;
}

/*
 * Cookie-banner CSS loads asynchronously (not needed for first paint).
 *
 * DECISION: jQuery stays in <head> for now — live WPCode snippets ("Favorites issue fix", "Add NIF/NIE field…")
 * print inline jQuery in the body. Moving it to the footer is part of the snippet port (phase 5, BUILD-PLAN).
 */
add_filter(
	'style_loader_tag',
	static function ( string $tag, string $handle ): string {
		if ( 'moove_gdpr_frontend' !== $handle || is_admin() ) {
			return $tag;
		}
		$async = str_replace( "media='all'", "media='print' onload=\"this.media='all'\"", $tag );
		return $async . '<noscript>' . $tag . '</noscript>';
	},
	10,
	2
);
