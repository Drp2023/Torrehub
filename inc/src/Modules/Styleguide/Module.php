<?php
/**
 * Hidden /styleguide route (F-01…F-05): the component library and visual-regression baseline.
 * Visible to users who can edit theme options, or to everyone on a local copy. Always noindex.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Styleguide;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden /styleguide route.
 */
final class Module extends BaseModule {

	private const QUERY_VAR = 'th_styleguide';

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'styleguide';
	}

	/**
	 * Settings label.
	 */
	public function label(): string {
		return __( 'Styleguide', 'torrehub' );
	}

	/**
	 * Settings description.
	 */
	public function description(): string {
		return __( 'Hidden /styleguide page with every component and state. Visible to administrators only.', 'torrehub' );
	}

	/**
	 * Hook everything up.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'guard' ) );
		add_filter( 'template_include', array( $this, 'template' ) );
		add_action( 'after_switch_theme', 'flush_rewrite_rules' );
	}

	/**
	 * Register the /styleguide rewrite rule.
	 */
	public function rewrite(): void {
		add_rewrite_rule( '^styleguide/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/**
	 * Add the query var.
	 *
	 * @param array<int,string> $vars Query vars.
	 * @return array<int,string>
	 */
	public function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Is the current request the styleguide?
	 */
	public static function is_styleguide(): bool {
		return (bool) get_query_var( self::QUERY_VAR );
	}

	/**
	 * Restrict access, set noindex, load styleguide CSS.
	 */
	public function guard(): void {
		if ( ! self::is_styleguide() ) {
			return;
		}
		if ( ! th_is_local() && ! current_user_can( 'edit_theme_options' ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'pre_get_document_title', static fn() => __( 'Styleguide', 'torrehub' ) . ' — ' . get_bloginfo( 'name' ) );
		add_action(
			'wp_enqueue_scripts',
			static function () {
				th_enqueue_css_bundle( 'styleguide', array() );
			},
			30
		);
	}

	/**
	 * Swap in the styleguide template.
	 *
	 * @param string $template Template path.
	 */
	public function template( string $template ): string {
		if ( self::is_styleguide() && ! is_404() ) {
			$file = TH_DIR . '/page-templates/styleguide.php';
			return is_readable( $file ) ? $file : $template;
		}
		return $template;
	}
}
