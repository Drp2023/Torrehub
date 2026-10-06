<?php
/**
 * B-01 — Guides index (posts page, categories, tags): heading, category chips, featured guide, three cards, text
 * list, "Load more guides".
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Guides\Module as Guides;

global $wp_query;
$page_no     = max( 1, (int) get_query_var( 'paged' ) );
$topic       = is_category() || is_tag() ? get_queried_object() : null;
$featured    = ( ! $topic && 1 === $page_no ) ? Guides::featured() : null;
$guides_list = array_values( array_filter( (array) $wp_query->posts, static fn( $p ) => ! $featured || $p->ID !== $featured->ID ) );
$cards       = array_slice( $guides_list, 0, 3 );
$texts       = array_slice( $guides_list, 3 );
$total       = (int) wp_count_posts( 'post' )->publish;
$topic_cats  = get_categories(
	array(
		'hide_empty' => true,
		'exclude'    => array( (int) get_option( 'default_category' ) ),
	)
);
$next        = $page_no < (int) $wp_query->max_num_pages ? get_pagenum_link( $page_no + 1 ) : '';
?>
<div class="th-container th-guides">
	<header class="th-guides__head">
		<div>
			<p class="th-guides__eyebrow"><?php esc_html_e( 'Torrehub guides', 'torrehub' ); ?></p>
			<h1 class="th-guides__title">
				<?php echo esc_html( $topic instanceof WP_Term ? html_entity_decode( $topic->name, ENT_QUOTES ) : (string) th_mod( 'th_guides_title' ) ); ?>
			</h1>
		</div>
		<p class="th-guides__count">
			<?php
			/* translators: %s: number of guides */
			echo esc_html( sprintf( _n( '%s guide', '%s guides', $total, 'torrehub' ), number_format_i18n( $total ) ) );
			?>
		</p>
	</header>

	<?php if ( $topic_cats ) : ?>
		<nav class="th-guides__chips" aria-label="<?php esc_attr_e( 'Guide topics', 'torrehub' ); ?>">
			<a class="th-chip th-chip--topic" href="<?php echo esc_url( Guides::url() ); ?>"<?php echo $topic ? '' : ' aria-current="page"'; ?>><?php esc_html_e( 'All', 'torrehub' ); ?></a>
			<?php foreach ( $topic_cats as $topic_cat ) : ?>
				<a class="th-chip th-chip--topic" href="<?php echo esc_url( (string) get_category_link( $topic_cat ) ); ?>"<?php echo $topic && $topic->term_id === $topic_cat->term_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( html_entity_decode( $topic_cat->name, ENT_QUOTES ) ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( ! $featured && ! $guides_list ) : ?>
		<?php
		th_component(
			'empty-state',
			array(
				'icon'  => 'file',
				'title' => __( 'No guides here yet', 'torrehub' ),
				'text'  => __( 'New guides are added every week. Have a look at the other topics meanwhile.', 'torrehub' ),
			)
		);
		?>
	<?php else : ?>
		<?php if ( $featured ) : ?>
			<?php
			get_template_part(
				'template-parts/guides/card',
				null,
				array(
					'post'    => $featured,
					'variant' => 'featured',
					'eager'   => true,
				)
			);
			?>
		<?php endif; ?>

		<?php if ( $cards ) : ?>
			<ul class="th-guides__cards" role="list">
				<?php foreach ( $cards as $i => $guide ) : ?>
					<li>
						<?php
						get_template_part(
							'template-parts/guides/card',
							null,
							array(
								'post'    => $guide,
								'variant' => 'card',
								'eager'   => ! $featured && 0 === $i,
							)
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $texts ) : ?>
			<ul class="th-guides__texts" role="list">
				<?php foreach ( $texts as $guide ) : ?>
					<li>
						<?php
						get_template_part(
							'template-parts/guides/card',
							null,
							array(
								'post'    => $guide,
								'variant' => 'text',
							)
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $next || $page_no > 1 ) : ?>
			<div class="th-guides__more">
				<?php if ( $next ) : ?>
					<a class="th-btn th-btn--outline th-btn--lg" href="<?php echo esc_url( $next ); ?>"><?php esc_html_e( 'Load more guides', 'torrehub' ); ?></a>
				<?php endif; ?>
				<?php if ( $page_no > 1 ) : ?>
					<a class="th-btn th-btn--text" href="<?php echo esc_url( $topic instanceof WP_Term ? (string) get_term_link( $topic ) : Guides::url() ); ?>"><?php esc_html_e( 'Back to the newest', 'torrehub' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
