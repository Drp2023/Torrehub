<?php
/**
 * Grid / list results: cards, the map discovery module (3rd cell, page 1), end-of-results card, empty state,
 * "Load N more" (a plain link to the next page; archive.js appends it in place).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Archive\Search $search, @type int $total }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Archive\MapData;

global $wp_query;
$th_search  = $args['search'];
$total      = (int) $args['total'];
$page_count = (int) $wp_query->max_num_pages;
$is_row     = 'list' === $th_search->view;

if ( 0 === $total ) {
	get_template_part( 'template-parts/archive/empty', null, $args );
	return;
}

$cards = array();
foreach ( $wp_query->posts as $i => $listing_post ) {
	$card = th_listing_card_args(
		$listing_post,
		array(
			'heading' => 'h2',
			'eager'   => 1 === $th_search->page && 0 === $i,
		)
	);
	if ( ! $card ) {
		continue;
	}
	if ( $is_row ) {
		$card['variant']    = 'row';
		$card['image_size'] = 'th-card-sm';
	} else {
		$card['actions'] = array(
			array(
				'label' => __( 'Contact', 'torrehub' ),
				'icon'  => 'phone',
				'href'  => $card['url'] . '#contact',
				'block' => true,
				'attrs' => array(
					/* translators: %s: listing title */
					'aria-label' => sprintf( __( 'Contact: %s', 'torrehub' ), $card['title'] ),
				),
			),
		);
	}
	$cards[] = $card;
}

$pins      = ( ! $is_row && 1 === $th_search->page ) ? MapData::pins( $cards ) : array();
$discovery = count( $pins ) >= 2 && count( $cards ) >= 2 ? min( 2, count( $cards ) ) : -1;
$last_page = $th_search->page >= $page_count;
$remaining = max( 0, $total - $th_search->page * (int) $wp_query->get( 'posts_per_page' ) );
$next      = min( $remaining, (int) $wp_query->get( 'posts_per_page' ) );
?>
<ul class="<?php echo esc_attr( th_classes( 'th-results', $is_row ? 'th-results--list' : 'th-results--grid' ) ); ?>" role="list" data-th-results>
	<?php foreach ( $cards as $i => $card ) : ?>
		<?php if ( $i === $discovery ) : ?>
			<li class="th-results__item th-results__item--discovery">
				<?php
				get_template_part(
					'template-parts/archive/discovery',
					null,
					array(
						'search' => $th_search,
						'total'  => $total,
						'pins'   => $pins,
					)
				);
				?>
			</li>
		<?php endif; ?>
		<li class="th-results__item">
			<?php th_component( 'listing-card', $card ); ?>
		</li>
	<?php endforeach; ?>

	<?php if ( $last_page && ( $th_search->page > 1 || $total > 2 ) ) : ?>
		<li class="th-results__item th-results__item--end">
			<?php get_template_part( 'template-parts/archive/end', null, $args ); ?>
		</li>
	<?php endif; ?>
</ul>

<p class="th-sr-only" role="status" aria-live="polite" data-th-results-status></p>

<?php if ( ! $last_page ) : ?>
	<div class="th-load-more" data-th-more-wrap>
		<?php
		th_component(
			'button',
			array(
				/* translators: %s: number of further listings */
				'label'   => sprintf( _n( 'Load %s more', 'Load %s more', $next, 'torrehub' ), number_format_i18n( $next ) ),
				'variant' => 'outline',
				'size'    => 'lg',
				'href'    => $th_search->url( array( 'page' => $th_search->page + 1 ) ),
				'attrs'   => array( 'data-th-load-more' => '' ),
			)
		);
		?>
	</div>
<?php endif; ?>

<?php if ( $th_search->page > 1 ) : ?>
	<p class="th-results__prev">
		<a href="<?php echo esc_url( $th_search->url( array( 'page' => $th_search->page > 2 ? $th_search->page - 1 : null ) ) ); ?>" rel="prev"><?php esc_html_e( '← Previous results', 'torrehub' ); ?></a>
	</p>
<?php endif; ?>

<template data-th-skeleton>
	<li class="th-results__item th-results__item--skeleton"><?php th_component( 'skeleton-card' ); ?></li>
</template>
