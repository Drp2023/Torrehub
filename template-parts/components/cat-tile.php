<?php
/**
 * Category tile (root categories) or row (drawer / mega panel).
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string $name
 *     @type string $url
 *     @type string $icon    Icon name (cat-*).
 *     @type string $tint    blue|clay|grey|amber|green.
 *     @type string $meta    "48 listings · 37 categories".
 *     @type string $layout  tile|row. Default 'tile'.
 * }
 */

defined( 'ABSPATH' ) || exit;

$a    = wp_parse_args(
	$args ?? array(),
	array(
		'name'   => '',
		'url'    => '',
		'icon'   => 'cat-services',
		'tint'   => 'grey',
		'meta'   => '',
		'layout' => 'tile',
	)
);
$tint = in_array( $a['tint'], array( 'blue', 'clay', 'grey', 'amber', 'green' ), true ) ? $a['tint'] : 'grey';

if ( 'row' === $a['layout'] ) : ?>
	<a class="<?php echo esc_attr( th_classes( 'th-cat-row', 'th-tint--' . $tint ) ); ?>" href="<?php echo esc_url( $a['url'] ); ?>">
		<?php th_icon( $a['icon'], array( 'size' => 20 ) ); ?>
		<span><?php echo esc_html( $a['name'] ); ?></span>
		<?php th_icon( 'chevron-right', array( 'size' => 15 ) ); ?>
	</a>
<?php else : ?>
	<a class="<?php echo esc_attr( th_classes( 'th-cat-tile', 'th-tint--' . $tint ) ); ?>" href="<?php echo esc_url( $a['url'] ); ?>">
		<?php th_icon( $a['icon'], array( 'size' => 26 ) ); ?>
		<span class="th-cat-tile__name"><?php echo esc_html( $a['name'] ); ?></span>
		<?php if ( $a['meta'] ) : ?>
			<span class="th-cat-tile__meta"><?php echo esc_html( $a['meta'] ); ?></span>
		<?php endif; ?>
	</a>
	<?php
endif;
