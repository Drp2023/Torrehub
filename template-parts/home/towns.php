<?php
/**
 * Towns worth exploring (P-01): the 5 towns with most listings; image = rtcl_location term meta `_rtcl_image`.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$towns = Directory::top_towns( 5 );
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
	<ul class="th-home__town-list th-scroller" role="list">
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
