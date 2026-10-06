<?php
/**
 * My account (G-02 / G-03): Classified Listing's account page and endpoints inside the theme layout.
 *
 * - The page renders template-parts/account/page.php (header, side navigation, content); the endpoints still come
 *   from Classified Listing ([rtcl_my_account]), with theme overrides for the wrapper, dashboard and my listings.
 * - "My listings" deletes to the trash (restorable by an admin) after an ownership check.
 * - Modules add sections with the `th_account_sections` filter (e.g. Verification).
 *
 * DECISION: the quota bar ("3 of 5 free listings") stays hidden while the Quota module is off (BUILD-PLAN v1 #9);
 * "Unread" chats appear with the Chat module (phase 6).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Account;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Account module.
 */
final class Module extends BaseModule {

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'account';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'My account', 'torrehub' );
	}

	/**
	 * Core building block.
	 */
	public function optional(): bool {
		return false;
	}

	/**
	 * Needs Classified Listing.
	 */
	public function requirements_met(): bool {
		return th_has_rtcl();
	}

	/**
	 * Requirement message.
	 */
	public function requirement_message(): string {
		return __( 'Requires the Classified Listing plugin.', 'torrehub' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'template_include', array( $this, 'template' ), 100 );
		add_filter( 'th_page_css_bundles', array( $this, 'css_bundles' ) );
		th_on_front_post( 'th_delete_listing', array( $this, 'delete_listing' ) );
		// Classified Listing's own dashboard greeting block is replaced by the theme dashboard.
		add_action( 'init', static fn() => remove_action( 'rtcl_account_dashboard', array( \Rtcl\Controllers\Hooks\TemplateHooks::class, 'user_information' ) ), 20 );
	}

	/**
	 * The account page id.
	 */
	public static function page_id(): int {
		return (int) ( get_option( 'rtcl_advanced_settings', array() )['myaccount'] ?? 0 );
	}

	/**
	 * Is the account page being viewed?
	 */
	public static function is_account(): bool {
		$id = self::page_id();
		return $id && is_page( $id );
	}

	/**
	 * Theme layout for the account page.
	 *
	 * @param string $template Template.
	 */
	public function template( $template ) {
		if ( self::is_account() && is_user_logged_in() ) {
			nocache_headers();
			$file = locate_template( 'template-parts/account/page.php' );
			return $file ? $file : $template;
		}
		return $template;
	}

	/**
	 * Account CSS bundle.
	 *
	 * @param array<int,string> $bundles Page bundles.
	 * @return array<int,string>
	 */
	public function css_bundles( $bundles ): array {
		$bundles = (array) $bundles;
		if ( self::is_account() ) {
			$bundles[] = 'account';
		}
		return $bundles;
	}

	/**
	 * Navigation: Classified Listing's menu items + module sections, with icons.
	 *
	 * @return array<int,array{key:string,label:string,url:string,icon:string,current:bool}>
	 */
	public static function nav(): array {
		$items = \Rtcl\Helpers\Functions::get_account_menu_items();
		/**
		 * Extra account sections: key => [ label, icon ] (rendered via `rtcl_account_{key}_endpoint`).
		 *
		 * @param array $sections Sections.
		 */
		$extra   = (array) apply_filters( 'th_account_sections', array() );
		$icons   = array(
			'dashboard'        => 'home',
			'listings'         => 'file',
			'add-listing'      => 'plus',
			'favourites'       => 'heart',
			'edit-account'     => 'user',
			'profile-settings' => 'settings',
			'payments'         => 'file',
			'logout'           => 'log-out',
		);
		$current = '';
		foreach ( array_keys( $items + $extra ) as $key ) {
			if ( 'dashboard' !== $key && \Rtcl\Helpers\Functions::is_account_page( $key ) ) {
				$current = $key;
			}
		}
		$current = $current ? $current : 'dashboard';
		$out     = array();
		foreach ( $items as $key => $label ) {
			// Members can't post: Classified Listing hides these only for users flagged "buyer", older members aren't.
			if ( ( 'favourites' === $key && ! th_favourites_enabled() ) || ( in_array( $key, array( 'listings', 'add-listing' ), true ) && ! th_user_can_post() ) ) {
				continue;
			}
			if ( 'logout' === $key ) {
				// Module sections come before "Log out".
				foreach ( $extra as $ekey => $edef ) {
					$out[] = array(
						'key'     => $ekey,
						'label'   => (string) $edef[0],
						'url'     => \Rtcl\Helpers\Link::get_account_endpoint_url( $ekey ),
						'icon'    => (string) ( $edef[1] ?? 'info' ),
						'current' => $current === $ekey,
					);
				}
			}
			$out[] = array(
				'key'     => $key,
				'label'   => (string) $label,
				'url'     => 'add-listing' === $key ? th_url_post_listing() : ( 'logout' === $key ? wp_logout_url( home_url( '/' ) ) : \Rtcl\Helpers\Link::get_account_endpoint_url( $key ) ),
				'icon'    => $icons[ $key ] ?? 'info',
				'current' => $current === $key,
			);
		}
		// "Log out" always last.
		usort( $out, static fn( $a, $b ) => (int) ( 'logout' === $a['key'] ) <=> (int) ( 'logout' === $b['key'] ) );
		return $out;
	}

	/**
	 * Move one of the current user's listings to the trash.
	 */
	public function delete_listing(): void {
		$id   = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified next.
		$back = \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' );
		check_admin_referer( 'th_delete_listing_' . $id );
		$ok = is_user_logged_in() && 'rtcl_listing' === get_post_type( $id ) && get_current_user_id() === (int) get_post_field( 'post_author', $id );
		if ( $ok ) {
			wp_trash_post( $id );
		}
		wp_safe_redirect( add_query_arg( 'th_deleted', $ok ? '1' : '0', $back ) );
		exit;
	}

	/**
	 * Dashboard numbers for a user.
	 *
	 * @param int $user_id User id.
	 * @return array{active:int,pending:int,views:int}
	 */
	public static function stats( int $user_id ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- per-user dashboard numbers, small result.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT post_status, COUNT(*) n FROM {$wpdb->posts} WHERE post_type = 'rtcl_listing' AND post_author = %d GROUP BY post_status", $user_id ), OBJECT_K );
		$views = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(CAST(pm.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_views' AND p.post_type = 'rtcl_listing' AND p.post_author = %d AND p.post_status = 'publish'", $user_id ) );
		// phpcs:enable
		return array(
			'active'  => (int) ( $rows['publish']->n ?? 0 ),
			'pending' => (int) ( $rows['pending']->n ?? 0 ) + (int) ( $rows['draft']->n ?? 0 ),
			'views'   => $views,
		);
	}

	/**
	 * Time-of-day greeting in Madrid time.
	 */
	public static function greeting(): string {
		$hour = (int) ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'Europe/Madrid' ) ) )->format( 'G' );
		if ( $hour < 12 ) {
			return __( 'Good morning', 'torrehub' );
		}
		return $hour < 19 ? __( 'Good afternoon', 'torrehub' ) : __( 'Good evening', 'torrehub' );
	}
}
