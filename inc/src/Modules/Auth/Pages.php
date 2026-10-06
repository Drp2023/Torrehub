<?php
/**
 * The auth pages: /login/ (existing page 4999), /register/ (5014), /lost-password/ (created when missing).
 * The theme renders them itself, whatever their stored content (the old pages are Elementor layouts).
 * Ids live in the option `th_auth_pages`; pages are found by slug or created on the first admin request.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Auth page registry.
 */
final class Pages {

	public const OPTION = 'th_auth_pages';

	/**
	 * Page definitions: key => [ slug, title ].
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function defs(): array {
		return array(
			'login'    => array( 'login', __( 'Log in', 'torrehub' ) ),
			'register' => array( 'register', __( 'Create an account', 'torrehub' ) ),
			'lost'     => array( 'lost-password', __( 'Reset your password', 'torrehub' ) ),
		);
	}

	/**
	 * Page id for a key (0 when missing).
	 *
	 * @param string $key login|register|lost.
	 */
	public static function id( string $key ): int {
		$ids = (array) get_option( self::OPTION, array() );
		$id  = (int) ( $ids[ $key ] ?? 0 );
		if ( $id && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
			return $id;
		}
		$slug = self::defs()[ $key ][0] ?? '';
		$page = $slug ? get_page_by_path( $slug ) : null;
		return $page && 'publish' === $page->post_status ? (int) $page->ID : 0;
	}

	/**
	 * URL of an auth page (falls back to wp-login.php while a page is missing).
	 *
	 * @param string $key login|register|lost.
	 */
	public static function url( string $key ): string {
		$id = self::id( $key );
		if ( $id ) {
			return (string) get_permalink( $id );
		}
		$actions = array(
			'login'    => '',
			'register' => 'register',
			'lost'     => 'lostpassword',
		);
		return site_url( 'wp-login.php' . ( $actions[ $key ] ? '?action=' . $actions[ $key ] : '' ), 'login' );
	}

	/**
	 * Which auth page is being viewed ('' if none).
	 */
	public static function current(): string {
		if ( ! is_page() ) {
			return '';
		}
		$id = get_queried_object_id();
		foreach ( array_keys( self::defs() ) as $key ) {
			if ( $id && self::id( $key ) === $id ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * Make sure all pages exist (admin_init; cheap after the first run).
	 */
	public static function ensure(): void {
		$ids     = (array) get_option( self::OPTION, array() );
		$changed = false;
		foreach ( self::defs() as $key => $def ) {
			$id = self::id( $key );
			if ( ! $id && current_user_can( 'publish_pages' ) ) {
				$id = (int) wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $def[1],
						'post_name'    => $def[0],
						'post_content' => '',
					)
				);
			}
			if ( $id && (int) ( $ids[ $key ] ?? 0 ) !== $id ) {
				$ids[ $key ] = $id;
				$changed     = true;
			}
		}
		if ( $changed ) {
			update_option( self::OPTION, $ids );
		}
	}
}
