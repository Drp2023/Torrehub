<?php
/**
 * Result of an alert unsubscribe link (or its undo).
 *
 * @package Torrehub
 *
 * @var array $args { @type object|null $alert, @type string $state off|on|invalid }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\SearchAlerts\Module as Alerts;

$alert = $args['alert'] ?? null;
$state = (string) ( $args['state'] ?? 'invalid' );
?>
<main id="main" class="th-main th-container th-section" tabindex="-1">
	<div class="th-error-panel th-alerts-panel">
		<span class="th-icon-circle"><?php th_icon( 'bell' ); ?></span>
		<?php if ( 'invalid' === $state ) : ?>
			<h1 class="th-error-panel__title"><?php esc_html_e( 'This link doesn’t work any more', 'torrehub' ); ?></h1>
			<p><?php esc_html_e( 'The saved search may have been deleted. You can manage your saved searches in your account.', 'torrehub' ); ?></p>
		<?php elseif ( 'off' === $state ) : ?>
			<h1 class="th-error-panel__title"><?php esc_html_e( 'E-mails stopped', 'torrehub' ); ?></h1>
			<p>
				<?php
				/* translators: %s: saved search name */
				echo esc_html( sprintf( __( 'You won’t get e-mails for “%s” any more. The search stays saved.', 'torrehub' ), (string) $alert->label ) );
				?>
			</p>
			<form method="post" action="<?php echo esc_url( Alerts::token_url( $alert, 'on' ) ); ?>">
				<?php
				th_component(
					'button',
					array(
						'label'   => __( 'Undo — keep sending them', 'torrehub' ),
						'variant' => 'outline',
						'type'    => 'submit',
					)
				);
				?>
			</form>
		<?php else : ?>
			<h1 class="th-error-panel__title"><?php esc_html_e( 'E-mails back on', 'torrehub' ); ?></h1>
			<p>
				<?php
				/* translators: %s: saved search name */
				echo esc_html( sprintf( __( 'We’ll e-mail you daily about new listings for “%s”.', 'torrehub' ), (string) $alert->label ) );
				?>
			</p>
		<?php endif; ?>
		<div class="th-cluster">
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Manage saved searches', 'torrehub' ),
					'variant' => 'ink',
					'href'    => is_user_logged_in() ? Alerts::url() : th_url_login( Alerts::url() ),
				)
			);
			th_component(
				'button',
				array(
					'label'   => __( 'Browse listings', 'torrehub' ),
					'variant' => 'white',
					'href'    => th_url_listings(),
				)
			);
			?>
		</div>
	</div>
</main>
