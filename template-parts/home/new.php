<?php
/**
 * New near you (P-01): latest 4 published listings in the visitor's town; topped up from other towns when the
 * town has fewer than 4 (DECISION — chip then says "& nearby"). Mobile: first card + 2 compact rows.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

$th_town = th_current_town();
$limit   = 4;
$base    = array(
	'post_type'           => 'rtcl_listing',
	'post_status'         => 'publish',
	'posts_per_page'      => $limit,
	'orderby'             => 'date',
	'order'               => 'DESC',
	'no_found_rows'       => true,
	'ignore_sticky_posts' => true,
	'fields'              => 'ids',
);

$ids = array();
if ( $th_town ) {
	$ids = get_posts(
		$base + array(
			'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'rtcl_location',
					'field'    => 'slug',
					'terms'    => $th_town['slug'],
				),
			),
		)
	);
}
$local = count( $ids );
if ( $local < $limit ) {
	$ids = array_merge(
		$ids,
		get_posts(
			array_merge(
				$base,
				array(
					'posts_per_page' => $limit - $local,
					'post__not_in'   => $ids ? $ids : array( 0 ),
				)
			)
		)
	);
}
if ( ! $ids ) {
	return;
}

if ( $th_town && $local >= $limit ) {
	$chip = $th_town['name'];
} elseif ( $th_town && $local > 0 ) {
	/* translators: %s: town name */
	$chip = sprintf( __( '%s & nearby', 'torrehub' ), $th_town['name'] );
} else {
	$chip = __( 'Costa Blanca', 'torrehub' );
}
?>
<section class="th-container th-section th-home__new" aria-labelledby="th-new-title">
	<?php
	th_component(
		'section-head',
		array(
			'title' => __( 'New near you', 'torrehub' ),
			'id'    => 'th-new-title',
			'chip'  => $chip,
			'link'  => th_url_listings( $th_town && $local ? array( 'rtcl_location' => $th_town['slug'] ) : array() ),
		)
	);
	?>
	<ul class="th-home__cards" role="list">
		<?php
		foreach ( $ids as $i => $listing_id ) :
			$card = th_listing_card_args( $listing_id, array( 'heading' => 'h3' ) );
			if ( ! $card ) {
				continue;
			}
			?>
			<li class="th-home__card">
				<?php th_component( 'listing-card', $card ); ?>
				<?php if ( $i > 0 ) : ?>
					<?php
					th_component(
						'listing-card',
						array_merge(
							$card,
							array(
								'variant'    => 'row',
								'image_size' => 'th-card-sm',
								'attrs'      => array(),
								'badges'     => array(),
							)
						)
					);
					?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
