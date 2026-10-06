<?php
/**
 * For sellers (P-01): dark call-to-action.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

$how = (string) th_mod( 'th_sellers_link' );
?>
<section class="th-module th-module--dark th-module--large th-home__sellers" aria-labelledby="th-sellers-title">
	<p class="th-module__eyebrow"><?php esc_html_e( 'For sellers', 'torrehub' ); ?></p>
	<h2 class="th-module__title" id="th-sellers-title"><?php echo esc_html( th_sellers_title() ); ?></h2>
	<p><?php echo esc_html( th_mod( 'th_sellers_text' ) ); ?></p>
	<div class="th-cluster">
		<?php
		th_component(
			'button',
			array(
				'label'   => th_user_can_post() ? __( 'Post a listing', 'torrehub' ) : __( 'Create a seller account', 'torrehub' ),
				'variant' => 'accent',
				'href'    => th_user_can_post() ? th_url_post_listing() : ( get_option( 'users_can_register' ) ? wp_registration_url() : th_url_login() ),
			)
		);
		if ( $how ) {
			th_component(
				'button',
				array(
					'label'   => __( 'How it works', 'torrehub' ),
					'variant' => 'ghost',
					'href'    => $how,
				)
			);
		}
		?>
	</div>
</section>
