<?php
/**
 * "This weekend" (P-01): Events starting next Saturday–Sunday (Europe/Madrid) in the visitor's town.
 *
 * DECISION: with zero events the module still links to the Events category ("What's on near …") instead of
 * showing "0 events".
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;

$th_town = th_current_town();
$root    = Directory::root( 'events' );
if ( ! $root ) {
	return;
}
$weekend = Directory::weekend_events( $th_town ? $th_town['slug'] : '' );
$url     = add_query_arg( $th_town ? array( 'rtcl_location' => $th_town['slug'] ) : array(), $root['url'] );
$place   = $th_town ? $th_town['name'] : __( 'the Costa Blanca', 'torrehub' );
?>
<section class="th-module th-module--accent th-home__weekend" aria-labelledby="th-weekend-title">
	<p class="th-module__eyebrow"><?php esc_html_e( 'This weekend', 'torrehub' ); ?></p>
	<h2 class="th-module__title" id="th-weekend-title">
		<?php
		if ( $weekend['count'] > 0 ) {
			/* translators: 1: number of events, 2: town name */
			echo esc_html( sprintf( _n( '%1$s event near %2$s', '%1$s events near %2$s', $weekend['count'], 'torrehub' ), number_format_i18n( $weekend['count'] ), $place ) );
		} else {
			/* translators: %s: town name */
			echo esc_html( sprintf( __( 'What’s on near %s', 'torrehub' ), $place ) );
		}
		?>
	</h2>
	<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'See what’s on', 'torrehub' ); ?></a>
</section>
