<?php
/**
 * Appearance → Listing cards: which Form Builder fields show on listing cards (up to 3, in form order).
 *
 * The flag is Classified Listing's own `archive_view` on the field definition (`rtcl_forms.fields`) — the same one
 * the card reads (FBField::isArchiveViewAble). Its switch in the plugin's Form Builder ("Display at archive page")
 * is Pro-only, so the theme offers it here. Private fields (NIF, plates…) never show, whatever the flag.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Archive;

use Rtcl\Models\Form\Form;
use Torrehub\Data\ListingFields;

defined( 'ABSPATH' ) || exit;

/**
 * Card fields admin screen.
 */
final class CardFields {

	private const PAGE = 'torrehub-card-fields';

	/**
	 * Field types that make a short card attribute.
	 */
	private const TYPES = array( 'text', 'select', 'radio', 'checkbox', 'number', 'date' );

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_th_card_fields', array( $this, 'save' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu(): void {
		add_theme_page( __( 'Listing cards', 'torrehub' ), __( 'Listing cards', 'torrehub' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * Card-capable fields of a form, in form order.
	 *
	 * @param Form $form Form.
	 * @return array<string,array<string,mixed>> uuid => field.
	 */
	private static function fields( Form $form ): array {
		$out = array();
		foreach ( (array) $form->sections as $section ) {
			foreach ( (array) ( $section['containers'] ?? array() ) as $container ) {
				foreach ( (array) ( $container['fields'] ?? array() ) as $uuid ) {
					$field = $form->fields[ $uuid ] ?? null;
					if ( is_array( $field ) && empty( $field['preset'] ) && in_array( $field['element'] ?? '', self::TYPES, true ) && ! ListingFields::is_private( $field ) ) {
						$out[ (string) $uuid ] = $field;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Save the flags into the form definitions.
	 */
	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'torrehub' ), 403 );
		}
		check_admin_referer( 'th_card_fields' );
		$posted = isset( $_POST['th_card'] ) && is_array( $_POST['th_card'] ) ? map_deep( wp_unslash( $_POST['th_card'] ), 'sanitize_key' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_key on every value.
		global $wpdb;
		foreach ( Form::query()->get() as $form ) {
			$on     = array_keys( (array) ( $posted[ (string) $form->id ] ?? array() ) );
			$fields = (array) $form->fields;
			$dirty  = false;
			foreach ( array_keys( self::fields( $form ) ) as $uuid ) {
				$want = in_array( sanitize_key( $uuid ), $on, true );
				if ( (bool) ( $fields[ $uuid ]['archive_view'] ?? false ) !== $want ) {
					$fields[ $uuid ]['archive_view'] = $want;
					$dirty                           = true;
				}
			}
			if ( $dirty ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Classified Listing's own table; one row per changed form.
				$wpdb->update( $wpdb->prefix . 'rtcl_forms', array( 'fields' => wp_json_encode( $fields ) ), array( 'id' => (int) $form->id ) );
			}
		}
		wp_cache_flush_group( 'rtcl' );
		wp_safe_redirect( add_query_arg( 'th_msg', 'saved', admin_url( 'themes.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Render the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msg = isset( $_GET['th_msg'] ) ? sanitize_key( wp_unslash( $_GET['th_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Listing cards', 'torrehub' ); ?></h1>
			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Card fields saved.', 'torrehub' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Tick the listing-form fields to show as short details on listing cards (archive, home page, related listings). Cards show the first three that have a value, in form order. Private fields (NIF, number plates…) are never shown.', 'torrehub' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="th_card_fields">
				<?php wp_nonce_field( 'th_card_fields' ); ?>
				<?php foreach ( Form::query()->where( 'status', 'publish' )->order_by( 'title', 'ASC' )->get() as $form ) : ?>
					<?php $fields = self::fields( $form ); ?>
					<h2><?php echo esc_html( html_entity_decode( (string) $form->title, ENT_QUOTES ) ); ?></h2>
					<?php if ( ! $fields ) : ?>
						<p><?php esc_html_e( 'No fields suitable for cards.', 'torrehub' ); ?></p>
						<?php continue; ?>
					<?php endif; ?>
					<fieldset style="columns:3 220px;max-width:900px">
						<legend class="screen-reader-text"><?php echo esc_html( (string) $form->title ); ?></legend>
						<?php foreach ( $fields as $uuid => $field ) : ?>
							<label style="display:block;margin:0 0 6px">
								<input type="checkbox" name="<?php echo esc_attr( 'th_card[' . $form->id . '][' . $uuid . ']' ); ?>" value="1" <?php checked( ! empty( $field['archive_view'] ) ); ?>>
								<?php echo esc_html( html_entity_decode( (string) ( $field['label'] ?? $field['name'] ), ENT_QUOTES ) ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endforeach; ?>
				<?php submit_button( __( 'Save card fields', 'torrehub' ) ); ?>
			</form>
		</div>
		<?php
	}
}
