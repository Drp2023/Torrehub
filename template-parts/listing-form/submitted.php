<?php
/**
 * S-18 — listing submitted (or changes saved).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\ListingForm\Workspace $ws }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\ListingForm\Module;

$ws             = $args['ws'];
$listing_status = get_post_status( $ws->listing_id );
$live           = 'publish' === $listing_status;
$updated        = isset( $_GET['th_updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state.
if ( $live ) {
	$panel_title = $updated ? __( 'Changes saved', 'torrehub' ) : __( 'Your listing is live', 'torrehub' );
	$panel_text  = __( 'Anyone can find it now.', 'torrehub' );
} else {
	$panel_title = $updated ? __( 'Changes submitted', 'torrehub' ) : __( 'Listing submitted', 'torrehub' );
	$panel_text  = __( 'An administrator will review it, usually within 48 hours. You’ll get an e-mail when it goes live.', 'torrehub' );
}
?>
<div class="th-container th-lf-done">
	<section class="th-auth-panel th-auth-panel--green th-lf-done__panel" role="status" aria-labelledby="th-lf-done-title">
		<span class="th-icon-circle"><?php th_icon( 'check' ); ?></span>
		<h1 class="th-auth-panel__title" id="th-lf-done-title"><?php echo esc_html( $panel_title ); ?></h1>
		<p><?php echo esc_html( $panel_text ); ?></p>
		<p class="th-lf-done__name"><?php echo esc_html( html_entity_decode( get_the_title( $ws->listing_id ), ENT_QUOTES ) ); ?></p>
		<div class="th-stack" style="--th-stack-gap:10px">
			<?php
			th_component(
				'button',
				array(
					'label'   => $live ? __( 'View listing', 'torrehub' ) : __( 'View my listings', 'torrehub' ),
					'variant' => 'whatsapp',
					'size'    => 'lg',
					'block'   => true,
					'href'    => $live ? (string) get_permalink( $ws->listing_id ) : \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' ),
				)
			);
			th_component(
				'button',
				array(
					'label'   => __( 'Add another listing', 'torrehub' ),
					'variant' => 'white',
					'size'    => 'lg',
					'block'   => true,
					'href'    => Module::url(),
				)
			);
			?>
		</div>
	</section>
</div>
