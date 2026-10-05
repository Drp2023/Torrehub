<?php
/**
 * Small, dependency-free helpers.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/** Is the free Classified Listing plugin (the listing engine) available? */
function th_has_rtcl(): bool {
	return function_exists( 'rtcl' ) && class_exists( '\Rtcl\Helpers\Functions' );
}

/** Local development copy (LocalWP)? */
function th_is_local(): bool {
	return 'local' === wp_get_environment_type();
}

/**
 * Versioned URL for a theme asset (filemtime in dev, theme version otherwise).
 *
 * @param string $path Path relative to the theme root, e.g. 'assets/css/tokens.css'.
 */
function th_asset( string $path ): string {
	return TH_URI . '/' . ltrim( $path, '/' );
}

/**
 * Cache-busting version for a theme asset.
 *
 * @param string $path Path relative to the theme root.
 */
function th_asset_version( string $path ): string {
	$file = TH_DIR . '/' . ltrim( $path, '/' );
	return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && is_readable( $file ) ) ? (string) filemtime( $file ) : TH_VERSION;
}

/**
 * Build a class attribute value from a list with conditional entries.
 *
 * Example: th_classes( 'th-btn', [ 'th-btn--accent' => $accent, 'is-loading' => $loading ] ).
 *
 * @param mixed ...$parts Strings, or arrays of class => condition / numeric-keyed class names.
 */
function th_classes( ...$parts ): string {
	$out = array();
	foreach ( $parts as $part ) {
		if ( is_string( $part ) && '' !== $part ) {
			$out[] = $part;
		} elseif ( is_array( $part ) ) {
			foreach ( $part as $key => $value ) {
				if ( is_int( $key ) ) {
					if ( is_string( $value ) && '' !== $value ) {
						$out[] = $value;
					}
				} elseif ( $value ) {
					$out[] = $key;
				}
			}
		}
	}
	// Entries may hold several space-separated classes ('th-a th-b'): split before sanitising each one.
	$out = preg_split( '/\s+/', implode( ' ', $out ), -1, PREG_SPLIT_NO_EMPTY );
	return implode( ' ', array_unique( array_filter( array_map( 'sanitize_html_class', $out ) ) ) );
}

/**
 * Render HTML attributes from an array (values escaped; true → bare attribute; false/null → omitted).
 *
 * @param array<string,mixed> $attrs Attribute name => value.
 */
function th_attrs( array $attrs ): string {
	$html = '';
	foreach ( $attrs as $name => $value ) {
		if ( null === $value || false === $value ) {
			continue;
		}
		$name  = preg_replace( '/[^a-zA-Z0-9_:\-]/', '', (string) $name );
		$html .= true === $value ? ' ' . $name : sprintf( ' %s="%s"', $name, esc_attr( (string) $value ) );
	}
	return $html;
}
