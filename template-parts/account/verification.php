<?php
/**
 * Account → Verification: status, privacy explanation, upload form.
 *
 * @package Torrehub
 *
 * @var array $args { @type array $record Verification record. }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Verification\Module as Verification;

$record = $args['record'];
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
$code = isset( $_GET['th_v'] ) ? sanitize_key( wp_unslash( $_GET['th_v'] ) ) : '';

$messages = array(
	'sent'    => array( 'success', __( 'Thanks — your document was sent. We’ll e-mail you when it’s reviewed.', 'torrehub' ) ),
	'missing' => array( 'error', __( 'Choose a file to upload.', 'torrehub' ) ),
	'size'    => array( 'error', __( 'The file is larger than 10 MB.', 'torrehub' ) ),
	'type'    => array( 'error', __( 'Upload a JPG, PNG, WebP or PDF file.', 'torrehub' ) ),
	'consent' => array( 'error', __( 'Please confirm that we may use the document to verify your account.', 'torrehub' ) ),
	'exists'  => array( 'info', __( 'Your request is already being reviewed.', 'torrehub' ) ),
	'denied'  => array( 'error', __( 'Only seller accounts can be verified.', 'torrehub' ) ),
	'error'   => array( 'error', __( 'The upload failed. Please try again.', 'torrehub' ) ),
);
?>
<div class="th-stack">
	<h1 class="th-account__title"><?php esc_html_e( 'Verification', 'torrehub' ); ?></h1>

	<?php if ( isset( $messages[ $code ] ) ) : ?>
		<?php
		th_component(
			'alert',
			array(
				'variant' => $messages[ $code ][0],
				'text'    => $messages[ $code ][1],
				'role'    => 'error' === $messages[ $code ][0] ? 'alert' : 'status',
			)
		);
		?>
	<?php endif; ?>

	<?php if ( 'approved' === $record['status'] ) : ?>
		<section class="th-auth-panel th-account-verify--approved">
			<h2 class="th-auth-panel__title"><?php th_icon( 'shield-check', array( 'size' => 18 ) ); ?> <?php esc_html_e( 'You’re verified', 'torrehub' ); ?></h2>
			<p><?php esc_html_e( 'Your listings show the Verified badge. We don’t keep your document.', 'torrehub' ); ?></p>
		</section>
	<?php elseif ( 'pending' === $record['status'] ) : ?>
		<section class="th-auth-panel th-auth-panel--amber">
			<h2 class="th-auth-panel__title"><?php th_icon( 'clock', array( 'size' => 18 ) ); ?> <?php esc_html_e( 'In review', 'torrehub' ); ?></h2>
			<p>
				<?php
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'Sent on %s. We usually reply within 48 hours.', 'torrehub' ), wp_date( get_option( 'date_format' ), (int) $record['submitted'] ) ) );
				?>
			</p>
		</section>
	<?php else : ?>
		<?php if ( 'rejected' === $record['status'] ) : ?>
			<?php
			th_component(
				'alert',
				array(
					'variant' => 'error',
					'title'   => __( 'The last document couldn’t be verified', 'torrehub' ),
					'text'    => $record['reason'] ? $record['reason'] : __( 'Please upload a clearer or different document.', 'torrehub' ),
				)
			);
			?>
		<?php endif; ?>
		<section class="th-account-card th-stack" aria-labelledby="th-verify-how">
			<h2 id="th-verify-how"><?php esc_html_e( 'Get the Verified badge', 'torrehub' ); ?></h2>
			<ol class="th-account-verify__steps">
				<li><?php esc_html_e( 'Upload a photo or scan of an ID document in your name (passport, ID card or residence card).', 'torrehub' ); ?></li>
				<li><?php esc_html_e( 'A person checks that it matches your account — usually within 48 hours.', 'torrehub' ); ?></li>
				<li><?php esc_html_e( 'Your listings show the Verified badge. The document is deleted right after the check.', 'torrehub' ); ?></li>
			</ol>
			<form class="th-stack" method="post" enctype="multipart/form-data" action="<?php echo esc_url( \Rtcl\Helpers\Link::get_account_endpoint_url( Verification::ENDPOINT ) ); ?>">
				<input type="hidden" name="th_action" value="th_verification_upload">
				<?php wp_nonce_field( 'th_verification_upload' ); ?>
				<div class="th-field">
					<label class="th-label" for="th-document"><?php esc_html_e( 'ID document', 'torrehub' ); ?></label>
					<input class="th-input th-input--file" id="th-document" type="file" name="document" required accept="image/jpeg,image/png,image/webp,application/pdf" aria-describedby="th-document-help">
					<p class="th-help" id="th-document-help"><?php esc_html_e( 'JPG, PNG, WebP or PDF, up to 10 MB. Cover any numbers you don’t want us to see — we only check that the name and photo match.', 'torrehub' ); ?></p>
				</div>
				<label class="th-check">
					<input type="checkbox" name="consent" value="1" required>
					<span><?php esc_html_e( 'I agree that Torrehub uses this document only to verify my account and deletes it after the check.', 'torrehub' ); ?></span>
				</label>
				<?php
				th_component(
					'button',
					array(
						'label'   => __( 'Send for review', 'torrehub' ),
						'variant' => 'accent',
						'icon'    => 'upload',
						'type'    => 'submit',
					)
				);
				?>
			</form>
		</section>
	<?php endif; ?>
</div>
