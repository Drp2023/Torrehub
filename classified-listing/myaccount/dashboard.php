<?php
/**
 * Account dashboard (G-02 / G-03): greeting, numbers, cards from modules (verification …), latest listings.
 *
 * @package Torrehub
 *
 * @var WP_User $current_user
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Account\Module as Account;
use Torrehub\Modules\Auth\Accounts;

$user      = isset( $current_user ) && $current_user instanceof WP_User ? $current_user : wp_get_current_user();
$acct_type = Accounts::type( $user );
$seller    = in_array( $acct_type, array( 'seller', 'business' ), true ) || user_can( $user, 'edit_others_posts' );
$stats     = Account::stats( $user->ID );
$name      = $user->first_name ? $user->first_name : $user->display_name;

/**
 * Dashboard number tiles: [ label, value, tone (blue|green|clay|amber) ].
 *
 * @param array   $tiles Tiles.
 * @param WP_User $user  User.
 */
$tiles = (array) apply_filters(
	'th_account_stats',
	$seller ? array(
		array( __( 'Active', 'torrehub' ), $stats['active'], 'blue' ),
		array( __( 'Views', 'torrehub' ), $stats['views'], 'green' ),
		array( __( 'Pending', 'torrehub' ), $stats['pending'], 'amber' ),
	) : array(),
	$user
);

$recent = $seller ? get_posts(
	array(
		'post_type'      => 'rtcl_listing',
		'post_status'    => array( 'publish', 'pending', 'draft', 'rtcl-expired' ),
		'author'         => $user->ID,
		'posts_per_page' => 3,
	)
) : array();
?>
<div class="th-account-dash th-stack">
	<header class="th-account-dash__head">
		<h1 class="th-account__title">
			<?php
			/* translators: 1: greeting ("Good morning"), 2: first name */
			echo esc_html( sprintf( __( '%1$s, %2$s', 'torrehub' ), Account::greeting(), $name ) );
			?>
		</h1>
		<p class="th-account-dash__sub">
			<?php echo esc_html( $acct_type ? Accounts::type_label( $acct_type ) : __( 'Team', 'torrehub' ) ); ?>
			<?php if ( '1' === (string) get_user_meta( $user->ID, 'rtcl_verified_seller', true ) ) : ?>
				<?php
				th_component(
					'badge',
					array(
						'label'   => __( 'Verified', 'torrehub' ),
						'variant' => 'verified',
					)
				);
				?>
			<?php endif; ?>
		</p>
	</header>

	<?php if ( $tiles ) : ?>
		<ul class="th-account-stats" role="list">
			<?php foreach ( $tiles as $tile ) : ?>
				<li class="th-account-stats__tile th-account-stats__tile--<?php echo esc_attr( (string) $tile[2] ); ?>">
					<span class="th-account-stats__value"><?php echo esc_html( number_format_i18n( (int) $tile[1] ) ); ?></span>
					<span class="th-account-stats__label"><?php echo esc_html( (string) $tile[0] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php
	/**
	 * Dashboard cards from modules (Verification: "Get your verified badge").
	 *
	 * @param WP_User $user User.
	 */
	do_action( 'th_account_dashboard_cards', $user );
	?>

	<?php if ( $seller ) : ?>
		<section class="th-account-card" aria-labelledby="th-dash-listings">
			<div class="th-section-head">
				<h2 id="th-dash-listings"><?php esc_html_e( 'Your latest listings', 'torrehub' ); ?></h2>
				<?php if ( $recent ) : ?>
					<a class="th-section-head__link" href="<?php echo esc_url( \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' ) ); ?>"><?php esc_html_e( 'All my listings', 'torrehub' ); ?> <?php th_icon( 'arrow-right', array( 'size' => 16 ) ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( $recent ) : ?>
				<div class="th-stack" style="--th-stack-gap:10px">
					<?php
					foreach ( $recent as $listing_post ) {
						get_template_part( 'template-parts/account/listing-row', null, array( 'post' => $listing_post ) );
					}
					?>
				</div>
			<?php else : ?>
				<?php
				th_component(
					'empty-state',
					array(
						'icon'          => 'plus',
						'title'         => __( 'Post your first listing', 'torrehub' ),
						'text'          => __( 'It takes a few minutes. Every listing is checked before it goes live.', 'torrehub' ),
						'heading_level' => 'h3',
						'actions'       => array(
							array(
								'label'   => __( 'Post a listing', 'torrehub' ),
								'variant' => 'accent',
								'href'    => th_url_post_listing(),
							),
						),
					)
				);
				?>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<section class="th-account-card" aria-labelledby="th-dash-member">
			<h2 id="th-dash-member"><?php esc_html_e( 'Find what’s around you', 'torrehub' ); ?></h2>
			<p><?php esc_html_e( 'Browse services, homes, cars and what’s on — and contact sellers directly.', 'torrehub' ); ?></p>
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Browse listings', 'torrehub' ),
					'variant' => 'primary',
					'href'    => th_url_listings(),
				)
			);
			?>
		</section>
	<?php endif; ?>

	<?php do_action( 'rtcl_account_dashboard', $user ); ?>
</div>
