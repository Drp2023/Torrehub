<?php
/**
 * Description ("About this service"). Long texts are clamped on mobile with a Read more toggle (listing.js).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

$view    = $args['view'];
$content = trim( (string) $view->post->post_content );
if ( '' === $content ) {
	return;
}
?>
<section class="th-listing-card" id="about" aria-labelledby="about-title">
	<h2 class="th-listing-card__title" id="about-title"><?php esc_html_e( 'About this listing', 'torrehub' ); ?></h2>
	<div class="th-prose th-listing__about" data-th-clamp>
		<?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- post content, filtered like any post. ?>
	</div>
</section>
