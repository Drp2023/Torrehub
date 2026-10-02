<?php
/**
 * Listing card (data-driven; phase 3 adds th_listing_card_args( $listing ) to map RTCL listings).
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string     $variant      default|featured|urgent|expired|row|map. Default 'default'.
 *     @type string     $title
 *     @type string     $url
 *     @type int|string $image        Attachment ID or image URL (empty → neutral placeholder).
 *     @type string     $image_alt    Defaults to ''. Decorative: the title link names the card.
 *     @type string     $image_size   Default 'th-card'.
 *     @type bool       $eager        LCP image: no lazy-loading, fetchpriority=high.
 *     @type string     $price        Formatted price text ("€12,450").
 *     @type string     $price_suffix "/ hour", "/ month".
 *     @type array      $badges       List of badge component args.
 *     @type array      $attrs        List of short attribute strings (chips).
 *     @type string     $location
 *     @type string     $age          "2 days ago" (human_time_diff).
 *     @type string     $meta_end     Overrides the right meta slot (e.g. "Ends in 2 days", "Listing ended 4 days ago").
 *     @type bool       $verified     Shows "✓ Verified" in the meta row.
 *     @type float      $rating
 *     @type int        $rating_count
 *     @type int        $photo_count  Shows "5 photos" chip when > 1.
 *     @type bool       $fav          Show the favourite button.
 *     @type bool       $fav_pressed
 *     @type int        $listing_id   For the favourite button (data attribute).
 *     @type array      $actions      List of button component args (archive card: Contact + chat).
 *     @type string     $heading      h2|h3. Default 'h3'.
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'variant'      => 'default',
		'title'        => '',
		'url'          => '',
		'image'        => '',
		'image_alt'    => '',
		'image_size'   => 'th-card',
		'eager'        => false,
		'price'        => '',
		'price_suffix' => '',
		'badges'       => array(),
		'attrs'        => array(),
		'location'     => '',
		'age'          => '',
		'meta_end'     => '',
		'verified'     => false,
		'rating'       => 0,
		'rating_count' => 0,
		'photo_count'  => 0,
		'fav'          => false,
		'fav_pressed'  => false,
		'listing_id'   => 0,
		'actions'      => array(),
		'heading'      => 'h3',
	)
);

$variant = in_array( $a['variant'], array( 'default', 'featured', 'urgent', 'expired', 'row', 'map' ), true ) ? $a['variant'] : 'default';
$heading = 'h2' === $a['heading'] ? 'h2' : 'h3';

$img_attrs = array(
	'class'    => 'th-card__img',
	'alt'      => $a['image_alt'],
	'loading'  => $a['eager'] ? 'eager' : 'lazy',
	'decoding' => 'async',
	'sizes'    => 'row' === $variant ? '(min-width: 900px) 240px, 110px' : '(min-width: 1200px) 440px, (min-width: 600px) 50vw, 100vw',
);
if ( $a['eager'] ) {
	$img_attrs['fetchpriority'] = 'high';
}
?>
<article class="<?php echo esc_attr( th_classes( 'th-card', 'default' !== $variant ? 'th-card--' . $variant : '' ) ); ?>">
	<div class="th-card__media">
		<?php
		if ( is_numeric( $a['image'] ) && (int) $a['image'] > 0 ) {
			echo wp_get_attachment_image( (int) $a['image'], $a['image_size'], false, $img_attrs );
		} elseif ( is_string( $a['image'] ) && '' !== $a['image'] ) {
			printf(
				'<img src="%s" alt="%s" loading="%s" decoding="async" width="640" height="480">',
				esc_url( $a['image'] ),
				esc_attr( $a['image_alt'] ),
				esc_attr( $img_attrs['loading'] )
			);
		}
		?>

		<?php if ( $a['badges'] ) : ?>
			<div class="th-card__badges">
				<?php
				foreach ( $a['badges'] as $badge ) {
					th_component( 'badge', (array) $badge );
				}
				?>
			</div>
		<?php endif; ?>

		<?php if ( (int) $a['photo_count'] > 1 ) : ?>
			<span class="th-card__photos">
				<?php
				th_component(
					'badge',
					array(
						'variant' => 'on-photo',
						/* translators: %s: number of photos */
						'label'   => sprintf( _n( '%s photo', '%s photos', (int) $a['photo_count'], 'torrehub' ), number_format_i18n( (int) $a['photo_count'] ) ),
					)
				);
				?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( $a['fav'] ) : ?>
		<div class="th-card__fav">
			<button type="button" class="th-btn th-btn--icon th-btn--sm th-btn--white th-btn--fav" aria-pressed="<?php echo $a['fav_pressed'] ? 'true' : 'false'; ?>" data-th-fav="<?php echo esc_attr( (string) (int) $a['listing_id'] ); ?>">
				<?php th_icon( 'heart', array( 'size' => 18 ) ); ?>
				<?php th_icon( 'heart-filled', array( 'size' => 18 ) ); ?>
				<span class="th-sr-only">
					<?php
					/* translators: %s: listing title */
					echo esc_html( sprintf( __( 'Save %s', 'torrehub' ), $a['title'] ) );
					?>
				</span>
			</button>
		</div>
	<?php endif; ?>

	<div class="th-card__body">
		<?php if ( '' !== $a['price'] ) : ?>
			<p class="th-card__price">
				<?php echo esc_html( $a['price'] ); ?>
				<?php if ( $a['price_suffix'] ) : ?>
					<small><?php echo esc_html( $a['price_suffix'] ); ?></small>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<<?php echo esc_html( $heading ); ?> class="th-card__title">
			<a href="<?php echo esc_url( $a['url'] ); ?>"><?php echo esc_html( $a['title'] ); ?></a>
		</<?php echo esc_html( $heading ); ?>>

		<?php if ( $a['rating'] > 0 ) : ?>
			<?php
			th_component(
				'rating',
				array(
					'average' => $a['rating'],
					'count'   => $a['rating_count'],
				)
			);
			?>
		<?php endif; ?>

		<?php if ( $a['attrs'] ) : ?>
			<ul class="th-card__attrs" role="list">
				<?php foreach ( $a['attrs'] as $attr ) : ?>
					<li>
					<?php
					th_component(
						'chip',
						array(
							'label'   => (string) $attr,
							'variant' => 'attr',
						)
					);
					?>
						</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $a['location'] || $a['age'] || $a['meta_end'] || $a['verified'] ) : ?>
			<p class="th-card__meta">
				<span>
					<?php if ( $a['location'] ) : ?>
						<?php th_icon( 'map-pin', array( 'size' => 14 ) ); ?>
						<?php echo esc_html( $a['location'] ); ?>
					<?php endif; ?>
				</span>
				<span>
					<?php if ( $a['meta_end'] ) : ?>
						<span class="<?php echo esc_attr( 'urgent' === $variant ? 'th-card__ends' : '' ); ?>"><?php echo esc_html( $a['meta_end'] ); ?></span>
					<?php elseif ( $a['verified'] ) : ?>
						<span class="th-card__verified"><?php th_icon( 'check', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Verified', 'torrehub' ); ?></span>
					<?php else : ?>
						<?php echo esc_html( $a['age'] ); ?>
					<?php endif; ?>
				</span>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( $a['actions'] ) : ?>
		<div class="th-card__actions">
			<?php
			foreach ( $a['actions'] as $btn_args ) {
				th_component( 'button', (array) $btn_args );
			}
			?>
		</div>
	<?php endif; ?>
</article>
