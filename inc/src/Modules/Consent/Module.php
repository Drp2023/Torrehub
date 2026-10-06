<?php
/**
 * Cookie consent — replaces gdpr-cookie-compliance (decision 10).
 *
 * - Categories: necessary (always), preferences, statistics, marketing.
 * - Non-essential code never runs before consent: scripts entered in Appearance › Torrehub › Cookie consent, and
 *   enqueued scripts mapped with the `th_consent_script_handles` filter, are printed as `type="text/plain"` and
 *   consent.js turns them into live scripts after the visitor agrees. Cache-safe: the HTML is the same for everyone.
 * - Embeds in content (YouTube, Vimeo, Maps … via the Embed block) load only after a click on their placeholder.
 * - Choice in the `th_consent` cookie (first party, 12 months, versioned: changing the version asks again).
 * - "Reject" is as prominent as "Accept"; "Cookie settings" in the footer reopens the choice at any time.
 *
 * DECISION: the bar appears only when a non-essential category has something to load — the site's own cookies
 * (login, town, language, session) are strictly necessary and need no consent (AEPD guide). "Always show the bar" is
 * a setting. Maps (OpenStreetMap tiles, no cookies) and the language switcher (Google Translate, on request) are
 * treated as functions the visitor asks for, and are listed on the settings dialog.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Consent;

use Torrehub\Core\Module as BaseModule;
use Torrehub\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Consent module.
 */
final class Module extends BaseModule {

	public const OPTION     = 'th_consent';
	public const COOKIE     = 'th_consent';
	public const CATEGORIES = array( 'preferences', 'statistics', 'marketing' );

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'consent';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Cookie consent', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Consent bar and settings; blocks non-essential scripts and embeds until the visitor agrees.', 'torrehub' );
	}

	/**
	 * Settings (also while the module is off, so it can be prepared before switching it on).
	 */
	public function always(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'th_settings_sections', array( $this, 'settings_section' ) );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'wp_footer', array( $this, 'render' ), 5 );
		add_action( 'wp_footer', array( $this, 'print_scripts' ), 50 );
		add_filter( 'script_loader_tag', array( $this, 'gate_handles' ), 10, 2 );
		add_filter( 'render_block_core/embed', array( $this, 'gate_embed' ), 10, 2 );
		add_filter( 'script_module_data_th-app', array( $this, 'module_data' ) );
		add_action( 'th_footer_legal_links', array( $this, 'footer_link' ) );
	}

	/* ------------------------------------------------------------------ settings */

	/**
	 * Saved settings with defaults.
	 *
	 * @return array{version:int,always:bool,text:string,scripts:array<string,string>}
	 */
	public static function settings(): array {
		$saved = (array) get_option( self::OPTION, array() );
		return array(
			'version' => max( 1, (int) ( $saved['version'] ?? 1 ) ),
			'always'  => ! empty( $saved['always'] ),
			'text'    => (string) ( $saved['text'] ?? '' ),
			'scripts' => array_merge( array_fill_keys( self::CATEGORIES, '' ), array_map( 'strval', (array) ( $saved['scripts'] ?? array() ) ) ),
		);
	}

	/**
	 * Register the option (Appearance › Torrehub).
	 */
	public function register_setting(): void {
		register_setting(
			Settings::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitise; scripts only from users allowed to post unfiltered HTML (else the old value stays).
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$old   = self::settings();
		$out   = array(
			'version' => $old['version'],
			'always'  => ! empty( $input['always'] ),
			'text'    => sanitize_textarea_field( (string) ( $input['text'] ?? '' ) ),
			'scripts' => $old['scripts'],
		);
		if ( current_user_can( 'unfiltered_html' ) ) {
			foreach ( self::CATEGORIES as $cat ) {
				$out['scripts'][ $cat ] = trim( (string) ( $input['scripts'][ $cat ] ?? '' ) );
			}
		}
		// A new version asks everyone again: on request, or when a category gets code for the first time.
		$added = array_diff_key( array_filter( $out['scripts'] ), array_filter( $old['scripts'] ) );
		if ( ! empty( $input['renew'] ) || $added ) {
			++$out['version'];
		}
		return $out;
	}

	/**
	 * Settings section.
	 */
	public function settings_section(): void {
		$s      = self::settings();
		$labels = self::labels();
		?>
		<h2 id="th-consent-settings"><?php esc_html_e( 'Cookie consent', 'torrehub' ); ?></h2>
		<p><?php esc_html_e( 'Code pasted here runs only for visitors who agreed to its category. Paste the full snippet as given by the provider (Google Analytics, Meta Pixel …). Strictly necessary cookies (login, chosen town, language, session) need no consent.', 'torrehub' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Consent bar', 'torrehub' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[always]" value="1" <?php checked( $s['always'] ); ?>> <?php esc_html_e( 'Always show it (otherwise only when a category below has code)', 'torrehub' ); ?></label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[renew]" value="1"> <?php esc_html_e( 'Ask every visitor again on their next visit', 'torrehub' ); ?></label>
					<p class="description">
						<?php
						/* translators: %d: consent version */
						echo esc_html( sprintf( __( 'Consent version %d. Adding code to a category asks again automatically.', 'torrehub' ), $s['version'] ) );
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="th-consent-text"><?php esc_html_e( 'Bar text', 'torrehub' ); ?></label></th>
				<td><textarea class="large-text" rows="2" id="th-consent-text" name="<?php echo esc_attr( self::OPTION ); ?>[text]" placeholder="<?php echo esc_attr( self::default_text() ); ?>"><?php echo esc_textarea( $s['text'] ); ?></textarea></td>
			</tr>
			<?php foreach ( self::CATEGORIES as $cat ) : ?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( 'th-consent-' . $cat ); ?>"><?php echo esc_html( $labels[ $cat ][0] ); ?></label></th>
					<td>
						<textarea class="large-text code" rows="4" id="<?php echo esc_attr( 'th-consent-' . $cat ); ?>" name="<?php echo esc_attr( self::OPTION . '[scripts][' . $cat . ']' ); ?>"<?php disabled( ! current_user_can( 'unfiltered_html' ) ); ?>><?php echo esc_textarea( $s['scripts'][ $cat ] ); ?></textarea>
						<p class="description"><?php echo esc_html( $labels[ $cat ][1] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Category names and descriptions.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function labels(): array {
		return array(
			'necessary'   => array( __( 'Strictly necessary', 'torrehub' ), __( 'Log-in, your chosen town and language, the listing form session and this choice. Always on.', 'torrehub' ) ),
			'preferences' => array( __( 'Preferences', 'torrehub' ), __( 'Remember extra settings and enable third-party features you didn’t open yourself.', 'torrehub' ) ),
			'statistics'  => array( __( 'Statistics', 'torrehub' ), __( 'Anonymous visit counts that help us improve the site.', 'torrehub' ) ),
			'marketing'   => array( __( 'Marketing', 'torrehub' ), __( 'Measure ads and show relevant offers on other sites. Includes embedded videos and posts from other sites.', 'torrehub' ) ),
		);
	}

	/**
	 * Default bar text.
	 */
	public static function default_text(): string {
		return __( 'We use cookies that are needed for the site to work. With your permission we’d also use optional ones — you can change your choice at any time under “Cookie settings”.', 'torrehub' );
	}

	/* ------------------------------------------------------------------ what needs consent */

	/**
	 * Enqueued script handles that need consent: handle => category. Filterable (e.g. an analytics plugin).
	 *
	 * @return array<string,string>
	 */
	public static function handles(): array {
		/**
		 * Enqueued scripts that may only run after consent.
		 *
		 * @param array<string,string> $handles handle => preferences|statistics|marketing.
		 */
		return array_filter( (array) apply_filters( 'th_consent_script_handles', array() ), static fn( $c ) => in_array( $c, self::CATEGORIES, true ) );
	}

	/**
	 * Categories that have something to load.
	 *
	 * @return array<int,string>
	 */
	public static function active_categories(): array {
		$s    = self::settings();
		$cats = array_keys( array_filter( $s['scripts'], static fn( $code ) => '' !== trim( $code ) ) );
		$cats = array_merge( $cats, array_values( self::handles() ) );
		if ( has_block( 'core/embed' ) || has_block( 'core-embed/youtube' ) ) {
			$cats[] = 'marketing';
		}
		return array_values( array_unique( $cats ) );
	}

	/**
	 * Show the bar on first visit?
	 */
	public static function needs_bar(): bool {
		return self::settings()['always'] || (bool) self::active_categories();
	}

	/* ------------------------------------------------------------------ output */

	/**
	 * Bar + settings dialog (footer).
	 */
	public function render(): void {
		get_template_part(
			'template-parts/consent/bar',
			null,
			array(
				'settings' => self::settings(),
				'labels'   => self::labels(),
				'show'     => self::needs_bar(),
			)
		);
	}

	/**
	 * Configured snippets, inert until consent (`type="text/plain"`).
	 */
	public function print_scripts(): void {
		foreach ( self::settings()['scripts'] as $cat => $code ) {
			if ( '' !== trim( $code ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin code (unfiltered_html only), made inert.
				echo self::inert( $code, $cat ) . "\n";
			}
		}
	}

	/**
	 * Make a pasted snippet inert: scripts become text/plain, iframes keep their URL in data-src, noscript (tracking
	 * pixels that can't wait for consent) is dropped.
	 *
	 * @param string $code     Snippet.
	 * @param string $category Category.
	 */
	public static function inert( string $code, string $category ): string {
		$cat  = esc_attr( $category );
		$code = (string) preg_replace( '#<noscript\b[^>]*>.*?</noscript>#is', '', $code );
		$code = (string) preg_replace_callback(
			'#<script\b([^>]*)>#i',
			static function ( $m ) use ( $cat ) {
				$attrs = (string) preg_replace( '#\stype=("|\')[^"\']*\1#i', '', $m[1] );
				return '<script type="text/plain" data-th-consent="' . $cat . '"' . $attrs . '>';
			},
			$code
		);
		return (string) preg_replace( '#<iframe\b([^>]*)\ssrc=("|\')([^"\']+)\2#i', '<iframe$1 data-th-consent="' . $cat . '" data-src="$3"', $code );
	}

	/**
	 * Enqueued scripts mapped to a category: printed inert.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Handle.
	 */
	public function gate_handles( $tag, $handle ) {
		$cat = self::handles()[ $handle ] ?? '';
		return $cat ? self::inert( (string) $tag, $cat ) : $tag;
	}

	/**
	 * Embed block (YouTube, Vimeo, Twitter …): a placeholder with a button; the embed loads after a click (or with
	 * marketing consent).
	 *
	 * @param string              $html  Block HTML.
	 * @param array<string,mixed> $block Block.
	 */
	public function gate_embed( $html, $block ) {
		if ( is_admin() || '' === trim( (string) $html ) || is_feed() ) {
			return $html;
		}
		$provider = (string) ( $block['attrs']['providerNameSlug'] ?? '' );
		$url      = (string) ( $block['attrs']['url'] ?? '' );
		$host     = $url ? (string) wp_parse_url( $url, PHP_URL_HOST ) : $provider;
		$inner    = self::inert( (string) $html, 'marketing' );
		ob_start();
		?>
		<div class="th-embed-gate" data-th-embed-gate>
			<template data-th-embed><?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-rendered embed, made inert. ?></template>
			<p>
				<?php
				/* translators: %s: site the content comes from, e.g. www.youtube.com */
				echo esc_html( sprintf( __( 'This content is hosted by %s, which may set cookies.', 'torrehub' ), $host ) );
				?>
			</p>
			<button type="button" class="th-btn th-btn--white th-btn--sm" data-th-embed-load><?php esc_html_e( 'Load content', 'torrehub' ); ?></button>
			<?php if ( $url ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open on the original site', 'torrehub' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Config for consent.js.
	 *
	 * @param array<string,mixed> $data Module data.
	 * @return array<string,mixed>
	 */
	public function module_data( array $data ): array {
		$data['consent'] = array(
			'cookie'  => self::COOKIE,
			'version' => self::settings()['version'],
			'days'    => 365,
			'secure'  => is_ssl(),
		);
		return $data;
	}

	/**
	 * "Cookie settings" link in the footers.
	 */
	public function footer_link(): void {
		echo '<button type="button" class="th-link-button" data-th-consent-open>' . esc_html__( 'Cookie settings', 'torrehub' ) . '</button>';
	}
}
