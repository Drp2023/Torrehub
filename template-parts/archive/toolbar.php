<?php
/**
 * Result count, sort (auto-submitting select; button without JS) and the grid / list / map switch.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Archive\Search $search, @type int $total }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Archive\Search;

$th_search = $args['search'];
$total     = (int) $args['total'];
$town      = $th_search->town ? html_entity_decode( $th_search->town->name, ENT_QUOTES ) : '';

$orders = array(
	'date-desc'  => __( 'Newest', 'torrehub' ),
	'price-asc'  => __( 'Price: low to high', 'torrehub' ),
	'price-desc' => __( 'Price: high to low', 'torrehub' ),
	'views-desc' => __( 'Most viewed', 'torrehub' ),
);
if ( $th_search->radius ) {
	$orders = array( 'nearest' => __( 'Nearest', 'torrehub' ) ) + $orders;
}
$current_order = $th_search->orderby ? $th_search->orderby : ( $th_search->radius ? 'nearest' : 'date-desc' );

$params = $th_search->params();
unset( $params['orderby'], $params['page'] );
list( $form_action, $hidden ) = Search::form_target( $params );

$views = array(
	'grid' => array( __( 'Grid', 'torrehub' ), 'view-grid' ),
	'list' => array( __( 'List', 'torrehub' ), 'view-list' ),
	'map'  => array( __( 'Map', 'torrehub' ), 'map-pin' ),
);
?>
<div class="th-toolbar">
	<p class="th-result-count" id="th-result-count">
		<?php
		if ( $town ) {
			printf(
				/* translators: 1: number of results (wrapped in <strong>), 2: town */
				esc_html( _n( '%1$s result near %2$s', '%1$s results near %2$s', $total, 'torrehub' ) ),
				'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>',
				esc_html( $town )
			);
		} else {
			printf(
				/* translators: %s: number of results (wrapped in <strong>) */
				esc_html( _n( '%s result', '%s results', $total, 'torrehub' ) ),
				'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
			);
		}
		?>
	</p>

	<div class="th-cluster">
		<?php if ( $total > 1 ) : ?>
			<form class="th-sort" method="get" action="<?php echo esc_url( $form_action ); ?>" data-th-autosubmit>
				<?php Search::hidden_inputs( $hidden ); ?>
				<label class="th-sr-only" for="th-sort"><?php esc_html_e( 'Sort by', 'torrehub' ); ?></label>
				<select id="th-sort" name="orderby">
					<?php foreach ( $orders as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_order, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?>
				<noscript><button class="th-btn th-btn--outline th-btn--sm" type="submit"><?php esc_html_e( 'Sort', 'torrehub' ); ?></button></noscript>
			</form>
		<?php endif; ?>

		<nav class="th-view-toggle" aria-label="<?php esc_attr_e( 'Result view', 'torrehub' ); ?>">
			<?php foreach ( $views as $view => $meta ) : ?>
				<a href="<?php echo esc_url( $th_search->url( array( 'view' => 'grid' === $view ? null : $view ) ) ); ?>"<?php echo $view === $th_search->view ? ' aria-current="true"' : ''; ?>>
					<?php th_icon( $meta[1], array( 'size' => 16 ) ); ?>
					<span class="<?php echo esc_attr( 'map' === $view ? '' : 'th-sr-only' ); ?>"><?php echo esc_html( $meta[0] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
</div>
