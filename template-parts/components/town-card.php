<?php
/**
 * Town card: photo with readability gradient, name and listing count.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string     $name
 *     @type string     $url
 *     @type int|string $image  Attachment ID or URL.
 *     @type int        $count
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'name'  => '',
		'url'   => '',
		'image' => '',
		'count' => null,
	)
);
?>
<a class="th-town" href="<?php echo esc_url( $a['url'] ); ?>">
	<?php
	if ( is_numeric( $a['image'] ) && (int) $a['image'] > 0 ) {
		echo wp_get_attachment_image(
			(int) $a['image'],
			'th-town',
			false,
			array(
				'alt'     => '',
				'loading' => 'lazy',
				'sizes'   => '(min-width: 1200px) 266px, (min-width: 600px) 33vw, 50vw',
			)
		);
	} elseif ( $a['image'] ) {
		printf( '<img src="%s" alt="" loading="lazy" width="532" height="400">', esc_url( (string) $a['image'] ) );
	}
	?>
	<span class="th-town__text">
		<span class="th-town__name"><?php echo esc_html( $a['name'] ); ?></span>
		<?php if ( null !== $a['count'] ) : ?>
			<span class="th-town__count">
				<?php
				/* translators: %s: number of listings */
				echo esc_html( sprintf( _n( '%s listing', '%s listings', (int) $a['count'], 'torrehub' ), number_format_i18n( (int) $a['count'] ) ) );
				?>
			</span>
		<?php endif; ?>
	</span>
</a>
