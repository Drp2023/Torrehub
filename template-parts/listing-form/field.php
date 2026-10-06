<?php
/**
 * One Form Builder field in the listing workspace. Input names follow Classified Listing's form data
 * (`pricing[price]`, `map[latitude]`, `location[0][term_id]`, `checkbox_x[]` …) — the browser serialises the form
 * and `rtcl_update_listing` parses it with parse_str(), exactly as with its React app.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type Torrehub\Modules\ListingForm\Workspace $ws
 *     @type array $field  Field definition.
 *     @type mixed $value  Current value.
 *     @type bool  $switch Controls a conditional section (select → choice cards).
 * }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\ListingForm\Fields;
use Torrehub\Modules\ListingForm\Module;

$ws       = $args['ws'];
$field    = $args['field'];
$value    = $args['value'];
$element  = (string) $field['element'];
$name     = (string) $field['name'];
$uuid     = (string) $field['uuid'];
$field_id = Fields::dom_id( $field );
$label    = html_entity_decode( (string) ( $field['label'] ?? '' ), ENT_QUOTES );
$help     = html_entity_decode( wp_strip_all_tags( (string) ( $field['help_message'] ?? '' ) ), ENT_QUOTES );
$req      = Fields::required( $field );
$help_id  = $field_id . '-help';
$err_id   = $field_id . '-error';
$holder   = (string) ( $field['placeholder'] ?? '' );
$holder   = in_array( $holder, array( '- Select -', 'Listing Title', 'Select a type' ), true ) ? '' : $holder;
$rules    = Fields::rules( $field );
if ( in_array( $element, array( 'phone', 'whatsapp' ), true ) && '' === $help ) {
	$help = __( 'With the country code, e.g. +34 600 000 000.', 'torrehub' );
}
$wrap = th_classes(
	'th-lf-field',
	'th-lf-field--' . $element,
	array( 'th-lf-field--wide' => Fields::wide( $field ) || $args['switch'] )
);

/**
 * Shared attributes for a single control (id, name, aria).
 *
 * @param array<string,mixed> $extra Extra attributes.
 * @return string
 */
$control = static function ( array $extra = array() ) use ( $field_id, $name, $req, $help, $help_id ): string {
	return th_attrs(
		array_merge(
			array(
				'id'               => $field_id,
				'name'             => $name,
				'aria-required'    => $req ? 'true' : null,
				'aria-describedby' => $help ? $help_id : null,
			),
			$extra
		)
	);
};

$label_html = static function () use ( $field_id, $label ): void {
	if ( '' !== $label ) {
		printf( '<label class="th-label" for="%1$s">%2$s</label>', esc_attr( $field_id ), esc_html( $label ) );
	}
};

$legend_html = static function ( string $text = '' ) use ( $label ): void {
	$text = '' !== $text ? $text : $label;
	if ( '' !== $text ) {
		printf( '<legend class="th-label">%s</legend>', esc_html( $text ) );
	}
};

$scalar = is_scalar( $value ) ? (string) $value : '';
?>
<div class="<?php echo esc_attr( $wrap ); ?>" data-th-lf-field="<?php echo esc_attr( $uuid ); ?>">
	<?php
	switch ( $element ) :
		case 'title':
		case 'text':
		case 'email':
		case 'url':
		case 'website':
		case 'phone':
		case 'whatsapp':
		case 'telegram':
		case 'zipcode':
		case 'number':
			$types = array(
				'email'    => 'email',
				'url'      => 'url',
				'website'  => 'url',
				'phone'    => 'tel',
				'whatsapp' => 'tel',
				'number'   => 'number',
			);
			$auto  = array(
				'email'   => 'email',
				'phone'   => 'tel',
				'zipcode' => 'postal-code',
				'website' => 'url',
			);
			$label_html();
			?>
			<div class="th-control">
				<input class="th-input" type="<?php echo esc_attr( $types[ $element ] ?? 'text' ); ?>" value="<?php echo esc_attr( $scalar ); ?>"<?php echo $control( array( 'placeholder' => $holder ? $holder : null, 'autocomplete' => $auto[ $element ] ?? null, 'maxlength' => isset( $rules['max'] ) && 'number' !== $element ? (int) $rules['max']['value'] : null, 'step' => 'number' === $element ? 'any' : null, 'inputmode' => 'number' === $element ? 'decimal' : null ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- th_attrs() escapes. ?>>
			</div>
			<?php
			break;

		case 'description':
		case 'excerpt':
		case 'textarea':
		case 'address':
			$text = 'description' === $element && 'wp_editor' === ( $field['editor_type'] ?? '' ) ? Fields::plain_text( $scalar ) : html_entity_decode( $scalar, ENT_QUOTES );
			$max  = isset( $rules['max'] ) ? (int) $rules['max']['value'] : 0;
			$label_html();
			?>
			<textarea class="th-textarea" rows="<?php echo esc_attr( (string) max( 4, (int) ( $field['rows'] ?? 6 ) ) ); ?>"<?php echo $control( array( 'placeholder' => $holder ? $holder : null, 'maxlength' => $max ? $max : null, 'data-th-counter' => $max ? $field_id . '-count' : null ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>><?php echo esc_textarea( $text ); ?></textarea>
			<?php if ( $max ) : ?>
				<span class="th-counter" id="<?php echo esc_attr( $field_id . '-count' ); ?>" aria-hidden="true" data-template="<?php /* translators: 1: characters typed, 2: maximum */ echo esc_attr__( '%1$s / %2$s characters', 'torrehub' ); ?>"></span>
			<?php endif; ?>
			<?php
			break;

		case 'select':
			$options = Fields::options( $field );
			if ( $args['switch'] ) :
				?>
				<fieldset class="th-lf-choices" id="<?php echo esc_attr( $field_id ); ?>"<?php echo $help ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?>>
					<?php $legend_html(); ?>
					<div class="th-lf-choices__grid">
						<?php foreach ( $options as $opt_value => $opt_label ) : ?>
							<label class="th-choice th-lf-choice">
								<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $opt_value ); ?>" <?php checked( $scalar, (string) $opt_value ); ?>>
								<?php th_icon( Fields::choice_icon( (string) $opt_value ), array( 'size' => 20 ) ); ?>
								<span class="th-choice__title"><?php echo esc_html( $opt_label ); ?></span>
								<span class="th-choice__radio"><?php th_icon( 'check', array( 'size' => 12 ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<?php
			else :
				$label_html();
				?>
				<div class="th-control">
					<select class="th-select"<?php echo $control(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<option value=""><?php esc_html_e( 'Choose…', 'torrehub' ); ?></option>
						<?php foreach ( $options as $opt_value => $opt_label ) : ?>
							<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $scalar, (string) $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="th-control__chevron" aria-hidden="true"><?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?></span>
				</div>
				<?php
			endif;
			break;

		case 'radio':
			$options   = Fields::options( $field );
			$segmented = count( $options ) <= 3 && max( array_map( 'mb_strlen', $options ? $options : array( '' ) ) ) <= 16;
			?>
			<fieldset class="th-lf-radios" id="<?php echo esc_attr( $field_id ); ?>"<?php echo $help ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?>>
				<?php $legend_html(); ?>
				<div class="<?php echo esc_attr( $segmented ? 'th-segmented th-segmented--block' : 'th-lf-checks' ); ?>">
					<?php foreach ( $options as $opt_value => $opt_label ) : ?>
						<label<?php echo $segmented ? '' : ' class="th-check"'; ?>><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $opt_value ); ?>" <?php checked( $scalar, (string) $opt_value ); ?>> <span><?php echo esc_html( $opt_label ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<?php
			break;

		case 'checkbox':
			$options = Fields::options( $field );
			$checked = array_map( 'strval', is_array( $value ) ? $value : ( '' !== $scalar ? array( $scalar ) : array() ) );
			?>
			<fieldset class="th-lf-checkboxes" id="<?php echo esc_attr( $field_id ); ?>"<?php echo $help ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?>>
				<?php $legend_html(); ?>
				<div class="th-lf-checks th-lf-checks--grid">
					<?php foreach ( $options as $opt_value => $opt_label ) : ?>
						<label class="th-check"><input type="checkbox" name="<?php echo esc_attr( $name . '[]' ); ?>" value="<?php echo esc_attr( $opt_value ); ?>" <?php checked( in_array( (string) $opt_value, $checked, true ) ); ?>> <span><?php echo esc_html( $opt_label ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<?php
			break;

		case 'date':
			$native = Fields::date_input_type( $field );
			if ( 'range' === ( $field['date_type'] ?? 'single' ) ) :
				$range = is_array( $value ) ? $value : array();
				?>
				<fieldset class="th-lf-daterange" id="<?php echo esc_attr( $field_id ); ?>">
					<?php $legend_html(); ?>
					<div class="th-lf-pair">
						<label class="th-field"><span class="th-label__optional"><?php esc_html_e( 'From', 'torrehub' ); ?></span>
							<input class="th-input" type="<?php echo esc_attr( $native ); ?>" name="<?php echo esc_attr( $name . '[start]' ); ?>" value="<?php echo esc_attr( Fields::date_native( $field, $range['start'] ?? '' ) ); ?>" data-th-date="<?php echo esc_attr( (string) ( $field['date_format'] ?? 'Y-m-d' ) ); ?>">
						</label>
						<label class="th-field"><span class="th-label__optional"><?php esc_html_e( 'To', 'torrehub' ); ?></span>
							<input class="th-input" type="<?php echo esc_attr( $native ); ?>" name="<?php echo esc_attr( $name . '[end]' ); ?>" value="<?php echo esc_attr( Fields::date_native( $field, $range['end'] ?? '' ) ); ?>" data-th-date="<?php echo esc_attr( (string) ( $field['date_format'] ?? 'Y-m-d' ) ); ?>">
						</label>
					</div>
				</fieldset>
				<?php
			else :
				$label_html();
				?>
				<div class="th-control">
					<input class="th-input" type="<?php echo esc_attr( $native ); ?>" value="<?php echo esc_attr( Fields::date_native( $field, $scalar ) ); ?>"<?php echo $control( array( 'data-th-date' => (string) ( $field['date_format'] ?? 'Y-m-d' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				</div>
				<?php
			endif;
			break;

		case 'pricing':
			$price      = is_array( $value ) ? $value : array();
			$opts       = array_map( 'strval', (array) ( $field['options'] ?? array() ) );
			$currency   = html_entity_decode( \Rtcl\Helpers\Functions::get_currency_symbol(), ENT_QUOTES );
			$ptype      = (string) ( $price['pricing_type'] ?? ( $field['pricing_type'] ?? 'price' ) );
			$price_lbl  = html_entity_decode( (string) ( $field['price_label'] ?? __( 'Price', 'torrehub' ) ), ENT_QUOTES );
			$units      = \Rtcl\Resources\Options::get_price_unit_list();
			$unit_keys  = ! empty( $field['price_units'] ) ? array_intersect( array_keys( $units ), (array) $field['price_units'] ) : array_keys( $units );
			$type_names = array(
				'price'    => __( 'Fixed price', 'torrehub' ),
				'range'    => __( 'Price range', 'torrehub' ),
				'disabled' => __( 'No price', 'torrehub' ),
			);
			?>
			<fieldset class="th-lf-pricing" id="<?php echo esc_attr( $field_id ); ?>">
				<?php $legend_html(); ?>
				<?php if ( in_array( 'pricing_type', $opts, true ) ) : ?>
					<div class="th-segmented" role="radiogroup" aria-label="<?php echo esc_attr( html_entity_decode( (string) ( $field['pricing_type_label'] ?? __( 'Pricing type', 'torrehub' ) ), ENT_QUOTES ) ); ?>">
						<?php foreach ( \Rtcl\Resources\Options::get_listing_pricing_types() as $key => $type_label ) : ?>
							<label><input type="radio" name="<?php echo esc_attr( $name . '[pricing_type]' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $ptype, $key ); ?>><?php echo esc_html( $type_names[ $key ] ?? $type_label ); ?></label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<div class="th-lf-grid th-lf-grid--nested" data-th-lf-when="<?php echo esc_attr( in_array( 'pricing_type', $opts, true ) ? $name . '[pricing_type]!=disabled' : '' ); ?>">
					<?php if ( in_array( 'price_type', $opts, true ) ) : ?>
						<div class="th-field">
							<label class="th-label" for="<?php echo esc_attr( $field_id . '-ptype' ); ?>"><?php echo esc_html( html_entity_decode( (string) ( $field['price_type_label'] ?? __( 'Price type', 'torrehub' ) ), ENT_QUOTES ) ); ?></label>
							<div class="th-control">
								<select class="th-select" id="<?php echo esc_attr( $field_id . '-ptype' ); ?>" name="<?php echo esc_attr( $name . '[price_type]' ); ?>">
									<?php foreach ( \Rtcl\Resources\Options::get_price_types() as $key => $type_label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) ( $price['price_type'] ?? ( $field['price_type'] ?? 'fixed' ) ), $key ); ?>><?php echo esc_html( $type_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<span class="th-control__chevron" aria-hidden="true"><?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?></span>
							</div>
						</div>
					<?php endif; ?>
					<div class="th-field" data-th-lf-when="<?php echo esc_attr( in_array( 'price_type', $opts, true ) ? $name . '[price_type]!=on_call' : '' ); ?>">
						<label class="th-label" for="<?php echo esc_attr( $field_id . '-price' ); ?>"><?php echo esc_html( $price_lbl ); ?></label>
						<div class="th-control th-control--prefix">
							<span class="th-control__affix th-control__affix--start" aria-hidden="true"><?php echo esc_html( $currency ); ?></span>
							<input class="th-input" type="text" inputmode="decimal" id="<?php echo esc_attr( $field_id . '-price' ); ?>" name="<?php echo esc_attr( $name . '[price]' ); ?>" value="<?php echo esc_attr( (string) ( $price['price'] ?? '' ) ); ?>"<?php echo $req ? ' aria-required="true"' : ''; ?> data-th-lf-price>
						</div>
					</div>
					<?php if ( in_array( 'pricing_type', $opts, true ) ) : ?>
						<div class="th-field" data-th-lf-when="<?php echo esc_attr( $name . '[pricing_type]=range' ); ?>">
							<label class="th-label" for="<?php echo esc_attr( $field_id . '-max' ); ?>"><?php esc_html_e( 'Maximum price', 'torrehub' ); ?></label>
							<div class="th-control th-control--prefix">
								<span class="th-control__affix th-control__affix--start" aria-hidden="true"><?php echo esc_html( $currency ); ?></span>
								<input class="th-input" type="text" inputmode="decimal" id="<?php echo esc_attr( $field_id . '-max' ); ?>" name="<?php echo esc_attr( $name . '[max_price]' ); ?>" value="<?php echo esc_attr( (string) ( $price['max_price'] ?? '' ) ); ?>">
							</div>
						</div>
					<?php endif; ?>
					<?php if ( in_array( 'price_unit', $opts, true ) ) : ?>
						<div class="th-field">
							<label class="th-label" for="<?php echo esc_attr( $field_id . '-unit' ); ?>"><?php echo esc_html( html_entity_decode( (string) ( $field['price_unit_label'] ?? __( 'Price unit', 'torrehub' ) ), ENT_QUOTES ) ); ?></label>
							<div class="th-control">
								<select class="th-select" id="<?php echo esc_attr( $field_id . '-unit' ); ?>" name="<?php echo esc_attr( $name . '[price_unit]' ); ?>">
									<option value=""><?php esc_html_e( 'Choose…', 'torrehub' ); ?></option>
									<?php foreach ( $unit_keys as $key ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) ( $price['price_unit'] ?? '' ), $key ); ?>><?php echo esc_html( $units[ $key ]['title'] ?? $key ); ?></option>
									<?php endforeach; ?>
								</select>
								<span class="th-control__chevron" aria-hidden="true"><?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?></span>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</fieldset>
			<?php
			break;

		case 'location':
			$items   = is_array( $value ) ? $value : array();
			$current = $items ? Fields::term_id( end( $items ) ) : 0;
			$label   = '' !== $label ? $label : __( 'Town', 'torrehub' );
			$label_html();
			?>
			<div class="th-control">
				<select class="th-select" data-th-lf-town<?php echo $control( array( 'name' => $name . '[0][term_id]' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<option value=""><?php esc_html_e( 'Choose a town…', 'torrehub' ); ?></option>
					<?php foreach ( \Torrehub\Data\Directory::towns() as $town ) : ?>
						<option value="<?php echo esc_attr( (string) $town['id'] ); ?>" <?php selected( $current, (int) $town['id'] ); ?>><?php echo esc_html( $town['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="th-control__chevron" aria-hidden="true"><?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?></span>
			</div>
			<?php
			break;

		case 'map':
			$point = is_array( $value ) ? $value : array();
			$lat   = is_numeric( $point['latitude'] ?? null ) ? (string) $point['latitude'] : '';
			$lng   = is_numeric( $point['longitude'] ?? null ) ? (string) $point['longitude'] : '';
			?>
			<fieldset class="th-lf-pin" id="<?php echo esc_attr( $field_id ); ?>" data-th-pin aria-describedby="<?php echo esc_attr( $field_id . '-pinhelp' ); ?>">
				<?php $legend_html( __( 'Exact location', 'torrehub' ) ); ?>
				<p class="th-help" id="<?php echo esc_attr( $field_id . '-pinhelp' ); ?>"><?php esc_html_e( 'Click the map where the listing is, then drag the pin to fine-tune. With the keyboard: move the map with the arrow keys and use “Pin at map centre”. Radius search and “Nearest” use this point.', 'torrehub' ); ?></p>
				<div class="th-lf-pin__map" data-th-pin-map></div>
				<div class="th-lf-pin__bar">
					<span class="th-lf-pin__readout" data-th-pin-readout aria-live="polite"></span>
					<span class="th-cluster">
						<button type="button" class="th-btn th-btn--neutral th-btn--sm" data-th-pin-centre><?php th_icon( 'crosshair', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Pin at map centre', 'torrehub' ); ?></span></button>
						<button type="button" class="th-btn th-btn--neutral th-btn--sm" data-th-pin-locate><?php th_icon( 'locate', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Use my location', 'torrehub' ); ?></span></button>
						<button type="button" class="th-btn th-btn--text th-btn--sm" data-th-pin-clear><?php esc_html_e( 'Remove pin', 'torrehub' ); ?></button>
					</span>
				</div>
				<input type="hidden" name="<?php echo esc_attr( $name . '[latitude]' ); ?>" value="<?php echo esc_attr( $lat ); ?>" data-th-pin-lat>
				<input type="hidden" name="<?php echo esc_attr( $name . '[longitude]' ); ?>" value="<?php echo esc_attr( $lng ); ?>" data-th-pin-lng>
				<?php if ( Fields::flag( $field['allow_hide_map'] ?? false ) ) : ?>
					<label class="th-check"><input type="checkbox" name="<?php echo esc_attr( $name . '[hide_map]' ); ?>" value="1" <?php checked( ! empty( $point['hide_map'] ) ); ?>> <span><?php esc_html_e( 'Don’t show the map on my listing (search still uses the pin)', 'torrehub' ); ?></span></label>
				<?php endif; ?>
			</fieldset>
			<?php
			break;

		case 'images':
			$limits = Module::image_limits( 0, $ws->form );
			$images = $ws->images();
			$types  = strtoupper( implode( '/', array_unique( array_map( static fn( $t ) => 'jpeg' === $t ? 'jpg' : $t, $limits['types'] ) ) ) );
			?>
			<fieldset class="th-lf-photos" data-th-uploader data-max="<?php echo esc_attr( (string) $limits['count'] ); ?>" data-bytes="<?php echo esc_attr( (string) $limits['bytes'] ); ?>" data-types="<?php echo esc_attr( implode( ',', $limits['types'] ) ); ?>">
				<div class="th-lf-photos__head">
					<legend class="th-lf-photos__title"><?php echo esc_html( '' !== $label ? $label : __( 'Photos', 'torrehub' ) ); ?></legend>
					<p class="th-lf-photos__hint" id="<?php echo esc_attr( $field_id . '-hint' ); ?>">
						<span data-th-uploader-count><?php echo esc_html( (string) count( $images ) ); ?></span>
						<?php
						/* translators: 1: maximum photos, 2: file types, 3: size limit in MB */
						echo esc_html( sprintf( __( 'of %1$s · %2$s · max %3$s MB each', 'torrehub' ), number_format_i18n( $limits['count'] ), $types, number_format_i18n( $limits['bytes'] / MB_IN_BYTES ) ) );
						?>
					</p>
				</div>
				<ul class="th-lf-photos__grid" role="list" data-th-uploader-list>
					<?php foreach ( $images as $image ) : ?>
						<li class="th-lf-photo" data-id="<?php echo esc_attr( (string) $image['id'] ); ?>"<?php echo $image['featured'] ? ' data-cover' : ''; ?>>
							<img src="<?php echo esc_url( $image['url'] ); ?>" alt="" loading="lazy">
							<span class="th-lf-photo__cover"><?php esc_html_e( 'Cover', 'torrehub' ); ?></span>
							<button type="button" class="th-lf-photo__make" data-th-photo-cover><?php esc_html_e( 'Make cover', 'torrehub' ); ?></button>
							<button type="button" class="th-lf-photo__remove" data-th-photo-remove aria-label="<?php esc_attr_e( 'Remove', 'torrehub' ); ?>"><?php th_icon( 'close', array( 'size' => 14 ) ); ?></button>
						</li>
					<?php endforeach; ?>
					<li class="th-lf-photo th-lf-photo--add" data-th-uploader-add>
						<label class="th-lf-photo__drop">
							<input class="th-sr-only" type="file" multiple accept="<?php echo esc_attr( implode( ',', array_map( static fn( $t ) => 'image/' . ( 'jpg' === $t ? 'jpeg' : $t ), $limits['types'] ) ) ); ?>" aria-describedby="<?php echo esc_attr( $field_id . '-hint' ); ?>" data-th-uploader-input>
							<?php th_icon( 'plus', array( 'size' => 22 ) ); ?>
							<span><?php esc_html_e( 'Add photos', 'torrehub' ); ?></span>
							<span class="th-lf-photo__drophint"><?php esc_html_e( 'or drop them here', 'torrehub' ); ?></span>
						</label>
					</li>
				</ul>
				<div class="th-alert th-alert--error th-lf-photos__error" role="alert" data-th-uploader-error hidden><?php th_icon( 'alert-circle', array( 'size' => 20 ) ); ?><p></p></div>
			</fieldset>
			<?php
			break;

		case 'file':
			$files = $ws->listing_id ? (array) \Rtcl\Services\FormBuilder\FBHelper::getFieldAttachmentFiles( $ws->listing_id, $field ) : array();
			$v     = (array) ( $field['validation'] ?? array() );
			$max   = max( 1, (int) ( $v['max_file_count']['value'] ?? 1 ) );
			$mb    = (float) ( $v['max_file_size']['value'] ?? 2 );
			$exts  = array();
			foreach ( (array) ( $v['allowed_file_types']['value'] ?? array() ) as $group ) {
				$exts = array_merge( $exts, explode( '|', (string) $group ) );
			}
			$exts = array_values( array_unique( array_filter( array_map( 'strtolower', $exts ) ) ) );
			?>
			<fieldset class="th-lf-files" id="<?php echo esc_attr( $field_id ); ?>" data-th-file-field="<?php echo esc_attr( $uuid ); ?>" data-max="<?php echo esc_attr( (string) $max ); ?>" data-bytes="<?php echo esc_attr( (string) (int) round( $mb * MB_IN_BYTES ) ); ?>" data-types="<?php echo esc_attr( implode( ',', $exts ) ); ?>">
				<?php $legend_html(); ?>
				<ul class="th-lf-files__list" role="list" data-th-file-list>
					<?php foreach ( $files as $file ) : ?>
						<?php
						if ( ! is_array( $file ) || empty( $file['uid'] ) ) {
							continue;
						}
						?>
						<li class="th-lf-file" data-id="<?php echo esc_attr( (string) $file['uid'] ); ?>">
							<?php th_icon( 'file', array( 'size' => 18 ) ); ?>
							<a href="<?php echo esc_url( (string) $file['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $file['name'] ); ?></a>
							<button type="button" class="th-btn th-btn--text th-btn--sm" data-th-file-remove><?php esc_html_e( 'Remove', 'torrehub' ); ?></button>
						</li>
					<?php endforeach; ?>
				</ul>
				<label class="th-btn th-btn--neutral th-btn--sm th-lf-files__add" data-th-file-add<?php echo count( $files ) >= $max ? ' hidden' : ''; ?>>
					<input class="th-sr-only" type="file" accept="<?php echo esc_attr( $exts ? '.' . implode( ',.', $exts ) : '' ); ?>" data-th-file-input>
					<?php th_icon( 'upload', array( 'size' => 16 ) ); ?>
					<span><?php echo esc_html( $field['btn_text'] ?? __( 'Choose file', 'torrehub' ) ); ?></span>
				</label>
				<p class="th-help">
					<?php
					/* translators: 1: file types, 2: size limit in MB */
					echo esc_html( sprintf( __( '%1$s · max %2$s MB', 'torrehub' ), strtoupper( implode( ', ', $exts ) ), number_format_i18n( $mb, $mb < 1 ? 1 : 0 ) ) );
					?>
				</p>
			</fieldset>
			<?php
			break;

		case 'social_profiles':
			$profiles = is_array( $value ) ? $value : array();
			?>
			<fieldset class="th-lf-social" id="<?php echo esc_attr( $field_id ); ?>">
				<?php $legend_html(); ?>
				<div class="th-lf-grid th-lf-grid--nested">
					<?php foreach ( \Rtcl\Resources\Options::get_social_profiles_list() as $key => $platform ) : ?>
						<div class="th-field">
							<label class="th-label" for="<?php echo esc_attr( $field_id . '-' . $key ); ?>"><?php echo esc_html( 'twitter' === $key ? 'X (Twitter)' : $platform ); ?></label>
							<input class="th-input" type="url" id="<?php echo esc_attr( $field_id . '-' . $key ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $profiles[ $key ] ?? '' ) ); ?>" placeholder="https://" data-th-lf-url>
						</div>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<?php
			break;

		case 'video_urls':
			$videos = array_values( array_filter( is_array( $value ) ? array_map( 'strval', $value ) : ( '' !== $scalar ? array( $scalar ) : array() ) ) );
			?>
			<fieldset class="th-lf-videos" id="<?php echo esc_attr( $field_id ); ?>">
				<?php $legend_html(); ?>
				<?php $video_inputs = max( 2, count( $videos ) ); ?>
				<?php for ( $i = 0; $i < $video_inputs; $i++ ) : ?>
					<label class="th-sr-only" for="<?php echo esc_attr( $field_id . '-' . $i ); ?>">
						<?php
						/* translators: %d: video number */
						echo esc_html( sprintf( __( 'Video link %d', 'torrehub' ), $i + 1 ) );
						?>
					</label>
					<input class="th-input" type="url" id="<?php echo esc_attr( $field_id . '-' . $i ); ?>" name="<?php echo esc_attr( $name . '[]' ); ?>" value="<?php echo esc_attr( $videos[ $i ] ?? '' ); ?>" placeholder="https://www.youtube.com/watch?v=…" data-th-lf-url>
				<?php endfor; ?>
				<p class="th-help"><?php esc_html_e( 'YouTube or Vimeo links.', 'torrehub' ); ?></p>
			</fieldset>
			<?php
			break;

		case 'business_hours':
			$bh         = is_array( $value ) ? $value : array();
			$active     = ! empty( $bh['active'] );
			$hours_type = 'selective' === ( $bh['type'] ?? '' ) ? 'selective' : '247';
			$days       = (array) ( $bh['days'] ?? array() );
			?>
			<fieldset class="th-lf-hours" id="<?php echo esc_attr( $field_id ); ?>">
				<?php $legend_html(); ?>
				<label class="th-toggle-row"><span class="th-toggle"><input type="checkbox" name="<?php echo esc_attr( $name . '[active]' ); ?>" value="1" <?php checked( $active ); ?>></span> <span><?php esc_html_e( 'Show opening hours', 'torrehub' ); ?></span></label>
				<div class="th-stack" data-th-lf-when="<?php echo esc_attr( $name . '[active]=1' ); ?>" style="--th-stack-gap:12px">
					<div class="th-segmented" role="radiogroup" aria-label="<?php esc_attr_e( 'Opening hours', 'torrehub' ); ?>">
						<label><input type="radio" name="<?php echo esc_attr( $name . '[type]' ); ?>" value="selective" <?php checked( $hours_type, 'selective' ); ?>><?php esc_html_e( 'Set hours', 'torrehub' ); ?></label>
						<label><input type="radio" name="<?php echo esc_attr( $name . '[type]' ); ?>" value="247" <?php checked( $hours_type, '247' ); ?>><?php esc_html_e( 'Open 24/7', 'torrehub' ); ?></label>
					</div>
					<table class="th-lf-hours__table" data-th-lf-when="<?php echo esc_attr( $name . '[type]=selective' ); ?>">
						<thead class="th-sr-only"><tr><th scope="col"><?php esc_html_e( 'Day', 'torrehub' ); ?></th><th scope="col"><?php esc_html_e( 'Opens', 'torrehub' ); ?></th><th scope="col"><?php esc_html_e( 'Closes', 'torrehub' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( \Rtcl\Resources\Options::get_week_days() as $day_key => $day_name ) : ?>
								<?php
								$day   = (array) ( $days[ $day_key ] ?? array() );
								$open  = ! empty( $day['open'] );
								$times = array_values( (array) ( $day['times'] ?? array() ) );
								$times = $times ? $times : array(
									array(
										'start' => '09:00',
										'end'   => '18:00',
									),
								);
								$base  = $name . '[days][' . $day_key . ']';
								?>
								<tr>
									<th scope="row"><label class="th-check"><input type="checkbox" name="<?php echo esc_attr( $base . '[open]' ); ?>" value="1" <?php checked( $open ); ?>> <span><?php echo esc_html( $day_name ); ?></span></label></th>
									<?php foreach ( array_slice( $times, 0, 1 ) as $t => $time ) : ?>
										<td><input class="th-input th-input--time" type="time" name="<?php echo esc_attr( $base . '[times][' . $t . '][start]' ); ?>" value="<?php echo esc_attr( (string) ( $time['start'] ?? '' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: day */ __( '%s opens', 'torrehub' ), $day_name ) ); ?>"></td>
										<td><input class="th-input th-input--time" type="time" name="<?php echo esc_attr( $base . '[times][' . $t . '][end]' ); ?>" value="<?php echo esc_attr( (string) ( $time['end'] ?? '' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: day */ __( '%s closes', 'torrehub' ), $day_name ) ); ?>"></td>
									<?php endforeach; ?>
								</tr>
								<?php foreach ( array_slice( $times, 1 ) as $t => $time ) : ?>
									<tr class="th-lf-hours__extra">
										<td></td>
										<td><input class="th-input th-input--time" type="time" name="<?php echo esc_attr( $base . '[times][' . ( $t + 1 ) . '][start]' ); ?>" value="<?php echo esc_attr( (string) ( $time['start'] ?? '' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: day */ __( '%s opens again', 'torrehub' ), $day_name ) ); ?>"></td>
										<td><input class="th-input th-input--time" type="time" name="<?php echo esc_attr( $base . '[times][' . ( $t + 1 ) . '][end]' ); ?>" value="<?php echo esc_attr( (string) ( $time['end'] ?? '' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: day */ __( '%s closes again', 'torrehub' ), $day_name ) ); ?>"></td>
									</tr>
								<?php endforeach; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</fieldset>
			<?php
			break;

		case 'repeater':
			$rows     = array_values( is_array( $value ) ? $value : array() );
			$subs     = array_values( array_filter( (array) ( $field['fields'] ?? array() ), static fn( $s ) => is_array( $s ) && ! empty( $s['name'] ) && in_array( $s['element'] ?? 'text', array( 'text', 'number', 'url', 'textarea', 'select' ), true ) ) );
			$max_rows = (int) ( $field['max_repeat_field'] ?? 0 );
			$row_html = static function ( $index, array $row ) use ( $subs, $name, $field_id, $label ): void {
				?>
				<li class="th-lf-repeat__row">
					<?php foreach ( $subs as $sub ) : ?>
						<?php
						$sub_id    = $field_id . '-' . $index . '-' . sanitize_html_class( (string) $sub['name'] );
						$sub_name  = $name . '[' . $index . '][' . $sub['name'] . ']';
						$sub_value = isset( $row[ $sub['name'] ] ) && is_scalar( $row[ $sub['name'] ] ) ? (string) $row[ $sub['name'] ] : '';
						$sub_label = html_entity_decode( (string) ( $sub['label'] ?? '' ), ENT_QUOTES );
						$sub_label = '' !== $sub_label ? $sub_label : $label;
						?>
						<label class="th-sr-only" for="<?php echo esc_attr( $sub_id ); ?>"><?php echo esc_html( $sub_label ); ?></label>
						<?php if ( 'select' === ( $sub['element'] ?? '' ) ) : ?>
							<select class="th-select" id="<?php echo esc_attr( $sub_id ); ?>" name="<?php echo esc_attr( $sub_name ); ?>">
								<option value=""><?php esc_html_e( 'Choose…', 'torrehub' ); ?></option>
								<?php foreach ( Fields::options( $sub ) as $opt_value => $opt_label ) : ?>
									<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $sub_value, (string) $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'textarea' === ( $sub['element'] ?? '' ) ) : ?>
							<textarea class="th-textarea" rows="2" id="<?php echo esc_attr( $sub_id ); ?>" name="<?php echo esc_attr( $sub_name ); ?>"><?php echo esc_textarea( $sub_value ); ?></textarea>
						<?php else : ?>
							<input class="th-input" type="<?php echo esc_attr( 'number' === ( $sub['element'] ?? '' ) ? 'number' : ( 'url' === ( $sub['element'] ?? '' ) ? 'url' : 'text' ) ); ?>" id="<?php echo esc_attr( $sub_id ); ?>" name="<?php echo esc_attr( $sub_name ); ?>" value="<?php echo esc_attr( $sub_value ); ?>" placeholder="<?php echo esc_attr( $sub_label ); ?>">
						<?php endif; ?>
					<?php endforeach; ?>
					<button type="button" class="th-btn th-btn--neutral th-btn--sm th-btn--icon" data-th-repeat-remove aria-label="<?php esc_attr_e( 'Remove this row', 'torrehub' ); ?>"><?php th_icon( 'close', array( 'size' => 14 ) ); ?></button>
				</li>
				<?php
			};
			?>
			<fieldset class="th-lf-repeat" id="<?php echo esc_attr( $field_id ); ?>" data-th-repeat="<?php echo esc_attr( $name ); ?>" data-max="<?php echo esc_attr( (string) $max_rows ); ?>">
				<?php $legend_html(); ?>
				<ol class="th-lf-repeat__list" role="list" data-th-repeat-list>
					<?php
					foreach ( $rows ? $rows : array( array() ) as $index => $row ) {
						$row_html( $index, (array) $row );
					}
					?>
				</ol>
				<template data-th-repeat-template><?php $row_html( '__i__', array() ); ?></template>
				<button type="button" class="th-btn th-btn--neutral th-btn--sm" data-th-repeat-add><?php th_icon( 'plus', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Add another', 'torrehub' ); ?></span></button>
			</fieldset>
			<?php
			break;

		case 'terms_and_condition':
			?>
			<label class="th-check th-lf-terms"><input type="checkbox" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $value ) ); ?><?php echo $req ? ' aria-required="true"' : ''; ?>> <span>
			<?php
			echo wp_kses(
				(string) ( $field['tnc_html'] ?? __( 'I accept the Terms & Conditions.', 'torrehub' ) ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			);
			?>
																			</span></label>
			<?php
			break;

		case 'html':
			echo wp_kses_post( (string) ( $field['html_codes'] ?? '' ) );
			break;
	endswitch;
	?>
	<?php if ( $help ) : ?>
		<p class="th-help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
	<?php endif; ?>
	<p class="th-help th-help--error" id="<?php echo esc_attr( $err_id ); ?>" data-th-lf-error hidden><?php th_icon( 'alert-circle', array( 'size' => 15 ) ); ?><span></span></p>
</div>
