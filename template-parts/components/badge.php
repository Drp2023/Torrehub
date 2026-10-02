<?php
/**
 * Badge / status pill.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string $label   Text.
 *     @type string $variant featured|urgent|verified|pending|expired|expired-solid|open|closed|active|neutral|info|error|amber|selected|cover|on-photo|member|seller|business.
 *     @type string $icon    Optional icon (e.g. 'check' for Verified).
 *     @type string $class
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'label'   => '',
		'variant' => 'neutral',
		'icon'    => '',
		'class'   => '',
	)
);
if ( '' === $a['label'] ) {
	return;
}
?>
<span class="<?php echo esc_attr( th_classes( 'th-badge', 'th-badge--' . $a['variant'], $a['class'] ) ); ?>">
	<?php if ( $a['icon'] ) : ?>
		<?php th_icon( $a['icon'], array( 'size' => 11 ) ); ?>
	<?php endif; ?>
	<?php echo esc_html( $a['label'] ); ?>
</span>
