<?php
/**
 * No posting allowance left (Quota module).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\ListingForm\Workspace $ws }
 */

defined( 'ABSPATH' ) || exit;

$ws = $args['ws'];
?>
<div class="th-container th-lf-done">
	<section class="th-auth-panel th-auth-panel--amber th-lf-done__panel" aria-labelledby="th-lf-blocked-title">
		<span class="th-icon-circle"><?php th_icon( 'clock' ); ?></span>
		<h1 class="th-auth-panel__title" id="th-lf-blocked-title"><?php esc_html_e( 'No free listings left for now', 'torrehub' ); ?></h1>
		<p><?php echo esc_html( $ws->reason ); ?></p>
		<?php
		th_component(
			'button',
			array(
				'label'   => __( 'View my listings', 'torrehub' ),
				'variant' => 'ink',
				'block'   => true,
				'href'    => \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' ),
			)
		);
		?>
	</section>
</div>
