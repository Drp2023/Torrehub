<?php
/**
 * Chip.
 *
 * Types:
 *  - 'filter'  active filter, renders a link that removes it: "Plumbing ×" (screen readers: "Remove filter: Plumbing").
 *  - 'choice'  checkbox/radio label ($input_type, $name, $value, $checked).
 *  - 'link'    navigation chip ($href, $current).
 *  - 'static'  attribute/suggestion text (no interaction).
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string $label
 *     @type string $type        filter|choice|link|static. Default 'static'.
 *     @type string $variant     Extra modifier: ink|success|clear|attr|suggest|amenity|topic|radius.
 *     @type string $href        For filter/link.
 *     @type bool   $current     For link chips (aria-current).
 *     @type string $icon        Leading icon.
 *     @type int    $count       Small orange count bubble ("Filters · 5").
 *     @type string $input_type  checkbox|radio (choice).
 *     @type string $name        Input name (choice).
 *     @type string $value       Input value (choice).
 *     @type bool   $checked     (choice).
 *     @type string $class
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'label'      => '',
		'type'       => 'static',
		'variant'    => '',
		'href'       => '',
		'current'    => false,
		'icon'       => '',
		'count'      => null,
		'input_type' => 'checkbox',
		'name'       => '',
		'value'      => '',
		'checked'    => false,
		'class'      => '',
	)
);

$classes = th_classes(
	'th-chip',
	array(
		'th-chip--filter'           => 'filter' === $a['type'],
		'th-chip--choice'           => 'choice' === $a['type'],
		'th-chip--' . $a['variant'] => '' !== $a['variant'],
	),
	$a['class']
);

$inner = static function () use ( $a ) {
	if ( $a['icon'] ) {
		th_icon( $a['icon'], array( 'size' => 14 ) );
	}
	echo '<span>' . esc_html( $a['label'] ) . '</span>';
	if ( null !== $a['count'] ) {
		echo '<span class="th-chip__count">' . esc_html( number_format_i18n( (int) $a['count'] ) ) . '</span>';
	}
};

switch ( $a['type'] ) {
	case 'filter':
		?>
		<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $a['href'] ); ?>">
			<span class="th-sr-only"><?php esc_html_e( 'Remove filter:', 'torrehub' ); ?></span>
			<?php $inner(); ?>
			<span class="th-chip__remove" aria-hidden="true"><?php th_icon( 'close', array( 'size' => 12 ) ); ?></span>
		</a>
		<?php
		break;

	case 'choice':
		?>
		<label class="<?php echo esc_attr( $classes ); ?>">
			<input type="<?php echo esc_attr( 'radio' === $a['input_type'] ? 'radio' : 'checkbox' ); ?>" name="<?php echo esc_attr( $a['name'] ); ?>" value="<?php echo esc_attr( $a['value'] ); ?>" <?php checked( $a['checked'] ); ?>>
			<?php $inner(); ?>
		</label>
		<?php
		break;

	case 'link':
		?>
		<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $a['href'] ); ?>"<?php echo $a['current'] ? ' aria-current="page"' : ''; ?>>
			<?php $inner(); ?>
		</a>
		<?php
		break;

	default:
		?>
		<span class="<?php echo esc_attr( $classes ); ?>"><?php $inner(); ?></span>
		<?php
}
