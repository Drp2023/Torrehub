<?php
/**
 * "More in {category}" — up to 4 related listings (design review: not drawn on L-01; L-17 links to similar ones).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

$view = $args['view'];
$ids  = $view->related( 4 );
if ( ! $ids ) {
	return;
}
// Related listings come from the whole root category (closest first), so the heading names the root.
$rel_term = $view->root ? $view->root : $view->category;
?>
<section class="th-section th-listing-related" aria-labelledby="related-title">
	<?php
	th_component(
		'section-head',
		array(
			/* translators: %s: category name */
			'title' => sprintf( __( 'More in %s', 'torrehub' ), html_entity_decode( $rel_term->name, ENT_QUOTES ) ),
			'id'    => 'related-title',
			'link'  => (string) get_term_link( $rel_term ),
		)
	);
	?>
	<ul class="th-grid th-listing-related__list" role="list" style="--th-cols-lg:4;--th-grid-gap:16px">
		<?php foreach ( $ids as $listing_id ) : ?>
			<?php
			$card = th_listing_card_args( $listing_id, array( 'heading' => 'h3' ) );
			if ( ! $card ) {
				continue;
			}
			?>
			<li class="th-listing-related__item"><?php th_component( 'listing-card', $card ); ?></li>
		<?php endforeach; ?>
	</ul>
</section>
