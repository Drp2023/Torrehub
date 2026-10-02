<?php
/**
 * Button / link-button.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string $label     Visible text (required unless icon-only with $aria_label).
 *     @type string $variant   accent|primary|outline|soft|destructive|ink|clay|whatsapp|neutral|white|ghost|clay-outline|text. Default 'primary'.
 *     @type string $size      sm|md|lg. Default 'md'.
 *     @type string $href      Renders <a> when set.
 *     @type string $type      button|submit. Default 'button'.
 *     @type string $icon      Leading icon name.
 *     @type string $icon_end  Trailing icon name.
 *     @type bool   $icon_only Round icon button ($aria_label required).
 *     @type string $aria_label
 *     @type bool   $block     Full width.
 *     @type bool   $disabled
 *     @type bool   $loading   Shows spinner, sets aria-busy and disables.
 *     @type string $class     Extra classes.
 *     @type array  $attrs     Extra attributes (data-*, aria-*).
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'label'      => '',
		'variant'    => 'primary',
		'size'       => 'md',
		'href'       => '',
		'type'       => 'button',
		'icon'       => '',
		'icon_end'   => '',
		'icon_only'  => false,
		'aria_label' => '',
		'block'      => false,
		'disabled'   => false,
		'loading'    => false,
		'class'      => '',
		'attrs'      => array(),
	)
);

$classes = th_classes(
	'th-btn',
	'th-btn--' . $a['variant'],
	array(
		'th-btn--sm'    => 'sm' === $a['size'],
		'th-btn--lg'    => 'lg' === $a['size'],
		'th-btn--icon'  => $a['icon_only'],
		'th-btn--block' => $a['block'],
		'is-loading'    => $a['loading'],
	),
	$a['class']
);

$is_link = '' !== $a['href'] && ! $a['disabled'];
$attrs   = array_merge(
	array(
		'class'         => $classes,
		'href'          => $is_link ? $a['href'] : null,
		'type'          => $is_link ? null : $a['type'],
		'disabled'      => ! $is_link && ( $a['disabled'] || $a['loading'] ),
		'aria-disabled' => $is_link && $a['disabled'] ? 'true' : null,
		'aria-busy'     => $a['loading'] ? 'true' : null,
		'aria-label'    => $a['aria_label'] ? $a['aria_label'] : null,
	),
	(array) $a['attrs']
);
if ( $is_link && isset( $attrs['href'] ) ) {
	$attrs['href'] = esc_url( $attrs['href'] );
}

$el_tag = $is_link ? 'a' : 'button';
?>
<<?php echo $el_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed whitelist ?><?php echo th_attrs( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $a['loading'] ) : ?>
		<?php
		th_icon(
			'spinner',
			array(
				'size'  => 18,
				'class' => 'th-btn__spinner',
			)
		);
		?>
	<?php elseif ( $a['icon'] ) : ?>
		<?php th_icon( $a['icon'], array( 'size' => 18 ) ); ?>
	<?php endif; ?>
	<?php if ( ! $a['icon_only'] && '' !== $a['label'] ) : ?>
		<span><?php echo esc_html( $a['label'] ); ?></span>
	<?php endif; ?>
	<?php if ( $a['icon_end'] ) : ?>
		<?php th_icon( $a['icon_end'], array( 'size' => 18 ) ); ?>
	<?php endif; ?>
</<?php echo $el_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
