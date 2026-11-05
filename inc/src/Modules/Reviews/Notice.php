<?php
/**
 * E-mail to the listing's owner when a moderator approves a review (client request 2026-11): the reviewer's name,
 * the rating, the text, the listing link and "Reply via chat" — never the reviewer's e-mail address.
 *
 * WordPress itself would e-mail the post author on approval ("Comments › E-mail me whenever anyone posts a comment"),
 * and that mail shows the commenter's e-mail address — it is switched off for reviews; this one replaces it.
 * Once per review (comment meta `th_review_notified`), however often it is unapproved and approved again.
 *
 * "Reply via chat" opens a conversation with the reviewer about the listing: the seller may start one with anyone
 * who has an approved review on it (`th_chat_seller_may_start`).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Reviews;

use Torrehub\Core\Mailer;
use Torrehub\Modules\Chat\Module as Chat;
use Torrehub\Modules\Chat\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Review notice.
 */
final class Notice {

	public const SENT = 'th_review_notified';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'notify_post_author', array( $this, 'no_core_mail' ), 10, 2 );
		add_action( 'transition_comment_status', array( $this, 'approved' ), 10, 3 );
		add_filter( 'th_chat_seller_may_start', array( $this, 'chat_allowed' ), 10, 3 );
	}

	/**
	 * Is it a review of a listing?
	 *
	 * @param \WP_Comment|null $comment Comment.
	 */
	private static function is_review( $comment ): bool {
		return $comment instanceof \WP_Comment && Module::TYPE === $comment->comment_type && 'rtcl_listing' === get_post_type( (int) $comment->comment_post_ID );
	}

	/**
	 * WordPress's own post-author e-mail: not for reviews (it would show the reviewer's e-mail address).
	 *
	 * @param bool       $notify     Core's decision.
	 * @param int|string $comment_id Comment.
	 */
	public function no_core_mail( $notify, $comment_id ) {
		return self::is_review( get_comment( (int) $comment_id ) ) ? false : $notify;
	}

	/**
	 * A review became approved.
	 *
	 * @param string      $new_status New status.
	 * @param string      $old_status Old status.
	 * @param \WP_Comment $comment    Comment.
	 */
	public function approved( $new_status, $old_status, $comment ): void {
		if ( 'approved' !== $new_status || 'approved' === $old_status || ! self::is_review( $comment ) || get_comment_meta( (int) $comment->comment_ID, self::SENT, true ) ) {
			return;
		}
		if ( self::send( $comment ) ) {
			update_comment_meta( (int) $comment->comment_ID, self::SENT, time() );
		}
	}

	/**
	 * The e-mail.
	 *
	 * @param \WP_Comment $comment Review.
	 */
	public static function send( \WP_Comment $comment ): bool {
		$listing_id = (int) $comment->comment_post_ID;
		$owner      = get_userdata( (int) get_post_field( 'post_author', $listing_id ) );
		$reviewer   = (int) $comment->user_id;
		if ( ! $owner || ! is_email( $owner->user_email ) || ( $reviewer && $reviewer === (int) $owner->ID ) ) {
			return false;
		}
		$title   = html_entity_decode( get_the_title( $listing_id ), ENT_QUOTES );
		$rating  = max( 0, min( 5, (int) get_comment_meta( (int) $comment->comment_ID, 'rating', true ) ) );
		$name    = $reviewer && get_userdata( $reviewer ) ? get_userdata( $reviewer )->display_name : (string) $comment->comment_author;
		$name    = '' !== trim( $name ) ? $name : __( 'A visitor', 'torrehub' );
		$head    = (string) get_comment_meta( (int) $comment->comment_ID, 'th_review_title', true );
		$lines   = array(
			/* translators: 1: reviewer name, 2: stars, 3: rating, 4: listing title */
			sprintf( __( '%1$s rated your listing %2$s (%3$d out of 5): “%4$s”.', 'torrehub' ), $name, str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ), $rating, $title ),
		);
		$lines[] = trim( ( '' !== $head ? '“' . $head . '”' . "\n" : '' ) . wp_strip_all_tags( (string) $comment->comment_content ) );
		$link    = (string) get_permalink( $listing_id );
		$button  = array( __( 'See the review', 'torrehub' ), $link . '#reviews' );
		if ( $reviewer && class_exists( Chat::class ) && Chat::active() ) {
			$thread  = Store::find( $listing_id, $reviewer );
			$button  = array(
				__( 'Reply via chat', 'torrehub' ),
				Chat::url(
					$thread ? array( 'thread' => (int) $thread->id ) : array(
						'listing' => $listing_id,
						'buyer'   => $reviewer,
					)
				),
			);
			$lines[] = __( 'Thank them or answer a question in the chat — your reply goes to them, not to the public review.', 'torrehub' );
		}
		return Mailer::send(
			$owner->user_email,
			/* translators: %s: listing title */
			sprintf( __( 'New review on “%s”', 'torrehub' ), $title ),
			__( 'You have a new review', 'torrehub' ),
			$lines,
			$button,
			'',
			array(
				'items'  => array( array( $title, __( 'Your listing', 'torrehub' ), $link ) ),
				'footer' => array( __( 'See the review on your listing', 'torrehub' ), $link . '#reviews' ),
			)
		);
	}

	/**
	 * The seller may start a chat with someone who reviewed the listing.
	 *
	 * @param bool $allowed    So far.
	 * @param int  $listing_id Listing.
	 * @param int  $buyer_id   The other user.
	 */
	public function chat_allowed( $allowed, $listing_id, $buyer_id ) {
		if ( $allowed ) {
			return true;
		}
		return (bool) get_comments(
			array(
				'post_id' => (int) $listing_id,
				'user_id' => (int) $buyer_id,
				'type'    => Module::TYPE,
				'status'  => 'approve',
				'count'   => true,
			)
		);
	}
}
