<?php
/**
 * Review form in a dialog (rendered open and in place with ?th_review_form=1 when JS is off).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

$view = $args['view'];
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
$is_open = ! empty( $_GET['th_review_form'] );
?>
<dialog class="th-dialog th-dialog--sheet" id="th-review-dialog" aria-labelledby="th-review-title" data-th-dialog<?php echo $is_open ? ' open' : ''; ?>>
	<form class="th-dialog__form" method="post" action="<?php echo esc_url( (string) get_permalink( $view->id ) ); ?>">
		<div class="th-dialog__head">
			<h2 class="th-dialog__title" id="th-review-title"><?php esc_html_e( 'Write a review', 'torrehub' ); ?></h2>
			<?php
			th_component(
				'button',
				array(
					'variant'    => 'neutral',
					'size'       => 'sm',
					'icon'       => 'close',
					'icon_only'  => true,
					'aria_label' => __( 'Close', 'torrehub' ),
					'href'       => $is_open ? (string) get_permalink( $view->id ) . '#reviews' : '',
					'attrs'      => array( 'data-th-dialog-close' => '' ),
				)
			);
			?>
		</div>
		<div class="th-dialog__body th-stack">
			<input type="hidden" name="th_action" value="th_review">
			<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) $view->id ); ?>">
			<?php wp_nonce_field( 'th_review_' . $view->id ); ?>
			<p class="th-dialog__context"><?php echo esc_html( $view->title ); ?></p>
			<fieldset class="th-stars">
				<legend class="th-label"><?php esc_html_e( 'Your rating', 'torrehub' ); ?></legend>
				<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
					<input type="radio" id="th-star-<?php echo (int) $i; ?>" name="rating" value="<?php echo (int) $i; ?>" required>
					<label for="th-star-<?php echo (int) $i; ?>">
						<?php th_icon( 'star-filled', array( 'size' => 30 ) ); ?>
						<span class="th-sr-only">
							<?php
							/* translators: %d: stars */
							echo esc_html( sprintf( _n( '%d star', '%d stars', $i, 'torrehub' ), $i ) );
							?>
						</span>
					</label>
				<?php endfor; ?>
			</fieldset>
			<?php
			th_component(
				'field',
				array(
					'id'            => 'th-review-title-input',
					'name'          => 'title',
					'label'         => __( 'Title', 'torrehub' ),
					'optional_hint' => true,
					'attrs'         => array( 'maxlength' => 100 ),
				)
			);
			th_component(
				'field',
				array(
					'id'        => 'th-review-text',
					'name'      => 'text',
					'label'     => __( 'Your review', 'torrehub' ),
					'type'      => 'textarea',
					'required'  => true,
					'maxlength' => 3000,
					'help'      => __( 'At least 20 characters. Reviews are checked before they appear.', 'torrehub' ),
					'attrs'     => array( 'minlength' => 20 ),
				)
			);
			?>
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
					'label'   => __( 'Publish review', 'torrehub' ),
					'variant' => 'accent',
					'type'    => 'submit',
				)
			);
			?>
		</div>
	</form>
</dialog>
