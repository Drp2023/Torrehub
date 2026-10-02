<?php
/**
 * Section header: h2 + optional chip + "See all →" link.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string $title
 *     @type string $id        Heading id (for aria-labelledby on the section).
 *     @type string $chip      Green chip text ("Within 10 km").
 *     @type string $link      URL.
 *     @type string $link_text Default "See all".
 *     @type string $eyebrow   Micro label above the title.
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'title'     => '',
		'id'        => '',
		'chip'      => '',
		'link'      => '',
		'link_text' => '',
		'eyebrow'   => '',
	)
);
?>
<div class="th-section-head">
	<div class="th-stack" style="--th-stack-gap:6px">
		<?php if ( $a['eyebrow'] ) : ?>
			<p class="th-micro"><?php echo esc_html( $a['eyebrow'] ); ?></p>
		<?php endif; ?>
		<div class="th-section-head__title">
			<h2<?php echo $a['id'] ? ' id="' . esc_attr( $a['id'] ) . '"' : ''; ?>><?php echo esc_html( $a['title'] ); ?></h2>
			<?php if ( $a['chip'] ) : ?>
				<?php
				th_component(
					'badge',
					array(
						'label'   => $a['chip'],
						'variant' => 'success',
					)
				);
				?>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $a['link'] ) : ?>
		<a class="th-section-head__link" href="<?php echo esc_url( $a['link'] ); ?>">
			<?php echo esc_html( $a['link_text'] ? $a['link_text'] : __( 'See all', 'torrehub' ) ); ?>
			<?php th_icon( 'arrow-right', array( 'size' => 16 ) ); ?>
		</a>
	<?php endif; ?>
</div>
