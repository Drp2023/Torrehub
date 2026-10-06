<?php
/**
 * Template Name: Contact (T-02)
 *
 * Contact form (Content\Module::contact) + contact details from the Customizer (Guides & pages). The page content,
 * if any, is shown as the intro.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Content\Module as Content;

get_header();
the_post();
$result      = Content::form_result();
$values      = $result['values'];
$form_errors = $result['errors'];
$sent        = isset( $_GET['th_sent'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state.
$email       = (string) th_mod( 'th_contact_email' );
$office      = (string) th_mod( 'th_contact_office' );
$hours       = (string) th_mod( 'th_contact_hours' );
$intro       = trim( (string) get_the_content() );
$user        = wp_get_current_user();
?>
<main id="main" class="th-main th-section th-contact" tabindex="-1">
	<div class="th-container th-stack">
		<header>
			<h1 class="th-page__title"><?php echo esc_html( html_entity_decode( get_the_title(), ENT_QUOTES ) ); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="th-contact__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</header>
		<div class="th-contact__layout">
			<section class="th-contact__card" id="contact-form" aria-labelledby="th-contact-form-title">
				<h2 class="th-sr-only" id="th-contact-form-title"><?php esc_html_e( 'Send us a message', 'torrehub' ); ?></h2>
				<?php if ( $sent ) : ?>
					<?php
					th_component(
						'alert',
						array(
							'variant' => 'success',
							'title'   => __( 'Message sent', 'torrehub' ),
							'text'    => __( 'We’ve got it and will reply within one working day.', 'torrehub' ),
							'role'    => 'status',
						)
					);
					?>
				<?php else : ?>
					<?php if ( ! empty( $form_errors['_'] ) ) : ?>
						<?php
						th_component(
							'alert',
							array(
								'variant' => 'error',
								'text'    => $form_errors['_'],
							)
						);
						?>
					<?php endif; ?>
					<form class="th-contact__form" method="post" action="<?php echo esc_url( (string) get_permalink() ); ?>" novalidate>
						<input type="hidden" name="th_action" value="th_contact">
						<input type="hidden" name="back" value="<?php echo esc_url( (string) get_permalink() ); ?>">
						<input type="hidden" name="th_ts" value="<?php echo esc_attr( Content::form_stamp() ); ?>">
						<?php wp_nonce_field( 'th_contact' ); ?>
						<p class="th-hp" aria-hidden="true"><label>Website <input type="text" name="th_website" tabindex="-1" autocomplete="off"></label></p>
						<div class="th-contact__pair">
						<?php
						th_component(
							'field',
							array(
								'id'           => 'th-contact-name',
								'name'         => 'name',
								'label'        => __( 'Your name', 'torrehub' ),
								'autocomplete' => 'name',
								'required'     => true,
								'value'        => $values['name'] ?? ( $user->exists() ? $user->display_name : '' ),
								'error'        => $form_errors['name'] ?? '',
							)
						);
						th_component(
							'field',
							array(
								'id'           => 'th-contact-email',
								'name'         => 'email',
								'label'        => __( 'E-mail address', 'torrehub' ),
								'type'         => 'email',
								'autocomplete' => 'email',
								'required'     => true,
								'value'        => $values['email'] ?? ( $user->exists() ? $user->user_email : '' ),
								'error'        => $form_errors['email'] ?? '',
							)
						);
						?>
						</div>
						<?php
						th_component(
							'field',
							array(
								'id'      => 'th-contact-subject',
								'name'    => 'subject',
								'label'   => __( 'Subject', 'torrehub' ),
								'type'    => 'select',
								'options' => Content::subjects(),
								'value'   => $values['subject'] ?? ( isset( $_GET['subject'] ) ? sanitize_key( wp_unslash( $_GET['subject'] ) ) : 'general' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preselect only.
							)
						);
						th_component(
							'field',
							array(
								'id'          => 'th-contact-message',
								'name'        => 'message',
								'label'       => __( 'Message', 'torrehub' ),
								'type'        => 'textarea',
								'required'    => true,
								'maxlength'   => 2000,
								'placeholder' => __( 'Tell us what’s happening…', 'torrehub' ),
								'value'       => $values['message'] ?? '',
								'error'       => $form_errors['message'] ?? '',
							)
						);
						?>
						<div class="th-cluster">
							<?php
							th_component(
								'button',
								array(
									'label'   => __( 'Send message', 'torrehub' ),
									'variant' => 'accent',
									'type'    => 'submit',
								)
							);
							?>
							<span class="th-meta"><?php esc_html_e( 'We never share your address.', 'torrehub' ); ?></span>
						</div>
					</form>
				<?php endif; ?>
			</section>

			<aside class="th-contact__aside">
				<?php if ( '' !== $intro ) : ?>
					<div class="th-contact__intro th-prose"><?php the_content(); ?></div>
				<?php endif; ?>
				<dl class="th-contact__details">
					<?php if ( is_email( $email ) ) : ?>
						<div class="th-contact__detail is-primary"><dt><?php esc_html_e( 'E-mail', 'torrehub' ); ?></dt><dd><a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a></dd></div>
					<?php endif; ?>
					<?php if ( '' !== $office ) : ?>
						<div class="th-contact__detail"><dt><?php esc_html_e( 'Office', 'torrehub' ); ?></dt><dd><?php echo nl2br( esc_html( $office ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped, then line breaks. ?></dd></div>
					<?php endif; ?>
					<?php if ( '' !== $hours ) : ?>
						<div class="th-contact__detail"><dt><?php esc_html_e( 'Hours', 'torrehub' ); ?></dt><dd><?php echo esc_html( $hours ); ?></dd></div>
					<?php endif; ?>
				</dl>
			</aside>
		</div>
	</div>
</main>
<?php
get_footer();
