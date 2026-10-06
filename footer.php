<?php
/**
 * Site footer (G-11) + mobile bottom nav (G-10).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_template_part( 'template-parts/footer/footer' );
get_template_part( 'template-parts/footer/bottom-nav' );

// Modal dialogs opened from the header (see header.php).
get_template_part( 'template-parts/header/drawer' );
if ( th_has_rtcl() ) {
	get_template_part( 'template-parts/header/location-dialog' );
}

wp_footer();
?>
</body>
</html>
