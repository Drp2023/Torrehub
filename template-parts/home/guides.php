<?php
/**
 * Latest guides (client request 2026-11): a short intro (Customizer, can be switched off) and the 3 newest guides
 * as the Guides index's cards. Hidden while there are no guides.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Guides\Module as Guides;

$latest = class_exists( Guides::class ) ? Guides::latest( 3 ) : array();
if ( ! $latest ) {
	return;
}
$intro = th_mod( 'th_home_guides_intro_on' ) ? trim( (string) th_mod( 'th_home_guides_intro' ) ) : '';
?>
<section class="th-container th-section th-home__guides" aria-labelledby="th-guides-latest-title">
	<?php
	th_component(
		'section-head',
		array(
			'title'     => __( 'Latest guides', 'torrehub' ),
			'id'        => 'th-guides-latest-title',
			'link'      => th_url_guides(),
			'link_text' => __( 'All guides', 'torrehub' ),
		)
	);
	?>
	<?php if ( '' !== $intro ) : ?>
		<p class="th-home__guides-intro"><?php echo esc_html( $intro ); ?></p>
	<?php endif; ?>
	<ul class="th-guides__cards" role="list">
		<?php foreach ( $latest as $guide ) : ?>
			<li>
				<?php
				get_template_part(
					'template-parts/guides/card',
					null,
					array(
						'post'    => $guide,
						'variant' => 'card',
					)
				);
				?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
