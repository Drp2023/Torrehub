<?php
/**
 * Posting isn't possible: a member account (no listings) or someone else's listing.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\ListingForm\Workspace $ws }
 */

defined( 'ABSPATH' ) || exit;

$ws = $args['ws'];
if ( 'member' === $ws->reason ) {
	$panel_title = __( 'Posting needs a seller account', 'torrehub' );
	$panel_text  = __( 'Your account is a Member account: you can browse, save and contact sellers. To post listings, you need a Private Seller or Business Seller account — contact us and we’ll switch it.', 'torrehub' );
} elseif ( 'form' === $ws->reason ) {
	$panel_title = __( 'This listing can’t be edited here', 'torrehub' );
	$panel_text  = __( 'Its form no longer exists. Contact us and we’ll update it for you.', 'torrehub' );
} else {
	$panel_title = __( 'You can’t edit this listing', 'torrehub' );
	$panel_text  = __( 'It belongs to another account.', 'torrehub' );
}
?>
<div class="th-container th-lf-done">
	<section class="th-auth-panel th-auth-panel--clay th-lf-done__panel" aria-labelledby="th-lf-denied-title">
		<span class="th-icon-circle"><?php th_icon( 'lock' ); ?></span>
		<h1 class="th-auth-panel__title" id="th-lf-denied-title"><?php echo esc_html( $panel_title ); ?></h1>
		<p><?php echo esc_html( $panel_text ); ?></p>
		<?php
		th_component(
			'button',
			array(
				'label'   => __( 'Go to my account', 'torrehub' ),
				'variant' => 'clay',
				'block'   => true,
				'href'    => th_url_account(),
			)
		);
		?>
	</section>
</div>
