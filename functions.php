<?php
/**
 * Torrehub theme bootstrap.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

define( 'TH_VERSION', '0.1.0' );
define( 'TH_DIR', get_template_directory() );
define( 'TH_URI', get_template_directory_uri() );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The Torrehub theme requires PHP 8.1 or newer.', 'torrehub' ) . '</p></div>';
		}
	);
	return;
}

require_once TH_DIR . '/inc/bootstrap.php';
