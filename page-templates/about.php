<?php
/**
 * Template Name: About (T-01)
 *
 * Blue hero (title, excerpt as the intro, live numbers: categories, towns, verified sellers, languages), then the
 * page content in a card next to the featured image.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$stats = th_has_rtcl() ? \Torrehub\Data\Directory::stats() : array();
$tiles = array_filter(
	array(
		array( $stats['categories'] ?? 0, __( 'Categories', 'torrehub' ) ),
		array( $stats['towns'] ?? 0, __( 'Towns', 'torrehub' ) ),
		array( $stats['verified'] ?? 0, __( 'Verified', 'torrehub' ) ),
		array( count( th_get_languages() ), __( 'Languages', 'torrehub' ) ),
	),
	static fn( $t ) => $t[0] > 0
);
$thumb = (int) get_post_thumbnail_id();
?>
<main id="main" class="th-main th-section th-about" tabindex="-1">
	<div class="th-container th-stack" style="--th-stack-gap:var(--th-s-4)">
		<section class="th-about__hero" aria-labelledby="th-about-title">
			<h1 class="th-about__title" id="th-about-title"><?php echo esc_html( html_entity_decode( get_the_title(), ENT_QUOTES ) ); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="th-about__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
			<?php if ( $tiles ) : ?>
				<ul class="th-about__stats" role="list">
					<?php foreach ( $tiles as $tile ) : ?>
						<li><strong><?php echo esc_html( number_format_i18n( (int) $tile[0] ) ); ?></strong><span><?php echo esc_html( $tile[1] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<div class="<?php echo esc_attr( 'th-about__body' . ( $thumb ? ' is-with-image' : '' ) ); ?>">
			<div class="th-about__card th-prose">
				<?php the_content(); ?>
			</div>
			<?php if ( $thumb ) : ?>
				<figure class="th-about__image">
					<?php echo wp_get_attachment_image( $thumb, 'large', false, array( 'alt' => '', 'sizes' => '(max-width: 899px) 100vw, 640px' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>
				</figure>
			<?php endif; ?>
		</div>
	</div>
</main>
<?php
get_footer();
