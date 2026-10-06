<?php
/**
 * Template Name: FAQ (T-03)
 *
 * The page content's Details blocks are the questions (accordion); the search box hides the ones that don't match
 * (JS; without it the box is hidden and every question is listed). FAQPage JSON-LD: Content\Module::faq_schema().
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();
the_post();
?>
<main id="main" class="th-main th-section th-faq" tabindex="-1">
	<div class="th-container th-faq__inner">
		<h1 class="th-page__title"><?php echo esc_html( html_entity_decode( get_the_title(), ENT_QUOTES ) ); ?></h1>
		<?php if ( has_excerpt() ) : ?>
			<p class="th-faq__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php endif; ?>
		<noscript><style>.th-faq__search { display: none; }</style></noscript>
		<div class="th-search th-faq__search" data-th-faq-search>
			<?php th_icon( 'search', array( 'size' => 18, 'class' => 'th-search__icon' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound ?>
			<label class="th-sr-only" for="th-faq-q"><?php esc_html_e( 'Search the FAQ', 'torrehub' ); ?></label>
			<input class="th-search__input" id="th-faq-q" type="search" placeholder="<?php esc_attr_e( 'Search the FAQ', 'torrehub' ); ?>" autocomplete="off">
		</div>
		<p class="th-faq__none" data-th-faq-none hidden role="status"><?php esc_html_e( 'No question matches. Try another word, or contact us.', 'torrehub' ); ?></p>
		<div class="th-faq__list th-prose" data-th-faq>
			<?php the_content(); ?>
		</div>
	</div>
</main>
<?php
get_footer();
