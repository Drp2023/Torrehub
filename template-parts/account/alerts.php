<?php
/**
 * Account › Saved searches: name, what it searches, how often (or off), results link, delete.
 *
 * @package Torrehub
 *
 * @var array $args { @type array<int,object> $alerts }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\SearchAlerts\Matcher;
use Torrehub\Modules\SearchAlerts\Module as Alerts;
use Torrehub\Modules\SearchAlerts\Store;

$alerts  = (array) ( $args['alerts'] ?? array() );
$notice  = isset( $_GET['th_alert'] ) ? sanitize_key( wp_unslash( $_GET['th_alert'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display notice.
$notices = array(
	'updated' => __( 'Saved search updated.', 'torrehub' ),
	'deleted' => __( 'Saved search deleted.', 'torrehub' ),
	'missing' => __( 'That saved search no longer exists.', 'torrehub' ),
);
$labels  = array(
	'instant' => __( 'Instantly', 'torrehub' ),
	'daily'   => __( 'Daily', 'torrehub' ),
	'weekly'  => __( 'Weekly', 'torrehub' ),
	'off'     => __( 'Off', 'torrehub' ),
);
?>
<div class="th-stack th-alerts">
	<header class="th-account-dash__head">
		<h1 class="th-account__title"><?php esc_html_e( 'Saved searches', 'torrehub' ); ?></h1>
		<p class="th-account-dash__sub">
			<?php
			/* translators: %s: e-mail address */
			echo esc_html( sprintf( __( 'New matching listings are e-mailed to %s.', 'torrehub' ), wp_get_current_user()->user_email ) );
			?>
		</p>
	</header>

	<?php if ( isset( $notices[ $notice ] ) ) : ?>
		<?php
		th_component(
			'alert',
			array(
				'variant' => 'missing' === $notice ? 'error' : 'success',
				'text'    => $notices[ $notice ],
				'role'    => 'status',
			)
		);
		?>
	<?php endif; ?>

	<?php if ( ! $alerts ) : ?>
		<?php
		th_component(
			'empty-state',
			array(
				'icon'          => 'bell',
				'title'         => __( 'No saved searches yet', 'torrehub' ),
				'text'          => __( 'Search or filter the listings, then use “Save this search” — we’ll e-mail you when something new matches.', 'torrehub' ),
				'heading_level' => 'h2',
				'actions'       => array(
					array(
						'label'   => __( 'Browse listings', 'torrehub' ),
						'variant' => 'primary',
						'href'    => th_url_listings(),
					),
				),
			)
		);
		?>
	<?php else : ?>
		<ul class="th-alerts__list" role="list">
			<?php foreach ( $alerts as $alert ) : ?>
				<?php
				$params  = Store::params( $alert );
				$form_id = 'th-alert-' . (int) $alert->id;
				?>
				<li class="<?php echo esc_attr( 'th-alert-row' . ( 'off' === $alert->frequency ? ' is-off' : '' ) ); ?>">
					<div class="th-alert-row__main">
						<h2 class="th-alert-row__name"><?php echo esc_html( (string) $alert->label ); ?></h2>
						<p class="th-alert-row__summary"><?php echo esc_html( Matcher::summary( $params ) ); ?></p>
						<a class="th-alert-row__link" href="<?php echo esc_url( Alerts::results_url( $alert ) ); ?>"><?php esc_html_e( 'See results', 'torrehub' ); ?> <span aria-hidden="true">→</span></a>
					</div>
					<form class="th-alert-row__form" id="<?php echo esc_attr( $form_id ); ?>" method="post" action="<?php echo esc_url( Alerts::url() ); ?>">
						<input type="hidden" name="th_action" value="th_alert_update">
						<input type="hidden" name="alert" value="<?php echo esc_attr( (string) $alert->id ); ?>">
						<?php wp_nonce_field( 'th_alert_update_' . $alert->id ); ?>
						<label class="th-sr-only" for="<?php echo esc_attr( $form_id . '-freq' ); ?>">
							<?php
							/* translators: %s: saved search name */
							echo esc_html( sprintf( __( 'How often for “%s”', 'torrehub' ), $alert->label ) );
							?>
						</label>
						<div class="th-control">
							<select class="th-select" id="<?php echo esc_attr( $form_id . '-freq' ); ?>" name="frequency">
								<?php foreach ( $labels as $value => $text ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $alert->frequency, $value ); ?>><?php echo esc_html( $text ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="th-control__chevron" aria-hidden="true"><?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?></span>
						</div>
						<?php
						th_component(
							'button',
							array(
								'label'   => __( 'Save', 'torrehub' ),
								'variant' => 'neutral',
								'size'    => 'sm',
								'type'    => 'submit',
							)
						);
						?>
					</form>
					<form method="post" action="<?php echo esc_url( Alerts::url() ); ?>" data-th-confirm="<?php esc_attr_e( 'Delete this saved search?', 'torrehub' ); ?>">
						<input type="hidden" name="th_action" value="th_alert_delete">
						<input type="hidden" name="alert" value="<?php echo esc_attr( (string) $alert->id ); ?>">
						<?php wp_nonce_field( 'th_alert_delete_' . $alert->id ); ?>
						<?php
						th_component(
							'button',
							array(
								'variant'    => 'destructive',
								'size'       => 'sm',
								'icon'       => 'trash',
								'icon_only'  => true,
								'type'       => 'submit',
								/* translators: %s: saved search name */
								'aria_label' => sprintf( __( 'Delete “%s”', 'torrehub' ), $alert->label ),
							)
						);
						?>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
