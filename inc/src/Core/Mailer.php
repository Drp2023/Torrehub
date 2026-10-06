<?php
/**
 * Theme e-mails: one simple, translatable HTML layout (inline styles, table-free) with a plain-text alternative.
 * Delivery is wp_mail(), so FluentSMTP (live) or Mailpit (local) send it.
 *
 * @package Torrehub
 */

namespace Torrehub\Core;

defined( 'ABSPATH' ) || exit;

/**
 * E-mail sender.
 */
final class Mailer {

	/**
	 * Send a message.
	 *
	 * @param string                        $to         Recipient.
	 * @param string                        $subject    Subject (without site prefix).
	 * @param string                        $heading    Heading in the body.
	 * @param array<int,string>             $paragraphs Plain-text paragraphs (escaped here).
	 * @param array{0:string,1:string}|null $button [ label, url ].
	 * @param string                        $footnote   Small print under the button.
	 */
	public static function send( string $to, string $subject, string $heading, array $paragraphs, ?array $button = null, string $footnote = '' ): bool {
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$html = self::html( $heading, $paragraphs, $button, $footnote );
		$text = $heading . "\n\n" . implode( "\n\n", $paragraphs ) . ( $button ? "\n\n" . $button[0] . ': ' . $button[1] : '' ) . ( $footnote ? "\n\n" . $footnote : '' );

		$alt = static function ( $phpmailer ) use ( $text ) {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer API.
		};
		add_action( 'phpmailer_init', $alt );
		$sent = wp_mail( $to, sprintf( '[%s] %s', $site, $subject ), $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		remove_action( 'phpmailer_init', $alt );
		return $sent;
	}

	/**
	 * HTML body.
	 *
	 * @param string            $heading    Heading.
	 * @param array<int,string> $paragraphs Paragraphs.
	 * @param array|null        $button     [ label, url ].
	 * @param string            $footnote   Footnote.
	 */
	private static function html( string $heading, array $paragraphs, ?array $button, string $footnote ): string {
		$site = esc_html( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
		$out  = '<!doctype html><html><body style="margin:0;padding:24px;background:#f7f7fb;font-family:Helvetica,Arial,sans-serif;color:#3a4562;">';
		$out .= '<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e2e2ec;border-radius:16px;padding:28px;">';
		$out .= '<p style="margin:0 0 18px;font-weight:700;color:#0056b3;">' . $site . '</p>';
		$out .= '<h1 style="margin:0 0 14px;font-size:22px;line-height:1.25;color:#17203a;">' . esc_html( $heading ) . '</h1>';
		foreach ( $paragraphs as $p ) {
			$out .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.55;">' . nl2br( esc_html( $p ) ) . '</p>';
		}
		if ( $button ) {
			$out .= '<p style="margin:22px 0;"><a href="' . esc_url( $button[1] ) . '" style="display:inline-block;padding:13px 22px;border-radius:100px;background:#ff8c00;color:#17203a;font-weight:700;text-decoration:none;">' . esc_html( $button[0] ) . '</a></p>';
			/* translators: %s: URL */
			$out .= '<p style="margin:0 0 12px;font-size:12px;color:#5f6a8a;word-break:break-all;">' . esc_html( sprintf( __( 'Or open this link: %s', 'torrehub' ), $button[1] ) ) . '</p>';
		}
		if ( $footnote ) {
			$out .= '<p style="margin:18px 0 0;font-size:12px;color:#5f6a8a;">' . esc_html( $footnote ) . '</p>';
		}
		$out .= '</div></body></html>';
		return $out;
	}
}
