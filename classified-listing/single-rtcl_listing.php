<?php
/**
 * Classified Listing template override → the theme's listing page (Listing module).
 * Falls back to the plugin's own template when the module is switched off.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

if ( ! Torrehub\Modules\Listing\Module::active() ) {
	require rtcl()->plugin_path() . '/templates/single-rtcl_listing.php';
	return;
}

get_template_part( 'template-parts/listing/single' );
