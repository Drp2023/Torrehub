<?php
/**
 * Towns worth exploring (P-01): the 12 main towns (top-level locations, client 2026-11), 6 / 4 / 3 / 2 per row —
 * 12 fills every row at each width; image = rtcl_location term meta `_rtcl_image`.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$towns = Directory::home_towns( 12 );
if ( ! $towns ) {
	return;
}
?>
<section class="th-container th-section th-home__towns" aria-labelledby="th-towns-title">
	<?php
	th_component(
		'section-head',
		array(
			'title'     => __( 'Towns worth exploring', 'torrehub' ),
			'id'        => 'th-towns-title',
			'link'      => th_url_listings(),
			/* translators: %s: number of towns */
			'link_text' => sprintf( __( 'All %s towns', 'torrehub' ), number_format_i18n( Directory::stats()['towns'] ) ),
		)
	);
	?>
	<ul class="th-home__town-list" role="list">
		<?php foreach ( $towns as $town ) : ?>
			<li>
				<?php
				th_component(
					'town-card',
					array(
						'name'  => $town['name'],
						'url'   => $town['url'],
						'image' => $town['image'],
						'count' => $town['count'],
					)
				);
				?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
