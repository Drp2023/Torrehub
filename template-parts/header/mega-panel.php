<?php
/**
 * Explore mega panel (G-04): 10 roots with real counts → sub-category groups → "Popular" dark card.
 *
 * Roots are a vertical tablist; each tabpanel holds that root's sub-categories (level-2 as group headings with
 * their level-3 links, or one flat group when a root has no grandchildren). Hidden until the Explore button opens it.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$tree = Directory::category_tree();
if ( ! $tree ) {
	return;
}
$th_town  = th_current_town();
$town_arg = $th_town ? array( 'rtcl_location' => $th_town['slug'] ) : array();
?>
<div class="th-mega" id="th-mega" data-th-mega hidden>
	<div class="th-container th-mega__inner">
		<div class="th-mega__roots">
			<p class="th-micro">
				<?php
				/* translators: 1: number of root categories, 2: number of all categories */
				echo esc_html( sprintf( __( '%1$s roots → %2$s', 'torrehub' ), number_format_i18n( count( $tree ) ), number_format_i18n( Directory::stats()['categories'] ) ) );
				?>
			</p>
			<div role="tablist" aria-orientation="vertical" aria-label="<?php esc_attr_e( 'Categories', 'torrehub' ); ?>">
				<?php foreach ( $tree as $i => $root ) : ?>
					<button type="button" role="tab" class="th-mega__root" id="th-mega-tab-<?php echo esc_attr( $root['slug'] ); ?>"
						aria-controls="th-mega-panel-<?php echo esc_attr( $root['slug'] ); ?>"
						aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $i ? '0' : '-1'; ?>">
						<span><?php echo esc_html( $root['name'] ); ?></span>
						<span class="th-mega__count"><?php echo esc_html( number_format_i18n( $root['count'] ) ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<?php
		foreach ( $tree as $i => $root ) :
			$children = $root['children'];
			$has_deep = (bool) array_filter( $children, static fn( $c ) => ! empty( $c['children'] ) );
			// Groups: level-2 with children become headings; level-2 leaves are collected into one "More" group.
			$groups = array();
			$leaves = array();
			foreach ( $children as $child ) {
				if ( $has_deep && $child['children'] ) {
					$groups[] = array(
						'title' => $child['name'],
						'url'   => $child['url'],
						'links' => $child['children'],
					);
				} else {
					$leaves[] = $child;
				}
			}
			if ( $leaves ) {
				$groups[] = array(
					'title' => $has_deep ? __( 'More', 'torrehub' ) : __( 'Categories', 'torrehub' ),
					'url'   => '',
					'links' => $leaves,
				);
			}
			// Popular: sub-categories with the most listings in this root (all levels).
			$flat = array();
			$walk = static function ( array $nodes ) use ( &$walk, &$flat ) {
				foreach ( $nodes as $n ) {
					$flat[] = $n;
					$walk( $n['children'] );
				}
			};
			$walk( $children );
			usort( $flat, static fn( $a, $b ) => $b['count'] <=> $a['count'] );
			$popular = array_slice( array_filter( $flat, static fn( $n ) => $n['count'] > 0 ), 0, 4 );
			?>
			<section class="th-mega__panel" role="tabpanel" id="th-mega-panel-<?php echo esc_attr( $root['slug'] ); ?>" aria-labelledby="th-mega-tab-<?php echo esc_attr( $root['slug'] ); ?>"<?php echo 0 === $i ? '' : ' hidden'; ?>>
				<div class="th-mega__groups">
					<?php foreach ( $groups as $group ) : ?>
						<div class="th-mega__group">
							<h3 class="th-mega__group-title">
								<?php if ( $group['url'] ) : ?>
									<a href="<?php echo esc_url( add_query_arg( $town_arg, $group['url'] ) ); ?>"><?php echo esc_html( $group['title'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $group['title'] ); ?>
								<?php endif; ?>
							</h3>
							<ul role="list">
								<?php foreach ( $group['links'] as $sub_link ) : ?>
									<li><a href="<?php echo esc_url( add_query_arg( $town_arg, $sub_link['url'] ) ); ?>"><?php echo esc_html( $sub_link['name'] ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>

				<aside class="th-mega__popular" aria-label="<?php esc_attr_e( 'Popular', 'torrehub' ); ?>">
					<p class="th-module__eyebrow">
						<?php
						echo esc_html(
							$th_town
								/* translators: %s: town name */
								? sprintf( __( 'Popular near %s', 'torrehub' ), $th_town['name'] )
								: __( 'Popular', 'torrehub' )
						);
						?>
					</p>
					<?php if ( $popular ) : ?>
						<ul role="list">
							<?php foreach ( $popular as $pop_node ) : ?>
								<li><a href="<?php echo esc_url( add_query_arg( $town_arg, $pop_node['url'] ) ); ?>"><?php echo esc_html( $pop_node['name'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<a class="th-mega__all" href="<?php echo esc_url( add_query_arg( $town_arg, $root['url'] ) ); ?>">
						<?php
						echo esc_html(
							$root['count'] > 0
								/* translators: 1: number of listings, 2: category name */
								? sprintf( __( 'All %1$s in %2$s', 'torrehub' ), number_format_i18n( $root['count'] ), $root['name'] )
								/* translators: %s: category name */
								: sprintf( __( 'Browse %s', 'torrehub' ), $root['name'] )
						);
						?>
						<?php th_icon( 'arrow-right', array( 'size' => 16 ) ); ?>
					</a>
				</aside>
			</section>
		<?php endforeach; ?>
	</div>
</div>
