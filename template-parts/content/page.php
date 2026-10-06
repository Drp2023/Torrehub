<?php
/**
 * Generic page / single (in the loop): title + content.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;
?>
<main id="main" class="th-main th-section th-page" tabindex="-1">
	<article class="th-container th-page__inner">
		<h1 class="th-page__title"><?php echo esc_html( html_entity_decode( get_the_title(), ENT_QUOTES ) ); ?></h1>
		<div class="th-prose">
			<?php the_content(); ?>
		</div>
	</article>
</main>
