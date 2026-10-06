<?php
/**
 * Contact module (blue): price, Show phone number, WhatsApp, (Chat — phase 6), e-mail enquiry.
 *
 * Phone and WhatsApp aren't in the HTML: listing.js fetches them on click (counted like RTCL's own reveal).
 * Without JS, "Show phone number" reloads with ?th_contact=1 and the numbers are rendered here.
 * E-mail: logged-in users (Customizer); guests see the login prompt from the design.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Listing\Module as Listing;

$view = $args['view'];
if ( ! $view->is_public() ) {
	return;
}
$needs_login_phone = th_mod( 'th_contact_phone_login' ) && ! is_user_logged_in();
$needs_login_email = th_mod( 'th_contact_email_login' ) && ! is_user_logged_in();
$login             = th_url_login( (string) get_permalink( $view->id ) . '#contact' );

// No-JS reveal.
$numbers = null;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, rate limited in contact_numbers().
if ( ! empty( $_GET['th_contact'] ) && ! $needs_login_phone ) {
	$numbers = Listing::contact_numbers( $view->id );
	$numbers = is_array( $numbers ) ? $numbers : null;
}
$user = wp_get_current_user();
?>
<section class="th-module th-module--contact th-listing-contact" id="contact" aria-labelledby="contact-title">
	<h2 class="th-sr-only" id="contact-title"><?php esc_html_e( 'Contact the seller', 'torrehub' ); ?></h2>
	<?php if ( '' !== $view->price['amount'] ) : ?>
		<p class="th-listing-contact__price">
			<?php echo esc_html( $view->price['amount'] ); ?>
			<?php if ( $view->price['suffix'] ) : ?>
				<small><?php echo esc_html( $view->price['suffix'] ); ?></small>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( $view->contact['phone'] || $view->contact['whatsapp'] ) : ?>
		<div class="th-listing-contact__buttons" data-th-reveal-wrap>
			<?php if ( $needs_login_phone ) : ?>
				<?php
				th_component(
					'button',
					array(
						'label'   => __( 'Log in to see the phone number', 'torrehub' ),
						'variant' => 'accent',
						'icon'    => 'phone',
						'block'   => true,
						'href'    => $login,
					)
				);
				?>
			<?php elseif ( $numbers ) : ?>
				<?php if ( $numbers['phone'] ) : ?>
					<?php
					th_component(
						'button',
						array(
							'label'   => $numbers['phone'],
							'variant' => 'accent',
							'icon'    => 'phone',
							'block'   => true,
							'href'    => 'tel:' . $numbers['tel'],
						)
					);
					?>
				<?php endif; ?>
				<?php if ( $numbers['wa_link'] ) : ?>
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'WhatsApp', 'torrehub' ),
							'variant' => 'whatsapp',
							'icon'    => 'whatsapp',
							'block'   => true,
							'href'    => $numbers['wa_link'],
							'attrs'   => array(
								'target' => '_blank',
								'rel'    => 'noopener',
							),
						)
					);
					?>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( $view->contact['phone'] ) : ?>
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Show phone number', 'torrehub' ),
							'variant' => 'accent',
							'icon'    => 'phone',
							'block'   => true,
							'href'    => add_query_arg( 'th_contact', '1' ) . '#contact',
							'attrs'   => array(
								'data-th-reveal' => 'phone',
								'rel'            => 'nofollow',
							),
						)
					);
					?>
				<?php endif; ?>
				<?php if ( $view->contact['whatsapp'] ) : ?>
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'WhatsApp', 'torrehub' ),
							'variant' => 'whatsapp',
							'icon'    => 'whatsapp',
							'block'   => true,
							'href'    => add_query_arg( 'th_contact', '1' ) . '#contact',
							'attrs'   => array(
								'data-th-reveal' => 'whatsapp',
								'rel'            => 'nofollow',
							),
						)
					);
					?>
				<?php endif; ?>
			<?php endif; ?>
			<?php
			/**
			 * More contact buttons (the Chat module adds "Chat" in phase 6).
			 *
			 * @param Torrehub\Modules\Listing\View $view Listing.
			 */
			do_action( 'th_listing_contact_buttons', $view );
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! $view->is_owner ) : ?>
		<div class="th-listing-contact__email">
			<p class="th-module__eyebrow"><?php esc_html_e( 'E-mail enquiry', 'torrehub' ); ?></p>
			<?php if ( $needs_login_email ) : ?>
				<p class="th-listing-contact__note">
					<?php esc_html_e( 'Chat and e-mail need an account.', 'torrehub' ); ?>
					<a href="<?php echo esc_url( $login ); ?>"><?php esc_html_e( 'Log in', 'torrehub' ); ?> <span aria-hidden="true">→</span></a>
				</p>
			<?php else : ?>
				<form class="th-stack th-listing-contact__form" method="post" action="<?php echo esc_url( (string) get_permalink( $view->id ) ); ?>" data-th-enquiry style="--th-stack-gap:10px">
					<input type="hidden" name="th_action" value="th_contact_seller">
					<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) $view->id ); ?>">
					<?php wp_nonce_field( 'th_enquiry_' . $view->id ); ?>
					<p class="th-listing-contact__from">
						<?php
						/* translators: 1: user name, 2: e-mail */
						echo esc_html( sprintf( __( 'From %1$s · %2$s', 'torrehub' ), $user->display_name, $user->user_email ) );
						?>
					</p>
					<label class="th-sr-only" for="th-enquiry-phone"><?php esc_html_e( 'Your phone (optional)', 'torrehub' ); ?></label>
					<input class="th-input th-input--on-color" id="th-enquiry-phone" type="tel" name="phone" autocomplete="tel" placeholder="<?php esc_attr_e( 'Your phone (optional)', 'torrehub' ); ?>">
					<label class="th-sr-only" for="th-enquiry-message"><?php esc_html_e( 'Message', 'torrehub' ); ?></label>
					<textarea class="th-textarea th-input--on-color" id="th-enquiry-message" name="message" rows="4" required minlength="10" maxlength="3000" placeholder="<?php echo esc_attr( __( 'Hi, I’m interested in…', 'torrehub' ) ); ?>"></textarea>
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Send message', 'torrehub' ),
							'variant' => 'white',
							'type'    => 'submit',
							'block'   => true,
						)
					);
					?>
					<p class="th-listing-contact__status" role="status" aria-live="polite" data-th-enquiry-status></p>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
