<?php
/**
 * Gallery: desktop = 1 large + 2 stacked (+N photos on the last); mobile = swipeable strip with "1 / 5".
 * Every photo is a link to the full image (works without JS); gallery.js opens them in a lightbox dialog.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Listing\Module as Listing;

$view   = $args['view'];
$images = $view->images;
$count  = count( $images );
?>
<section class="<?php echo esc_attr( th_classes( 'th-gallery', 'th-gallery--n' . min( 3, max( 1, $count ) ) ) ); ?>" aria-label="<?php esc_attr_e( 'Photos', 'torrehub' ); ?>" data-th-gallery>
	<?php if ( ! $count ) : ?>
		<div class="th-gallery__empty" aria-hidden="true"><?php th_icon( th_category_meta( $view->root ? $view->root->slug : '' )['icon'] ?? 'info', array( 'size' => 48 ) ); ?></div>
	<?php else : ?>
		<ul class="th-gallery__strip" role="list" data-th-gallery-strip>
			<?php
			foreach ( $images as $i => $image_id ) :
				$full = wp_get_attachment_image_src( $image_id, 'full' );
				$alt  = trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
				/* translators: 1: photo number, 2: total, 3: listing title */
				$label = sprintf( __( 'Photo %1$s of %2$s: %3$s', 'torrehub' ), number_format_i18n( $i + 1 ), number_format_i18n( $count ), $alt ? $alt : $view->title );
				?>
				<li class="th-gallery__item">
					<a class="th-gallery__link" href="<?php echo esc_url( $full ? $full[0] : '' ); ?>" data-th-gallery-index="<?php echo esc_attr( (string) $i ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
						<?php
						echo wp_get_attachment_image(
							$image_id,
							0 === $i ? 'th-gallery' : 'th-gallery-sm',
							false,
							array(
								'class'         => 'th-gallery__img',
								'alt'           => '',
								'loading'       => 0 === $i ? 'eager' : 'lazy',
								'fetchpriority' => 0 === $i ? 'high' : 'auto',
								'decoding'      => 'async',
								'sizes'         => 0 === $i ? Listing::GALLERY_SIZES : '(min-width: 900px) 30vw, 100vw',
							)
						);
						?>
						<?php if ( 2 === $i && $count > 3 ) : ?>
							<span class="th-gallery__more" aria-hidden="true">
								<?php
								/* translators: %s: number of further photos */
								echo esc_html( sprintf( _n( '+%s photo', '+%s photos', $count - 3, 'torrehub' ), number_format_i18n( $count - 3 ) ) );
								?>
							</span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $count > 1 ) : ?>
			<span class="th-gallery__counter" aria-hidden="true"><span data-th-gallery-current>1</span> / <?php echo esc_html( number_format_i18n( $count ) ); ?></span>
		<?php endif; ?>
	<?php endif; ?>
</section>

<?php if ( $count ) : ?>
	<dialog class="th-dialog th-lightbox" id="th-lightbox" aria-label="<?php esc_attr_e( 'Photo viewer', 'torrehub' ); ?>" data-th-dialog data-th-lightbox>
		<div class="th-lightbox__stage">
			<img class="th-lightbox__img" src="" alt="" data-th-lightbox-img>
		</div>
		<p class="th-lightbox__counter" aria-live="polite" data-th-lightbox-counter></p>
		<button type="button" class="th-btn th-btn--icon th-btn--white th-lightbox__close" data-th-dialog-close aria-label="<?php esc_attr_e( 'Close', 'torrehub' ); ?>"><?php th_icon( 'close', array( 'size' => 20 ) ); ?></button>
		<?php if ( $count > 1 ) : ?>
			<button type="button" class="th-btn th-btn--icon th-btn--white th-lightbox__prev" data-th-lightbox-step="-1" aria-label="<?php esc_attr_e( 'Previous photo', 'torrehub' ); ?>"><?php th_icon( 'chevron-left', array( 'size' => 22 ) ); ?></button>
			<button type="button" class="th-btn th-btn--icon th-btn--white th-lightbox__next" data-th-lightbox-step="1" aria-label="<?php esc_attr_e( 'Next photo', 'torrehub' ); ?>"><?php th_icon( 'chevron-right', array( 'size' => 22 ) ); ?></button>
		<?php endif; ?>
	</dialog>
<?php endif; ?>
