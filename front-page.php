<?php
/**
 * Home (P-01). Every block can be switched off in Customizer → Torrehub → Home page.
 *
 * Applies to whatever page is set as the static front page (live site: currently "Coming Soon", see BUILD-PLAN Q).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="th-main th-home" tabindex="-1">
	<?php if ( th_has_rtcl() ) : ?>
		<div class="th-container th-home__top">
			<?php
			if ( th_mod( 'th_home_hero' ) ) {
				get_template_part( 'template-parts/home/hero' );
			}
			?>
			<div class="th-home__side">
				<?php
				if ( th_mod( 'th_home_weekend' ) ) {
					get_template_part( 'template-parts/home/weekend' );
				}
				if ( th_mod( 'th_home_stats' ) ) {
					get_template_part( 'template-parts/home/stats' );
				}
				?>
			</div>
		</div>
		<?php
		foreach ( array( 'explore', 'new', 'towns' ) as $block ) {
			if ( th_mod( 'th_home_' . $block ) ) {
				get_template_part( 'template-parts/home/' . $block );
			}
		}
		?>
	<?php endif; ?>

	<?php if ( th_mod( 'th_home_sellers' ) || th_mod( 'th_home_why' ) ) : ?>
		<div class="th-container th-home__bottom">
			<?php
			if ( th_mod( 'th_home_sellers' ) ) {
				get_template_part( 'template-parts/home/sellers' );
			}
			if ( th_mod( 'th_home_why' ) ) {
				get_template_part( 'template-parts/home/why' );
			}
			?>
		</div>
	<?php endif; ?>

	<?php
	// Page content of the static front page, if the editor added any.
	while ( have_posts() ) {
		the_post();
		if ( '' !== trim( (string) get_post_field( 'post_content', get_the_ID() ) ) && ! get_post_meta( get_the_ID(), '_elementor_edit_mode', true ) ) {
			echo '<div class="th-container th-section th-prose">';
			the_content();
			echo '</div>';
		}
	}
	?>
</main>
<?php
get_footer();
