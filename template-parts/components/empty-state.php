<?php
/**
 * Empty state (no results, empty lists).
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string $icon    Default 'search-empty'.
 *     @type string $title
 *     @type string $text
 *     @type array  $actions List of button component args.
 *     @type string $heading_level h2|h3. Default 'h2'.
 * }
 */

defined( 'ABSPATH' ) || exit;

$a     = wp_parse_args(
	$args ?? array(),
	array(
		'icon'          => 'search-empty',
		'title'         => '',
		'text'          => '',
		'actions'       => array(),
		'heading_level' => 'h2',
	)
);
$level = in_array( $a['heading_level'], array( 'h2', 'h3' ), true ) ? $a['heading_level'] : 'h2';
?>
<div class="th-empty">
	<span class="th-empty__icon"><?php th_icon( $a['icon'], array( 'size' => 28 ) ); ?></span>
	<?php if ( $a['title'] ) : ?>
		<<?php echo esc_html( $level ); ?> class="th-empty__title"><?php echo esc_html( $a['title'] ); ?></<?php echo esc_html( $level ); ?>>
	<?php endif; ?>
	<?php if ( $a['text'] ) : ?>
		<p><?php echo esc_html( $a['text'] ); ?></p>
	<?php endif; ?>
	<?php if ( $a['actions'] ) : ?>
		<div class="th-empty__actions">
			<?php
			foreach ( $a['actions'] as $btn_args ) {
				th_component( 'button', (array) $btn_args );
			}
			?>
		</div>
	<?php endif; ?>
</div>
