<?php
/**
 * Language list abstraction (brief §6). Currently: GTranslate. Returns [] when no provider is active.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Active translation provider id ('' when none).
 */
function th_translation_provider(): string {
	if ( defined( 'GTRANSLATE_VERSION' ) || class_exists( 'GTranslate' ) ) {
		return 'gtranslate';
	}
	return '';
}

/**
 * Configured languages, default first.
 *
 * @return array<int,array{code:string,name:string,short:string,default:bool}>
 */
function th_get_languages(): array {
	static $list = null;
	if ( null !== $list ) {
		return $list;
	}
	$list = array();

	if ( 'gtranslate' === th_translation_provider() ) {
		$opt = get_option( 'GTranslate' );
		if ( ! is_array( $opt ) ) {
			return $list;
		}
		$default = (string) ( $opt['default_language'] ?? 'en' );
		// Flag-based looks (float, flags*) use fincl_langs; text looks (dropdown, lang_names…) use incl_langs.
		$look  = (string) ( $opt['widget_look'] ?? 'float' );
		$key   = in_array( $look, array( 'dropdown', 'lang_names', 'lang_codes', 'globe' ), true ) ? 'incl_langs' : 'fincl_langs';
		$codes = array_values( array_unique( array_filter( array_map( 'strval', (array) ( $opt[ $key ] ?? array() ) ) ) ) );
		if ( ! in_array( $default, $codes, true ) ) {
			array_unshift( $codes, $default );
		}
		// Default language first, others in configured order.
		usort( $codes, static fn( $a, $b ) => ( $b === $default ) <=> ( $a === $default ) );

		$native = array();
		if ( class_exists( 'GTranslate' ) && isset( GTranslate::$lang_array_native_json ) ) {
			$native = (array) json_decode( (string) GTranslate::$lang_array_native_json, true );
		}
		foreach ( $codes as $code ) {
			$list[] = array(
				'code'    => $code,
				'name'    => (string) ( $native[ $code ] ?? strtoupper( $code ) ),
				'short'   => strtoupper( substr( $code, 0, 2 ) ),
				'default' => $code === $default,
			);
		}
	}

	/**
	 * Filter the language list.
	 *
	 * @param array $list Languages.
	 */
	$list = (array) apply_filters( 'th_languages', $list );
	return $list;
}
