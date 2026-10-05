<?php
/**
 * Explore the hub (P-01): 10 root tiles with real listing / sub-category counts.
 * Mobile shows 6 + a toggle for the rest (design: "All 10 →").
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$tree = Directory::category_tree();
if ( ! $tree ) {
	return;
}
$cat_map = th_category_map();
?>
<section class="th-container th-section th-home__explore" aria-labelledby="th-explore-title">
	<?php
	th_component(
		'section-head',
		array(
			'title'     => __( 'Explore the hub', 'torrehub' ),
			'id'        => 'th-explore-title',
			'link'      => th_url_listings(),
			/* translators: %s: number of categories */
			'link_text' => sprintf( __( 'All %s categories', 'torrehub' ), number_format_i18n( Directory::stats()['categories'] ) ),
		)
	);
	?>
	<ul class="th-grid th-home__tiles" id="th-home-tiles" role="list" style="--th-cols-sm:2;--th-cols-md:3;--th-cols-lg:5">
		<?php foreach ( $tree as $root ) : ?>
			<li>
				<?php
				th_component(
					'cat-tile',
					array(
						'name' => $root['name'],
						'url'  => $root['url'],
						'icon' => $cat_map[ $root['slug'] ]['icon'] ?? 'info',
						'tint' => $cat_map[ $root['slug'] ]['tint'] ?? 'grey',
						'meta' => sprintf(
							/* translators: 1: listings, 2: sub-categories */
							__( '%1$s · %2$s', 'torrehub' ),
							/* translators: %s: number of listings */
							sprintf( _n( '%s listing', '%s listings', $root['count'], 'torrehub' ), number_format_i18n( $root['count'] ) ),
							/* translators: %s: number of sub-categories */
							sprintf( _n( '%s category', '%s categories', $root['descendants'], 'torrehub' ), number_format_i18n( $root['descendants'] ) )
						),
					)
				);
				?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( count( $tree ) > 6 ) : ?>
		<button type="button" class="th-link-btn th-home__more" aria-expanded="false" aria-controls="th-home-tiles" data-th-reveal="th-home-tiles">
			<?php
			/* translators: %s: number of root categories */
			echo esc_html( sprintf( __( 'Show all %s', 'torrehub' ), number_format_i18n( count( $tree ) ) ) );
			?>
		</button>
	<?php endif; ?>
</section>
