<?php
/**
 * Mobile bottom navigation (G-10), < 900px only.
 *
 * Home · Search · Post (accent) · Saved/Guides · Account. "Saved" needs RTCL favourites (currently off) → Guides.
 * Logged in, the Chat module replaces it with "Chats" + unread count (`th_bottom_nav_items`).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

$items = array(
	array(
		'label'   => __( 'Home', 'torrehub' ),
		'icon'    => 'home',
		'url'     => home_url( '/' ),
		'current' => is_front_page(),
	),
	array(
		'label'   => __( 'Search', 'torrehub' ),
		'icon'    => 'search',
		'url'     => th_url_listings(),
		'current' => is_post_type_archive( 'rtcl_listing' ) || is_tax( array( 'rtcl_category', 'rtcl_location' ) ),
	),
	array(
		'label'   => __( 'Post', 'torrehub' ),
		'icon'    => 'plus',
		'url'     => th_url_post_listing(),
		'current' => false,
		'accent'  => true,
	),
);

if ( th_favourites_enabled() ) {
	$items[] = array(
		'label'   => __( 'Saved', 'torrehub' ),
		'icon'    => 'heart',
		'url'     => is_user_logged_in() ? th_rtcl_page_url( 'myaccount', '/my-account/' ) . 'favourites/' : th_url_login(),
		'current' => false,
	);
} else {
	$items[] = array(
		'label'   => __( 'Guides', 'torrehub' ),
		'icon'    => 'file',
		'url'     => th_url_guides(),
		'current' => is_home() || is_singular( 'post' ),
	);
}

$items[] = array(
	'label'   => __( 'Account', 'torrehub' ),
	'icon'    => 'user',
	'url'     => th_url_account(),
	'current' => false,
);

/**
 * Bottom nav items (Chat swaps the 4th for "Chats" + unread badge when logged in).
 *
 * @param array $items [ label, icon, url, current, accent?, badge? ].
 */
$items = (array) apply_filters( 'th_bottom_nav_items', $items );
?>
<nav class="th-bottom-nav" aria-label="<?php esc_attr_e( 'Quick navigation', 'torrehub' ); ?>">
	<ul role="list">
		<?php foreach ( $items as $item ) : ?>
			<li>
				<a class="<?php echo esc_attr( th_classes( 'th-bottom-nav__item', array( 'th-bottom-nav__item--post' => ! empty( $item['accent'] ) ) ) ); ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['current'] ? ' aria-current="page"' : ''; ?>>
					<span class="th-bottom-nav__icon">
						<?php th_icon( $item['icon'], array( 'size' => 22 ) ); ?>
						<?php if ( ! empty( $item['badge'] ) ) : ?>
							<span class="th-badge-count" data-th-chat-badge>
								<?php echo esc_html( $item['badge'] > 99 ? '99+' : (string) $item['badge'] ); ?>
								<span class="th-sr-only"><?php esc_html_e( 'unread', 'torrehub' ); ?></span>
							</span>
						<?php endif; ?>
					</span>
					<span class="th-bottom-nav__label"><?php echo esc_html( $item['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
