<?php
/**
 * Plugin Name: Torrehub — local safety net
 * Description: LOCAL ONLY. Forces every wp_mail() into Local's Mailpit and refuses to run anywhere else.
 *
 * Installed by bin/setup-local.sh into wp-content/mu-plugins/. Never deploy this file.
 */

defined( 'ABSPATH' ) || exit;

if ( 'local' !== wp_get_environment_type() ) {
	return;
}

if ( ! defined( 'TH_MAILPIT_HOST' ) ) {
	define( 'TH_MAILPIT_HOST', '127.0.0.1' );
}
if ( ! defined( 'TH_MAILPIT_PORT' ) ) {
	define( 'TH_MAILPIT_PORT', 10001 );
}

/*
 * Run last so no SMTP plugin (fluent-smtp etc.) can re-point PHPMailer afterwards.
 */
add_action(
	'phpmailer_init',
	static function ( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host        = TH_MAILPIT_HOST;
		$phpmailer->Port        = (int) TH_MAILPIT_PORT;
		$phpmailer->SMTPAuth    = false;
		$phpmailer->Username    = '';
		$phpmailer->Password    = '';
		$phpmailer->SMTPSecure  = '';
		$phpmailer->SMTPAutoTLS = false;
	},
	PHP_INT_MAX
);

/*
 * fluent-smtp replaces wp_mail() entirely when active; keep it off locally even if someone re-activates it.
 */
add_filter(
	'option_active_plugins',
	static function ( $plugins ) {
		return is_array( $plugins ) ? array_values( array_diff( $plugins, array( 'fluent-smtp/fluent-smtp.php' ) ) ) : $plugins;
	}
);

/*
 * No search engine should ever index a local copy.
 */
add_filter( 'pre_option_blog_public', '__return_zero' );
