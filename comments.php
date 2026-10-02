<?php
/**
 * Comments (Guides). Listing reviews get their own template in phase 4 (Reviews module).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="th-comments th-stack" aria-labelledby="th-comments-title">
	<?php if ( have_comments() ) : ?>
		<h2 id="th-comments-title">
			<?php
			$th_count = get_comments_number();
			/* translators: %s: number of comments */
			echo esc_html( sprintf( _n( '%s comment', '%s comments', $th_count, 'torrehub' ), number_format_i18n( $th_count ) ) );
			?>
		</h2>
		<ol class="th-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'      => 'ol',
					'short_ping' => true,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php
	if ( comments_open() ) {
		comment_form( array( 'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">' ) );
	}
	?>
</section>
