<?php
/**
 * Guide card (B-01): featured (blue hero), card (image + excerpt; a row on mobile) or text (title only).
 *
 * @package Torrehub
 *
 * @var array $args { @type WP_Post $post, @type string $variant featured|card|text, @type bool $eager }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Guides\Module as Guides;

$guide       = $args['post'];
$variant     = (string) ( $args['variant'] ?? 'card' );
$guide_cat   = Guides::primary_category( $guide );
$minutes     = Guides::reading_time( $guide );
$url         = (string) get_permalink( $guide );
$guide_title = html_entity_decode( get_the_title( $guide ), ENT_QUOTES );
$date        = get_the_date( 'featured' === $variant ? '' : 'j F', $guide );
/* translators: %d: minutes */
$read = sprintf( _n( '%d min read', '%d min read', $minutes, 'torrehub' ), $minutes );
/* translators: %d: minutes */
$short = sprintf( _n( '%d min', '%d min', $minutes, 'torrehub' ), $minutes );
$chip  = static function () use ( $guide_cat, $variant ): void {
	if ( $guide_cat ) {
		printf( '<span class="%1$s">%2$s</span>', esc_attr( 'th-guide-chip' . ( 'featured' === $variant ? ' th-guide-chip--white' : '' ) ), esc_html( html_entity_decode( $guide_cat->name, ENT_QUOTES ) ) );
	}
};
$image = static function ( string $size, string $css ) use ( $guide, $args ): void {
	$id = (int) get_post_thumbnail_id( $guide );
	echo '<span class="' . esc_attr( $css ) . '">';
	if ( $id ) {
		echo wp_get_attachment_image(
			$id,
			$size,
			false,
			array(
				'alt'           => '',
				'loading'       => empty( $args['eager'] ) ? 'lazy' : 'eager',
				'fetchpriority' => empty( $args['eager'] ) ? null : 'high',
				'sizes'         => 'th-card' === $size ? '(max-width: 599px) 120px, (max-width: 1199px) 45vw, 400px' : '(max-width: 899px) 100vw, 640px',
			)
		);
	} else {
		th_icon( 'image', array( 'size' => 24 ) );
	}
	echo '</span>';
};
?>
<?php if ( 'featured' === $variant ) : ?>
	<article class="th-guide-featured">
		<?php $image( 'large', 'th-guide-featured__media' ); ?>
		<span class="th-guide-featured__badge"><?php esc_html_e( 'Featured', 'torrehub' ); ?></span>
		<div class="th-guide-featured__body">
			<?php $chip(); ?>
			<h2 class="th-guide-featured__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $guide_title ); ?></a></h2>
			<?php if ( has_excerpt( $guide ) || '' !== trim( (string) $guide->post_content ) ) : ?>
				<p class="th-guide-featured__excerpt"><?php echo esc_html( wp_html_excerpt( get_the_excerpt( $guide ), 180, '…' ) ); ?></p>
			<?php endif; ?>
			<p class="th-guide-featured__meta">
				<?php echo th_get_avatar( (int) $guide->post_author, 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
				<span>
					<strong><?php echo esc_html( get_the_author_meta( 'display_name', (int) $guide->post_author ) ); ?></strong>
					<span><?php echo esc_html( $date . ' · ' . $read ); ?></span>
				</span>
			</p>
		</div>
	</article>
<?php elseif ( 'text' === $variant ) : ?>
	<article class="th-guide-text">
		<?php $chip(); ?>
		<h3 class="th-guide-text__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $guide_title ); ?></a></h3>
		<p class="th-guide-text__meta"><?php echo esc_html( $date . ' · ' . $short ); ?></p>
	</article>
<?php else : ?>
	<article class="th-guide-card">
		<?php $image( 'th-card', 'th-guide-card__media' ); ?>
		<div class="th-guide-card__body">
			<?php $chip(); ?>
			<h3 class="th-guide-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $guide_title ); ?></a></h3>
			<p class="th-guide-card__excerpt"><?php echo esc_html( wp_html_excerpt( get_the_excerpt( $guide ), 120, '…' ) ); ?></p>
			<p class="th-guide-card__meta"><?php echo esc_html( $date . ' · ' . $short ); ?></p>
		</div>
	</article>
<?php endif; ?>
