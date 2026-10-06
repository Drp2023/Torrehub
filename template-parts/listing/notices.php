<?php
/**
 * Status panels (L-17 expired, L-18 pending) and results of no-JS form posts (?th_review=, ?th_report=, ?th_enquiry=).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Listing\Module as Listing;
use Torrehub\Modules\Listing\Report;

$view = $args['view'];

if ( 'rtcl-expired' === $view->status ) :
	$similar = $view->category ? (string) get_term_link( $view->category ) : th_url_listings();
	?>
	<div class="th-listing-state th-listing-state--expired" role="status">
		<p class="th-listing-state__title"><?php esc_html_e( 'This listing has expired', 'torrehub' ); ?></p>
		<p><?php esc_html_e( 'The seller can’t be contacted through it any more.', 'torrehub' ); ?></p>
		<?php
		th_component(
			'button',
			array(
				'label' => $view->category
					/* translators: %s: category name */
					? sprintf( __( 'See similar in %s', 'torrehub' ), html_entity_decode( $view->category->name, ENT_QUOTES ) )
					: __( 'Browse listings', 'torrehub' ),
				'href'  => $similar,
			)
		);
		?>
	</div>
	<?php
elseif ( ! $view->is_public() && $view->is_owner ) :
	?>
	<div class="th-listing-state th-listing-state--pending" role="status">
		<p class="th-listing-state__title"><?php esc_html_e( 'Pending approval — only you can see this', 'torrehub' ); ?></p>
		<p><?php esc_html_e( 'An administrator is reviewing it, usually within 48 hours. You can still edit it.', 'torrehub' ); ?></p>
		<div class="th-cluster">
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Edit listing', 'torrehub' ),
					'variant' => 'clay',
					'href'    => \Rtcl\Helpers\Link::get_listing_edit_page_link( $view->id ),
				)
			);
			th_component(
				'button',
				array(
					'label'   => __( 'My listings', 'torrehub' ),
					'variant' => 'clay-outline',
					'href'    => \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' ),
				)
			);
			?>
		</div>
	</div>
	<?php
endif;

// Results of no-JS submissions.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
$notices = array();
if ( isset( $_GET['th_review'] ) ) {
	$code      = sanitize_key( wp_unslash( $_GET['th_review'] ) );
	$texts     = array(
		'published' => array( 'success', __( 'Thanks — your review is published.', 'torrehub' ) ),
		'pending'   => array( 'success', __( 'Thanks — your review will appear after a quick check.', 'torrehub' ) ),
		'done'      => array( 'info', __( 'You have already reviewed this listing.', 'torrehub' ) ),
		'own'       => array( 'info', __( 'You can’t review your own listing.', 'torrehub' ) ),
		'rating'    => array( 'error', __( 'Choose a star rating.', 'torrehub' ) ),
		'text'      => array( 'error', __( 'Write at least 20 characters (and a title of up to 100).', 'torrehub' ) ),
	);
	$notices[] = $texts[ $code ] ?? array( 'error', __( 'Your review couldn’t be saved. Please try again.', 'torrehub' ) );
}
if ( isset( $_GET['th_report'] ) ) {
	$code      = sanitize_key( wp_unslash( $_GET['th_report'] ) );
	$notices[] = array( 'sent' === $code ? 'success' : 'error', Report::message( $code ) );
}
if ( isset( $_GET['th_enquiry'] ) ) {
	$code      = sanitize_key( wp_unslash( $_GET['th_enquiry'] ) );
	$notices[] = array( 'sent' === $code ? 'success' : 'error', Listing::enquiry_message( $code ) );
}
// phpcs:enable
foreach ( $notices as $notice ) {
	th_component(
		'alert',
		array(
			'variant' => $notice[0],
			'text'    => $notice[1],
			'role'    => 'error' === $notice[0] ? 'alert' : 'status',
		)
	);
}
