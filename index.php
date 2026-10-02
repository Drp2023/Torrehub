<?php
/**
 * Fallback template.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="th-main th-container th-section" tabindex="-1">
	<?php if ( have_posts() ) : ?>
		<div class="th-stack">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<?php if ( is_singular() ) : ?>
						<h1><?php the_title(); ?></h1>
						<div class="th-prose"><?php the_content(); ?></div>
					<?php else : ?>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<?php the_excerpt(); ?>
					<?php endif; ?>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<?php
		th_component(
			'empty-state',
			array(
				'title' => __( 'Nothing here yet', 'torrehub' ),
				'text'  => __( 'Try a search or browse the categories.', 'torrehub' ),
			)
		);
		?>
	<?php endif; ?>
</main>
<?php
get_footer();
