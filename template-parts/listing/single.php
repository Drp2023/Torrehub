<?php
/**
 * Single listing (L-01 desktop, L-01/L-15 mobile, L-17 expired, L-18 pending).
 *
 * Main column: gallery, header, section tabs, details, about, features, video, location + hours, reviews, related.
 * Side column (desktop, sticky): contact module, seller card, safety note. Mobile: sticky contact bar + sheet.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Listing\View;

get_header();

while ( have_posts() ) :
	the_post();
	$view  = new View( get_post() );
	$parts = array( 'view' => $view );
	?>
	<main id="main" class="th-main th-listing" tabindex="-1" data-th-listing>
		<div class="th-container">
			<div class="th-listing__top">
				<?php get_template_part( 'template-parts/components/breadcrumb', null, array( 'items' => $view->breadcrumb() ) ); ?>
				<a class="th-listing__back" href="<?php echo esc_url( th_url_listings() ); ?>" data-th-back hidden><?php th_icon( 'arrow-left', array( 'size' => 14 ) ); ?> <span><?php esc_html_e( 'Back to results', 'torrehub' ); ?></span></a>
			</div>

			<?php get_template_part( 'template-parts/listing/notices', null, $parts ); ?>

			<div class="th-listing__layout">
				<div class="th-listing__main">
					<?php
					get_template_part( 'template-parts/listing/gallery', null, $parts );
					get_template_part( 'template-parts/listing/header', null, $parts );
					get_template_part( 'template-parts/listing/tabs', null, $parts );
					get_template_part( 'template-parts/listing/details', null, $parts );
					get_template_part( 'template-parts/listing/about', null, $parts );
					get_template_part( 'template-parts/listing/features', null, $parts );
					get_template_part( 'template-parts/listing/place', null, $parts );
					if ( Torrehub\Modules\Reviews\Module::active() ) {
						get_template_part( 'template-parts/listing/reviews', null, $parts );
					}
					?>
				</div>
				<aside class="th-listing__side" aria-label="<?php esc_attr_e( 'Contact and seller', 'torrehub' ); ?>">
					<?php
					get_template_part( 'template-parts/listing/contact', null, $parts );
					get_template_part( 'template-parts/listing/seller', null, $parts );
					get_template_part( 'template-parts/listing/safety', null, $parts );
					?>
				</aside>
			</div>

			<?php get_template_part( 'template-parts/listing/related', null, $parts ); ?>
		</div>

		<?php
		if ( $view->is_public() && ! $view->is_owner ) {
			get_template_part( 'template-parts/listing/contact-bar', null, $parts );
		}
		get_template_part( 'template-parts/listing/report-dialog', null, $parts );
		?>
	</main>
	<?php
endwhile;

get_footer();
