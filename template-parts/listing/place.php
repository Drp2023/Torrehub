<?php
/**
 * Location (map card) + opening hours side by side; either may be missing.
 * Hours: today highlighted, Open/Closed pill; on mobile collapsed to "Today · 08:00 – 20:00" (details element).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\BusinessHours;

$view  = $args['view'];
$point = $view->point;
$hours = $view->hours;
if ( ! $point && ! $hours ) {
	return;
}
$town    = $view->town ? html_entity_decode( $view->town->name, ENT_QUOTES ) : '';
$address = trim( implode( ', ', array_filter( array( $view->fields['address'], $town ) ) ) );
$maps    = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $point && ! $point['approx'] ? $point['lat'] . ',' . $point['lng'] : $address . ', Alicante, Spain' );
?>
<div class="<?php echo esc_attr( th_classes( 'th-listing-place', array( 'th-listing-place--single' => ! $point || ! $hours ) ) ); ?>">
	<?php if ( $point ) : ?>
		<section class="th-listing-card th-listing-map" id="location" aria-labelledby="location-title">
			<h2 class="th-sr-only" id="location-title"><?php esc_html_e( 'Location', 'torrehub' ); ?></h2>
			<div class="th-listing-map__canvas" data-th-map-single data-lat="<?php echo esc_attr( (string) $point['lat'] ); ?>" data-lng="<?php echo esc_attr( (string) $point['lng'] ); ?>" data-approx="<?php echo $point['approx'] ? '1' : '0'; ?>" role="region" aria-label="<?php esc_attr_e( 'Map', 'torrehub' ); ?>">
				<noscript><p class="th-mapview__noscript"><?php esc_html_e( 'The map needs JavaScript.', 'torrehub' ); ?></p></noscript>
			</div>
			<div class="th-listing-map__card">
				<?php if ( $address ) : ?>
					<p class="th-listing-map__address"><?php echo esc_html( $address ); ?></p>
				<?php endif; ?>
				<?php if ( $point['approx'] ) : ?>
					<p class="th-listing-map__note"><?php esc_html_e( 'Approximate location: the town, not the exact address.', 'torrehub' ); ?></p>
				<?php endif; ?>
				<a href="<?php echo esc_url( $maps ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in Google Maps', 'torrehub' ); ?> <span aria-hidden="true">→</span></a>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $hours ) : ?>
		<?php
		$week    = BusinessHours::week( $hours );
		$today   = array_values( array_filter( $week, static fn( $r ) => $r['today'] ) )[0] ?? null;
		$is_open = (bool) ( $view->open['open'] ?? false );
		?>
		<section class="th-listing-card th-listing-hours" id="hours" aria-labelledby="hours-title">
			<div class="th-listing-card__head">
				<h2 class="th-listing-card__title" id="hours-title"><?php esc_html_e( 'Opening hours', 'torrehub' ); ?></h2>
				<?php
				th_component(
					'badge',
					array(
						'label'   => $is_open ? __( 'Open', 'torrehub' ) : __( 'Closed', 'torrehub' ),
						'variant' => $is_open ? 'success' : 'neutral',
					)
				);
				?>
			</div>
			<details class="th-listing-hours__details" data-th-hours open>
				<summary class="th-listing-hours__today">
					<span>
						<?php
						/* translators: %s: today's hours */
						echo esc_html( sprintf( __( 'Today · %s', 'torrehub' ), $today ? $today['text'] : '' ) );
						?>
					</span>
					<?php th_icon( 'chevron-down', array( 'size' => 18 ) ); ?>
				</summary>
				<table class="th-listing-hours__table">
					<caption class="th-sr-only"><?php esc_html_e( 'Opening hours by day (local time, Spain)', 'torrehub' ); ?></caption>
					<tbody>
					<?php foreach ( $week as $row ) : ?>
						<tr<?php echo $row['today'] ? ' class="is-today" aria-current="date"' : ''; ?>>
							<th scope="row"><?php echo esc_html( $row['name'] ); ?></th>
							<td><?php echo esc_html( $row['text'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		</section>
	<?php endif; ?>
</div>
