<?php
defined( 'WP_CLI' ) || exit; // CLI only — this file lives inside the theme folder.
/**
 * Detach external services from the local copy. LOCAL ONLY.
 *
 * Run: wp eval-file bin/detach-services.php --skip-plugins --skip-themes
 * Never prints secret values — only which keys were blanked.
 *
 * Original values are NOT kept: the live site is the source of truth.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Refusing to run: WP_ENVIRONMENT_TYPE is not "local".' );
}

/**
 * Blank selected keys inside an array option. Returns the keys that actually changed.
 */
function th_blank_option_keys( string $option, array $keys, array $set = array() ): array {
	$value = get_option( $option, null );
	if ( ! is_array( $value ) ) {
		return array();
	}
	$changed = array();
	foreach ( $keys as $key ) {
		if ( isset( $value[ $key ] ) && '' !== $value[ $key ] ) {
			$value[ $key ] = '';
			$changed[]     = $key;
		}
	}
	foreach ( $set as $key => $new ) {
		if ( ( $value[ $key ] ?? null ) !== $new ) {
			$value[ $key ] = $new;
			$changed[]     = "{$key}=" . var_export( $new, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
	if ( $changed ) {
		update_option( $option, $value );
	}
	return $changed;
}

$log = array();

// OpenAI.
$log['rtcl_ai_settings'] = th_blank_option_keys( 'rtcl_ai_settings', array( 'gpt_api_key' ) );

// Pusher (chat realtime). DECISION: also switch Pusher off so the chat JS doesn't try to connect with empty creds.
$log['rtcl_chat_settings'] = th_blank_option_keys( 'rtcl_chat_settings', array( 'pusher_app_id', 'pusher_app_key', 'pusher_app_secret' ), array( 'pusher_enable' => '' ) );

// reCAPTCHA + MaxMind (Google Maps key stays: it is domain-restricted and the map may simply not load locally).
$log['rtcl_misc_settings']     = th_blank_option_keys( 'rtcl_misc_settings', array( 'recaptcha_site_key', 'recaptcha_secret_key', 'maxmind_license_key' ) );
$log['rtcl_misc_map_settings'] = th_blank_option_keys( 'rtcl_misc_map_settings', array( 'maxmind_license_key' ) );

// Payments.
$log['rtcl_payment_paypal'] = th_blank_option_keys( 'rtcl_payment_paypal', array( 'api_username', 'api_password', 'api_signature', 'email', 'receiver_email' ), array( 'enabled' => false ) );
foreach ( array( 'rtcl_payment_stripe', 'rtcl_payment_authorizenet' ) as $opt ) {
	$log[ $opt ] = th_blank_option_keys( $opt, array( 'secret_key', 'publishable_key', 'test_secret_key', 'test_publishable_key', 'webhook_secret', 'api_login_id', 'transaction_key' ) );
}

// Fluent Forms captcha integrations.
foreach ( array( '_fluentform_reCaptcha_details', '_fluentform_hCaptcha_details', '_fluentform_turnstile_details' ) as $opt ) {
	if ( get_option( $opt ) ) {
		delete_option( $opt );
		$log[ $opt ] = array( 'deleted' );
	}
}

// fluent-smtp stored SMTP credentials. The plugin is also deactivated by setup-local.sh.
$fsmtp = get_option( 'fluentmail-settings' );
if ( is_array( $fsmtp ) && ! empty( $fsmtp['connections'] ) ) {
	$fsmtp['connections'] = array();
	$fsmtp['mappings']    = array();
	update_option( 'fluentmail-settings', $fsmtp );
	$log['fluentmail-settings'] = array( 'connections', 'mappings' );
}

// Insert Headers & Footers: tracking snippets (GA/Pixel) would count local page views. Keep a copy, blank the live value.
foreach ( array( 'ihaf_insert_header', 'ihaf_insert_body', 'ihaf_insert_footer' ) as $opt ) {
	$v = get_option( $opt );
	if ( is_string( $v ) && '' !== trim( $v ) ) {
		update_option( $opt . '__live_copy', $v, false );
		update_option( $opt, '' );
		$log[ $opt ] = array( 'blanked (copy in ' . $opt . '__live_copy)' );
	}
}

// Search Alert: remove scheduled events (WP-Cron + Action Scheduler) so nothing fires even if cron is re-enabled.
$cron      = _get_cron_array();
$unhooked  = 0;
foreach ( (array) $cron as $ts => $hooks ) {
	foreach ( (array) $hooks as $hook => $events ) {
		if ( false !== stripos( $hook, 'search_alert' ) || false !== stripos( $hook, 'searchalert' ) ) {
			wp_clear_scheduled_hook( $hook );
			++$unhooked;
		}
	}
}
$log['wp-cron search-alert hooks cleared'] = array( (string) $unhooked );

global $wpdb;
$as_table = $wpdb->prefix . 'actionscheduler_actions';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $as_table ) ) === $as_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$n = $wpdb->query( "UPDATE `{$as_table}` SET status = 'canceled' WHERE status = 'pending' AND (hook LIKE '%search_alert%' OR hook LIKE '%searchalert%')" );
	$log['action-scheduler search-alert canceled'] = array( (string) (int) $n );
}

update_option( 'blog_public', '0' );
$log['blog_public'] = array( '0' );

foreach ( $log as $what => $keys ) {
	WP_CLI::log( str_pad( $what, 42 ) . ': ' . ( $keys ? implode( ', ', $keys ) : '(nothing to change)' ) );
}
WP_CLI::success( 'External services detached.' );
