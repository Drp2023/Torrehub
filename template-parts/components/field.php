<?php
/**
 * Form field: label + control + help/error, wired with aria-describedby / aria-invalid.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string $field_id           Required.
 *     @type string $name
 *     @type string $label
 *     @type string $field_type         text|email|password|tel|number|url|search|textarea|select. Default 'text'.
 *     @type string $value
 *     @type string $placeholder
 *     @type string $help
 *     @type string $error        Error message → aria-invalid + error help.
 *     @type bool   $valid        Verified state (green border + check).
 *     @type bool   $required
 *     @type bool   $readonly
 *     @type bool   $disabled
 *     @type bool   $optional_hint Show "(optional)" next to the label.
 *     @type string $prefix       "€".
 *     @type string $suffix       "km".
 *     @type array  $options      For select: value => label.
 *     @type bool   $pill         Pill input variant.
 *     @type bool   $on_color     White borderless input for blue / tinted surfaces.
 *     @type bool   $password_meter Adds the strength meter (type=password).
 *     @type int    $maxlength    Textarea: shows a counter.
 *     @type string $autocomplete
 *     @type string $inputmode
 *     @type array  $attrs        Extra attributes on the control.
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'id'             => '',
		'name'           => '',
		'label'          => '',
		'type'           => 'text',
		'value'          => '',
		'placeholder'    => '',
		'help'           => '',
		'error'          => '',
		'valid'          => false,
		'required'       => false,
		'readonly'       => false,
		'disabled'       => false,
		'optional_hint'  => false,
		'prefix'         => '',
		'suffix'         => '',
		'options'        => array(),
		'pill'           => false,
		'on_color'       => false,
		'password_meter' => false,
		'maxlength'      => 0,
		'autocomplete'   => '',
		'inputmode'      => '',
		'attrs'          => array(),
	)
);

$field_id = $a['id'] ? $a['id'] : 'th-field-' . wp_unique_id();
$help_id  = $field_id . '-help';
$err_id   = $field_id . '-error';
$meter_id = $field_id . '-strength';
$count_id = $field_id . '-count';

$described = array_filter(
	array(
		$a['error'] ? $err_id : '',
		$a['help'] ? $help_id : '',
		$a['password_meter'] ? $meter_id : '',
	)
);

$control_attrs = array_merge(
	array(
		'id'               => $field_id,
		'name'             => $a['name'] ? $a['name'] : $field_id,
		'placeholder'      => $a['placeholder'] ? $a['placeholder'] : null,
		'required'         => $a['required'],
		'aria-required'    => $a['required'] ? 'true' : null,
		'readonly'         => $a['readonly'],
		'disabled'         => $a['disabled'],
		'aria-invalid'     => $a['error'] ? 'true' : null,
		'aria-describedby' => $described ? implode( ' ', $described ) : null,
		'autocomplete'     => $a['autocomplete'] ? $a['autocomplete'] : null,
		'inputmode'        => $a['inputmode'] ? $a['inputmode'] : null,
	),
	(array) $a['attrs']
);

$field_type = $a['type'];
$classes    = array(
	'th-control'         => true,
	'th-control--prefix' => (bool) $a['prefix'],
	'th-control--suffix' => (bool) $a['suffix'],
	'th-control--status' => $a['valid'],
	'th-control--toggle' => 'password' === $field_type,
);
?>
<div class="th-field">
	<?php if ( $a['label'] ) : ?>
		<label class="th-label" for="<?php echo esc_attr( $field_id ); ?>">
			<?php echo esc_html( $a['label'] ); ?>
			<?php if ( $a['optional_hint'] ) : ?>
				<span class="th-label__optional"><?php esc_html_e( '(optional)', 'torrehub' ); ?></span>
			<?php endif; ?>
		</label>
	<?php endif; ?>

	<div class="<?php echo esc_attr( th_classes( $classes ) ); ?>"<?php echo 'password' === $field_type ? ' data-th-password' : ''; ?>>
		<?php if ( $a['prefix'] ) : ?>
			<span class="th-control__affix th-control__affix--start" aria-hidden="true"><?php echo esc_html( $a['prefix'] ); ?></span>
		<?php endif; ?>

		<?php if ( 'textarea' === $field_type ) : ?>
			<?php
			if ( $a['maxlength'] ) {
				$control_attrs['maxlength']       = (int) $a['maxlength'];
				$control_attrs['data-th-counter'] = $count_id;
			}
			?>
			<textarea class="th-textarea"<?php echo th_attrs( $control_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( (string) $a['value'] ); ?></textarea>
		<?php elseif ( 'select' === $field_type ) : ?>
			<select class="th-select"<?php echo th_attrs( $control_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php foreach ( (array) $a['options'] as $value => $label ) : ?>
					<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $a['value'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="th-control__chevron" aria-hidden="true"><?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?></span>
		<?php else : ?>
			<input class="
			<?php
			echo esc_attr(
				th_classes(
					'th-input',
					array(
						'th-input--pill'     => $a['pill'],
						'th-input--on-color' => $a['on_color'],
						'is-valid'           => $a['valid'],
					)
				)
			);
			?>
							" type="<?php echo esc_attr( $field_type ); ?>" value="<?php echo esc_attr( (string) $a['value'] ); ?>"<?php echo th_attrs( $control_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php endif; ?>

		<?php if ( $a['suffix'] ) : ?>
			<span class="th-control__affix th-control__affix--end" aria-hidden="true"><?php echo esc_html( $a['suffix'] ); ?></span>
		<?php endif; ?>
		<?php if ( $a['valid'] ) : ?>
			<span class="th-control__status">
			<?php
			th_icon(
				'check',
				array(
					'size'  => 18,
					'label' => __( 'Valid', 'torrehub' ),
				)
			);
			?>
												</span>
		<?php endif; ?>
		<?php if ( 'password' === $field_type ) : ?>
			<button type="button" class="th-control__toggle" data-th-password-toggle aria-pressed="false" aria-controls="<?php echo esc_attr( $field_id ); ?>"
				aria-label="<?php esc_attr_e( 'Show password', 'torrehub' ); ?>"
				data-label-show="<?php esc_attr_e( 'Show password', 'torrehub' ); ?>"
				data-label-hide="<?php esc_attr_e( 'Hide password', 'torrehub' ); ?>">
				<?php th_icon( 'eye', array( 'size' => 18 ) ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $a['password_meter'] ) : ?>
		<div class="th-strength" id="<?php echo esc_attr( $meter_id ); ?>" data-th-strength-for="<?php echo esc_attr( $field_id ); ?>" data-score="0"
			data-labels="<?php echo esc_attr( implode( '|', array( '', __( 'Weak', 'torrehub' ), __( 'Fair', 'torrehub' ), __( 'Good', 'torrehub' ), __( 'Strong', 'torrehub' ) ) ) ); ?>">
			<span class="th-strength__bars" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
			<span class="th-strength__label" aria-live="polite"></span>
		</div>
	<?php endif; ?>

	<?php if ( $a['error'] ) : ?>
		<p class="th-help th-help--error" id="<?php echo esc_attr( $err_id ); ?>"><?php th_icon( 'alert-circle', array( 'size' => 15 ) ); ?><span><?php echo esc_html( $a['error'] ); ?></span></p>
	<?php endif; ?>
	<?php if ( $a['help'] ) : ?>
		<p class="th-help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $a['help'] ); ?></p>
	<?php endif; ?>
	<?php if ( 'textarea' === $field_type && $a['maxlength'] ) : ?>
		<span class="th-counter" id="<?php echo esc_attr( $count_id ); ?>" aria-hidden="true"
			data-template="<?php /* translators: 1: characters typed, 2: maximum */ echo esc_attr__( '%1$s / %2$s characters', 'torrehub' ); ?>"></span>
	<?php endif; ?>
</div>
