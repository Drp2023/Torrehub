<?php
/**
 * One of my listings: dash-row with status, views, edit and delete (to trash, after a confirm).
 *
 * @package Torrehub
 *
 * @var array $args { @type WP_Post $post }
 */

defined( 'ABSPATH' ) || exit;

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
			'edit_url'   => \Rtcl\Helpers\Link::get_listing_edit_page_link( $listing_id ),
			'listing_id' => 0, // The delete control is the form below (no JS needed).
		)
	);
	?>
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
