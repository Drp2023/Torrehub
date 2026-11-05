<?php
/**
 * "Your listing is live" e-mail to the owner when a moderator approves it (pending → publish).
 *
 * Classified Listing has its own e-mail for this (Settings › Email › Notify users › "Listing is approved/published"),
 * but it is switched off on the live site and its template is unbranded; this one uses the theme's e-mail layout.
 * When that option is on, Classified Listing sends and the theme stays quiet — never two e-mails.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Listing;

use Torrehub\Core\Mailer;
use Torrehub\Modules\Lifetime\Module as Lifetime;

defined( 'ABSPATH' ) || exit;

/**
 * Approval notice.
 */
final class Approval {

	/**
	 * Hooks.
	 */
	public function register(): void {
		// After Lifetime (priority 5) has set the end date the e-mail mentions.
		add_action( 'transition_post_status', array( $this, 'approved' ), 20, 3 );
	}

	/**
	 * Pending → publish.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function approved( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || 'pending' !== $old_status || ! $post instanceof \WP_Post || 'rtcl_listing' !== $post->post_type ) {
			return;
		}
		if ( self::rtcl_sends() || get_current_user_id() === (int) $post->post_author ) {
			return;
		}
		self::send( (int) $post->ID );
	}

	/**
	 * Is Classified Listing's own "approved/published" e-mail switched on?
	 */
	public static function rtcl_sends(): bool {
		$opts = (array) get_option( 'rtcl_email_notifications_settings', array() );
		return in_array( 'listing_published', (array) ( $opts['notify_users'] ?? array() ), true );
	}

	/**
	 * The e-mail.
	 *
	 * @param int $listing_id Listing.
	 */
	public static function send( int $listing_id ): bool {
		$owner = get_userdata( (int) get_post_field( 'post_author', $listing_id ) );
		if ( ! $owner || ! is_email( $owner->user_email ) ) {
			return false;
		}
		$title = html_entity_decode( get_the_title( $listing_id ), ENT_QUOTES );
		$card  = function_exists( 'th_listing_card_args' ) ? th_listing_card_args( get_post( $listing_id ) ) : array();
		/* translators: %s: listing title */
		$lines = array( sprintf( __( 'Good news: “%s” has been approved and is now live.', 'torrehub' ), $title ) );
		$ends  = class_exists( Lifetime::class ) ? Lifetime::ends( $listing_id ) : 0;
		if ( $ends ) {
			$remind  = Lifetime::settings()['remind'];
			$lines[] = $remind > 0
				/* translators: %s: date */
				? sprintf( __( 'It runs until %s. We’ll e-mail you before it ends, and renewing is free.', 'torrehub' ), wp_date( get_option( 'date_format' ), $ends ) )
				/* translators: %s: date */
				: sprintf( __( 'It runs until %s. Renewing is free.', 'torrehub' ), wp_date( get_option( 'date_format' ), $ends ) );
		}
		return Mailer::send(
			$owner->user_email,
			/* translators: %s: listing title */
			sprintf( __( '“%s” is live', 'torrehub' ), $title ),
			__( 'Your listing is live', 'torrehub' ),
			$lines,
			array( __( 'View your listing', 'torrehub' ), (string) get_permalink( $listing_id ) ),
			'',
			array(
				'items'  => array( array( $title, (string) ( $card['price'] ?? '' ), (string) get_permalink( $listing_id ) ) ),
				'footer' => array( __( 'My listings', 'torrehub' ), \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' ) ),
			)
		);
	}
}
