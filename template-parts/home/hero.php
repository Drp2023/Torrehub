<?php
/**
 * Home hero (P-01): eyebrow with real town count, H1 (the page's only h1), lead, search.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$stats = Directory::stats();
?>
<section class="th-module th-module--hero th-home__hero" aria-labelledby="th-home-title">
	<span class="th-module__eyebrow-pill">
		<?php
		/* translators: %s: number of towns */
		echo esc_html( sprintf( __( 'Costa Blanca · %s towns', 'torrehub' ), number_format_i18n( $stats['towns'] ) ) );
		?>
	</span>
	<h1 class="th-module__title" id="th-home-title"><?php echo esc_html( th_mod( 'th_hero_title' ) ); ?></h1>
	<p class="th-lead"><?php echo esc_html( th_mod( 'th_hero_lead' ) ); ?></p>
	<?php
	get_template_part(
		'template-parts/header/search',
		null,
		array(
			'variant'     => 'hero',
			'id'          => 'th-hero-q',
			'placeholder' => __( 'Try “plumber” or “long-term rental”', 'torrehub' ),
		)
	);
	?>
</section>
