<?php
/**
 * Account › Favourites (override): the saved listings as cards (heart pressed — tapping it removes the listing from
 * the list), pagination, empty state. Query from Classified Listing (its `rtcl_favourites` user meta, live listings).
 *
 * @package Torrehub
 *
 * @var WP_Query $rtcl_query
 */

defined( 'ABSPATH' ) || exit;

use Rtcl\Helpers\Link;

$base  = Link::get_account_endpoint_url( 'favourites' );
$total = (int) $rtcl_query->found_posts;
?>
<div class="th-stack">
	<div class="th-account-dash__head">
		<h1 class="th-account__title"><?php esc_html_e( 'Favourites', 'torrehub' ); ?></h1>
		<?php if ( $total ) : ?>
			<p class="th-account-dash__sub">
				<?php
				/* translators: %s: number of listings */
				echo esc_html( sprintf( _n( '%s saved listing', '%s saved listings', $total, 'torrehub' ), number_format_i18n( $total ) ) );
				?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( $rtcl_query->have_posts() ) : ?>
		<ul class="th-grid th-favourites" role="list" style="--th-cols-sm:1;--th-cols-md:2;--th-cols-lg:3" data-th-fav-list>
			<?php foreach ( $rtcl_query->posts as $listing_post ) : ?>
				<li class="th-favourites__item">
					<?php
					// Classified Listing rewrites get_the_title() on account pages ("My Favourites Listings").
					th_component( 'listing-card', th_listing_card_args( $listing_post, array( 'title' => html_entity_decode( (string) get_post_field( 'post_title', $listing_post ), ENT_QUOTES ) ) ) );
					?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		$links = paginate_links(
			array(
				'base'    => trailingslashit( $base ) . '%_%',
				'format'  => 'page/%#%/',
				'current' => max( 1, (int) $rtcl_query->get( 'paged' ) ),
				'total'   => (int) $rtcl_query->max_num_pages,
				'type'    => 'list',
			)
		);
		if ( $links ) {
			echo '<nav class="th-pagination" aria-label="' . esc_attr__( 'Pages', 'torrehub' ) . '">' . wp_kses_post( $links ) . '</nav>';
		}
		?>
	<?php endif; ?>

	<div data-th-fav-empty<?php echo $rtcl_query->have_posts() ? ' hidden' : ''; ?>>
		<?php
		th_component(
			'empty-state',
			array(
				'icon'    => 'heart',
				'title'   => __( 'No favourites yet', 'torrehub' ),
				'text'    => __( 'Tap the heart on a listing to keep it here.', 'torrehub' ),
				'actions' => array(
					array(
						'label'   => __( 'Browse listings', 'torrehub' ),
						'variant' => 'primary',
						'href'    => th_url_listings(),
					),
				),
			)
		);
		?>
	</div>
</div>
