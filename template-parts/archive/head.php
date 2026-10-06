<?php
/**
 * Archive head: breadcrumb, H1, intro, sub-category chips.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Archive\Search $search }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$th_search = $args['search'];

$intro = '';
if ( $th_search->category && ! $th_search->town ) {
	$intro = wp_strip_all_tags( term_description( $th_search->category ) );
} elseif ( $th_search->town && ! $th_search->category ) {
	$intro = wp_strip_all_tags( term_description( $th_search->town ) );
}

// Sub-categories of the current category, or the roots on the unfiltered archive.
$subs = array();
if ( $th_search->category ) {
	$node = Directory::category_node( (int) $th_search->category->term_id );
	$subs = $node ? $node['children'] : array();
} else {
	$subs = Directory::category_tree();
}
$subs = array_values( array_filter( $subs, static fn( $n ) => $n['count'] > 0 ) );
?>
<header class="th-archive__head">
	<?php get_template_part( 'template-parts/components/breadcrumb', null, array( 'items' => $th_search->breadcrumb() ) ); ?>
	<div class="th-archive__title-row">
		<div class="th-stack" style="--th-stack-gap:8px">
			<h1 class="th-archive__title"><?php echo esc_html( $th_search->heading() ); ?></h1>
			<?php if ( '' !== trim( $intro ) ) : ?>
				<p class="th-archive__intro"><?php echo esc_html( wp_html_excerpt( $intro, 220, '…' ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		/**
		 * Actions next to the archive heading (the SearchAlerts module adds "Save this search" in phase 7).
		 *
		 * @param Torrehub\Modules\Archive\Search $search Current search.
		 */
		do_action( 'th_archive_head_actions', $th_search );
		?>
	</div>

	<?php if ( $subs ) : ?>
		<nav class="th-archive__subs" aria-label="<?php echo esc_attr( $th_search->category ? __( 'Sub-categories', 'torrehub' ) : __( 'Categories', 'torrehub' ) ); ?>">
			<ul class="th-scroller" role="list">
				<?php foreach ( $subs as $sub ) : ?>
					<li>
						<a class="th-chip th-chip--topic" href="<?php echo esc_url( $th_search->url_for_category( $sub['slug'] ) ); ?>">
							<?php echo esc_html( $sub['name'] ); ?>
							<span class="th-archive__sub-count"><?php echo esc_html( number_format_i18n( $sub['count'] ) ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</header>
