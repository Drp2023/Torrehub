<?php
/**
 * Consent bar + "Cookie settings" dialog. consent.js shows the bar when no current choice exists (and the site has
 * something optional to load), applies the choice and reopens the dialog from the footer link.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type array $settings Consent settings.
 *     @type array $labels   Category labels.
 *     @type bool  $show     Ask on first visit.
 * }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Consent\Module as Consent;

$cfg     = $args['settings'];
$labels  = $args['labels'];
$active  = Consent::active_categories();
$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
$privacy = $privacy ? (string) get_permalink( $privacy ) : '';
$text    = '' !== trim( $cfg['text'] ) ? $cfg['text'] : Consent::default_text();
$cookies = array(
	array( 'wordpress_logged_in_*, wordpress_sec_*', __( 'Keeps you logged in (only after logging in).', 'torrehub' ) ),
	array( 'wp_rtcl_session_*', __( 'Keeps the listing form and account pages working.', 'torrehub' ) ),
	array( 'th_location', __( 'The town you picked (1 year).', 'torrehub' ) ),
	array( 'googtrans', __( 'The language you picked, when you use the language switcher.', 'torrehub' ) ),
	array( Consent::COOKIE, __( 'This choice (1 year).', 'torrehub' ) ),
);
?>
<section class="th-consent" data-th-consent-bar data-show="<?php echo esc_attr( $args['show'] ? '1' : '0' ); ?>" aria-labelledby="th-consent-title" hidden>
	<h2 class="th-sr-only" id="th-consent-title"><?php esc_html_e( 'Cookies', 'torrehub' ); ?></h2>
	<p class="th-consent__text">
		<?php echo esc_html( $text ); ?>
		<?php if ( $privacy ) : ?>
			<a href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'Privacy Policy', 'torrehub' ); ?></a>
		<?php endif; ?>
	</p>
	<div class="th-consent__actions">
		<button type="button" class="th-btn th-btn--text th-btn--sm" data-th-consent-open><?php esc_html_e( 'Settings', 'torrehub' ); ?></button>
		<button type="button" class="th-btn th-btn--ink th-btn--sm" data-th-consent-reject><?php esc_html_e( 'Reject optional', 'torrehub' ); ?></button>
		<button type="button" class="th-btn th-btn--ink th-btn--sm" data-th-consent-accept><?php esc_html_e( 'Accept all', 'torrehub' ); ?></button>
	</div>
</section>

<dialog class="th-dialog th-consent-dialog" id="th-consent-dialog" aria-labelledby="th-consent-dialog-title">
	<form class="th-dialog__form" method="dialog" data-th-consent-form>
		<div class="th-dialog__head">
			<h2 class="th-dialog__title" id="th-consent-dialog-title"><?php esc_html_e( 'Cookie settings', 'torrehub' ); ?></h2>
			<button type="button" class="th-btn th-btn--icon th-btn--sm th-btn--neutral" data-th-consent-close aria-label="<?php esc_attr_e( 'Close', 'torrehub' ); ?>"><?php th_icon( 'close', array( 'size' => 16 ) ); ?></button>
		</div>
		<div class="th-dialog__body th-stack" style="--th-stack-gap:14px">
			<p><?php esc_html_e( 'Choose which optional cookies and third-party content we may use. You can change this at any time from the “Cookie settings” link at the bottom of every page.', 'torrehub' ); ?></p>
			<ul class="th-consent__cats" role="list">
				<li class="th-consent__cat">
					<label class="th-toggle-row">
						<span class="th-toggle"><input type="checkbox" checked disabled></span>
						<span><strong><?php echo esc_html( $labels['necessary'][0] ); ?></strong> <span class="th-meta"><?php esc_html_e( 'Always on', 'torrehub' ); ?></span></span>
					</label>
					<p class="th-help"><?php echo esc_html( $labels['necessary'][1] ); ?></p>
				</li>
				<?php foreach ( Consent::CATEGORIES as $category ) : ?>
					<li class="th-consent__cat">
						<label class="th-toggle-row">
							<span class="th-toggle"><input type="checkbox" name="<?php echo esc_attr( $category ); ?>" value="1" data-th-consent-cat="<?php echo esc_attr( $category ); ?>"></span>
							<span>
								<strong><?php echo esc_html( $labels[ $category ][0] ); ?></strong>
								<?php if ( ! in_array( $category, $active, true ) ) : ?>
									<span class="th-meta"><?php esc_html_e( 'Not in use at the moment', 'torrehub' ); ?></span>
								<?php endif; ?>
							</span>
						</label>
						<p class="th-help"><?php echo esc_html( $labels[ $category ][1] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
			<details class="th-consent__details">
				<summary><?php esc_html_e( 'Cookies this site always uses', 'torrehub' ); ?></summary>
				<dl>
					<?php foreach ( $cookies as $cookie ) : ?>
						<div><dt><code><?php echo esc_html( $cookie[0] ); ?></code></dt><dd><?php echo esc_html( $cookie[1] ); ?></dd></div>
					<?php endforeach; ?>
				</dl>
				<p class="th-help"><?php esc_html_e( 'Maps are drawn from OpenStreetMap (no cookies). Translations use Google Translate only after you pick a language.', 'torrehub' ); ?></p>
			</details>
		</div>
		<div class="th-dialog__foot th-consent__foot">
			<button type="button" class="th-btn th-btn--ink" data-th-consent-reject><?php esc_html_e( 'Reject optional', 'torrehub' ); ?></button>
			<button type="button" class="th-btn th-btn--neutral" data-th-consent-save><?php esc_html_e( 'Save choices', 'torrehub' ); ?></button>
			<button type="button" class="th-btn th-btn--ink" data-th-consent-accept><?php esc_html_e( 'Accept all', 'torrehub' ); ?></button>
		</div>
	</form>
</dialog>
