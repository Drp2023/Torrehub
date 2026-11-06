<?php
/**
 * Compare toggle — card (round icon button under the heart) or listing header chip (next to Save). A link to
 * /compare/?toggle=ID: without JS it adds the listing and opens the comparison; compare.js toggles in place.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type int    $listing_id Listing.
 *     @type string $title      Listing title (accessible name on the card).
 *     @type string $variant    card|chip.
 *     @type bool   $pressed    In the comparison.
 * }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Compare\Module as Compare;

$a          = wp_parse_args(
	$args ?? array(),
	array(
		'listing_id' => 0,
		'title'      => '',
		'variant'    => 'card',
		'pressed'    => false,
	)
);
$compare_id = (int) $a['listing_id'];
if ( ! $compare_id || ! class_exists( Compare::class ) || ! Compare::active() ) {
	return;
}
$chip = 'chip' === $a['variant'];
?>
<a class="<?php echo esc_attr( $chip ? 'th-chip th-listing-head__action th-listing-head__action--compare' : 'th-btn th-btn--icon th-btn--sm th-btn--white th-btn--compare' ); ?>" href="<?php echo esc_url( Compare::url( array( 'toggle' => $compare_id ) ) ); ?>" role="button" aria-pressed="<?php echo $a['pressed'] ? 'true' : 'false'; ?>" data-th-compare="<?php echo esc_attr( (string) $compare_id ); ?>" rel="nofollow">
	<?php th_icon( 'compare', array( 'size' => $chip ? 16 : 18 ) ); ?>
	<?php if ( $chip ) : ?>
		<span><?php esc_html_e( 'Compare', 'torrehub' ); ?></span>
	<?php else : ?>
		<?php /* translators: %s: listing title */ ?>
		<span class="th-sr-only"><?php echo esc_html( sprintf( __( 'Compare %s', 'torrehub' ), $a['title'] ) ); ?></span>
	<?php endif; ?>
</a>
