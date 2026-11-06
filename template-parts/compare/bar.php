<?php
/**
 * Compare bar: floating at the bottom while listings are selected — category and count, thumbs with remove, empty
 * slots up to 3, Clear, Compare. Plain links (work without JS); compare.js swaps the bar after each change.
 *
 * @package Torrehub
 *
 * @var array $args { @type int[] $ids, @type WP_Term|null $category }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Compare\Module as Compare;

$ids      = array_map( 'intval', (array) ( $args['ids'] ?? array() ) );
$category = $args['category'] ?? null;
$count    = count( $ids );
?>
<section class="th-compare-bar" data-th-compare-bar aria-labelledby="th-compare-bar-title">
	<div class="th-compare-bar__info">
		<h2 class="th-compare-bar__title" id="th-compare-bar-title"><?php esc_html_e( 'Compare listings', 'torrehub' ); ?></h2>
		<p class="th-compare-bar__meta">
			<?php
			echo esc_html(
				implode(
					' · ',
					array_filter(
						array(
							$category instanceof WP_Term ? html_entity_decode( $category->name, ENT_QUOTES ) : '',
							/* translators: 1: listings selected, 2: maximum */
							sprintf( __( '%1$d of %2$d', 'torrehub' ), $count, Compare::MAX ),
						)
					)
				)
			);
			?>
		</p>
	</div>
	<ul class="th-compare-bar__items" role="list">
		<?php foreach ( $ids as $listing_id ) : ?>
			<?php $listing_title = html_entity_decode( get_the_title( $listing_id ), ENT_QUOTES ); ?>
			<li class="th-compare-bar__item">
				<a class="th-compare-bar__thumb" href="<?php echo esc_url( (string) get_permalink( $listing_id ) ); ?>">
					<?php
					$thumb = Compare::image( $listing_id );
					if ( $thumb ) {
						echo wp_get_attachment_image( $thumb, 'th-thumb', false, array( 'alt' => '' ) );
					}
					?>
					<span class="th-sr-only"><?php echo esc_html( $listing_title ); ?></span>
				</a>
				<a class="th-compare-bar__remove" href="<?php echo esc_url( Compare::url( array( 'remove' => $listing_id ) ) ); ?>" data-th-compare-remove="<?php echo esc_attr( (string) $listing_id ); ?>" rel="nofollow">
					<?php th_icon( 'close', array( 'size' => 12 ) ); ?>
					<?php /* translators: %s: listing title */ ?>
					<span class="th-sr-only"><?php echo esc_html( sprintf( __( 'Remove %s from the comparison', 'torrehub' ), $listing_title ) ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
		<?php for ( $slot = $count; $slot < Compare::MAX; $slot++ ) : ?>
			<li class="th-compare-bar__slot" aria-hidden="true"></li>
		<?php endfor; ?>
	</ul>
	<div class="th-compare-bar__actions">
		<a class="th-btn th-btn--text th-btn--sm" href="<?php echo esc_url( Compare::url( array( 'clear' => 1 ) ) ); ?>" data-th-compare-clear rel="nofollow"><?php esc_html_e( 'Clear', 'torrehub' ); ?></a>
		<a class="th-btn th-btn--primary" href="<?php echo esc_url( Compare::url() ); ?>">
			<?php
			/* translators: %d: number of listings selected */
			echo esc_html( sprintf( __( 'Compare (%d)', 'torrehub' ), $count ) );
			?>
		</a>
	</div>
</section>
