<?php
/**
 * Map view (P-09): the result list next to an OpenStreetMap map (Leaflet, loaded on demand by map.js).
 * Desktop: list left, sticky map right. Mobile: map first, list below; tapping a pin shows that listing's card.
 * Without JS the list alone is the result (the map area explains that it needs JavaScript).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Archive\Search $search, @type int $total }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Archive\MapData;
use Torrehub\Modules\Archive\Search;

global $wp_query;
$th_search = $args['search'];
$total     = (int) $args['total'];

if ( 0 === $total ) {
	get_template_part( 'template-parts/archive/empty', null, $args );
	return;
}

$cards = array();
foreach ( $wp_query->posts as $listing_post ) {
	$card = th_listing_card_args(
		$listing_post,
		array(
			'heading'    => 'h2',
			'variant'    => 'row',
			'image_size' => 'th-card-sm',
		)
	);
	if ( $card ) {
		$cards[] = $card;
	}
}
$pins     = MapData::pins( $cards );
$approx   = count( array_filter( array_column( $pins, 'approx' ) ) );
$exact    = count( $pins ) - $approx;
$unmapped = count( $cards ) - count( $pins );
$center   = th_town_coordinates()[ $th_search->town ? $th_search->town->slug : 'torrevieja' ] ?? array( 37.978, -0.683 );
?>
<div class="th-mapview" data-th-map>
	<div class="th-mapview__map">
		<div class="th-mapview__canvas" data-th-map-canvas role="region" aria-label="<?php esc_attr_e( 'Map of the results', 'torrehub' ); ?>">
			<noscript><p class="th-mapview__noscript"><?php esc_html_e( 'The map needs JavaScript. The results are listed below.', 'torrehub' ); ?></p></noscript>
		</div>
		<div class="th-mapview__controls" data-th-map-controls hidden>
			<?php
			foreach ( array(
				'locate'   => array( 'locate', __( 'Show my location', 'torrehub' ) ),
				'zoom-in'  => array( 'plus', __( 'Zoom in', 'torrehub' ) ),
				'zoom-out' => array( 'minus', __( 'Zoom out', 'torrehub' ) ),
			) as $control => $meta ) {
				th_component(
					'button',
					array(
						'variant'    => 'white',
						'icon'       => $meta[0],
						'icon_only'  => true,
						'aria_label' => $meta[1],
						'class'      => 'th-mapview__control',
						'attrs'      => array( 'data-th-map-action' => $control ),
					)
				);
			}
			?>
		</div>
		<div class="th-mapview__card" data-th-map-card aria-live="polite"></div>
		<?php if ( $approx || $unmapped ) : ?>
			<p class="th-mapview__note">
				<?php
				if ( $approx && ! $exact ) {
					esc_html_e( 'Pins show the town, not the exact address.', 'torrehub' );
				} elseif ( $approx ) {
					/* translators: %s: number of listings */
					echo esc_html( sprintf( _n( '%s pin shows only the town, not the exact address.', '%s pins show only the town, not the exact address.', $approx, 'torrehub' ), number_format_i18n( $approx ) ) );
				}
				if ( $unmapped ) {
					echo ' ';
					/* translators: %s: number of listings */
					echo esc_html( sprintf( _n( '%s listing has no location and isn’t on the map.', '%s listings have no location and aren’t on the map.', $unmapped, 'torrehub' ), number_format_i18n( $unmapped ) ) );
				}
				?>
			</p>
		<?php endif; ?>
	</div>

	<ul class="th-mapview__list" role="list" data-th-map-list>
		<?php foreach ( $cards as $card ) : ?>
			<li class="th-mapview__item" data-th-pin-id="<?php echo esc_attr( (string) $card['listing_id'] ); ?>">
				<?php th_component( 'listing-card', $card ); ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( $total > Search::MAP_LIMIT ) : ?>
		<p class="th-mapview__note">
			<?php
			/* translators: 1: shown, 2: total */
			echo esc_html( sprintf( __( 'Showing the first %1$s of %2$s. Narrow the search to see the rest.', 'torrehub' ), number_format_i18n( Search::MAP_LIMIT ), number_format_i18n( $total ) ) );
			?>
		</p>
	<?php endif; ?>

	<script type="application/json" data-th-map-data>
	<?php
	echo wp_json_encode(
		array(
			'center' => $center,
			'pins'   => $pins,
		),
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
	);
	?>
	</script>
</div>
