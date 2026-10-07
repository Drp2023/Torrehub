<?php
/**
 * "Back soon" page shown to visitors while maintenance mode is on (Tools › Torrehub migration). Built from the auth
 * layout and the error panel — no new design (design review).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header( 'auth', array( 'page' => 'maintenance' ) );
?>
<main id="main" class="th-main th-section" tabindex="-1">
	<div class="th-container">
		<div class="th-error-panel th-error-panel--page">
			<h1 class="th-error-panel__title"><?php esc_html_e( 'Back soon', 'torrehub' ); ?></h1>
			<p><?php esc_html_e( 'We’re updating Torrehub. Please check back in a few minutes.', 'torrehub' ); ?></p>
		</div>
	</div>
</main>
<?php
get_footer( 'auth' );
