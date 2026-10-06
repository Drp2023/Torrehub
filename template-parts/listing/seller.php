<?php
/**
 * Seller card: logo/avatar, name, verification line, member since, active listings, socials, all listings link.
 * DECISION: "Typical reply" stays hidden until the Chat module can measure it (BUILD-PLAN Q6).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

$view   = $args['view'];
$seller = $view->seller;

$verified_line = '';
if ( $seller['verified'] && $seller['business'] ) {
	$verified_line = $seller['nif'] ? __( 'Verified business · NIF on file', 'torrehub' ) : __( 'Verified business', 'torrehub' );
} elseif ( $seller['verified'] ) {
	$verified_line = __( 'Verified seller', 'torrehub' );
}

$icons = array(
	'facebook'  => 'facebook',
	'instagram' => 'instagram',
);
?>
<section class="th-listing-card th-listing-seller" aria-labelledby="seller-title">
	<div class="th-listing-seller__head">
		<?php
		if ( $seller['logo'] ) {
			echo wp_get_attachment_image(
				$seller['logo'],
				'th-thumb',
				false,
				array(
					'class' => 'th-avatar',
					'alt'   => '',
					'style' => '--th-avatar-size:48px',
				)
			);
		} else {
			echo th_get_avatar( $seller['id'], 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		}
		?>
		<div>
			<h2 class="th-listing-seller__name" id="seller-title"><?php echo esc_html( $seller['name'] ); ?></h2>
			<?php if ( $verified_line ) : ?>
				<p class="th-listing-seller__verified"><?php th_icon( 'check', array( 'size' => 13 ) ); ?> <?php echo esc_html( $verified_line ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<dl class="th-listing-seller__facts">
		<?php if ( $seller['since'] ) : ?>
			<div><dt><?php esc_html_e( 'Member since', 'torrehub' ); ?></dt><dd><?php echo esc_html( $seller['since'] ); ?></dd></div>
		<?php endif; ?>
		<div><dt><?php esc_html_e( 'Active listings', 'torrehub' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $seller['listings'] ) ); ?></dd></div>
	</dl>

	<?php if ( $view->socials ) : ?>
		<ul class="th-cluster th-listing-seller__socials" role="list">
			<?php foreach ( $view->socials as $network => $url ) : ?>
				<li>
					<a class="th-btn th-btn--icon th-btn--sm th-btn--neutral" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="nofollow noopener">
						<?php th_icon( $icons[ $network ] ?? 'globe', array( 'size' => 16 ) ); ?>
						<span class="th-sr-only"><?php echo esc_html( ucfirst( (string) $network ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $seller['listings'] > 1 && $seller['url'] ) : ?>
		<a class="th-listing-seller__all" href="<?php echo esc_url( $seller['url'] ); ?>"><?php esc_html_e( 'All listings from this seller', 'torrehub' ); ?> <span aria-hidden="true">→</span></a>
	<?php endif; ?>
</section>
