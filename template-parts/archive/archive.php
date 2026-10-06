<?php
/**
 * Listing archive (P-03 / P-04 / P-09 / P-10 / P-13): /listings/, category, town and tag archives, search results.
 *
 * The main query is Classified Listing's, refined by Torrehub\Modules\Archive\Search (see Module::apply_search).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Archive\Search;

global $wp_query;
$th_search = Search::current();
$total     = (int) $wp_query->found_posts;
$parts     = array(
	'search' => $th_search,
	'total'  => $total,
);

get_header();
?>
<main id="main" class="<?php echo esc_attr( th_classes( 'th-main', 'th-archive', 'th-archive--' . $th_search->view ) ); ?>" tabindex="-1" data-th-archive>
	<div class="th-container">
		<?php
		get_template_part( 'template-parts/archive/head', null, $parts );
		get_template_part( 'template-parts/archive/filter-bar', null, $parts );
		get_template_part( 'template-parts/archive/toolbar', null, $parts );

		if ( 'map' === $th_search->view ) {
			get_template_part( 'template-parts/archive/map-view', null, $parts );
		} else {
			get_template_part( 'template-parts/archive/results', null, $parts );
		}
		?>
	</div>
	<?php get_template_part( 'template-parts/archive/filter-sheet', null, $parts ); ?>
</main>
<?php
get_footer();
