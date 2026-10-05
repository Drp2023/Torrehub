<?php
/**
 * Town picker (G-05): use my location, top towns, full filterable list. Works without JS (links with ?th_town=).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$towns   = Directory::towns();
$th_town = th_current_town();
if ( ! $towns ) {
	return;
}
?>
<dialog class="th-dialog th-dialog--sheet th-location" id="th-location" data-th-dialog aria-labelledby="th-location-title">
	<div class="th-dialog__head">
		<h2 class="th-dialog__title" id="th-location-title"><?php esc_html_e( 'Choose your town', 'torrehub' ); ?></h2>
		<?php
		th_component(
			'button',
			array(
				'variant'    => 'neutral',
				'size'       => 'sm',
				'icon'       => 'close',
				'icon_only'  => true,
				'aria_label' => __( 'Close', 'torrehub' ),
				'attrs'      => array( 'data-th-dialog-close' => '' ),
			)
		);
		?>
	</div>
	<div class="th-dialog__body th-stack">
		<button type="button" class="th-location__locate" data-th-locate>
			<?php th_icon( 'locate', array( 'size' => 18 ) ); ?>
			<span><?php esc_html_e( 'Use my current location', 'torrehub' ); ?></span>
		</button>
		<p class="th-help" data-th-locate-status role="status" aria-live="polite"></p>

		<div class="th-field">
			<label class="th-label" for="th-town-filter"><?php esc_html_e( 'Find a town', 'torrehub' ); ?></label>
			<input class="th-input th-input--pill" id="th-town-filter" type="search" autocomplete="off" data-th-town-filter
				placeholder="<?php esc_attr_e( 'e.g. Altea', 'torrehub' ); ?>">
		</div>

		<ul class="th-location__list" role="list" data-th-town-list>
			<?php foreach ( $towns as $town ) : ?>
				<li data-name="<?php echo esc_attr( remove_accents( mb_strtolower( $town['name'] ) ) ); ?>">
					<a href="<?php echo esc_url( add_query_arg( 'th_town', $town['slug'] ) ); ?>" data-th-town="<?php echo esc_attr( $town['slug'] ); ?>"
						<?php echo ( $th_town && $th_town['slug'] === $town['slug'] ) ? ' aria-current="true"' : ''; ?>>
						<span><?php echo esc_html( $town['name'] ); ?></span>
						<span class="th-location__count">
							<?php
							/* translators: %s: number of listings */
							echo esc_html( sprintf( _n( '%s listing', '%s listings', $town['count'], 'torrehub' ), number_format_i18n( $town['count'] ) ) );
							?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="th-meta" data-th-town-empty hidden><?php esc_html_e( 'No town matches that name.', 'torrehub' ); ?></p>
	</div>
</dialog>
