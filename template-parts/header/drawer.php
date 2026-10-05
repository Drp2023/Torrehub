<?php
/**
 * Mobile drawer (G-09): CTAs, town block, 10 categories, languages.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$th_town = th_current_town();
$top     = th_has_rtcl() ? Directory::top_towns( 3 ) : array();
$tree    = th_has_rtcl() ? Directory::category_tree() : array();
$cat_map = th_category_map();
?>
<dialog class="th-dialog th-dialog--drawer th-drawer" id="th-drawer" data-th-dialog aria-labelledby="th-drawer-title">
	<div class="th-dialog__head">
		<h2 class="th-sr-only" id="th-drawer-title"><?php esc_html_e( 'Menu', 'torrehub' ); ?></h2>
		<?php get_template_part( 'template-parts/header/logo' ); ?>
		<?php
		th_component(
			'button',
			array(
				'variant'    => 'white',
				'icon'       => 'close',
				'icon_only'  => true,
				'aria_label' => __( 'Close menu', 'torrehub' ),
				'attrs'      => array( 'data-th-dialog-close' => '' ),
			)
		);
		?>
	</div>
	<div class="th-dialog__body th-stack th-drawer__body">
		<div class="th-drawer__ctas">
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Post a listing', 'torrehub' ),
					'variant' => 'accent',
					'size'    => 'lg',
					'href'    => th_url_post_listing(),
				)
			);
			th_component(
				'button',
				array(
					'label'   => is_user_logged_in() ? __( 'My account', 'torrehub' ) : __( 'Log in', 'torrehub' ),
					'variant' => 'outline',
					'size'    => 'lg',
					'href'    => th_url_account(),
				)
			);
			?>
		</div>

		<?php if ( $th_town ) : ?>
			<section class="th-drawer__town" aria-labelledby="th-drawer-town">
				<div class="th-drawer__town-head">
					<p id="th-drawer-town"><?php th_icon( 'map-pin-dot', array( 'size' => 18 ) ); ?> <?php echo esc_html( $th_town['name'] ); ?></p>
					<button type="button" class="th-link-btn" data-th-dialog-open="th-location" aria-controls="th-location" aria-haspopup="dialog"><?php esc_html_e( 'Change', 'torrehub' ); ?></button>
				</div>
				<button type="button" class="th-location__locate" data-th-locate>
					<?php th_icon( 'locate', array( 'size' => 18 ) ); ?>
					<span><?php esc_html_e( 'Use my current location', 'torrehub' ); ?></span>
				</button>
				<ul class="th-cluster" role="list" style="--th-cluster-gap:6px">
					<?php foreach ( $top as $town ) : ?>
						<li>
							<a class="th-chip th-chip--town" href="<?php echo esc_url( add_query_arg( 'th_town', $town['slug'] ) ); ?>" data-th-town="<?php echo esc_attr( $town['slug'] ); ?>"<?php echo $town['slug'] === $th_town['slug'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $town['name'] ); ?></a>
						</li>
					<?php endforeach; ?>
					<li>
						<button type="button" class="th-chip th-chip--town" data-th-dialog-open="th-location" aria-controls="th-location" aria-haspopup="dialog">
							<?php
							/* translators: %s: number of towns */
							echo esc_html( sprintf( __( 'All %s towns', 'torrehub' ), number_format_i18n( Directory::stats()['towns'] ) ) );
							?>
						</button>
					</li>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( $tree ) : ?>
			<nav aria-labelledby="th-drawer-explore">
				<p class="th-micro" id="th-drawer-explore"><?php esc_html_e( 'Explore', 'torrehub' ); ?></p>
				<ul class="th-stack" role="list" style="--th-stack-gap:8px">
					<?php foreach ( $tree as $root ) : ?>
						<li>
							<?php
							th_component(
								'cat-tile',
								array(
									'layout' => 'row',
									'name'   => $root['name'],
									'url'    => $root['url'],
									'icon'   => $cat_map[ $root['slug'] ]['icon'] ?? 'info',
									'tint'   => $cat_map[ $root['slug'] ]['tint'] ?? 'grey',
								)
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

		<a class="th-drawer__link" href="<?php echo esc_url( th_url_guides() ); ?>"><?php esc_html_e( 'Guides', 'torrehub' ); ?> <?php th_icon( 'chevron-right', array( 'size' => 15 ) ); ?></a>

		<?php if ( count( th_get_languages() ) > 1 ) : ?>
			<section aria-labelledby="th-drawer-lang">
				<p class="th-micro" id="th-drawer-lang"><?php esc_html_e( 'Language', 'torrehub' ); ?></p>
				<?php get_template_part( 'template-parts/header/language-switcher', null, array( 'variant' => 'chips' ) ); ?>
			</section>
		<?php endif; ?>
	</div>
</dialog>
