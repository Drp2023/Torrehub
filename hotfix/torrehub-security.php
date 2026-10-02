<?php
/**
 * Plugin Name: Torrehub — security hotfix
 * Description: Closes two holes in the legacy plugin stack until the new theme replaces it: cldirectory-core's unauthenticated user export and rtcl-seller-verification's cross-user document AJAX (IDOR).
 * Version:     1.0.0
 * Author:      Torrehub
 * License:     GPL-2.0-or-later
 *
 * Install as a must-use plugin: wp-content/mu-plugins/torrehub-security.php (see hotfix/README.md).
 * Safe to keep after go-live: every check is a no-op when the affected plugin is gone.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/*
 * 1) cldirectory-core: `?export_user=1` dumps users 2-9 (email, password hash, usermeta) into
 *    plugins/cldirectory-core/demo-users/*.json. It runs inside the plugin constructor, i.e. while the plugin
 *    file is being included — before WordPress has authenticated anyone. At that moment an admin cannot be told
 *    apart from a visitor, so the parameter is removed for everyone. Must-use plugins load before regular plugins,
 *    which is what makes this effective. (It is a demo-data developer tool; no admin workflow depends on it.)
 */
if ( isset( $_GET['export_user'] ) || isset( $_REQUEST['export_user'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	unset( $_GET['export_user'], $_REQUEST['export_user'] );
}

/*
 * 2) rtcl-seller-verification: upload/delete/download AJAX handlers take `user_id` from the request without
 *    checking it belongs to the caller (upload/delete have no nonce check at all). Any logged-in user could
 *    replace, delete or download another user's ID documents.
 *    Rule: the target user must be the current user, unless the caller can `edit_users` (administrators managing
 *    a seller from wp-admin).
 */
add_action(
	'admin_init',
	static function () {
		if ( ! wp_doing_ajax() || empty( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$guarded = array(
			'rtcl_ajax_documents_photo_upload',
			'rtcl_ajax_documents_photo_delete',
			'rtcl_ajax_document_file_upload',
			'rtcl_ajax_documents_file_delete',
			'rtcl_ajax_documents_file_download',
		);
		$action  = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $action, $guarded, true ) ) {
			return;
		}

		$current = get_current_user_id();
		$target  = isset( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : $current; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $current || ( $target !== $current && ! current_user_can( 'edit_users' ) ) ) {
			wp_send_json_error( array( 'message' => 'You are not allowed to manage these documents.' ), 403 );
		}
	},
	0 // Before admin-ajax.php dispatches wp_ajax_{action}.
);
