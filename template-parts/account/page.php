<?php
/**
 * My account page (logged in): side navigation + the current section from Classified Listing.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Account\Module as Account;

get_header();
$nav = Account::nav();
?>
<main id="main" class="th-main th-account" tabindex="-1">
	<div class="th-container th-account__layout">
		<nav class="th-account__nav" aria-label="<?php esc_attr_e( 'My account', 'torrehub' ); ?>">
			<ul class="th-account__menu" role="list">
				<?php foreach ( $nav as $item ) : ?>
					<li>
						<a class="<?php echo esc_attr( th_classes( 'th-account__link', array( 'is-logout' => 'logout' === $item['key'] ) ) ); ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['current'] ? ' aria-current="page"' : ''; ?>>
							<?php th_icon( $item['icon'], array( 'size' => 18 ) ); ?>
							<span><?php echo esc_html( $item['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<div class="th-account__content">
			<?php
			while ( have_posts() ) {
				the_post();
				echo do_shortcode( '[rtcl_my_account]' );
			}
			?>
		</div>
	</div>
</main>
<?php
get_footer();
