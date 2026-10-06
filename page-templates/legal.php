<?php
/**
 * Template Name: Legal (T-04 / T-05)
 *
 * Privacy policy, terms, legal notice: "Last updated", numbered contents from the h2 headings (sticky on desktop),
 * the text.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
$toc = \Torrehub\Modules\Content\Module::toc( get_post() );
?>
<main id="main" class="th-main th-section th-legal" tabindex="-1">
	<div class="th-container">
		<header class="th-legal__head">
			<h1 class="th-page__title"><?php echo esc_html( html_entity_decode( get_the_title(), ENT_QUOTES ) ); ?></h1>
			<p class="th-legal__updated">
				<?php
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'Last updated %s', 'torrehub' ), get_the_modified_date() ) );
				?>
			</p>
		</header>
		<div class="<?php echo esc_attr( 'th-legal__layout' . ( count( $toc ) >= 2 ? ' is-with-toc' : '' ) ); ?>">
			<?php if ( count( $toc ) >= 2 ) : ?>
				<?php $numbered = ! array_filter( $toc, static fn( $t ) => preg_match( '/^\d/', $t['text'] ) ); ?>
				<nav class="<?php echo esc_attr( 'th-toc th-legal__toc' . ( $numbered ? ' th-toc--numbered' : '' ) ); ?>" aria-label="<?php esc_attr_e( 'Contents', 'torrehub' ); ?>" data-th-toc>
					<ol role="list">
						<?php foreach ( $toc as $item ) : ?>
							<li><a href="<?php echo esc_attr( '#' . $item['id'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a></li>
						<?php endforeach; ?>
					</ol>
				</nav>
			<?php endif; ?>
			<div class="th-prose th-legal__body">
				<?php the_content(); ?>
			</div>
		</div>
	</div>
</main>
<?php
get_footer();
