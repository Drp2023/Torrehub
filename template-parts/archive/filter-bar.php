<?php
/**
 * Filter pill bar: "Filters · N" (opens the sheet), active filter chips (each removes itself), Clear all.
 *
 * Without JS the Filters link reloads the page with the sheet rendered open (?th_sheet=1).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Archive\Search $search }
 */

defined( 'ABSPATH' ) || exit;

$th_search = $args['search'];
$chips     = $th_search->chips();
$active    = $th_search->active_count();
$open      = add_query_arg( 'th_sheet', '1', $th_search->url( array( 'page' => $th_search->page > 1 ? $th_search->page : null ) ) ) . '#th-filters';
?>
<div class="th-filterbar">
	<a class="th-chip th-chip--ink th-filterbar__open" href="<?php echo esc_url( $open ); ?>" data-th-dialog-open="th-filters" aria-controls="th-filters" aria-haspopup="dialog" aria-expanded="false">
		<span>
			<?php
			echo esc_html(
				$active
					/* translators: %s: number of active filters */
					? sprintf( __( 'Filters · %s', 'torrehub' ), number_format_i18n( $active ) )
					: __( 'Filters', 'torrehub' )
			);
			?>
		</span>
		<?php th_icon( 'filter', array( 'size' => 16 ) ); ?>
	</a>

	<?php if ( $chips ) : ?>
		<ul class="th-filterbar__chips" role="list" aria-label="<?php esc_attr_e( 'Active filters', 'torrehub' ); ?>">
			<?php foreach ( $chips as $chip ) : ?>
				<li>
					<?php
					th_component(
						'chip',
						array(
							'type'    => 'filter',
							'label'   => $chip['label'],
							'href'    => $chip['url'],
							'variant' => $chip['variant'],
						)
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( count( $chips ) > 1 ) : ?>
			<a class="th-filterbar__clear" href="<?php echo esc_url( th_url_listings( 'map' === $th_search->view ? array( 'view' => 'map' ) : array() ) ); ?>"><?php esc_html_e( 'Clear all', 'torrehub' ); ?></a>
		<?php endif; ?>
	<?php endif; ?>
</div>
