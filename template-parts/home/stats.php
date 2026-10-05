<?php
/**
 * Stats card (P-01): live listings / verified sellers / categories (cached 1 h) + suggestion chips.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$stats   = Directory::stats();
$th_town = th_current_town();
$chips   = array_filter( array_map( 'trim', explode( ',', (string) th_mod( 'th_hero_chips' ) ) ) );
?>
<section class="th-module th-module--bordered th-home__stats" aria-label="<?php esc_attr_e( 'Torrehub in numbers', 'torrehub' ); ?>">
	<dl class="th-stats">
		<div><dt><?php esc_html_e( 'Listings', 'torrehub' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $stats['listings'] ) ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Verified', 'torrehub' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $stats['verified'] ) ); ?></dd></div>
		<div><dt><?php esc_html_e( 'Categories', 'torrehub' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $stats['categories'] ) ); ?></dd></div>
	</dl>
	<?php if ( $chips ) : ?>
		<ul class="th-cluster th-home__chips" role="list" style="--th-cluster-gap:6px">
			<?php foreach ( $chips as $chip ) : ?>
				<li>
					<?php
					th_component(
						'chip',
						array(
							'type'    => 'link',
							'variant' => 'suggest',
							'label'   => $chip,
							'href'    => th_url_listings(
								array_filter(
									array(
										'q'             => $chip,
										'rtcl_location' => $th_town ? $th_town['slug'] : '',
									)
								)
							),
						)
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
