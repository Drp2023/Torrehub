<?php
/**
 * My listings (override): status tabs, rows with edit/delete, pagination. Query from Classified Listing.
 *
 * @package Torrehub
 *
 * @var WP_Query $rtcl_query
 */

defined( 'ABSPATH' ) || exit;

use Rtcl\Helpers\Link;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters / notices.
$active  = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : 'any';
$deleted = isset( $_GET['th_deleted'] ) ? sanitize_key( wp_unslash( $_GET['th_deleted'] ) ) : '';
// phpcs:enable
$base        = Link::get_account_endpoint_url( 'listings' );
$status_tabs = array(
	'any'          => __( 'All', 'torrehub' ),
	'publish'      => __( 'Active', 'torrehub' ),
	'pending'      => __( 'Pending', 'torrehub' ),
	'rtcl-expired' => __( 'Expired', 'torrehub' ),
);
?>
<div class="th-stack">
	<div class="th-account-dash__head th-cluster" style="justify-content:space-between">
		<h1 class="th-account__title"><?php esc_html_e( 'My listings', 'torrehub' ); ?></h1>
		<?php
		th_component(
			'button',
			array(
				'label'   => __( 'Post a listing', 'torrehub' ),
				'variant' => 'accent',
				'icon'    => 'plus',
				'href'    => th_url_post_listing(),
			)
		);
		?>
	</div>

	<?php get_template_part( 'template-parts/listing-form/drafts', null, array( 'back' => $base ) ); ?>

	<?php if ( '1' === $deleted ) : ?>
		<?php
		th_component(
			'alert',
			array(
				'variant' => 'success',
				'text'    => __( 'Listing deleted.', 'torrehub' ),
				'role'    => 'status',
			)
		);
		?>
	<?php elseif ( '0' === $deleted ) : ?>
		<?php
		th_component(
			'alert',
			array(
				'variant' => 'error',
				'text'    => __( 'That listing couldn’t be deleted.', 'torrehub' ),
			)
		);
		?>
	<?php endif; ?>

	<nav class="th-cluster" aria-label="<?php esc_attr_e( 'Filter by status', 'torrehub' ); ?>">
		<?php foreach ( $status_tabs as $value => $label ) : ?>
			<a class="th-chip th-chip--topic" href="<?php echo esc_url( 'any' === $value ? $base : add_query_arg( 'status', $value, $base ) ); ?>"<?php echo $active === $value ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( $rtcl_query->have_posts() ) : ?>
		<div class="th-stack" style="--th-stack-gap:10px">
			<?php
			foreach ( $rtcl_query->posts as $listing_post ) {
				get_template_part( 'template-parts/account/listing-row', null, array( 'post' => $listing_post ) );
			}
			?>
		</div>
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
	<?php else : ?>
		<?php
		th_component(
			'empty-state',
			array(
				'icon'    => 'file',
				'title'   => 'any' === $active ? __( 'No listings yet', 'torrehub' ) : __( 'Nothing here', 'torrehub' ),
				'text'    => 'any' === $active ? __( 'Your listings will show up here once you post them.', 'torrehub' ) : __( 'No listings with this status.', 'torrehub' ),
				'actions' => 'any' === $active ? array(
					array(
						'label'   => __( 'Post a listing', 'torrehub' ),
						'variant' => 'accent',
						'href'    => th_url_post_listing(),
					),
				) : array(),
			)
		);
		?>
	<?php endif; ?>
</div>
