<?php
/**
 * Registration (A-03 step 1 account type, A-05/A-07 step 2 details, A-09 states).
 *
 * Step 1 is a GET form (?type=member|seller|business), so both steps work without JS.
 * Client decision 2026-10-06 (GDPR): no NIE for private sellers; businesses give their NIF (validated).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Auth\Accounts;
use Torrehub\Modules\Auth\Forms;
use Torrehub\Modules\Auth\Pages;

$result      = Forms::result( 'register' );
$form_errors = $result['errors'] ?? array();
$values      = $result['values'] ?? array();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state only.
$acct_type  = sanitize_key( $values['type'] ?? ( isset( $_GET['type'] ) ? wp_unslash( $_GET['type'] ) : '' ) );
$registered = isset( $_GET['th_registered'] ) ? sanitize_email( wp_unslash( $_GET['th_registered'] ) ) : '';
$confirmed  = isset( $_GET['th_confirmed'] ) ? sanitize_key( wp_unslash( $_GET['th_confirmed'] ) ) : '';
// phpcs:enable
$acct_type = isset( Accounts::TYPES[ $acct_type ] ) ? $acct_type : '';

$settings = (array) get_option( 'rtcl_account_settings', array() );
$terms    = (int) ( $settings['page_for_terms_and_conditions'] ?? 0 );
$privacy  = (int) ( $settings['page_for_privacy_policy'] ?? get_option( 'wp_page_for_privacy_policy' ) );

$cards = array(
	'member'   => array(
		'icon'  => 'user',
		'title' => __( 'Member', 'torrehub' ),
		'lead'  => __( 'Browse, save, message and review. No listings.', 'torrehub' ),
		'yes'   => array( __( 'Favourites & alerts', 'torrehub' ), __( 'Chat with sellers', 'torrehub' ) ),
		'no'    => array( __( 'Post listings', 'torrehub' ) ),
		'foot'  => __( 'No document required', 'torrehub' ),
	),
	'seller'   => array(
		'icon'  => 'account-private-seller',
		'title' => __( 'Private Seller', 'torrehub' ),
		'lead'  => __( 'Sell your own car, flat or items as an individual.', 'torrehub' ),
		'yes'   => array( __( 'Everything a Member has', 'torrehub' ), __( 'Post listings', 'torrehub' ), __( 'Verified badge (optional ID check)', 'torrehub' ) ),
		'no'    => array(),
		'foot'  => __( 'No document required', 'torrehub' ),
	),
	'business' => array(
		'icon'  => 'account-business-seller',
		'title' => __( 'Business Seller', 'torrehub' ),
		'lead'  => __( 'Companies, agencies and tradespeople.', 'torrehub' ),
		'yes'   => array( __( 'Everything a Seller has', 'torrehub' ), __( 'Business hours & logo', 'torrehub' ), __( 'Company profile fields', 'torrehub' ) ),
		'no'    => array(),
		'foot'  => __( 'NIF number required', 'torrehub' ),
	),
);

/* ------------------------------------------------------------------ states after submitting */

if ( $registered || $confirmed ) :
	?>
	<div class="th-auth__narrow th-stack">
		<?php if ( $registered ) : ?>
			<section class="th-auth-panel th-auth-panel--blue" role="status">
				<span class="th-icon-circle"><?php th_icon( 'mail' ); ?></span>
				<h1 class="th-auth-panel__title"><?php esc_html_e( 'Check your inbox', 'torrehub' ); ?></h1>
				<p>
					<?php
					/* translators: %s: e-mail address */
					echo esc_html( sprintf( __( 'We sent a confirmation link to %s. Open it to confirm your e-mail address — the link is valid for 48 hours.', 'torrehub' ), $registered ) );
					?>
				</p>
				<p><?php esc_html_e( 'After that, a person reviews your account, usually within 48 hours.', 'torrehub' ); ?></p>
			</section>
		<?php elseif ( 'ok' === $confirmed ) : ?>
			<section class="th-auth-panel th-auth-panel--clay" role="status">
				<span class="th-icon-circle"><?php th_icon( 'clock' ); ?></span>
				<h1 class="th-auth-panel__title"><?php esc_html_e( 'E-mail confirmed — awaiting approval', 'torrehub' ); ?></h1>
				<p><?php esc_html_e( 'Thanks! An administrator is reviewing your account, usually within 48 hours. We’ll e-mail you as soon as it’s approved. You can browse listings meanwhile.', 'torrehub' ); ?></p>
				<?php
				th_component(
					'button',
					array(
						'label'   => __( 'Browse listings while you wait', 'torrehub' ),
						'variant' => 'clay',
						'block'   => true,
						'href'    => th_url_listings(),
					)
				);
				?>
			</section>
		<?php elseif ( 'done' === $confirmed ) : ?>
			<?php
			th_component(
				'alert',
				array(
					'variant' => 'info',
					'title'   => __( 'Already confirmed', 'torrehub' ),
					'text'    => __( 'This e-mail address is already confirmed. If your account is approved, you can log in.', 'torrehub' ),
					'actions' => array(
						array(
							'label' => __( 'Log in', 'torrehub' ),
							'href'  => Pages::url( 'login' ),
						),
					),
				)
			);
			?>
		<?php else : ?>
			<?php
			th_component(
				'alert',
				array(
					'variant' => 'error',
					'title'   => 'expired' === $confirmed ? __( 'This link has expired', 'torrehub' ) : __( 'This link isn’t valid', 'torrehub' ),
					'text'    => __( 'Ask for a new confirmation link below.', 'torrehub' ),
				)
			);
			?>
		<?php endif; ?>

		<?php if ( $registered || ! in_array( $confirmed, array( 'ok', 'done' ), true ) ) : ?>
			<section class="th-auth-panel" aria-labelledby="th-resend-title">
				<h2 class="th-auth-panel__title" id="th-resend-title"><?php esc_html_e( 'No e-mail?', 'torrehub' ); ?></h2>
				<p><?php esc_html_e( 'Check your spam folder, or send the link again.', 'torrehub' ); ?></p>
				<form method="post" action="<?php echo esc_url( Pages::url( 'login' ) ); ?>" class="th-auth-inline">
					<input type="hidden" name="th_action" value="th_resend">
					<?php wp_nonce_field( 'th_resend', '_wpnonce', false ); ?>
					<label class="th-sr-only" for="th-resend-email"><?php esc_html_e( 'E-mail address', 'torrehub' ); ?></label>
					<input class="th-input" id="th-resend-email" type="email" name="email" required autocomplete="email" value="<?php echo esc_attr( $registered ); ?>" placeholder="<?php esc_attr_e( 'Your e-mail address', 'torrehub' ); ?>">
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Send again', 'torrehub' ),
							'variant' => 'ink',
							'type'    => 'submit',
						)
					);
					?>
				</form>
			</section>
		<?php endif; ?>
	</div>
	<?php
	return;
endif;

/* ------------------------------------------------------------------ step 1: account type */

if ( '' === $acct_type ) :
	?>
	<form class="th-auth-step" method="get" action="<?php echo esc_url( Pages::url( 'register' ) ); ?>">
		<p class="th-micro th-auth-step__eyebrow"><?php esc_html_e( 'Step 1 of 2', 'torrehub' ); ?></p>
		<h1 class="th-auth__title"><?php esc_html_e( 'Join the hub', 'torrehub' ); ?></h1>
		<p class="th-lead th-auth__lead"><?php esc_html_e( 'Free for everyone. Every account is checked by a person before it goes live.', 'torrehub' ); ?></p>
		<fieldset class="th-auth-types">
			<legend class="th-sr-only"><?php esc_html_e( 'What will you use Torrehub for?', 'torrehub' ); ?></legend>
			<?php foreach ( $cards as $key => $card ) : ?>
				<label class="th-choice th-auth-type">
					<input type="radio" name="type" value="<?php echo esc_attr( $key ); ?>" <?php checked( 'seller', $key ); ?> required>
					<span class="th-choice__radio"><?php th_icon( 'check', array( 'size' => 12 ) ); ?></span>
					<span class="th-icon-circle"><?php th_icon( $card['icon'] ); ?></span>
					<span class="th-choice__title"><?php echo esc_html( $card['title'] ); ?></span>
					<span class="th-meta"><?php echo esc_html( $card['lead'] ); ?></span>
					<span class="th-auth-type__list">
						<?php foreach ( $card['yes'] as $item ) : ?>
							<span class="th-auth-type__item"><?php th_icon( 'check', array( 'size' => 14 ) ); ?> <?php echo esc_html( $item ); ?></span>
						<?php endforeach; ?>
						<?php foreach ( $card['no'] as $item ) : ?>
							<span class="th-auth-type__item is-no"><?php th_icon( 'close', array( 'size' => 14 ) ); ?> <?php echo esc_html( $item ); ?><span class="th-sr-only"> <?php esc_html_e( '(not included)', 'torrehub' ); ?></span></span>
						<?php endforeach; ?>
					</span>
					<span class="th-auth-type__foot"><?php echo esc_html( $card['foot'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
		th_component(
			'button',
			array(
				'label'    => __( 'Continue', 'torrehub' ),
				'variant'  => 'accent',
				'type'     => 'submit',
				'size'     => 'lg',
				'icon_end' => 'arrow-right',
				'class'    => 'th-auth-step__next',
			)
		);
		?>
	</form>
	<?php
	return;
endif;

/* ------------------------------------------------------------------ step 2: details */

$field = static function ( string $name, array $args ) use ( $form_errors, $values ) {
	th_component(
		'field',
		array_merge(
			array(
				'id'    => 'th-reg-' . $name,
				'name'  => $name,
				'value' => $values[ $name ] ?? '',
				'error' => $form_errors[ $name ] ?? '',
			),
			$args
		)
	);
};
?>
<div class="th-auth-register">
	<form class="th-auth-card th-auth-card--light th-stack" method="post" action="<?php echo esc_url( add_query_arg( 'type', $acct_type, Pages::url( 'register' ) ) ); ?>" novalidate data-th-register>
		<p class="th-micro th-auth-step__eyebrow">
			<?php
			/* translators: %s: account type */
			echo esc_html( sprintf( __( 'Step 2 of 2 · %s', 'torrehub' ), Accounts::type_label( $acct_type ) ) );
			?>
			· <a href="<?php echo esc_url( Pages::url( 'register' ) ); ?>"><?php esc_html_e( 'Change', 'torrehub' ); ?></a>
		</p>
		<h1 class="th-auth__title"><?php esc_html_e( 'Your details', 'torrehub' ); ?></h1>

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
		<?php elseif ( $form_errors ) : ?>
			<?php
			th_component(
				'alert',
				array(
					'variant' => 'error',
					/* translators: %d: number of fields with errors */
					'text'    => sprintf( _n( 'Please fix %d field below.', 'Please fix the %d fields below.', count( $form_errors ), 'torrehub' ), count( $form_errors ) ),
				)
			);
			?>
		<?php endif; ?>

		<input type="hidden" name="th_action" value="th_register">
		<input type="hidden" name="type" value="<?php echo esc_attr( $acct_type ); ?>">
		<?php wp_nonce_field( 'th_register', '_wpnonce', false ); ?>
		<?php Forms::bot_fields(); ?>

		<div class="th-auth-grid">
			<?php
			$field(
				'first_name',
				array(
					'label'        => __( 'First name', 'torrehub' ),
					'autocomplete' => 'given-name',
					'required'     => true,
				)
			);
			$field(
				'last_name',
				array(
					'label'        => __( 'Last name', 'torrehub' ),
					'autocomplete' => 'family-name',
					'required'     => true,
				)
			);
			?>
		</div>

		<?php if ( 'business' === $acct_type ) : ?>
			<div class="th-auth-grid">
				<?php
				$field(
					'company',
					array(
						'label'         => __( 'Business name', 'torrehub' ),
						'autocomplete'  => 'organization',
						'optional_hint' => true,
						'help'          => __( 'Shown on your listings instead of your name.', 'torrehub' ),
					)
				);
				$field(
					'nif',
					array(
						'label'        => __( 'NIF', 'torrehub' ),
						'required'     => true,
						'autocomplete' => 'off',
						'help'         => __( 'CIF (B12345674), DNI (12345678Z) or NIE-form NIF (X1234567L).', 'torrehub' ),
						'attrs'        => array(
							'data-th-nif'    => '',
							'spellcheck'     => 'false',
							'autocapitalize' => 'characters',
						),
					)
				);
				?>
			</div>
		<?php endif; ?>

		<div class="th-auth-grid">
			<?php
			$field(
				'username',
				array(
					'label'        => __( 'Username', 'torrehub' ),
					'autocomplete' => 'username',
					'required'     => true,
					'help'         => __( 'Letters, numbers, dot, dash or underscore.', 'torrehub' ),
					'attrs'        => array(
						'spellcheck'     => 'false',
						'autocapitalize' => 'none',
					),
				)
			);
			$field(
				'email',
				array(
					'label'        => __( 'E-mail address', 'torrehub' ),
					'type'         => 'email',
					'autocomplete' => 'email',
					'required'     => true,
					'help'         => __( 'We send a confirmation link here before your account is reviewed.', 'torrehub' ),
				)
			);
			?>
		</div>

		<div class="th-auth-grid">
			<?php
			$field(
				'password',
				array(
					'label'          => __( 'Password', 'torrehub' ),
					'type'           => 'password',
					'value'          => '',
					'autocomplete'   => 'new-password',
					'required'       => true,
					'password_meter' => true,
					'help'           => __( 'At least 10 characters.', 'torrehub' ),
					'attrs'          => array( 'minlength' => 10 ),
				)
			);
			$field(
				'password2',
				array(
					'label'        => __( 'Confirm password', 'torrehub' ),
					'type'         => 'password',
					'value'        => '',
					'autocomplete' => 'new-password',
					'required'     => true,
				)
			);
			?>
		</div>

		<div class="th-field">
			<label class="th-check">
				<input type="checkbox" name="terms" value="1" required<?php echo ! empty( $form_errors['terms'] ) ? ' aria-invalid="true" aria-describedby="th-terms-error"' : ''; ?>>
				<span>
					<?php
					printf(
						/* translators: 1: Terms link, 2: Privacy link */
						esc_html__( 'I accept the %1$s and the %2$s.', 'torrehub' ),
						'<a href="' . esc_url( $terms ? (string) get_permalink( $terms ) : '#' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terms & Conditions', 'torrehub' ) . '</a>',
						'<a href="' . esc_url( $privacy ? (string) get_permalink( $privacy ) : '#' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy Policy', 'torrehub' ) . '</a>'
					);
					?>
				</span>
			</label>
			<?php if ( ! empty( $form_errors['terms'] ) ) : ?>
				<p class="th-help th-help--error" id="th-terms-error"><?php th_icon( 'alert-circle', array( 'size' => 14 ) ); ?> <?php echo esc_html( $form_errors['terms'] ); ?></p>
			<?php endif; ?>
		</div>

		<div class="th-cluster">
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Create my account', 'torrehub' ),
					'variant' => 'accent',
					'type'    => 'submit',
					'size'    => 'lg',
				)
			);
			?>
			<span class="th-meta"><?php esc_html_e( 'Under a minute · reviewed within 48 hours', 'torrehub' ); ?></span>
		</div>
	</form>

	<aside class="th-stack th-auth-register__side">
		<section class="th-module th-module--contact" aria-labelledby="th-next-title">
			<p class="th-module__eyebrow" id="th-next-title"><?php esc_html_e( 'What happens next', 'torrehub' ); ?></p>
			<ol class="th-steps th-steps--on-blue">
				<li><div><strong><?php esc_html_e( 'Confirm your e-mail', 'torrehub' ); ?></strong><?php esc_html_e( 'We send a link straight away.', 'torrehub' ); ?></div></li>
				<li><div><strong><?php esc_html_e( 'A person reviews it', 'torrehub' ); ?></strong><?php esc_html_e( 'Manual approval, usually within 48 hours. It keeps the platform clean.', 'torrehub' ); ?></div></li>
				<?php if ( 'member' === $acct_type ) : ?>
					<li><div><strong><?php esc_html_e( 'Save, review and message', 'torrehub' ); ?></strong><?php esc_html_e( 'Contact sellers directly.', 'torrehub' ); ?></div></li>
				<?php else : ?>
					<li class="is-accent"><div><strong><?php esc_html_e( 'Post your first listing', 'torrehub' ); ?></strong><?php esc_html_e( 'Free — no commission, no subscription.', 'torrehub' ); ?></div></li>
				<?php endif; ?>
			</ol>
		</section>
		<?php if ( 'business' === $acct_type ) : ?>
			<section class="th-auth-panel" aria-labelledby="th-why-nif">
				<h2 class="th-auth-panel__title" id="th-why-nif"><?php th_icon( 'shield', array( 'size' => 16 ) ); ?> <?php esc_html_e( 'Why we ask for a NIF', 'torrehub' ); ?></h2>
				<p><?php esc_html_e( 'A Spanish tax id lets us check that a business is real. It’s only seen by our team and never shown on your listings.', 'torrehub' ); ?></p>
			</section>
		<?php endif; ?>
	</aside>
</div>
