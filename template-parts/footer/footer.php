<?php
/**
 * Footer (G-11): brand blurb + social, 4 menu columns (with data-driven fallbacks), © line, languages.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fallback link lists, used until a menu is assigned to the location.
 *
 * @return array<string,array<int,array{0:string,1:string}>>
 */
$th_footer_fallbacks = static function (): array {
	$cat  = static function ( string $slug ): ?array {
		$t = get_term_by( 'slug', $slug, 'rtcl_category' );
		return $t ? array( html_entity_decode( $t->name, ENT_QUOTES ), (string) get_term_link( $t ) ) : null;
	};
	$page = static function ( string $path, string $label ): ?array {
		$p = get_page_by_path( $path );
		return ( $p && 'publish' === $p->post_status ) ? array( $label, (string) get_permalink( $p ) ) : null;
	};

	$account = array(
		array( is_user_logged_in() ? __( 'My account', 'torrehub' ) : __( 'Log in', 'torrehub' ), th_url_account() ),
		array( __( 'Post a listing', 'torrehub' ), th_url_post_listing() ),
	);
	if ( ! is_user_logged_in() && get_option( 'users_can_register' ) ) {
		array_splice( $account, 1, 0, array( array( __( 'Register', 'torrehub' ), wp_registration_url() ) ) );
	}

	return array(
		'footer-browse'   => array_values( array_filter( array_map( $cat, array( 'properties', 'auto-moto-boats', 'services', 'jobs', 'marketplace' ) ) ) ),
		'footer-discover' => array_values(
			array_filter(
				array_merge(
					array_map( $cat, array( 'restaurants-nightlife', 'events', 'leisure-sport', 'tourist-attractions' ) ),
					array( array( __( 'Guides', 'torrehub' ), th_url_guides() ) )
				)
			)
		),
		'footer-account'  => $account,
		'footer-torrehub' => array_values(
			array_filter(
				array(
					$page( 'about-us', __( 'About', 'torrehub' ) ),
					$page( 'contact', __( 'Contact', 'torrehub' ) ),
					$page( 'faq', __( 'FAQ', 'torrehub' ) ),
					$page( 'privacy-policy', __( 'Privacy Policy', 'torrehub' ) ),
					$page( 'terms-conditions', __( 'Terms & Conditions', 'torrehub' ) ),
				)
			)
		),
	);
};

$columns   = array(
	'footer-browse'   => __( 'Browse', 'torrehub' ),
	'footer-discover' => __( 'Discover', 'torrehub' ),
	'footer-account'  => __( 'Account', 'torrehub' ),
	'footer-torrehub' => __( 'Torrehub', 'torrehub' ),
);
$fallbacks = null;
$socials   = array_filter(
	array(
		'facebook'  => array( th_mod( 'th_social_facebook' ), 'facebook-filled', 'Facebook' ),
		'instagram' => array( th_mod( 'th_social_instagram' ), 'instagram-filled', 'Instagram' ),
	),
	static fn( $s ) => '' !== $s[0]
);
?>
<footer class="th-footer" role="contentinfo">
	<div class="th-container">
		<div class="th-footer__grid">
			<div class="th-footer__brand">
				<?php get_template_part( 'template-parts/header/logo', null, array( 'variant' => 'light' ) ); ?>
				<p><?php echo esc_html( th_mod( 'th_footer_blurb' ) ); ?></p>
				<?php if ( $socials ) : ?>
					<ul class="th-footer__social" role="list">
						<?php foreach ( $socials as $social ) : ?>
							<li><a href="<?php echo esc_url( $social[0] ); ?>" rel="noopener" target="_blank"><?php th_icon( $social[1], array( 'size' => 18 ) ); ?><span class="th-sr-only"><?php echo esc_html( $social[2] ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<?php foreach ( $columns as $menu_location => $col_title ) : ?>
				<nav class="th-footer__col" aria-labelledby="<?php echo esc_attr( $menu_location ); ?>-title">
					<h2 class="th-footer__title" id="<?php echo esc_attr( $menu_location ); ?>-title"><?php echo esc_html( $col_title ); ?></h2>
					<?php
					if ( has_nav_menu( $menu_location ) ) {
						wp_nav_menu(
							array(
								'theme_location' => $menu_location,
								'container'      => false,
								'depth'          => 1,
								'items_wrap'     => '<ul role="list">%3$s</ul>',
							)
						);
					} else {
						$fallbacks ??= $th_footer_fallbacks();
						echo '<ul role="list">';
						foreach ( $fallbacks[ $menu_location ] as $fb_link ) {
							printf( '<li><a href="%s">%s</a></li>', esc_url( $fb_link[1] ), esc_html( $fb_link[0] ) );
						}
						echo '</ul>';
					}
					?>
				</nav>
			<?php endforeach; ?>
		</div>

		<div class="th-footer__bottom">
			<p class="th-footer__copy">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> · <?php esc_html_e( 'Costa Blanca, Spain', 'torrehub' ); ?></p>
			<?php get_template_part( 'template-parts/header/language-switcher', null, array( 'variant' => 'row' ) ); ?>
		</div>
	</div>
</footer>
