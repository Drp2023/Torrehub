<?php
/**
 * Dashboard card (G-03): "Get your verified badge" / request pending / rejected — try again.
 *
 * @package Torrehub
 *
 * @var array $args { @type array $record Verification record. }
 */

defined( 'ABSPATH' ) || exit;

$record = $args['record'];
$url    = \Rtcl\Helpers\Link::get_account_endpoint_url( 'verification' );
?>
<section class="th-account-verify th-account-verify--<?php echo esc_attr( $record['status'] ? $record['status'] : 'none' ); ?>" aria-labelledby="th-verify-card">
	<span class="th-icon-circle"><?php th_icon( 'pending' === $record['status'] ? 'clock' : 'shield', array( 'size' => 20 ) ); ?></span>
	<div class="th-stack" style="--th-stack-gap:4px">
		<?php if ( 'pending' === $record['status'] ) : ?>
			<h2 class="th-account-verify__title" id="th-verify-card"><?php esc_html_e( 'Verification in review', 'torrehub' ); ?></h2>
			<p><?php esc_html_e( 'We’ll check your document, usually within 48 hours.', 'torrehub' ); ?></p>
		<?php elseif ( 'rejected' === $record['status'] ) : ?>
			<h2 class="th-account-verify__title" id="th-verify-card"><?php esc_html_e( 'Verification not approved', 'torrehub' ); ?></h2>
			<p><?php esc_html_e( 'You can upload another document.', 'torrehub' ); ?></p>
		<?php else : ?>
			<h2 class="th-account-verify__title" id="th-verify-card"><?php esc_html_e( 'Get your verified badge', 'torrehub' ); ?></h2>
			<p><?php esc_html_e( 'Upload an ID document — reviewed within 48 hours, then deleted.', 'torrehub' ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( 'pending' !== $record['status'] ) : ?>
		<?php
		th_component(
			'button',
			array(
				'label'   => __( 'Upload document', 'torrehub' ),
				'variant' => 'clay',
				'href'    => $url,
				'class'   => 'th-account-verify__cta',
			)
		);
		?>
	<?php endif; ?>
</section>
