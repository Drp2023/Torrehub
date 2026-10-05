<?php
/**
 * Active town (G-05): cookie `th_location` (town slug), default from the Customizer.
 *
 * - Set via JS (picker, geolocation → nearest town, computed in the browser) or without JS via `?th_town=<slug>`.
 * - Templates read th_current_town(); the RTCL archive keeps using its own `rtcl_location` parameter in URLs.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Location;

use Torrehub\Core\Module as BaseModule;
use Torrehub\Data\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Location module.
 */
final class Module extends BaseModule {

	public const COOKIE = 'th_location';

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'location';
	}

	/**
	 * Settings label.
	 */
	public function label(): string {
		return __( 'Location', 'torrehub' );
	}

	/**
	 * Settings description.
	 */
	public function description(): string {
		return __( 'Remembers the visitor’s town (cookie) and uses it as the default area for search and “New near you”.', 'torrehub' );
	}

	/**
	 * Always on: the header depends on it.
	 */
	public function optional(): bool {
		return false;
	}

	/**
	 * Needs the Classified Listing plugin (towns are rtcl_location terms).
	 */
	public function requirements_met(): bool {
		return th_has_rtcl();
	}

	/**
	 * Settings-screen message when the plugin is missing.
	 */
	public function requirement_message(): string {
		return __( 'Requires the Classified Listing plugin.', 'torrehub' );
	}

	/**
	 * Hook everything up.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle_query_switch' ), 1 );
		add_filter( 'script_module_data_th-app', array( $this, 'script_data' ) );
	}

	/**
	 * No-JS fallback: `?th_town=slug` stores the town and redirects to the clean URL.
	 */
	public function handle_query_switch(): void {
		if ( empty( $_GET['th_town'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- idempotent preference, no state change beyond a cookie.
			return;
		}
		$slug = sanitize_title( wp_unslash( $_GET['th_town'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( Directory::town( $slug ) ) {
			setcookie(
				self::COOKIE,
				$slug,
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'secure'   => is_ssl(),
					'httponly' => false, // Read by the picker JS.
					'samesite' => 'Lax',
				)
			);
			$_COOKIE[ self::COOKIE ] = $slug;
		}
		wp_safe_redirect( remove_query_arg( 'th_town' ) );
		exit;
	}

	/**
	 * Town list + approximate coordinates for the browser-side "nearest town" lookup.
	 *
	 * @param array<string,mixed> $data Existing module data.
	 * @return array<string,mixed>
	 */
	public function script_data( array $data ): array {
		$coords = th_town_coordinates();
		$towns  = array();
		foreach ( Directory::towns() as $town ) {
			if ( isset( $coords[ $town['slug'] ] ) ) {
				$towns[] = array( $town['slug'], $coords[ $town['slug'] ][0], $coords[ $town['slug'] ][1] );
			}
		}
		$data['location'] = array(
			'cookie' => self::COOKIE,
			'path'   => COOKIEPATH ? COOKIEPATH : '/',
			'towns'  => $towns,
			'i18n'   => array(
				'locating'    => __( 'Finding your town…', 'torrehub' ),
				'denied'      => __( 'Location access was blocked. Choose your town instead.', 'torrehub' ),
				'unavailable' => __( 'Your location isn’t available right now.', 'torrehub' ),
			),
		);
		return $data;
	}
}
