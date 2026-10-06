<?php
/**
 * Reviews: summary (average, word, distribution), the two newest reviews, "Show all N" (a <details>, no JS needed),
 * and "Write a review" (dialog; without JS ?th_review_form=1 renders it in place).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Reviews\Module as Reviews;

$view    = $args['view'];
$summary = $view->reviews;
$reviews = Reviews::reviews( $view->id );
$blocked = Reviews::blocked_reason( $view->id );
$visible = array_slice( $reviews, 0, 2 );
$rest    = array_slice( $reviews, 2 );

$render = static function ( WP_Comment $review ) {
	$rating = (int) get_comment_meta( (int) $review->comment_ID, 'rating', true );
	$title  = (string) get_comment_meta( (int) $review->comment_ID, 'th_review_title', true );
	$author = $review->comment_author ? $review->comment_author : __( 'Anonymous', 'torrehub' );
	?>
	<article class="th-review">
		<header class="th-review__head">
			<?php echo th_get_avatar( (int) $review->user_id, 36, $author ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
			<div class="th-review__who">
				<p class="th-review__author"><?php echo esc_html( $author ); ?></p>
				<p class="th-review__date"><time datetime="<?php echo esc_attr( mysql2date( 'c', $review->comment_date_gmt ) ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $review->comment_date ) ); ?></time></p>
			</div>
			<?php
			if ( $rating ) {
				th_component(
					'rating',
					array(
						'average'    => $rating,
						'show_count' => false,
					)
				);
			}
			?>
		</header>
		<?php if ( $title ) : ?>
			<h3 class="th-review__title"><?php echo esc_html( $title ); ?></h3>
		<?php endif; ?>
		<div class="th-review__text"><?php echo wp_kses_post( wpautop( esc_html( $review->comment_content ) ) ); ?></div>
	</article>
	<?php
};
?>
<section class="th-listing-card th-listing-reviews" id="reviews" aria-labelledby="reviews-title">
	<div class="th-listing-card__head">
		<h2 class="th-listing-card__title" id="reviews-title"><?php esc_html_e( 'Reviews', 'torrehub' ); ?></h2>
		<?php
		if ( 'login' === $blocked ) {
			th_component(
				'button',
				array(
					'label'   => __( 'Write a review', 'torrehub' ),
					'variant' => 'ink',
					'size'    => 'sm',
					'href'    => th_url_login( (string) get_permalink( $view->id ) . '#reviews' ),
				)
			);
		} elseif ( '' === $blocked && $view->is_public() ) {
			th_component(
				'button',
				array(
					'label'   => __( 'Write a review', 'torrehub' ),
					'variant' => 'ink',
					'size'    => 'sm',
					'href'    => add_query_arg( 'th_review_form', '1' ) . '#th-review-dialog',
					'attrs'   => array(
						'data-th-dialog-open' => 'th-review-dialog',
						'aria-controls'       => 'th-review-dialog',
						'aria-haspopup'       => 'dialog',
					),
				)
			);
		}
		?>
	</div>

	<?php if ( $summary['count'] ) : ?>
		<div class="th-rating-summary th-listing-reviews__summary">
			<div class="th-stack" style="--th-stack-gap:8px">
				<p class="th-rating-summary__score"><?php echo esc_html( number_format_i18n( $summary['average'], 1 ) ); ?></p>
				<?php
				th_component(
					'rating',
					array(
						'average'    => $summary['average'],
						'label'      => Reviews::word( $summary['average'] ),
						'show_count' => false,
					)
				);
				?>
				<p class="th-listing-reviews__count">
					<?php
					/* translators: %s: number of reviews */
					echo esc_html( sprintf( _n( '%s review', '%s reviews', $summary['count'], 'torrehub' ), number_format_i18n( $summary['count'] ) ) );
					?>
				</p>
			</div>
			<ul class="th-rating-bars" role="list">
				<?php foreach ( $summary['bars'] as $star => $n ) : ?>
					<li>
						<span aria-hidden="true"><?php echo esc_html( (string) $star ); ?></span>
						<span class="th-rating-bars__track" aria-hidden="true"><span class="th-rating-bars__fill" style="--pct:<?php echo esc_attr( (string) round( 100 * $n / max( 1, $summary['count'] ) ) ); ?>%"></span></span>
						<span aria-hidden="true"><?php echo esc_html( number_format_i18n( $n ) ); ?></span>
						<span class="th-sr-only">
							<?php
							/* translators: 1: stars, 2: number of reviews */
							echo esc_html( sprintf( _n( '%1$s stars: %2$s review', '%1$s stars: %2$s reviews', $n, 'torrehub' ), $star, number_format_i18n( $n ) ) );
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="th-listing-reviews__list">
			<?php array_walk( $visible, $render ); ?>
		</div>
		<?php if ( $rest ) : ?>
			<details class="th-listing-reviews__more">
				<summary class="th-btn th-btn--outline">
					<?php
					/* translators: %s: number of reviews */
					echo esc_html( sprintf( __( 'Show all %s reviews', 'torrehub' ), number_format_i18n( count( $reviews ) ) ) );
					?>
				</summary>
				<div class="th-listing-reviews__list"><?php array_walk( $rest, $render ); ?></div>
			</details>
		<?php endif; ?>
	<?php else : ?>
		<p class="th-listing-reviews__none"><?php esc_html_e( 'No reviews yet. Have you used this? Be the first to review it.', 'torrehub' ); ?></p>
	<?php endif; ?>

	<?php if ( 'done' === $blocked ) : ?>
		<p class="th-listing-reviews__none"><?php esc_html_e( 'You have reviewed this listing — thank you.', 'torrehub' ); ?></p>
	<?php endif; ?>
</section>

<?php
if ( '' === $blocked && $view->is_public() ) {
	get_template_part( 'template-parts/listing/review-form', null, $args );
}
