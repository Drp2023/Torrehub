<?php
/**
 * Static pages (T-01 About, T-02 Contact, T-03 FAQ, T-04/T-05 legal), the generic page, 404 and the
 * Elementor → block content migration (`wp torrehub migrate-pages`, inc/cli.php).
 *
 * - Page templates in page-templates/ (selectable in the editor): about, contact, faq, legal.
 * - Contact form: the theme's own (no plugin): name, e-mail, subject, message; honeypot + signed time + 5 per hour
 *   per IP; sent to the Customizer address (default: admin e-mail) with Reply-To.
 * - FAQ: the page content's Details blocks are the questions (search box filters them); FAQPage JSON-LD from them.
 * - Legal: numbered "contents" from the h2 headings + "Last updated".
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Content;

use Torrehub\Core\Mailer;
use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Content module.
 */
final class Module extends BaseModule {

	/**
	 * Template basename of the current request (set at template_include).
	 *
	 * @var string
	 */
	private static string $template = '';

	/**
	 * Templates that use the content bundle.
	 */
	private const TEMPLATES = array( 'page.php', 'about.php', 'contact.php', 'faq.php', 'legal.php', '404.php' );

	/**
	 * Contact subjects (value => label). Filterable.
	 *
	 * @return array<string,string>
	 */
	public static function subjects(): array {
		/**
		 * Contact form subjects.
		 *
		 * @param array<string,string> $subjects value => label.
		 */
		return (array) apply_filters(
			'th_contact_subjects',
			array(
				'general' => __( 'General question', 'torrehub' ),
				'listing' => __( 'Report a listing', 'torrehub' ),
				'account' => __( 'My account', 'torrehub' ),
				'upgrade' => __( 'Upgrade to a seller account', 'torrehub' ),
				'press'   => __( 'Business & partnerships', 'torrehub' ),
			)
		);
	}

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'content';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Pages & contact form', 'torrehub' );
	}

	/**
	 * Core building block.
	 */
	public function optional(): bool {
		return false;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_post_type_support( 'page', 'excerpt' );
		add_filter( 'template_include', array( $this, 'remember_template' ), 999 );
		add_filter( 'th_page_css_bundles', array( $this, 'css_bundles' ) );
		th_on_front_post( 'th_contact', array( $this, 'contact' ) );
		add_action( 'wp_head', array( $this, 'faq_schema' ), 30 );
	}

	/**
	 * Remember which template renders the request.
	 *
	 * @param string $template Template path.
	 */
	public function remember_template( $template ) {
		self::$template = basename( (string) $template );
		return $template;
	}

	/**
	 * Content bundle for pages, the page templates and 404.
	 *
	 * @param array<int,string> $bundles Bundles.
	 * @return array<int,string>
	 */
	public function css_bundles( $bundles ): array {
		$bundles = (array) $bundles;
		if ( in_array( self::$template, self::TEMPLATES, true ) && ! in_array( 'content', $bundles, true ) ) {
			$bundles[] = 'content';
		}
		return $bundles;
	}

	/* ------------------------------------------------------------------ contact form */

	/**
	 * Contact form POST.
	 */
	public function contact(): void {
		check_admin_referer( 'th_contact' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$back   = isset( $_POST['back'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['back'] ) ), home_url( '/' ) ) : home_url( '/' );
		$values = array(
			'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'   => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'subject' => isset( $_POST['subject'] ) ? sanitize_key( wp_unslash( $_POST['subject'] ) ) : '',
			'message' => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
		);
		$trap   = ! empty( $_POST['th_website'] );
		$stamp  = isset( $_POST['th_ts'] ) ? sanitize_text_field( wp_unslash( $_POST['th_ts'] ) ) : '';
		// phpcs:enable
		$errors = array();
		if ( '' === $values['name'] ) {
			$errors['name'] = __( 'Tell us your name.', 'torrehub' );
		}
		if ( ! is_email( $values['email'] ) ) {
			$errors['email'] = __( 'Enter a complete e-mail address.', 'torrehub' );
		}
		if ( ! isset( self::subjects()[ $values['subject'] ] ) ) {
			$values['subject'] = 'general';
		}
		$length = mb_strlen( $values['message'] );
		if ( $length < 10 ) {
			$errors['message'] = __( 'Write a few words about what you need.', 'torrehub' );
		} elseif ( $length > 2000 ) {
			$errors['message'] = __( 'Keep it under 2,000 characters.', 'torrehub' );
		}
		// Bots: honeypot, a signed form time (3 s – 12 h), 5 messages per IP per hour.
		$parts = explode( '.', $stamp );
		$time  = (int) ( $parts[0] ?? 0 );
		$valid = 2 === count( $parts ) && hash_equals( wp_hash( 'th_contact|' . $time ), (string) $parts[1] ) && time() - $time >= 3 && time() - $time <= 12 * HOUR_IN_SECONDS;
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'th_contact_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $trap || ! $valid ) {
			// Pretend success: don't teach bots what tripped them.
			wp_safe_redirect( add_query_arg( 'th_sent', '1', $back ) . '#contact-form' );
			exit;
		}
		if ( $count >= 5 ) {
			$errors['_'] = __( 'Too many messages from your connection. Try again in an hour, or e-mail us directly.', 'torrehub' );
		}
		if ( $errors ) {
			set_transient( 'th_contact_form_' . md5( $ip . $stamp ), compact( 'values', 'errors' ), 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'th_form', rawurlencode( $stamp ), $back ) . '#contact-form' );
			exit;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		$to = (string) th_mod( 'th_contact_to' );
		$to = is_email( $to ) ? $to : (string) get_option( 'admin_email' );
		Mailer::send(
			$to,
			/* translators: 1: subject, 2: sender name */
			sprintf( __( 'Contact: %1$s — %2$s', 'torrehub' ), self::subjects()[ $values['subject'] ], $values['name'] ),
			self::subjects()[ $values['subject'] ],
			array(
				/* translators: 1: name, 2: e-mail */
				sprintf( __( 'From %1$s <%2$s>', 'torrehub' ), $values['name'], $values['email'] ),
				$values['message'],
			),
			null,
			__( 'Reply to this e-mail to answer the sender directly.', 'torrehub' ),
			array( 'headers' => array( 'Reply-To: ' . $values['name'] . ' <' . $values['email'] . '>' ) )
		);
		wp_safe_redirect( add_query_arg( 'th_sent', '1', $back ) . '#contact-form' );
		exit;
	}

	/**
	 * Signed time stamp for the contact form.
	 */
	public static function form_stamp(): string {
		$time = time();
		return $time . '.' . wp_hash( 'th_contact|' . $time );
	}

	/**
	 * Values + errors of a failed submission (by its stamp), once.
	 *
	 * @return array{values:array<string,string>,errors:array<string,string>}
	 */
	public static function form_result(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- looks up a short-lived result by its signed stamp.
		$stamp = isset( $_GET['th_form'] ) ? sanitize_text_field( wp_unslash( $_GET['th_form'] ) ) : '';
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'th_contact_form_' . md5( $ip . $stamp );
		$data  = $stamp ? get_transient( $key ) : false;
		if ( $data ) {
			delete_transient( $key );
		}
		return is_array( $data ) ? $data : array(
			'values' => array(),
			'errors' => array(),
		);
	}

	/* ------------------------------------------------------------------ FAQ */

	/**
	 * Questions of a FAQ page: its Details blocks (summary = question).
	 *
	 * @param \WP_Post $post Page.
	 * @return array<int,array{q:string,a:string}>
	 */
	public static function faq_items( \WP_Post $post ): array {
		$out = array();
		foreach ( parse_blocks( (string) $post->post_content ) as $block ) {
			if ( 'core/details' !== $block['blockName'] ) {
				continue;
			}
			$html = render_block( $block );
			if ( preg_match( '#<summary[^>]*>(.*?)</summary>(.*)</details>#is', $html, $m ) ) {
				$out[] = array(
					'q' => trim( wp_strip_all_tags( $m[1] ) ),
					'a' => trim( wp_strip_all_tags( do_shortcode( $m[2] ) ) ),
				);
			}
		}
		return $out;
	}

	/**
	 * FAQPage JSON-LD on the FAQ template.
	 */
	public function faq_schema(): void {
		if ( ! is_page() || 'page-templates/faq.php' !== get_page_template_slug() ) {
			return;
		}
		$items = self::faq_items( get_queried_object() );
		if ( ! $items ) {
			return;
		}
		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array_map(
				static fn( $i ) => array(
					'@type'          => 'Question',
					'name'           => $i['q'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $i['a'],
					),
				),
				$items
			),
		);
		printf( '<script type="application/ld+json">%s</script>' . "\n", wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Numbered contents of a legal page: its h2 headings.
	 *
	 * @param \WP_Post $post Page.
	 * @return array<int,array{id:string,text:string}>
	 */
	public static function toc( \WP_Post $post ): array {
		return \Torrehub\Modules\Guides\Module::toc( $post );
	}
}
