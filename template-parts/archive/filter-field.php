<?php
/**
 * One Form Builder field as a filter control (inside the filter sheet).
 *
 * UI: chips (multi) · segmented (Any + ≤3 options) · select (many options) · toggle (single "yes" checkbox)
 *     · range (number min/max) · date (one day).
 *
 * @package Torrehub
 *
 * @var array $args { @type array $def Definition from Search::definitions(), @type array $value Current value. }
 */

defined( 'ABSPATH' ) || exit;

$def   = $args['def'];
$value = (array) $args['value'];
$name  = 'f[' . $def['name'] . ']';
$uid   = 'th-f-' . sanitize_html_class( $def['name'] );

switch ( $def['ui'] ) {
	case 'toggle':
		$opt = (string) array_key_first( $def['options'] );
		?>
		<label class="th-toggle th-filters__field">
			<span><?php echo esc_html( $def['label'] ); ?></span>
			<input type="checkbox" role="switch" name="<?php echo esc_attr( $name . '[]' ); ?>" value="<?php echo esc_attr( $opt ); ?>" <?php checked( in_array( $opt, $value, true ) ); ?>>
		</label>
		<?php
		break;

	case 'segmented':
		?>
		<fieldset class="th-filters__field">
			<legend class="th-label"><?php echo esc_html( $def['label'] ); ?></legend>
			<div class="th-segmented th-segmented--block">
				<label><input type="radio" name="<?php echo esc_attr( $name . '[]' ); ?>" value="" <?php checked( ! $value ); ?>><?php esc_html_e( 'Any', 'torrehub' ); ?></label>
				<?php foreach ( $def['options'] as $opt => $opt_label ) : ?>
					<label><input type="radio" name="<?php echo esc_attr( $name . '[]' ); ?>" value="<?php echo esc_attr( (string) $opt ); ?>" <?php checked( in_array( (string) $opt, $value, true ) ); ?>><?php echo esc_html( $opt_label ); ?></label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
		break;

	case 'select':
		?>
		<div class="th-field th-filters__field">
			<label class="th-label" for="<?php echo esc_attr( $uid ); ?>"><?php echo esc_html( $def['label'] ); ?></label>
			<select class="th-select" id="<?php echo esc_attr( $uid ); ?>" name="<?php echo esc_attr( $name . '[]' ); ?>">
				<option value=""><?php esc_html_e( 'Any', 'torrehub' ); ?></option>
				<?php foreach ( $def['options'] as $opt => $opt_label ) : ?>
					<option value="<?php echo esc_attr( (string) $opt ); ?>" <?php selected( in_array( (string) $opt, $value, true ) ); ?>><?php echo esc_html( $opt_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
		break;

	case 'range':
		?>
		<fieldset class="th-filters__field">
			<legend class="th-label"><?php echo esc_html( $def['label'] ); ?></legend>
			<div class="th-filters__minmax">
				<label class="th-sr-only" for="<?php echo esc_attr( $uid . '-min' ); ?>">
					<?php
					/* translators: %s: field label */
					echo esc_html( sprintf( __( '%s, minimum', 'torrehub' ), $def['label'] ) );
					?>
				</label>
				<input class="th-input" type="number" inputmode="numeric" id="<?php echo esc_attr( $uid . '-min' ); ?>" name="<?php echo esc_attr( $name . '[min]' ); ?>" value="<?php echo esc_attr( (string) ( $value['min'] ?? '' ) ); ?>" min="<?php echo esc_attr( (string) $def['min'] ); ?>" max="<?php echo esc_attr( (string) $def['max'] ); ?>" placeholder="<?php echo esc_attr( (string) $def['min'] ); ?>">
				<span aria-hidden="true">–</span>
				<label class="th-sr-only" for="<?php echo esc_attr( $uid . '-max' ); ?>">
					<?php
					/* translators: %s: field label */
					echo esc_html( sprintf( __( '%s, maximum', 'torrehub' ), $def['label'] ) );
					?>
				</label>
				<input class="th-input" type="number" inputmode="numeric" id="<?php echo esc_attr( $uid . '-max' ); ?>" name="<?php echo esc_attr( $name . '[max]' ); ?>" value="<?php echo esc_attr( (string) ( $value['max'] ?? '' ) ); ?>" min="<?php echo esc_attr( (string) $def['min'] ); ?>" max="<?php echo esc_attr( (string) $def['max'] ); ?>" placeholder="<?php echo esc_attr( (string) $def['max'] ); ?>">
			</div>
		</fieldset>
		<?php
		break;

	case 'date':
		?>
		<div class="th-field th-filters__field">
			<label class="th-label" for="<?php echo esc_attr( $uid ); ?>"><?php echo esc_html( $def['label'] ); ?></label>
			<input class="th-input" type="date" id="<?php echo esc_attr( $uid ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) ( $value['day'] ?? '' ) ); ?>">
		</div>
		<?php
		break;

	default: // chips.
		?>
		<fieldset class="th-filters__field">
			<legend class="th-label"><?php echo esc_html( $def['label'] ); ?></legend>
			<div class="th-cluster">
				<?php
				foreach ( $def['options'] as $opt => $opt_label ) {
					th_component(
						'chip',
						array(
							'type'    => 'choice',
							'name'    => $name . '[]',
							'value'   => (string) $opt,
							'label'   => (string) $opt_label,
							'checked' => in_array( (string) $opt, $value, true ),
						)
					);
				}
				?>
			</div>
		</fieldset>
		<?php
}
