<?php
/**
 * The reminder e-mail's renewal link: the listing and a Renew button (POST), the result, or why there is nothing to
 * do. Same panel as the saved-search unsubscribe page (design review).
 *
 * @package Torrehub
 *
 * @var array $args { @type int $listing_id, @type string $state ready|running|renewed|invalid }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Lifetime\Module as Lifetime;

$listing_id = (int) ( $args['listing_id'] ?? 0 );
$state      = (string) ( $args['state'] ?? 'invalid' );
$listing_t  = $listing_id ? html_entity_decode( get_the_title( $listing_id ), ENT_QUOTES ) : '';
$ends       = $listing_id ? Lifetime::ends( $listing_id ) : 0;
$days       = $listing_id ? Lifetime::days( $listing_id ) : 0;
$my_list    = \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' );
?>
<main id="main" class="th-main th-container th-section" tabindex="-1">
	<div class="th-error-panel th-error-panel--page">
		<span class="th-icon-circle"><?php th_icon( 'invalid' === $state ? 'info' : 'clock' ); ?></span>
		<?php if ( 'invalid' === $state ) : ?>
			<h1 class="th-error-panel__title"><?php esc_html_e( 'This link doesn’t work any more', 'torrehub' ); ?></h1>
			<p><?php esc_html_e( 'The listing may have been deleted or is waiting for review. You can see all your listings in your account.', 'torrehub' ); ?></p>
		<?php elseif ( 'ready' === $state ) : ?>
			<h1 class="th-error-panel__title"><?php esc_html_e( 'Renew your listing', 'torrehub' ); ?></h1>
			<p>
				<?php
				echo esc_html(
					'rtcl-expired' === get_post_status( $listing_id )
						/* translators: %s: listing title */
						? sprintf( __( '“%s” has ended.', 'torrehub' ), $listing_t )
						/* translators: 1: listing title, 2: date */
						: sprintf( __( '“%1$s” runs until %2$s.', 'torrehub' ), $listing_t, wp_date( get_option( 'date_format' ), $ends ) )
				);
				echo ' ';
				/* translators: %d: number of days */
				echo esc_html( sprintf( _n( 'Renewing keeps it online for another %d day. It’s free.', 'Renewing keeps it online for another %d days. It’s free.', $days, 'torrehub' ), $days ) );
				?>
			</p>
			<form method="post" action="<?php echo esc_url( Lifetime::renew_url( $listing_id ) ); ?>">
				<?php
				th_component(
					'button',
					array(
						/* translators: %d: number of days */
						'label'   => sprintf( _n( 'Renew for %d day', 'Renew for %d days', $days, 'torrehub' ), $days ),
						'variant' => 'accent',
						'type'    => 'submit',
					)
				);
				?>
			</form>
		<?php else : ?>
			<h1 class="th-error-panel__title"><?php echo esc_html( 'renewed' === $state ? __( 'Listing renewed', 'torrehub' ) : __( 'Nothing to renew yet', 'torrehub' ) ); ?></h1>
			<p>
				<?php
				echo esc_html(
					$ends
						/* translators: 1: listing title, 2: date */
						? sprintf( __( '“%1$s” runs until %2$s.', 'torrehub' ), $listing_t, wp_date( get_option( 'date_format' ), $ends ) )
						/* translators: %s: listing title */
						: sprintf( __( '“%s” doesn’t expire.', 'torrehub' ), $listing_t )
				);
				if ( 'running' === $state ) {
					echo ' ' . esc_html__( 'You can renew it when it gets close to the end — we’ll send you a reminder.', 'torrehub' );
				}
				?>
			</p>
		<?php endif; ?>
		<div class="th-cluster">
			<?php
			if ( $listing_id && 'ready' !== $state ) {
				th_component(
					'button',
					array(
						'label'   => __( 'View listing', 'torrehub' ),
						'variant' => 'ink',
						'href'    => (string) get_permalink( $listing_id ),
					)
				);
			}
			th_component(
				'button',
				array(
					'label'   => __( 'My listings', 'torrehub' ),
					'variant' => 'white',
					'href'    => is_user_logged_in() ? $my_list : th_url_login( $my_list ),
				)
			);
			?>
		</div>
	</div>
</main>
