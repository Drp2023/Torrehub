<?php
/**
 * T-06 — page not found: search, back home, browse categories (live listing count).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();
$listings = th_has_rtcl() ? (int) \Torrehub\Data\Directory::stats()['listings'] : 0;
?>
<main id="main" class="th-main th-section" tabindex="-1">
	<div class="th-container">
		<div class="th-error-panel th-error-panel--page">
			<span class="th-error-panel__code" aria-hidden="true">404</span>
			<h1 class="th-error-panel__title"><?php esc_html_e( 'This page has moved on', 'torrehub' ); ?></h1>
			<p>
				<?php
				echo esc_html(
					$listings
						/* translators: %s: number of live listings */
						? sprintf( _n( 'The listing may have expired, or the address has a typo. There is %s live listing — try a search.', 'The listing may have expired, or the address has a typo. There are %s live listings — try a search.', $listings, 'torrehub' ), number_format_i18n( $listings ) )
						: __( 'The address may have a typo, or the page was removed.', 'torrehub' )
				);
				?>
			</p>
			<?php
			if ( th_has_rtcl() ) {
				get_template_part(
					'template-parts/header/search',
					null,
					array(
						'class' => 'th-error-panel__search',
						'id'    => 'th-404-q',
					)
				);
			}
			?>
			<div class="th-cluster">
				<?php
				th_component(
					'button',
					array(
						'label'   => __( 'Back to homepage', 'torrehub' ),
						'variant' => 'ink',
						'href'    => home_url( '/' ),
					)
				);
				if ( th_has_rtcl() ) {
					th_component(
						'button',
						array(
							'label'   => __( 'Browse all categories', 'torrehub' ),
							'variant' => 'white',
							'href'    => th_url_listings(),
						)
					);
				}
				?>
			</div>
		</div>
	</div>
</main>
<?php
get_footer();
