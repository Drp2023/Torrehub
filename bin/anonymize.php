<?php
defined( 'WP_CLI' ) || exit; // CLI only — this file lives inside the theme folder.
/**
 * Anonymise the imported live database. LOCAL ONLY.
 *
 * Run: wp eval-file bin/anonymize.php --skip-plugins --skip-themes
 * Prints counts only — never personal values.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Refusing to run: WP_ENVIRONMENT_TYPE is not "local".' );
}

global $wpdb;

$keep_login = getenv( 'TH_KEEP_USER' ) ?: 'dev';

/**
 * Valid-looking Spanish NIF (DNI form): 8 digits + mod-23 control letter.
 */
function th_fake_nif( int $seed ): string {
	$letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
	$num     = ( $seed * 104729 + 10000000 ) % 100000000;
	return str_pad( (string) $num, 8, '0', STR_PAD_LEFT ) . $letters[ $num % 23 ];
}

function th_fake_phone( int $seed, string $prefix = '600' ): string {
	return '+34 ' . $prefix . ' ' . str_pad( (string) ( $seed % 1000000 ), 6, '0', STR_PAD_LEFT );
}

$report = array();

/* ---------------------------------------------------------------- users */
$users = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login <> %s", $keep_login ) );
foreach ( $users as $id ) {
	$id = (int) $id;
	$wpdb->update(
		$wpdb->users,
		array(
			'user_email'    => "user{$id}@example.test",
			'user_pass'     => wp_hash_password( wp_generate_password( 32, true, true ) ),
			'user_login'    => "user{$id}",
			'user_nicename' => "user-{$id}",
			'display_name'  => "User {$id}",
			'user_url'      => '',
			'user_activation_key' => '',
		),
		array( 'ID' => $id )
	);
	$meta = array(
		'first_name'             => 'User',
		'last_name'              => (string) $id,
		'nickname'               => "user{$id}",
		'description'            => '',
		'custom_field_2'         => th_fake_nif( $id ),
		'_rtcl_phone'            => th_fake_phone( $id, '600' ),
		'_rtcl_whatsapp_number'  => th_fake_phone( $id, '611' ),
		'_rtcl_address'          => "Calle Ejemplo {$id}, 03181 Torrevieja",
		'_rtcl_website'          => '',
		'_rtcl_telegram'         => '',
		'billing_email'          => "user{$id}@example.test",
		'billing_phone'          => th_fake_phone( $id, '622' ),
	);
	foreach ( $meta as $key => $value ) {
		// Only overwrite meta that exists — don't invent NIE/NIF for members.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE user_id = %d AND meta_key = %s", $value, $id, $key ) );
	}
}
$report['users anonymised'] = count( $users );

/* NIE numbers are not kept at all (client decision 2026-10-06, GDPR): delete, don't fake. */
$report['NIE meta rows deleted'] = (int) $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('custom_field_1','nif_nie')" );

/* Seller-verification document references (ID scans) — remove meta AND the attachment files. */
$doc_ids = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('photo_id','other_document_id') AND meta_value REGEXP '^[0-9]+$'" );
$report['verification doc attachments deleted'] = 0;
foreach ( array_unique( array_map( 'intval', $doc_ids ) ) as $att_id ) {
	if ( $att_id && 'attachment' === get_post_type( $att_id ) && wp_delete_attachment( $att_id, true ) ) {
		++$report['verification doc attachments deleted'];
	}
}
$report['verification doc meta rows deleted'] = (int) $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('photo_id','other_document_id')" );

/* Sessions + application passwords. */
$report['session/app-password meta deleted'] = (int) $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('session_tokens','_application_passwords','_application_passwords_last_used')" );

/* -------------------------------------------------------- listing meta */
$listing_meta = array(
	'phone'                 => static fn( $pid ) => th_fake_phone( $pid, '633' ),
	'_rtcl_whatsapp_number' => static fn( $pid ) => th_fake_phone( $pid, '644' ),
	'email'                 => static fn( $pid ) => "listing{$pid}@example.test",
	'_rtcl_telegram'        => static fn( $pid ) => '',
);
$report['listing contact meta rewritten'] = 0;
foreach ( $listing_meta as $key => $make ) {
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_id, pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'rtcl_listing' AND pm.meta_key = %s AND pm.meta_value <> ''", $key ) );
	foreach ( $rows as $row ) {
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $make( (int) $row->post_id ) ), array( 'meta_id' => $row->meta_id ) );
		++$report['listing contact meta rewritten'];
	}
}

/* ------------------------------------------------------------- comments (reviews) */
$report['comments anonymised'] = (int) $wpdb->query(
	"UPDATE {$wpdb->comments} SET comment_author = CONCAT('Reviewer ', comment_ID), comment_author_email = CONCAT('reviewer', comment_ID, '@example.test'), comment_author_IP = '127.0.0.1', comment_author_url = '', comment_agent = ''"
);

/* ---------------------------------------------------------- truncations */
$truncate_patterns = array(
	'rtcl_conversation%',
	'rtcl_chat%',
	'rtcl_sessions',
	'fluentform_submissions',
	'fluentform_submission_meta',
	'fluentform_entry_details',
	'fluentform_logs',
	'e_submissions%',
	'wpforms_%',
	'cwp_forms_leads',
	'fsmpt_email_logs',
	'signups',
	'wpaas_activity_log',
	// Leftover plugins whose logs can hold addresses/IPs (documented in BUILD-PLAN, tables kept).
	'wpmailsmtp_debug_events',
	'post_smtp_log%',
	'rcb_consent%',
	'user_registration_sessions',
	'listing_stats_leads',
	'social_users',
	'geodir_post_reports',
	'woocommerce_sessions',
);
$truncated = array();
foreach ( $truncate_patterns as $pattern ) {
	$like   = $wpdb->esc_like( $wpdb->prefix ) . str_replace( '_', '\_', $pattern );
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	foreach ( $tables as $table ) {
		$wpdb->query( "TRUNCATE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$truncated[] = substr( $table, strlen( $wpdb->prefix ) );
	}
}
$report['tables truncated'] = count( $truncated ) . ' (' . implode( ', ', $truncated ) . ')';

/* ---------------------------------------------------------- site-level emails */
// Direct write: update_option() would fire the "Admin Email Changed" notice to the live address.
$wpdb->update( $wpdb->options, array( 'option_value' => 'dev@example.test' ), array( 'option_name' => 'admin_email' ) );
$wpdb->delete( $wpdb->options, array( 'option_name' => 'new_admin_email' ) );

/* ------------------------------------------------------------- output */
foreach ( $report as $label => $value ) {
	WP_CLI::log( str_pad( $label, 42 ) . ': ' . $value );
}
WP_CLI::success( 'Anonymisation complete.' );
