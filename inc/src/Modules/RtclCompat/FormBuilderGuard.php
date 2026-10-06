<?php
/**
 * Safety net for Classified Listing's admin Form Builder (Classified Listing › Form Builder).
 *
 * 1. Option values in use are kept. Renaming an option with "Show Values" off makes the builder derive a new
 *    value from the new label ("Motorbike" → "motorbike_test"): listings that stored the old value lose their
 *    label, archive filters miss them and conditions testing the old value ("Item type = Motorbike" shows the
 *    Motorcycle section) never match again. Before Classified Listing saves, an option whose old value vanished
 *    and whose place was taken by a new value gets its old value back when that value is stored on a listing or
 *    used by a condition. The new label stays.
 * 2. Classified Listing 6.1.5 writes a debug file (wp-content/rtcl-fb-debug.log, section titles) on every form
 *    save — publicly reachable. It is deleted after the save. Remove this once the plugin drops the debug code.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\RtclCompat;

defined( 'ABSPATH' ) || exit;

/**
 * Form Builder save guard.
 */
final class FormBuilderGuard {

	private const CHOICE = array( 'select', 'radio', 'checkbox' );

	/**
	 * Hooks (before Classified Listing's own handler at priority 10).
	 */
	public function register(): void {
		add_action( 'wp_ajax_rtcl_fb_admin_form_update', array( $this, 'before_save' ), 0 );
		add_action( 'admin_init', array( self::class, 'delete_debug_log' ) );
	}

	/**
	 * Keep in-use option values; schedule the debug-file removal.
	 */
	public function before_save(): void {
		register_shutdown_function( array( self::class, 'delete_debug_log' ) );
		if ( ! current_user_can( 'manage_rtcl_options' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Classified Listing's capability.
			return; // Classified Listing refuses the request itself.
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Classified Listing verifies its nonce right after; this only adjusts the payload.
		$form_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$raw     = isset( $_POST['fields'] ) && is_string( $_POST['fields'] ) ? json_decode( wp_unslash( $_POST['fields'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, sanitised by Classified Listing.
		// phpcs:enable
		$form = $form_id ? \Rtcl\Models\Form\Form::query()->find( $form_id ) : null;
		if ( ! $form || ! is_array( $raw ) ) {
			return;
		}
		$fixed = self::lock_values( (array) $form->fields, $raw, (array) $form->sections );
		if ( $fixed['changed'] ) {
			$_POST['fields'] = wp_slash( (string) wp_json_encode( $fixed['fields'] ) );
		}
	}

	/**
	 * Restore option values that vanished but are in use.
	 *
	 * @param array<string,array> $old      Saved fields (uuid => field).
	 * @param array<string,array> $posted   Posted fields.
	 * @param array<int,array>    $sections Saved sections (for conditions).
	 * @return array{fields:array,changed:bool}
	 */
	public static function lock_values( array $old, array $posted, array $sections ): array {
		$changed = false;
		foreach ( $posted as $uuid => $field ) {
			$before = $old[ $uuid ] ?? null;
			if ( ! is_array( $field ) || ! is_array( $before ) || ! in_array( $field['element'] ?? '', self::CHOICE, true ) || empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
				continue;
			}
			$old_values = array_map( static fn( $o ) => (string) ( $o['value'] ?? '' ), (array) ( $before['options'] ?? array() ) );
			$new_values = array_map( static fn( $o ) => is_array( $o ) ? (string) ( $o['value'] ?? '' ) : '', $field['options'] );
			foreach ( $old_values as $index => $value ) {
				if ( '' === $value || in_array( $value, $new_values, true ) || ! isset( $field['options'][ $index ] ) ) {
					continue;
				}
				$replacement = $new_values[ $index ];
				// Only a rename in place: the option at the same position carries a value that didn't exist before.
				if ( in_array( $replacement, $old_values, true ) || ! self::in_use( (string) ( $before['name'] ?? '' ), $value, (string) $uuid, $old, $sections ) ) {
					continue;
				}
				$posted[ $uuid ]['options'][ $index ]['value'] = $value;
				$new_values[ $index ]                          = $value;
				$changed                                       = true;
			}
		}
		return array(
			'fields'  => $posted,
			'changed' => $changed,
		);
	}

	/**
	 * Is an option value stored on a listing or tested by a condition?
	 *
	 * @param string              $name     Field (meta) name.
	 * @param string              $value    Option value.
	 * @param string              $uuid     Field uuid.
	 * @param array<string,array> $fields   Saved fields.
	 * @param array<int,array>    $sections Saved sections.
	 */
	private static function in_use( string $name, string $value, string $uuid, array $fields, array $sections ): bool {
		$logics = array_merge( array_column( $sections, 'logics' ), array_column( $fields, 'logics' ) );
		foreach ( $logics as $logic ) {
			foreach ( (array) ( is_array( $logic ) ? ( $logic['conditions'] ?? array() ) : array() ) as $condition ) {
				if ( is_array( $condition ) && (string) ( $condition['fieldId'] ?? '' ) === $uuid && (string) ( $condition['value'] ?? '' ) === $value ) {
					return true;
				}
			}
		}
		if ( '' === $name ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one indexed lookup per renamed option, admin save only.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", $name, $value ) );
	}

	/**
	 * Remove Classified Listing's debug file.
	 */
	public static function delete_debug_log(): void {
		$file = WP_CONTENT_DIR . '/rtcl-fb-debug.log';
		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
	}
}
