<?php
/**
 * One of my listings: dash-row with status, views, end date, edit, renew (ended or ending soon) and delete (to trash,
 * after a confirm).
 *
 * @package Torrehub
 *
 * @var array $args { @type WP_Post $post }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Lifetime\Module as Lifetime;

$listing_post   = $args['post'];
$listing_id     = (int) $listing_post->ID;
$listing_status = get_post_status( $listing_post );
$badges         = array(
	'publish'      => array( __( 'Active', 'torrehub' ), 'active' ),
	'pending'      => array( __( 'Pending review', 'torrehub' ), 'pending' ),
	'draft'        => array( __( 'Draft', 'torrehub' ), 'neutral' ),
	'rtcl-expired' => array( __( 'Expired', 'torrehub' ), 'expired' ),
);
$card           = th_listing_card_args( $listing_post );
$cats           = get_the_terms( $listing_post, 'rtcl_category' );
$cat_path       = ( $cats && ! is_wp_error( $cats ) ) ? implode( ' › ', array_map( static fn( $t ) => html_entity_decode( $t->name, ENT_QUOTES ), array_slice( $cats, 0, 2 ) ) ) : '';
$lifetime       = class_exists( Lifetime::class ) && th_has_rtcl();
$ends           = $lifetime ? Lifetime::ends( $listing_id ) : 0;
$can_renew      = $lifetime && Lifetime::renewable( $listing_id );
$note           = '';
if ( 'publish' === $listing_status && $ends ) {
	$note = $can_renew
		/* translators: %s: time left, e.g. "2 days" */
		? sprintf( __( 'Ends in %s', 'torrehub' ), human_time_diff( time(), $ends ) )
		/* translators: %s: date */
		: sprintf( __( 'Runs until %s', 'torrehub' ), wp_date( 'j M', $ends ) );
} elseif ( 'rtcl-expired' === $listing_status && $can_renew ) {
	$gone = (string) get_post_meta( $listing_id, 'deletion_date', true );
	/* translators: %s: date */
	$note = '' !== $gone ? sprintf( __( 'Renew by %s', 'torrehub' ), wp_date( 'j M', (int) get_gmt_from_date( $gone, 'U' ) ) ) : '';
}
?>
<div class="th-account-row">
	<?php
	th_component(
		'dash-row',
		array(
			'title'      => html_entity_decode( get_the_title( $listing_post ), ENT_QUOTES ),
			'url'        => 'publish' === $listing_status ? (string) get_permalink( $listing_id ) : add_query_arg(
				array(
					'post_type' => 'rtcl_listing',
					'p'         => $listing_id,
				),
				home_url( '/' )
			),
			'path'       => $cat_path,
			'image'      => $card ? $card['image'] : 0,
			'price'      => $card ? $card['price'] : '',
			'status'     => isset( $badges[ $listing_status ] ) ? array(
				'label'   => $badges[ $listing_status ][0],
				'variant' => $badges[ $listing_status ][1],
			) : array(),
			'views'      => (int) get_post_meta( $listing_id, '_views', true ),
			'note'       => $note,
			'edit_url'   => \Rtcl\Helpers\Link::get_listing_edit_page_link( $listing_id ),
			'listing_id' => 0, // The delete control is the form below (no JS needed).
		)
	);
	?>
	<div class="th-account-row__tools">
	<?php if ( $can_renew ) : ?>
		<form class="th-account-row__renew" method="post" action="<?php echo esc_url( \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' ) ); ?>">
			<input type="hidden" name="th_action" value="th_renew_listing">
			<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) $listing_id ); ?>">
			<?php wp_nonce_field( 'th_renew_listing_' . $listing_id ); ?>
			<?php
			th_component(
				'button',
				array(
					'label'      => __( 'Renew', 'torrehub' ),
					'variant'    => 'outline',
					'size'       => 'sm',
					'icon'       => 'clock',
					'type'       => 'submit',
					/* translators: 1: listing title, 2: number of days */
					'aria_label' => sprintf( __( 'Renew %1$s for %2$d days', 'torrehub' ), html_entity_decode( get_the_title( $listing_post ), ENT_QUOTES ), Lifetime::days( $listing_id ) ),
				)
			);
			?>
		</form>
	<?php endif; ?>
	<form class="th-account-row__delete" method="post" action="<?php echo esc_url( \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' ) ); ?>" data-th-confirm="<?php esc_attr_e( 'Delete this listing? An administrator can still restore it.', 'torrehub' ); ?>">
		<input type="hidden" name="th_action" value="th_delete_listing">
		<input type="hidden" name="listing_id" value="<?php echo esc_attr( (string) $listing_id ); ?>">
		<?php wp_nonce_field( 'th_delete_listing_' . $listing_id ); ?>
		<?php
		th_component(
			'button',
			array(
				'variant'    => 'destructive',
				'size'       => 'sm',
				'icon'       => 'trash',
				'icon_only'  => true,
				'type'       => 'submit',
				/* translators: %s: listing title */
				'aria_label' => sprintf( __( 'Delete %s', 'torrehub' ), html_entity_decode( get_the_title( $listing_post ), ENT_QUOTES ) ),
			)
		);
		?>
	</form>
	</div>
</div>
