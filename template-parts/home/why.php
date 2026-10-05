<?php
/**
 * Why Torrehub (P-01): three reasons with tinted icon circles.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

$reasons = array(
	array( 'map-pin-dot', 'blue', 'th_why_1' ),
	array( 'shield-check', 'green', 'th_why_2' ),
	array( 'chat', 'clay', 'th_why_3' ),
);
?>
<section class="th-module th-module--bordered th-home__why" aria-labelledby="th-why-title">
	<h2 class="th-module__title" id="th-why-title"><?php esc_html_e( 'Why Torrehub', 'torrehub' ); ?></h2>
	<ul class="th-stack" role="list" style="--th-stack-gap:20px">
		<?php foreach ( $reasons as list( $icon, $tint, $key ) ) : ?>
			<li class="th-home__reason">
				<span class="<?php echo esc_attr( th_classes( 'th-icon-circle', 'th-tint--' . $tint ) ); ?>"><?php th_icon( $icon ); ?></span>
				<div>
					<p class="th-home__reason-title"><?php echo esc_html( th_mod( $key . '_title' ) ); ?></p>
					<p class="th-meta"><?php echo esc_html( th_mod( $key . '_text' ) ); ?></p>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
