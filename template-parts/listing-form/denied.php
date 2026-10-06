<?php
/**
 * T-08 (403) — posting isn't possible: a member account (no listings) or someone else's listing.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\ListingForm\Workspace $ws }
 */

defined( 'ABSPATH' ) || exit;

$ws      = $args['ws'];
$user    = wp_get_current_user();
$contact = get_page_by_path( 'contact' );
$contact = $contact ? add_query_arg( 'subject', 'upgrade', (string) get_permalink( $contact ) ) : th_url_account();
if ( 'member' === $ws->reason ) {
	$panel_title = __( 'You need a seller account', 'torrehub' );
	$panel_text  = __( 'Posting listings requires a Private Seller or Business Seller account. Yours is a Member account, which can browse, save and chat.', 'torrehub' );
} elseif ( 'form' === $ws->reason ) {
	$panel_title = __( 'This listing can’t be edited here', 'torrehub' );
	$panel_text  = __( 'Its form no longer exists. Contact us and we’ll update it for you.', 'torrehub' );
} else {
	$panel_title = __( 'You can’t edit this listing', 'torrehub' );
	$panel_text  = __( 'It belongs to another account.', 'torrehub' );
}
?>
<div class="th-container th-lf-done">
	<section class="th-error-panel th-error-panel--403 th-lf-done__panel" aria-labelledby="th-lf-denied-title">
		<span class="th-icon-circle"><?php th_icon( 'ban' ); ?></span>
		<h1 class="th-error-panel__title" id="th-lf-denied-title"><?php echo esc_html( $panel_title ); ?></h1>
		<p><?php echo esc_html( $panel_text ); ?></p>
		<?php if ( 'member' === $ws->reason ) : ?>
			<p class="th-lf-denied__who">
				<?php echo th_get_avatar( $user->ID, 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
				<strong><?php echo esc_html( $user->display_name ); ?></strong>
				<?php
				th_component(
					'badge',
					array(
						'label'   => __( 'Member', 'torrehub' ),
						'variant' => 'verified',
					)
				);
				?>
			</p>
		<?php endif; ?>
		<div class="th-stack" style="--th-stack-gap:10px">
			<?php
			th_component(
				'button',
				array(
					'label'   => 'member' === $ws->reason ? __( 'Contact us to upgrade', 'torrehub' ) : __( 'Go to my account', 'torrehub' ),
					'variant' => 'clay',
					'block'   => true,
					'href'    => 'member' === $ws->reason ? $contact : th_url_account(),
				)
			);
			th_component(
				'button',
				array(
					'label'   => __( 'Back to browsing', 'torrehub' ),
					'variant' => 'white',
					'block'   => true,
					'href'    => th_url_listings(),
				)
			);
			?>
		</div>
	</section>
</div>
