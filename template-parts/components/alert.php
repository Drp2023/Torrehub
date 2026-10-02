<?php
/**
 * Inline alert / notice.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string $variant   info|success|error|attention|neutral|amber. Default 'info'.
 *     @type string $title
 *     @type string $text      Plain text (escaped).
 *     @type string $html      Trusted HTML body (passed through wp_kses_post).
 *     @type string $icon      Override icon.
 *     @type array  $actions   List of button component args.
 *     @type string $aria_role      'alert' for errors that must interrupt, 'status' for polite updates. Default: alert for error, else none.
 *     @type string $id        For aria-describedby targets.
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'variant' => 'info',
		'title'   => '',
		'text'    => '',
		'html'    => '',
		'icon'    => '',
		'actions' => array(),
		'role'    => null,
		'id'      => '',
	)
);

$icons     = array(
	'info'      => 'info',
	'success'   => 'check',
	'error'     => 'alert-circle',
	'attention' => 'clock',
	'neutral'   => 'info',
	'amber'     => 'info',
);
$icon      = $a['icon'] ? $a['icon'] : ( $icons[ $a['variant'] ] ?? 'info' );
$aria_role = $a['role'] ?? ( 'error' === $a['variant'] ? 'alert' : null );
?>
<?php
$alert_attrs = array(
	'class' => th_classes( 'th-alert', 'th-alert--' . $a['variant'] ),
	'role'  => $aria_role,
	'id'    => $a['id'] ? $a['id'] : null,
);
?>
<div<?php echo th_attrs( $alert_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- th_attrs() escapes every value. ?>>
	<?php th_icon( $icon, array( 'size' => 20 ) ); ?>
	<div>
		<?php if ( $a['title'] ) : ?>
			<strong class="th-alert__title"><?php echo esc_html( $a['title'] ); ?></strong>
		<?php endif; ?>
		<?php if ( $a['text'] ) : ?>
			<p><?php echo esc_html( $a['text'] ); ?></p>
		<?php endif; ?>
		<?php if ( $a['html'] ) : ?>
			<?php echo wp_kses_post( $a['html'] ); ?>
		<?php endif; ?>
		<?php if ( $a['actions'] ) : ?>
			<div class="th-alert__actions">
				<?php
				foreach ( $a['actions'] as $btn_args ) {
					th_component( 'button', array_merge( array( 'size' => 'sm' ), (array) $btn_args ) );
				}
				?>
			</div>
		<?php endif; ?>
	</div>
</div>
