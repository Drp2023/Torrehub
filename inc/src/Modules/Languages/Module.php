<?php
/**
 * Language switcher on top of GTranslate (G-06). The language list is read from GTranslate's own settings —
 * nothing hard-coded. Without a translation plugin the switcher is not rendered.
 *
 * Mechanics (GTranslate free, client-side): our links are `<a href="#" data-gt-lang="xx" class="notranslate">`;
 * GTranslate's base.js binds them and marks the active one with `gt-current-lang`. base.js is loaded once
 * through its own `[gt-link]` shortcode (output discarded). The floating GTranslate widget is switched off at
 * runtime so only the theme switcher shows. Other translation plugins (WPML, Polylang, TranslatePress) can be
 * added in th_get_languages() later.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Languages;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Languages module.
 */
final class Module extends BaseModule {

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'languages';
	}

	/**
	 * Settings label.
	 */
	public function label(): string {
		return __( 'Language switcher', 'torrehub' );
	}

	/**
	 * Settings description.
	 */
	public function description(): string {
		return __( 'Header, drawer and footer language switcher. Reads the language list from GTranslate.', 'torrehub' );
	}

	/**
	 * Only meaningful with a supported translation plugin.
	 */
	public function requirements_met(): bool {
		return th_translation_provider() !== '';
	}

	/**
	 * Settings-screen message when no plugin is active.
	 */
	public function requirement_message(): string {
		return __( 'No supported translation plugin is active (GTranslate).', 'torrehub' );
	}

	/**
	 * Hook everything up.
	 */
	public function register(): void {
		if ( 'gtranslate' === th_translation_provider() && ! is_admin() ) {
			// Hide GTranslate's own floating widget; the theme renders the switcher.
			add_filter(
				'option_GTranslate',
				static function ( $value ) {
					if ( is_array( $value ) ) {
						$value['floating_language_selector'] = 'no';
						$value['show_in_menu']               = '';
					}
					return $value;
				}
			);
			// GTranslate decides on its floating widget while the plugin loads (before this filter exists), so the
			// footer callback it registered is removed directly as well.
			remove_action( 'wp_footer', 'gtranslate_display_floating' );
			add_action( 'wp_footer', array( $this, 'load_gtranslate_script' ), 1 );
		}
	}

	/**
	 * Make GTranslate enqueue base.js (+ its settings) once, via its own API.
	 */
	public function load_gtranslate_script(): void {
		$languages = th_get_languages();
		if ( $languages && shortcode_exists( 'gt-link' ) ) {
			do_shortcode( '[gt-link lang="' . esc_attr( $languages[0]['code'] ) . '"]' );
		}
	}
}
