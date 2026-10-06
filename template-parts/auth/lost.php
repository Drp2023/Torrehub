<?php
/**
 * Lost password: request form → "check your inbox"; the e-mailed link (?key=&login=) → new-password form.
 * The answer never reveals whether an account exists.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Auth\Forms;
use Torrehub\Modules\Auth\Pages;

$lost_result = Forms::result( 'lost' );
$reset       = Forms::result( 'reset' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state; the key is checked on submit.
$key   = $reset['values']['key'] ?? ( isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '' );
$login = $reset['values']['login'] ?? ( isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '' );
$sent  = ! empty( $_GET['th_sent'] );
// phpcs:enable
?>
<div class="th-auth__narrow th-stack">
	<?php if ( $key && $login ) : ?>
		<?php
		$form_errors = $reset['errors'] ?? array();
		$valid       = ! is_wp_error( check_password_reset_key( $key, $login ) );
		?>
		<section class="th-auth-card th-auth-card--light th-stack" aria-labelledby="th-reset-title">
			<h1 class="th-auth__title" id="th-reset-title"><?php esc_html_e( 'Choose a new password', 'torrehub' ); ?></h1>
			<?php if ( ! $valid ) : ?>
				<?php
				th_component(
					'alert',
					array(
						'variant' => 'error',
						'title'   => __( 'This link is invalid or has expired', 'torrehub' ),
						'text'    => __( 'Reset links work once and for 24 hours. Ask for a new one.', 'torrehub' ),
						'actions' => array(
							array(
								'label' => __( 'Get a new link', 'torrehub' ),
								'href'  => Pages::url( 'lost' ),
							),
						),
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
				<form class="th-stack" method="post" action="<?php echo esc_url( Pages::url( 'lost' ) ); ?>">
					<input type="hidden" name="th_action" value="th_reset">
					<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
					<input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>">
					<?php wp_nonce_field( 'th_reset', '_wpnonce', false ); ?>
					<?php
					th_component(
						'field',
						array(
							'id'             => 'th-new-password',
							'name'           => 'password',
							'label'          => __( 'New password', 'torrehub' ),
							'type'           => 'password',
							'autocomplete'   => 'new-password',
							'required'       => true,
							'password_meter' => true,
							'error'          => $form_errors['password'] ?? '',
							'help'           => __( 'At least 10 characters.', 'torrehub' ),
						)
					);
					th_component(
						'field',
						array(
							'id'           => 'th-new-password2',
							'name'         => 'password2',
							'label'        => __( 'Confirm new password', 'torrehub' ),
							'type'         => 'password',
							'autocomplete' => 'new-password',
							'required'     => true,
							'error'        => $form_errors['password2'] ?? '',
						)
					);
					th_component(
						'button',
						array(
							'label'   => __( 'Save new password', 'torrehub' ),
							'variant' => 'accent',
							'type'    => 'submit',
							'block'   => true,
							'size'    => 'lg',
						)
					);
					?>
				</form>
			<?php endif; ?>
		</section>
	<?php elseif ( $sent ) : ?>
		<section class="th-auth-panel th-auth-panel--blue" role="status">
			<span class="th-icon-circle"><?php th_icon( 'mail' ); ?></span>
			<h1 class="th-auth-panel__title"><?php esc_html_e( 'Check your inbox', 'torrehub' ); ?></h1>
			<p><?php esc_html_e( 'If an active account matches what you entered, we’ve e-mailed a link to choose a new password. It works for 24 hours.', 'torrehub' ); ?></p>
			<p><a href="<?php echo esc_url( Pages::url( 'login' ) ); ?>"><?php esc_html_e( 'Back to log in', 'torrehub' ); ?></a></p>
		</section>
	<?php else : ?>
		<section class="th-auth-card th-auth-card--light th-stack" aria-labelledby="th-lost-title">
			<h1 class="th-auth__title" id="th-lost-title"><?php esc_html_e( 'Forgot your password?', 'torrehub' ); ?></h1>
			<p><?php esc_html_e( 'Enter your e-mail address or username and we’ll send you a link to choose a new one.', 'torrehub' ); ?></p>
			<?php if ( ! empty( $lost_result['errors']['_'] ) ) : ?>
				<?php
				th_component(
					'alert',
					array(
						'variant' => 'error',
						'text'    => $lost_result['errors']['_'],
					)
				);
				?>
			<?php endif; ?>
			<form class="th-stack" method="post" action="<?php echo esc_url( Pages::url( 'lost' ) ); ?>">
				<input type="hidden" name="th_action" value="th_lost">
				<?php wp_nonce_field( 'th_lost', '_wpnonce', false ); ?>
				<?php
				th_component(
					'field',
					array(
						'id'           => 'th-user-login',
						'name'         => 'user_login',
						'label'        => __( 'E-mail or username', 'torrehub' ),
						'autocomplete' => 'username',
						'required'     => true,
						'value'        => $lost_result['values']['user_login'] ?? '',
					)
				);
				th_component(
					'button',
					array(
						'label'   => __( 'Send reset link', 'torrehub' ),
						'variant' => 'accent',
						'type'    => 'submit',
						'block'   => true,
						'size'    => 'lg',
					)
				);
				?>
			</form>
			<p><a href="<?php echo esc_url( Pages::url( 'login' ) ); ?>"><?php esc_html_e( 'Back to log in', 'torrehub' ); ?></a></p>
		</section>
	<?php endif; ?>
</div>
