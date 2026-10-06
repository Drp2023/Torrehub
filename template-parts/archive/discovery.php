<?php
/**
 * Map discovery module (D-ARCHIVE / M-ARCHIVE): a static preview of the result pins + "Open map view".
 * Pure CSS — no map library is loaded until the visitor opens the map.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Archive\Search $search, @type int $total, @type array $pins }
 */

defined( 'ABSPATH' ) || exit;

$th_search = $args['search'];
$total     = (int) $args['total'];
$pins      = array_slice( (array) $args['pins'], 0, 6 );

$lats = array_column( $pins, 'lat' );
$lngs = array_column( $pins, 'lng' );
$lat0 = min( $lats );
$lat1 = max( $lats );
$lng0 = min( $lngs );
$lng1 = max( $lngs );
// Position pins inside a 12–88 % box (north up).
$pos   = static function ( float $v, float $lo, float $hi ): float {
	return $hi - $lo < 1e-6 ? 50.0 : 12 + 76 * ( $v - $lo ) / ( $hi - $lo );
};
$towns = count( array_unique( array_map( static fn( $p ) => round( $p['lat'], 1 ) . ',' . round( $p['lng'], 1 ), (array) $args['pins'] ) ) );
?>
<section class="th-discovery" aria-labelledby="th-discovery-title">
	<div class="th-discovery__map" aria-hidden="true">
		<?php foreach ( $pins as $i => $pin ) : ?>
			<span class="
			<?php
			echo esc_attr(
				th_classes(
					'th-pin',
					array(
						'th-pin--accent' => $pin['featured'],
						'th-pin--white'  => ! $pin['featured'] && 1 === $i % 2,
					)
				)
			);
			?>
							" style="<?php echo esc_attr( sprintf( 'left:%.1f%%;top:%.1f%%', $pos( $pin['lng'], $lng0, $lng1 ), 100 - $pos( $pin['lat'], $lat0, $lat1 ) ) ); ?>"><?php echo esc_html( $pin['label'] ); ?></span>
		<?php endforeach; ?>
	</div>
	<div class="th-discovery__body">
		<p class="th-discovery__eyebrow"><?php esc_html_e( 'Map view', 'torrehub' ); ?></p>
		<h2 class="th-discovery__title" id="th-discovery-title">
			<?php
			/* translators: %s: number of results */
			echo esc_html( sprintf( _n( 'See %s listing on the map', 'See all %s on the map', $total, 'torrehub' ), number_format_i18n( $total ) ) );
			?>
		</h2>
		<p class="th-discovery__text">
			<?php
			/* translators: %s: number of towns */
			echo esc_html( sprintf( _n( 'Spread over %s town — see what’s closest to you.', 'Spread over %s towns — see what’s closest to you.', $towns, 'torrehub' ), number_format_i18n( $towns ) ) );
			?>
		</p>
		<?php
		th_component(
			'button',
			array(
				'label'   => __( 'Open map view', 'torrehub' ),
				'variant' => 'white',
				'block'   => true,
				'href'    => $th_search->url( array( 'view' => 'map' ) ),
			)
		);
		?>
	</div>
</section>
