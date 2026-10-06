<?php
/**
 * Amenities & features: ✓ chips from yes-checkboxes, multi-checkboxes and repeater rows.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

$view = $args['view'];
if ( ! $view->fields['features'] ) {
	return;
}
?>
<section class="th-listing-card" id="features" aria-labelledby="features-title">
	<h2 class="th-listing-card__title" id="features-title"><?php esc_html_e( 'Amenities & features', 'torrehub' ); ?></h2>
	<ul class="th-cluster th-listing__features" role="list">
		<?php foreach ( $view->fields['features'] as $feature ) : ?>
			<li>
				<?php
				th_component(
					'chip',
					array(
						'label'   => $feature,
						'variant' => 'amenity',
						'icon'    => 'check',
					)
				);
				?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
