<?php
/**
 * B-02 — a guide: topic chip, title, author row (share, print), feature image, article, "Mentioned in this guide";
 * aside: "In this guide" (h2 headings, scrollspy) and the "Need help with this?" card. Reading progress on mobile.
 * Called inside the loop.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Guides\Module as Guides;

$guide     = get_post();
$guide_cat = Guides::primary_category( $guide );
$minutes   = Guides::reading_time( $guide );
$toc       = Guides::toc( $guide );
$related   = th_has_rtcl() ? Guides::related( $guide->ID ) : array();
$author    = (int) $guide->post_author;
$thumb     = (int) get_post_thumbnail_id( $guide );
?>
<div class="th-reading-progress" data-th-reading-progress aria-hidden="true"><span></span></div>
<main id="main" class="th-main th-section th-guide" tabindex="-1">
	<div class="<?php echo esc_attr( 'th-container th-guide__layout' . ( count( $toc ) < 2 && ! $related ? ' is-solo' : '' ) ); ?>">
		<article class="th-guide__article" data-th-guide>
			<header class="th-guide__head">
				<?php if ( $guide_cat ) : ?>
					<a class="th-guide-chip" href="<?php echo esc_url( (string) get_category_link( $guide_cat ) ); ?>"><?php echo esc_html( html_entity_decode( $guide_cat->name, ENT_QUOTES ) ); ?></a>
				<?php endif; ?>
				<h1 class="th-guide__title"><?php echo esc_html( html_entity_decode( get_the_title(), ENT_QUOTES ) ); ?></h1>
				<div class="th-guide__byline">
					<?php echo th_get_avatar( $author, 36 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
					<p>
						<strong><?php echo esc_html( get_the_author_meta( 'display_name', $author ) ); ?></strong>
						<span>
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
							·
							<?php
							/* translators: %d: minutes */
							echo esc_html( sprintf( _n( '%d min read', '%d min read', $minutes, 'torrehub' ), $minutes ) );
							?>
						</span>
					</p>
					<span class="th-guide__tools">
						<button type="button" class="th-btn th-btn--icon th-btn--sm th-btn--neutral" data-th-share data-title="<?php echo esc_attr( get_the_title() ); ?>" data-url="<?php echo esc_url( (string) get_permalink() ); ?>" hidden>
							<?php th_icon( 'share', array( 'size' => 16 ) ); ?><span class="th-sr-only"><?php esc_html_e( 'Share', 'torrehub' ); ?></span>
						</button>
						<button type="button" class="th-btn th-btn--icon th-btn--sm th-btn--neutral" data-th-print>
							<?php th_icon( 'print', array( 'size' => 16 ) ); ?><span class="th-sr-only"><?php esc_html_e( 'Print', 'torrehub' ); ?></span>
						</button>
					</span>
				</div>
			</header>

			<?php if ( $thumb ) : ?>
				<figure class="th-guide__image">
					<?php
					echo wp_get_attachment_image(
						$thumb,
						'large',
						false,
						array(
							'alt'           => '',
							'fetchpriority' => 'high',
							'loading'       => 'eager',
							'sizes'         => '(max-width: 899px) 100vw, 760px',
						)
					);
					?>
				</figure>
			<?php endif; ?>

			<div class="th-prose th-guide__body">
				<?php the_content(); ?>
			</div>

			<?php if ( $related ) : ?>
				<section class="th-guide__mentioned" aria-labelledby="th-guide-mentioned">
					<h2 class="th-guide__eyebrow" id="th-guide-mentioned"><?php esc_html_e( 'Mentioned in this guide', 'torrehub' ); ?></h2>
					<ul role="list">
						<?php foreach ( $related as $i => $rel_term ) : ?>
							<?php
							$meta  = th_category_meta( $rel_term );
							$count = Guides::listing_count( $rel_term );
							?>
							<li>
								<a class="<?php echo esc_attr( 'th-guide-mention th-tint--' . ( $meta['tint'] ?? 'blue' ) ); ?>" href="<?php echo esc_url( (string) get_term_link( $rel_term ) ); ?>">
									<span class="th-icon-circle"><?php th_icon( (string) ( $meta['icon'] ?? 'info' ), array( 'size' => 18 ) ); ?></span>
									<span class="th-guide-mention__name"><?php echo esc_html( html_entity_decode( $rel_term->name, ENT_QUOTES ) ); ?></span>
									<span class="th-guide-mention__count">
										<?php
										echo esc_html(
											$count
												/* translators: %s: number of listings */
												? sprintf( _n( '%s listing', '%s listings', $count, 'torrehub' ), number_format_i18n( $count ) )
												: __( 'See the category', 'torrehub' )
										);
										?>
										<span aria-hidden="true">→</span>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</article>

		<?php if ( count( $toc ) >= 2 || $related ) : ?>
		<aside class="th-guide__aside">
			<?php if ( count( $toc ) >= 2 ) : ?>
				<nav class="th-toc th-guide__toc" aria-labelledby="th-guide-toc" data-th-toc>
					<h2 class="th-guide__eyebrow" id="th-guide-toc"><?php esc_html_e( 'In this guide', 'torrehub' ); ?></h2>
					<ol role="list">
						<?php foreach ( $toc as $item ) : ?>
							<li><a href="<?php echo esc_attr( '#' . $item['id'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a></li>
						<?php endforeach; ?>
					</ol>
				</nav>
			<?php endif; ?>

			<?php if ( $related ) : ?>
				<?php
				$first = $related[0];
				$count = Guides::listing_count( $first );
				$name  = html_entity_decode( $first->name, ENT_QUOTES );
				?>
				<section class="th-guide-cta" aria-labelledby="th-guide-cta">
					<p class="th-guide-cta__eyebrow"><?php esc_html_e( 'Need help with this?', 'torrehub' ); ?></p>
					<h2 class="th-guide-cta__title" id="th-guide-cta">
						<?php
						/* translators: %s: listing category, e.g. "Gestoría & Tax Advisors" */
						echo esc_html( sprintf( __( 'Find %s near you', 'torrehub' ), $name ) );
						?>
					</h2>
					<?php if ( $count ) : ?>
						<p>
							<?php
							/* translators: %s: number of listings */
							echo esc_html( sprintf( _n( '%s listing on Torrehub.', '%s listings on Torrehub.', $count, 'torrehub' ), number_format_i18n( $count ) ) );
							?>
						</p>
					<?php endif; ?>
					<?php
					th_component(
						'button',
						array(
							/* translators: %s: listing category */
							'label'   => sprintf( __( 'Browse %s', 'torrehub' ), $name ),
							'variant' => 'white',
							'block'   => true,
							'href'    => (string) get_term_link( $first ),
						)
					);
					?>
				</section>
			<?php endif; ?>
		</aside>
		<?php endif; ?>
	</div>
</main>
