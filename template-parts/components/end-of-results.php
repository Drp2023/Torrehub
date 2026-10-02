<?php
/**
 * "That's all N" card at the end of a result list.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type int    $total
 *     @type string $text    Explanation ("Widen the search to see more nearby.").
 *     @type array  $action  Button component args (e.g. "Search within 25 km").
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'total'  => 0,
		'text'   => '',
		'action' => array(),
	)
);
?>
<div class="th-end">
	<span class="th-end__icon"><?php th_icon( 'search-empty', array( 'size' => 24 ) ); ?></span>
	<p class="th-end__title">
		<?php
		if ( 1 === (int) $a['total'] ) {
			esc_html_e( "That's the only one", 'torrehub' );
		} else {
			/* translators: %s: number of results */
			echo esc_html( sprintf( _n( "That's all %s", "That's all %s", (int) $a['total'], 'torrehub' ), number_format_i18n( (int) $a['total'] ) ) );
		}
		?>
	</p>
	<?php if ( $a['text'] ) : ?>
		<p><?php echo esc_html( $a['text'] ); ?></p>
	<?php endif; ?>
	<?php if ( $a['action'] ) : ?>
		<?php
		th_component(
			'button',
			array_merge(
				array(
					'variant' => 'ink',
					'size'    => 'sm',
				),
				(array) $a['action']
			)
		);
		?>
	<?php endif; ?>
</div>
