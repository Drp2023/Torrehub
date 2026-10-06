<?php
/**
 * Filter sheet (P-13): a GET form in a <dialog> — bottom sheet on mobile, centred panel on desktop.
 *
 * Works without JS (rendered open with ?th_sheet=1, plain submit). With JS: live "Show N results" count,
 * category change reloads with that category's fields, empty values are left out of the URL.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Archive\Search $search, @type int $total }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$th_search = $args['search'];
$total     = (int) $args['total'];
$defs      = $th_search->definitions();
$bounds    = $th_search->price_bounds();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
$is_open = ! empty( $_GET['th_sheet'] );

$cat_slug  = $th_search->category ? $th_search->category->slug : '';
$town_slug = $th_search->town ? $th_search->town->slug : '';

/* Category options: every category with listings (plus the selected one), indented by depth. */
$cat_options = array();
$walk        = static function ( array $nodes, int $depth ) use ( &$walk, &$cat_options, $cat_slug ) {
	foreach ( $nodes as $node ) {
		if ( $node['count'] > 0 || $node['slug'] === $cat_slug ) {
			$cat_options[ $node['slug'] ] = str_repeat( "\u{00A0}\u{00A0}\u{00A0}", $depth ) . $node['name']; // NBSP indent: no dashes in the closed select.
			$walk( $node['children'], $depth + 1 );
		}
	}
};
$walk( Directory::category_tree(), 0 );

$section_title = __( 'Details', 'torrehub' );
if ( $th_search->category ) {
	/* translators: %s: category name */
	$section_title = sprintf( __( '%s details', 'torrehub' ), html_entity_decode( $th_search->category->name, ENT_QUOTES ) );
}

$label = 0 === $total
	? __( 'No results', 'torrehub' )
	/* translators: %s: number of results */
	: sprintf( _n( 'Show %s result', 'Show %s results', $total, 'torrehub' ), number_format_i18n( $total ) );

$price_step = $bounds ? max( 1, 10 ** max( 0, (int) floor( log10( max( 1, $bounds['max'] ) ) ) - 2 ) ) : 1;
?>
<dialog class="th-dialog th-dialog--sheet th-filters" id="th-filters" data-th-dialog aria-labelledby="th-filters-title"<?php echo $is_open ? ' open' : ''; ?>>
	<form class="th-dialog__form" method="get" action="<?php echo esc_url( th_url_listings() ); ?>" data-th-filter-form>
		<div class="th-dialog__head">
			<h2 class="th-dialog__title" id="th-filters-title"><?php esc_html_e( 'Filters', 'torrehub' ); ?></h2>
			<div class="th-cluster">
				<a class="th-filters__clear" href="<?php echo esc_url( $th_search->url_cleared() ); ?>"><?php esc_html_e( 'Clear all', 'torrehub' ); ?></a>
				<?php
				th_component(
					'button',
					array(
						'variant'    => 'neutral',
						'size'       => 'sm',
						'icon'       => 'close',
						'icon_only'  => true,
						'aria_label' => __( 'Close filters', 'torrehub' ),
						'href'       => $is_open ? $th_search->url( array( 'page' => $th_search->page > 1 ? $th_search->page : null ) ) : '',
						'attrs'      => array( 'data-th-dialog-close' => '' ),
					)
				);
				?>
			</div>
		</div>

		<div class="th-dialog__body">
			<?php if ( '' !== $th_search->q ) : ?>
				<input type="hidden" name="q" value="<?php echo esc_attr( $th_search->q ); ?>">
			<?php endif; ?>
			<?php if ( 'grid' !== $th_search->view ) : ?>
				<input type="hidden" name="view" value="<?php echo esc_attr( $th_search->view ); ?>">
			<?php endif; ?>
			<?php if ( $th_search->orderby ) : ?>
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $th_search->orderby ); ?>">
			<?php endif; ?>

			<div class="th-sheet-section">
				<label class="th-sheet-section__title" for="th-f-category"><?php esc_html_e( 'Category', 'torrehub' ); ?></label>
				<select class="th-select th-filters__select" id="th-f-category" name="rtcl_category" data-th-category>
					<option value=""><?php esc_html_e( 'All categories', 'torrehub' ); ?></option>
					<?php foreach ( $cat_options as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cat_slug, $slug ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="th-sheet-section">
				<label class="th-sheet-section__title" for="th-f-town"><?php esc_html_e( 'Where', 'torrehub' ); ?></label>
				<select class="th-select th-filters__select" id="th-f-town" name="rtcl_location" data-th-town>
					<option value=""><?php esc_html_e( 'Anywhere on the coast', 'torrehub' ); ?></option>
					<?php foreach ( Directory::towns() as $town ) : ?>
						<option value="<?php echo esc_attr( $town['slug'] ); ?>" <?php selected( $town_slug, $town['slug'] ); ?>><?php echo esc_html( $town['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<fieldset class="th-filters__radius" data-th-radius<?php echo $th_search->town ? '' : ' hidden'; ?>>
					<legend class="th-sr-only"><?php esc_html_e( 'Distance from the town', 'torrehub' ); ?></legend>
					<div class="th-cluster">
						<?php
						$radii = array( 0 => __( 'This town only', 'torrehub' ) );
						foreach ( Torrehub\Modules\Archive\Search::RADII as $km ) {
							/* translators: %s: distance in km */
							$radii[ $km ] = sprintf( __( '%s km', 'torrehub' ), number_format_i18n( $km ) );
						}
						foreach ( $radii as $km => $radius_label ) {
							th_component(
								'chip',
								array(
									'type'       => 'choice',
									'variant'    => 'radius',
									'input_type' => 'radio',
									'name'       => 'radius',
									'value'      => (string) $km,
									'label'      => $radius_label,
									'checked'    => $th_search->radius === $km,
								)
							);
						}
						?>
					</div>
				</fieldset>
			</div>

			<?php if ( $bounds ) : ?>
				<?php
				$lo = $th_search->price_min ?? $bounds['min'];
				$hi = $th_search->price_max ?? $bounds['max'];
				?>
				<fieldset class="th-sheet-section" data-th-range-group>
					<legend class="th-sheet-section__title th-filters__range-head">
						<span><?php esc_html_e( 'Price', 'torrehub' ); ?></span>
						<span class="th-filters__range-out"><span data-th-range-out="lo"></span> – <span data-th-range-out="hi"></span></span>
					</legend>
					<div class="th-range" data-th-range data-prefix="€" data-plus="1">
						<span class="th-range__track"></span><span class="th-range__fill"></span>
						<input type="range" name="min_price" min="<?php echo esc_attr( (string) $bounds['min'] ); ?>" max="<?php echo esc_attr( (string) $bounds['max'] ); ?>" step="<?php echo esc_attr( (string) $price_step ); ?>" value="<?php echo esc_attr( (string) $lo ); ?>" aria-label="<?php esc_attr_e( 'Minimum price', 'torrehub' ); ?>" data-th-bound="<?php echo esc_attr( (string) $bounds['min'] ); ?>">
						<input type="range" name="max_price" min="<?php echo esc_attr( (string) $bounds['min'] ); ?>" max="<?php echo esc_attr( (string) $bounds['max'] ); ?>" step="<?php echo esc_attr( (string) $price_step ); ?>" value="<?php echo esc_attr( (string) $hi ); ?>" aria-label="<?php esc_attr_e( 'Maximum price', 'torrehub' ); ?>" data-th-bound="<?php echo esc_attr( (string) $bounds['max'] ); ?>">
					</div>
				</fieldset>
			<?php endif; ?>

			<?php if ( $defs ) : ?>
				<div class="th-sheet-section th-filters__fields">
					<p class="th-sheet-section__title th-filters__group-title"><?php echo esc_html( $section_title ); ?></p>
					<?php
					foreach ( $defs as $def ) {
						get_template_part(
							'template-parts/archive/filter-field',
							null,
							array(
								'def'   => $def,
								'value' => $th_search->fields[ $def['name'] ] ?? array(),
							)
						);
					}
					?>
				</div>
			<?php elseif ( ! $th_search->category ) : ?>
				<div class="th-sheet-section">
					<p class="th-help"><?php esc_html_e( 'Pick a category to filter by its details (make, rooms, cuisine…).', 'torrehub' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="th-sheet-section">
				<label class="th-toggle"><span><?php esc_html_e( 'Verified sellers only', 'torrehub' ); ?></span><input type="checkbox" role="switch" name="verified" value="1" <?php checked( $th_search->verified ); ?>></label>
			</div>
		</div>

		<div class="th-dialog__foot">
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Reset', 'torrehub' ),
					'variant' => 'outline',
					'href'    => $th_search->url_cleared(),
				)
			);
			th_component(
				'button',
				array(
					'label'   => $label,
					'variant' => 'accent',
					'type'    => 'submit',
					'attrs'   => array(
						'data-th-count' => '',
						'aria-live'     => 'polite',
					),
				)
			);
			?>
		</div>
	</form>
</dialog>
