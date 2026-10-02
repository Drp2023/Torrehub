<?php
/**
 * Rating pill: ★ 4.9 (28 reviews). Hidden when there is no rating.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type float  $average 0–5.
 *     @type int    $count   Number of reviews.
 *     @type string $label   Optional word label instead of the number ("Excellent").
 *     @type bool   $show_count Default true.
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'average'    => 0,
		'count'      => 0,
		'label'      => '',
		'show_count' => true,
	)
);

$average = round( (float) $a['average'], 1 );
$count   = (int) $a['count'];
if ( $average <= 0 && '' === $a['label'] ) {
	return;
}

/* translators: %s: average rating, e.g. 4.9 */
$sr = sprintf( __( 'Rated %s out of 5', 'torrehub' ), number_format_i18n( $average, 1 ) );
?>
<span class="th-rating">
	<span class="th-rating__pill">
		<?php th_icon( 'star-filled', array( 'size' => 13 ) ); ?>
		<span aria-hidden="true"><?php echo esc_html( '' !== $a['label'] ? $a['label'] : number_format_i18n( $average, 1 ) ); ?></span>
		<span class="th-sr-only"><?php echo esc_html( $sr ); ?></span>
	</span>
	<?php if ( $a['show_count'] && $count > 0 ) : ?>
		<span class="th-rating__count">
			<?php
			/* translators: %s: number of reviews */
			echo esc_html( sprintf( _n( '(%s review)', '(%s reviews)', $count, 'torrehub' ), number_format_i18n( $count ) ) );
			?>
		</span>
	<?php endif; ?>
</span>
