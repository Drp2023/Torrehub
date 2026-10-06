<?php
/**
 * Details tiles ("Service details", "Car specifications"…) + links (apply link, floor plan) + video.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

$view  = $args['view'];
$tiles = $view->details();
$root  = $view->root ? html_entity_decode( $view->root->name, ENT_QUOTES ) : '';
?>
<section class="th-listing-card" id="details" aria-labelledby="details-title">
	<h2 class="th-listing-card__title" id="details-title">
		<?php
		/* translators: %s: category name */
		echo esc_html( $root ? sprintf( __( '%s details', 'torrehub' ), $root ) : __( 'Details', 'torrehub' ) );
		?>
	</h2>
	<?php if ( $tiles ) : ?>
		<dl class="th-details">
			<?php foreach ( $tiles as $tile ) : ?>
				<div class="
				<?php
				echo esc_attr(
					th_classes(
						'th-detail',
						array(
							'th-detail--yes'   => 'green' === $tile['tone'],
							'th-detail--plain' => 'grey' === $tile['tone'],
						)
					)
				);
				?>
							">
					<dt><?php echo esc_html( $tile['label'] ); ?></dt>
					<dd><?php echo esc_html( $tile['value'] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<?php if ( $view->fields['links'] || $view->website ) : ?>
		<ul class="th-cluster th-listing__links" role="list">
			<?php foreach ( $view->fields['links'] as $doc_link ) : ?>
				<li>
					<?php
					th_component(
						'button',
						array(
							'label'   => $doc_link['label'],
							'variant' => 'soft',
							'size'    => 'sm',
							'icon'    => 'file' === $doc_link['kind'] ? 'file' : 'external',
							'href'    => $doc_link['url'],
							'attrs'   => array(
								'rel'    => 'nofollow noopener',
								'target' => '_blank',
							),
						)
					);
					?>
				</li>
			<?php endforeach; ?>
			<?php if ( $view->website ) : ?>
				<li>
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Website', 'torrehub' ),
							'variant' => 'soft',
							'size'    => 'sm',
							'icon'    => 'globe',
							'href'    => $view->website,
							'attrs'   => array(
								'rel'    => 'nofollow noopener',
								'target' => '_blank',
							),
						)
					);
					?>
				</li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>

	<?php foreach ( $view->videos as $video ) : ?>
		<?php
		// Privacy: nothing loads from YouTube until the visitor presses play (listing.js swaps in a nocookie embed).
		$yt = preg_match( '~(?:youtu\.be/|v=|embed/|shorts/)([\w-]{11})~', $video, $m ) ? $m[1] : '';
		?>
		<div class="th-video">
			<?php if ( $yt ) : ?>
				<button type="button" class="th-video__play" data-th-video="<?php echo esc_attr( $yt ); ?>">
					<?php th_icon( 'chevron-right', array( 'size' => 28 ) ); ?>
					<span><?php esc_html_e( 'Play video', 'torrehub' ); ?></span>
					<small><?php esc_html_e( 'Loads from YouTube', 'torrehub' ); ?></small>
				</button>
			<?php else : ?>
				<a href="<?php echo esc_url( $video ); ?>" rel="nofollow noopener" target="_blank"><?php esc_html_e( 'Watch the video', 'torrehub' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</section>
