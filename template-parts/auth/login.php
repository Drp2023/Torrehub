<?php
/**
 * Login (A-01 / A-02): blue module with the form, optional listing context card, error and status panels
 * (unconfirmed → resend link, awaiting approval, rejected), "No account yet?" card.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Auth\Forms;
use Torrehub\Modules\Auth\Pages;

$result      = Forms::result( 'login' );
$form_errors = $result['errors'] ?? array();
$values      = $result['values'] ?? array();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$redirect = $values['redirect_to'] ?? ( isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '' );
$notice   = isset( $_GET['th_notice'] ) ? sanitize_key( wp_unslash( $_GET['th_notice'] ) ) : '';
// phpcs:enable
$account_status = $form_errors['_status'] ?? '';

// Coming from a listing: show which one ("Log in to contact this seller").
$context = 0;
if ( $redirect ) {
	$context = url_to_postid( strtok( $redirect, '#' ) );
	$context = $context && 'rtcl_listing' === get_post_type( $context ) ? $context : 0;
}
$context_card = $context ? th_listing_card_args( $context ) : null;

$notices = array(
	'resent'   => __( 'If that address belongs to an unconfirmed account, we’ve sent a new confirmation link.', 'torrehub' ),
	'password' => __( 'Your password was changed. Log in with the new one.', 'torrehub' ),
);
?>
<div class="th-auth__narrow th-stack">
	<?php if ( isset( $notices[ $notice ] ) ) : ?>
		<?php
		th_component(
			'alert',
			array(
				'variant' => 'success',
				'text'    => $notices[ $notice ],
				'role'    => 'status',
			)
		);
		?>
	<?php endif; ?>

	<section class="th-module th-module--contact th-auth-card" aria-labelledby="th-login-title">
		<h1 class="th-auth-card__title" id="th-login-title"><?php esc_html_e( 'Welcome back', 'torrehub' ); ?></h1>
		<p class="th-auth-card__lead"><?php esc_html_e( 'Use the e-mail or username you registered with.', 'torrehub' ); ?></p>

		<?php if ( $context_card ) : ?>
			<div class="th-auth-context">
				<?php
				if ( is_numeric( $context_card['image'] ) && $context_card['image'] ) {
					echo wp_get_attachment_image( (int) $context_card['image'], 'th-thumb', false, array( 'alt' => '' ) );
				}
				?>
				<div>
					<p class="th-auth-context__title"><?php echo esc_html( $context_card['title'] ); ?></p>
					<p class="th-auth-context__text"><?php esc_html_e( 'Log in to contact this seller.', 'torrehub' ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $form_errors['_'] ) ) : ?>
			<?php
			th_component(
				'alert',
				array(
					'variant' => 'error',
					'text'    => $form_errors['_'],
					'id'      => 'th-login-error',
				)
			);
			?>
		<?php endif; ?>

		<form class="th-stack th-auth-form" method="post" action="<?php echo esc_url( Pages::url( 'login' ) ); ?>" style="--th-stack-gap:12px" data-th-auth-form>
			<input type="hidden" name="th_action" value="th_login">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
			<?php wp_nonce_field( 'th_login', '_wpnonce', false ); ?>
			<label class="th-sr-only" for="th-log"><?php esc_html_e( 'E-mail or username', 'torrehub' ); ?></label>
			<input class="th-input th-input--on-color" id="th-log" name="log" type="text" autocomplete="username" required placeholder="<?php esc_attr_e( 'E-mail or username', 'torrehub' ); ?>" value="<?php echo esc_attr( $values['log'] ?? '' ); ?>"<?php echo ! empty( $form_errors['_'] ) ? ' aria-invalid="true" aria-describedby="th-login-error"' : ''; ?>>
			<label class="th-sr-only" for="th-pwd"><?php esc_html_e( 'Password', 'torrehub' ); ?></label>
			<div class="th-control th-control--toggle" data-th-password>
				<input class="th-input th-input--on-color" id="th-pwd" name="pwd" type="password" autocomplete="current-password" required placeholder="<?php esc_attr_e( 'Password', 'torrehub' ); ?>">
				<button type="button" class="th-control__toggle" data-th-password-toggle aria-pressed="false" aria-label="<?php esc_attr_e( 'Show password', 'torrehub' ); ?>" data-label-show="<?php esc_attr_e( 'Show password', 'torrehub' ); ?>" data-label-hide="<?php esc_attr_e( 'Hide password', 'torrehub' ); ?>"><?php th_icon( 'eye', array( 'size' => 18 ) ); ?></button>
			</div>
			<div class="th-auth-form__row">
				<label class="th-check th-check--on-color"><input type="checkbox" name="rememberme" value="1" checked> <span><?php esc_html_e( 'Keep me logged in', 'torrehub' ); ?></span></label>
				<a class="th-auth-form__forgot" href="<?php echo esc_url( Pages::url( 'lost' ) ); ?>"><?php esc_html_e( 'Forgot your password?', 'torrehub' ); ?></a>
			</div>
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Log in', 'torrehub' ),
					'variant' => 'accent',
					'type'    => 'submit',
					'block'   => true,
					'size'    => 'lg',
				)
			);
			?>
		</form>
	</section>

	<?php if ( 'pending' === $account_status ) : ?>
		<section class="th-auth-panel th-auth-panel--clay" role="status">
			<h2 class="th-auth-panel__title"><?php esc_html_e( 'Awaiting approval', 'torrehub' ); ?></h2>
			<p><?php esc_html_e( 'Your e-mail is confirmed. An administrator is reviewing your account — usually within 48 hours. We’ll e-mail you when it’s approved.', 'torrehub' ); ?></p>
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Browse listings meanwhile', 'torrehub' ),
					'variant' => 'clay',
					'block'   => true,
					'href'    => th_url_listings(),
				)
			);
			?>
		</section>
	<?php elseif ( 'unconfirmed' === $account_status ) : ?>
		<section class="th-auth-panel th-auth-panel--amber" role="status">
			<h2 class="th-auth-panel__title"><?php esc_html_e( 'Confirm your e-mail first', 'torrehub' ); ?></h2>
			<p><?php esc_html_e( 'We sent you a confirmation link when you registered. Didn’t get it? Check your spam folder or send it again.', 'torrehub' ); ?></p>
			<form method="post" action="<?php echo esc_url( Pages::url( 'login' ) ); ?>" class="th-auth-inline">
				<input type="hidden" name="th_action" value="th_resend">
				<?php wp_nonce_field( 'th_resend', '_wpnonce', false ); ?>
				<label class="th-sr-only" for="th-resend-email"><?php esc_html_e( 'E-mail address', 'torrehub' ); ?></label>
				<input class="th-input" id="th-resend-email" type="email" name="email" required autocomplete="email" value="<?php echo esc_attr( is_email( $values['log'] ?? '' ) ? $values['log'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Your e-mail address', 'torrehub' ); ?>">
				<?php
				th_component(
					'button',
					array(
						'label'   => __( 'Send the link again', 'torrehub' ),
						'variant' => 'ink',
						'type'    => 'submit',
					)
				);
				?>
			</form>
		</section>
	<?php elseif ( 'rejected' === $account_status ) : ?>
		<?php
		th_component(
			'alert',
			array(
				'variant' => 'error',
				'title'   => __( 'This account wasn’t approved', 'torrehub' ),
				'text'    => __( 'Check the e-mail we sent you for details, or reply to it if you think this is a mistake.', 'torrehub' ),
			)
		);
		?>
	<?php endif; ?>

	<section class="th-auth-panel" aria-labelledby="th-noaccount">
		<h2 class="th-auth-panel__title" id="th-noaccount"><?php esc_html_e( 'No account yet?', 'torrehub' ); ?></h2>
		<p><?php esc_html_e( 'Free to register. Members save and message; sellers post listings.', 'torrehub' ); ?></p>
		<?php
		th_component(
			'button',
			array(
				'label'   => __( 'Create a free account', 'torrehub' ),
				'variant' => 'outline',
				'block'   => true,
				'href'    => Pages::url( 'register' ),
			)
		);
		?>
	</section>
</div>
