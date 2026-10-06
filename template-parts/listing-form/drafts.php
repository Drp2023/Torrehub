<?php
/**
 * "Continue a draft": the user's listing drafts with Continue / Delete (listing form start screen, My listings).
 *
 * @package Torrehub
 *
 * @var array $args { @type string $back URL to return to after deleting. }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\ListingForm\Drafts;
use Torrehub\Modules\ListingForm\Module;

$drafts = Drafts::for_user( get_current_user_id() );
$back   = (string) ( $args['back'] ?? Module::url() );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display notice.
$deleted = isset( $_GET['th_draft_deleted'] ) && '1' === $_GET['th_draft_deleted'];
?>
<?php if ( $deleted ) : ?>
	<?php
	th_component(
		'alert',
		array(
			'variant' => 'success',
			'text'    => __( 'Draft deleted.', 'torrehub' ),
			'role'    => 'status',
		)
	);
	?>
<?php endif; ?>
<?php if ( $drafts ) : ?>
	<section class="th-drafts" aria-labelledby="th-drafts-title">
		<h2 class="th-drafts__title" id="th-drafts-title"><?php esc_html_e( 'Continue a draft', 'torrehub' ); ?></h2>
		<ul class="th-drafts__list" role="list">
			<?php foreach ( $drafts as $draft ) : ?>
				<li class="th-draft">
					<a class="th-draft__link" href="<?php echo esc_url( $draft['url'] ); ?>">
						<span class="th-draft__name"><?php echo esc_html( $draft['title'] ); ?></span>
						<span class="th-meta">
							<?php
							echo esc_html(
								implode(
									' · ',
									array_filter(
										array(
											$draft['category'],
											/* translators: %s: human time difference, e.g. "2 hours" */
											$draft['saved'] ? sprintf( __( 'saved %s ago', 'torrehub' ), human_time_diff( $draft['saved'] ) ) : '',
										)
									)
								)
							);
							?>
						</span>
					</a>
					<form method="post" action="<?php echo esc_url( Module::url() ); ?>" data-th-confirm="<?php esc_attr_e( 'Delete this draft and its photos?', 'torrehub' ); ?>">
						<input type="hidden" name="th_action" value="th_delete_draft">
						<input type="hidden" name="draft_id" value="<?php echo esc_attr( (string) $draft['id'] ); ?>">
						<input type="hidden" name="back" value="<?php echo esc_url( $back ); ?>">
						<?php wp_nonce_field( 'th_delete_draft_' . $draft['id'] ); ?>
						<?php
						th_component(
							'button',
							array(
								'variant'    => 'destructive',
								'size'       => 'sm',
								'icon'       => 'trash',
								'icon_only'  => true,
								'type'       => 'submit',
								/* translators: %s: draft title */
								'aria_label' => sprintf( __( 'Delete draft %s', 'torrehub' ), $draft['title'] ),
							)
						);
						?>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
