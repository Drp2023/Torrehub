<?php
/**
 * Favourite (heart) control — card, listing header chip, mobile contact bar. Classified Listing's own favourites
 * (`rtcl_favourites` user meta, its AJAX action); only while they are switched on there.
 *
 * Logged in: a toggle button (aria-pressed, favourites.js). Guests: a link to the login page that comes back with the
 * listing saved (Account\Favourites) — works without JS.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type int    $listing_id Listing.
 *     @type string $title      Listing title (accessible name).
 *     @type string $variant    card|chip|bar.
 *     @type bool   $pressed    In the favourites.
 * }
 */

defined( 'ABSPATH' ) || exit;

$a      = wp_parse_args(
	$args ?? array(),
	array(
		'listing_id' => 0,
		'title'      => '',
		'variant'    => 'card',
		'pressed'    => false,
	)
);
$fav_id = (int) $a['listing_id'];
if ( ! $fav_id || ! th_favourites_enabled() ) {
	return;
}
$guest   = ! is_user_logged_in();
$classes = array(
	'card' => 'th-btn th-btn--icon th-btn--sm th-btn--white th-btn--fav',
	'chip' => 'th-chip th-listing-head__action',
	'bar'  => 'th-btn th-btn--icon th-btn--fav th-contact-bar__fav',
);
$class   = $classes[ $a['variant'] ] ?? $classes['card'];
$size    = array(
	'card' => 18,
	'chip' => 16,
	'bar'  => 20,
)[ $a['variant'] ] ?? 18;
$label   = $guest
	/* translators: %s: listing title */
	? sprintf( __( 'Log in to save %s', 'torrehub' ), $a['title'] )
	/* translators: %s: listing title */
	: sprintf( __( 'Save %s', 'torrehub' ), $a['title'] );
?>
<?php if ( $guest ) : ?>
	<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( th_fav_login_url( $fav_id ) ); ?>" rel="nofollow">
<?php else : ?>
	<button type="button" class="<?php echo esc_attr( $class ); ?>" aria-pressed="<?php echo $a['pressed'] ? 'true' : 'false'; ?>" data-th-fav="<?php echo esc_attr( (string) $fav_id ); ?>">
<?php endif; ?>
		<?php th_icon( 'heart', array( 'size' => $size ) ); ?>
		<?php if ( 'chip' === $a['variant'] ) : ?>
			<span aria-hidden="true"><?php esc_html_e( 'Save', 'torrehub' ); ?></span>
			<span class="th-sr-only"><?php echo esc_html( $label ); ?></span>
		<?php else : ?>
			<?php th_icon( 'heart-filled', array( 'size' => $size ) ); ?>
			<span class="th-sr-only"><?php echo esc_html( $label ); ?></span>
		<?php endif; ?>
<?php if ( $guest ) : ?>
	</a>
<?php else : ?>
	</button>
<?php endif; ?>
