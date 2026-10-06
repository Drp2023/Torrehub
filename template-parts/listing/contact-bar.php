<?php
/**
 * Mobile sticky contact bar (L-15) + contact sheet (L-15 sheet).
 * Bar: Call (accent) · WhatsApp (green) · heart/chat. Both open the sheet, which reveals the numbers.
 * Without JS the bar links to the contact module (#contact).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

$view   = $args['view'];
$seller = $view->seller;
$has    = $view->contact['phone'] || $view->contact['whatsapp'];
$login  = th_url_login( (string) get_permalink( $view->id ) . '#contact' );
$guest  = ! is_user_logged_in();
?>
<div class="th-contact-bar" data-th-contact-bar>
	<?php if ( $view->contact['phone'] ) : ?>
		<a class="th-btn th-btn--accent" href="#contact" data-th-dialog-open="th-contact-sheet" aria-controls="th-contact-sheet" aria-haspopup="dialog"><?php th_icon( 'phone', array( 'size' => 18 ) ); ?><span><?php esc_html_e( 'Call', 'torrehub' ); ?></span></a>
	<?php endif; ?>
	<?php if ( $view->contact['whatsapp'] ) : ?>
		<a class="th-btn th-btn--whatsapp" href="#contact" data-th-dialog-open="th-contact-sheet" aria-controls="th-contact-sheet" aria-haspopup="dialog"><?php th_icon( 'whatsapp', array( 'size' => 18 ) ); ?><span><?php esc_html_e( 'WhatsApp', 'torrehub' ); ?></span></a>
	<?php endif; ?>
	<?php if ( ! $has ) : ?>
		<a class="th-btn th-btn--primary" href="#contact"><?php th_icon( 'mail', array( 'size' => 18 ) ); ?><span><?php esc_html_e( 'Contact', 'torrehub' ); ?></span></a>
	<?php endif; ?>
	<?php if ( th_favourites_enabled() ) : ?>
		<button type="button" class="th-btn th-btn--icon th-btn--fav th-contact-bar__fav" aria-pressed="<?php echo th_is_favourite( $view->id ) ? 'true' : 'false'; ?>" data-th-fav="<?php echo esc_attr( (string) $view->id ); ?>">
			<?php th_icon( 'heart', array( 'size' => 20 ) ); ?>
			<?php th_icon( 'heart-filled', array( 'size' => 20 ) ); ?>
			<span class="th-sr-only"><?php esc_html_e( 'Save', 'torrehub' ); ?></span>
		</button>
	<?php endif; ?>
</div>

<?php if ( $has ) : ?>
	<dialog class="th-dialog th-dialog--sheet th-contact-sheet" id="th-contact-sheet" aria-labelledby="th-contact-sheet-title" data-th-dialog data-th-contact-sheet>
		<div class="th-dialog__head">
			<div class="th-contact-sheet__seller">
				<?php echo th_get_avatar( $seller['id'], 42 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
				<div>
					<h2 class="th-dialog__title" id="th-contact-sheet-title"><?php echo esc_html( $seller['name'] ); ?></h2>
					<?php if ( $seller['verified'] ) : ?>
						<p class="th-listing-seller__verified"><?php th_icon( 'check', array( 'size' => 13 ) ); ?> <?php esc_html_e( 'Verified', 'torrehub' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php
			th_component(
				'button',
				array(
					'variant'    => 'neutral',
					'size'       => 'sm',
					'icon'       => 'close',
					'icon_only'  => true,
					'aria_label' => __( 'Close', 'torrehub' ),
					'attrs'      => array( 'data-th-dialog-close' => '' ),
				)
			);
			?>
		</div>
		<div class="th-dialog__body th-stack" style="--th-stack-gap:10px" data-th-sheet-numbers>
			<?php if ( th_mod( 'th_contact_phone_login' ) && $guest ) : ?>
				<?php
				th_component(
					'button',
					array(
						'label'   => __( 'Log in to see the phone number', 'torrehub' ),
						'variant' => 'accent',
						'size'    => 'lg',
						'block'   => true,
						'href'    => $login,
					)
				);
				?>
			<?php else : ?>
				<?php if ( $view->contact['phone'] ) : ?>
					<a class="th-btn th-btn--accent th-btn--lg th-btn--block" href="#contact" data-th-sheet-phone aria-busy="true"><?php th_icon( 'phone', array( 'size' => 18 ) ); ?><span><?php esc_html_e( 'Loading…', 'torrehub' ); ?></span></a>
				<?php endif; ?>
				<?php if ( $view->contact['whatsapp'] ) : ?>
					<a class="th-btn th-btn--whatsapp th-btn--lg th-btn--block" href="#contact" data-th-sheet-whatsapp target="_blank" rel="noopener"><?php th_icon( 'whatsapp', array( 'size' => 18 ) ); ?><span><?php esc_html_e( 'Message on WhatsApp', 'torrehub' ); ?></span></a>
				<?php endif; ?>
			<?php endif; ?>
			<?php
			/** This action is documented in template-parts/listing/contact.php */
			do_action( 'th_listing_contact_buttons', $view, 'sheet' );
			?>
			<a class="th-btn th-btn--neutral th-btn--lg th-btn--block" href="#contact" data-th-dialog-close data-th-goto-contact><?php th_icon( 'mail', array( 'size' => 18 ) ); ?><span><?php esc_html_e( 'Send an e-mail', 'torrehub' ); ?></span></a>
			<?php if ( $guest && th_mod( 'th_contact_email_login' ) ) : ?>
				<p class="th-contact-sheet__note">
					<?php esc_html_e( 'Chat and e-mail require an account. Phone and WhatsApp are open to everyone.', 'torrehub' ); ?>
					<a href="<?php echo esc_url( $login ); ?>"><?php esc_html_e( 'Log in or register free', 'torrehub' ); ?> <span aria-hidden="true">→</span></a>
				</p>
			<?php endif; ?>
		</div>
	</dialog>
<?php endif; ?>
