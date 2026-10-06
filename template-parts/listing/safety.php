<?php
/**
 * "Staying safe" note (clay) — text from the Customizer (replaces the unpublished WPCode disclaimer snippet 5642).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

$text = trim( (string) th_mod( 'th_safety_text' ) );
if ( '' === $text ) {
	return;
}
?>
<aside class="th-module th-module--clay th-listing-safety" aria-labelledby="safety-title">
	<p class="th-module__eyebrow" id="safety-title"><?php echo esc_html( (string) th_mod( 'th_safety_title' ) ); ?></p>
	<p><?php echo esc_html( $text ); ?></p>
</aside>
