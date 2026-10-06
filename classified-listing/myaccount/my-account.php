<?php
/**
 * Classified Listing account wrapper override: the theme page (template-parts/account/page.php) draws the
 * navigation, so only notices and the current section are printed here.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

\Rtcl\Helpers\Functions::print_notices();
do_action( 'rtcl_account_content' );
