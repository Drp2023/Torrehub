<?php
/**
 * Report dialog (logged-in users who haven't reported yet). Without JS: ?th_report_form=1 renders it in place.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Listing\Report;

$view = $args['view'];
if ( ! is_user_logged_in() || $view->is_owner || ! $view->is_public() || Report::reported_by_current_user( $view->id ) ) {
	return;
}
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
$is_open = ! empty( $_GET['th_report_form'] );
?>
<dialog class="th-dialog th-dialog--sheet" id="th-report" aria-labelledby="th-report-title" data-th-dialog data-th-report<?php echo $is_open ? ' open' : ''; ?>>
	<form class="th-dialog__form" method="post" action="<?php echo esc_url( (string) get_permalink( $view->id ) ); ?>">
		<div class="th-dialog__head">
			<h2 class="th-dialog__title" id="th-report-title"><?php esc_html_e( 'Report this listing', 'torrehub' ); ?></h2>
			<?php
			th_component(
				'button',
				array(
					'variant'    => 'neutral',
					'size'       => 'sm',
					'icon'       => 'close',
					'icon_only'  => true,
					'aria_label' => __( 'Close', 'torrehub' ),
					'href'       => $is_open ? (string) get_permalink( $view->id ) : '',
					'attrs'      => array( 'data-th-dialog-close' => '' ),
				)
			);
			?>
		</div>
		<div class="th-dialog__body th-stack">
			<input type="hidden" name="th_action" value="th_report_listing">
			<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) $view->id ); ?>">
			<?php wp_nonce_field( 'th_report_' . $view->id ); ?>
			<fieldset class="th-stack" style="--th-stack-gap:8px">
				<legend class="th-label"><?php esc_html_e( 'What’s wrong with it?', 'torrehub' ); ?></legend>
				<?php foreach ( Report::reasons() as $value => $label ) : ?>
					<label class="th-check"><input type="radio" name="reason" value="<?php echo esc_attr( $value ); ?>" required> <span><?php echo esc_html( $label ); ?></span></label>
				<?php endforeach; ?>
			</fieldset>
			<?php
			th_component(
				'field',
				array(
					'id'            => 'th-report-message',
					'name'          => 'message',
					'label'         => __( 'Anything else we should know?', 'torrehub' ),
					'type'          => 'textarea',
					'optional_hint' => true,
					'maxlength'     => 1000,
					'help'          => __( 'Only our team sees this. The seller isn’t told who reported.', 'torrehub' ),
				)
			);
			?>
			<p class="th-help" role="status" aria-live="polite" data-th-report-status></p>
		</div>
		<div class="th-dialog__foot">
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Cancel', 'torrehub' ),
					'variant' => 'outline',
					'attrs'   => array( 'data-th-dialog-close' => '' ),
				)
			);
			th_component(
				'button',
				array(
					'label'   => __( 'Send report', 'torrehub' ),
					'variant' => 'destructive',
					'type'    => 'submit',
				)
			);
			?>
		</div>
	</form>
</dialog>
