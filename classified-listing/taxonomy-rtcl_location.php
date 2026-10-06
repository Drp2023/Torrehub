<?php
/**
 * Classified Listing template override → the theme's archive (Archive module).
 * Falls back to the plugin's own template when the module is switched off.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

if ( ! Torrehub\Modules\Archive\Module::active() ) {
	require rtcl()->plugin_path() . '/templates/taxonomy-rtcl_location.php';
	return;
}

get_template_part( 'template-parts/archive/archive' );
