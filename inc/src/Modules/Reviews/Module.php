<?php
/**
 * Listing reviews: WordPress comments with comment_type "review" and a `rating` (1–5) meta — the format the
 * existing reviews already use. Replaces review-schema(-pro) and Pro's comment-rating hooks.
 *
 * - Logged-in users only, one review per listing, not on their own listing (DECISION: spam control without captcha).
 * - New reviews wait for moderation unless the author can moderate comments; the moderator gets WP's usual e-mail.
 * - Aggregates are kept in Classified Listing's own meta (`_rtcl_average_rating`, `_rtcl_review_count`,
 *   `_rtcl_rating_count`), so cards, sorting and RTCL itself read the same numbers.
 * - Optional title: comment meta `th_review_title`.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Reviews;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Reviews module.
 */
final class Module extends BaseModule {

	public const TYPE = 'review';

	/**
	 * Registered in this request.
	 *
	 * @var bool
	 */
	private static bool $active = false;

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'reviews';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Reviews', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Star ratings and written reviews on listings, with moderation and rating summaries.', 'torrehub' );
	}

	/**
	 * Needs Classified Listing.
	 */
	public function requirements_met(): bool {
		return th_has_rtcl();
	}

	/**
	 * Requirement message.
	 */
	public function requirement_message(): string {
		return __( 'Requires the Classified Listing plugin.', 'torrehub' );
	}

	/**
	 * Is the module running?
	 */
	public static function active(): bool {
		return self::$active;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		self::$active = true;
		th_on_front_post( 'th_review', array( $this, 'submit' ) );

		// Keep aggregates in sync whatever changes a review (front end, admin, bulk actions, deletion).
		add_action( 'wp_insert_comment', array( $this, 'comment_changed' ) );
		add_action( 'edit_comment', array( $this, 'comment_changed' ) );
		add_action( 'deleted_comment', array( $this, 'comment_changed' ) );
		add_action( 'trashed_comment', array( $this, 'comment_changed' ) );
		add_action( 'untrashed_comment', array( $this, 'comment_changed' ) );
		add_action( 'wp_set_comment_status', array( $this, 'comment_changed' ) );
		add_action( 'updated_comment_meta', array( $this, 'meta_changed' ), 10, 3 );

		// Reviews show in Comments with their stars.
		add_filter( 'comment_text', array( $this, 'admin_comment_text' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ read */

	/**
	 * Approved reviews of a listing, newest first.
	 *
	 * @param int $listing_id Listing id.
	 * @return array<int,\WP_Comment>
	 */
	public static function reviews( int $listing_id ): array {
		return get_comments(
			array(
				'post_id' => $listing_id,
				'type'    => self::TYPE,
				'status'  => 'approve',
				'parent'  => 0,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			)
		);
	}

	/**
	 * Summary from the stored aggregates: average, count, distribution 5→1.
	 *
	 * @param int $listing_id Listing id.
	 * @return array{average:float,count:int,bars:array<int,int>}
	 */
	public static function summary( int $listing_id ): array {
		$dist = get_post_meta( $listing_id, '_rtcl_rating_count', true );
		$dist = is_array( $dist ) ? $dist : array();
		$bars = array();
		foreach ( array( 5, 4, 3, 2, 1 ) as $star ) {
			$bars[ $star ] = (int) ( $dist[ $star ] ?? 0 );
		}
		return array(
			'average' => round( (float) get_post_meta( $listing_id, '_rtcl_average_rating', true ), 1 ),
			'count'   => (int) get_post_meta( $listing_id, '_rtcl_review_count', true ),
			'bars'    => $bars,
		);
	}

	/**
	 * Word label for an average ("Excellent").
	 *
	 * @param float $average Average rating.
	 */
	public static function word( float $average ): string {
		if ( $average >= 4.5 ) {
			return __( 'Excellent', 'torrehub' );
		}
		if ( $average >= 4 ) {
			return __( 'Very good', 'torrehub' );
		}
		if ( $average >= 3 ) {
			return __( 'Good', 'torrehub' );
		}
		if ( $average >= 2 ) {
			return __( 'Fair', 'torrehub' );
		}
		return __( 'Poor', 'torrehub' );
	}

	/**
	 * Why the current user can't review this listing ('' = they can).
	 *
	 * @param int $listing_id Listing id.
	 */
	public static function blocked_reason( int $listing_id ): string {
		if ( ! is_user_logged_in() ) {
			return 'login';
		}
		$user = get_current_user_id();
		if ( (int) get_post_field( 'post_author', $listing_id ) === $user ) {
			return 'own';
		}
		$existing = get_comments(
			array(
				'post_id' => $listing_id,
				'type'    => self::TYPE,
				'user_id' => $user,
				'status'  => 'all',
				'count'   => true,
			)
		);
		return $existing ? 'done' : '';
	}

	/* ------------------------------------------------------------------ write */

	/**
	 * Handle the review form (POST to the listing page).
	 */
	public function submit(): void {
		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below, id needed for the redirect.
		$back       = $listing_id ? (string) get_permalink( $listing_id ) : home_url( '/' );

		if ( ! $listing_id || 'rtcl_listing' !== get_post_type( $listing_id ) || 'publish' !== get_post_status( $listing_id ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( th_url_login( $back . '#reviews' ) );
			exit;
		}
		check_admin_referer( 'th_review_' . $listing_id );

		$fail = static function ( string $code ) use ( $back ) {
			wp_safe_redirect( add_query_arg( 'th_review', $code, $back ) . '#reviews' );
			exit;
		};

		$reason = self::blocked_reason( $listing_id );
		if ( $reason ) {
			$fail( $reason );
		}
		$rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
		$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$text   = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';
		if ( $rating < 1 || $rating > 5 ) {
			$fail( 'rating' );
		}
		if ( mb_strlen( $text ) < 20 || mb_strlen( $text ) > 3000 || mb_strlen( $title ) > 100 ) {
			$fail( 'text' );
		}

		$user     = wp_get_current_user();
		$approved = current_user_can( 'moderate_comments' ) ? 1 : 0;
		$id       = wp_insert_comment(
			array(
				'comment_post_ID'      => $listing_id,
				'comment_type'         => self::TYPE,
				'comment_content'      => $text,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_author_IP'    => '', // Not stored (GDPR: not needed for moderation of logged-in users).
				'comment_agent'        => '',
				'user_id'              => $user->ID,
				'comment_approved'     => $approved,
				'comment_meta'         => array_filter(
					array(
						'rating'          => $rating,
						'th_review_title' => $title,
					)
				),
			)
		);
		if ( ! $id ) {
			$fail( 'error' );
		}
		if ( ! $approved ) {
			wp_notify_moderator( $id );
		}
		self::recalculate( $listing_id );
		wp_safe_redirect( add_query_arg( 'th_review', $approved ? 'published' : 'pending', $back ) . '#reviews' );
		exit;
	}

	/**
	 * A comment changed: recalc its listing when it's a review.
	 *
	 * @param int|string $comment_id Comment id.
	 */
	public function comment_changed( $comment_id ): void {
		$comment = get_comment( (int) $comment_id );
		if ( $comment && self::TYPE === $comment->comment_type && 'rtcl_listing' === get_post_type( (int) $comment->comment_post_ID ) ) {
			self::recalculate( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * Rating edited in the admin.
	 *
	 * @param int    $meta_id    Meta id.
	 * @param int    $comment_id Comment id.
	 * @param string $key        Meta key.
	 */
	public function meta_changed( $meta_id, $comment_id, $key ): void {
		if ( 'rating' === $key ) {
			$this->comment_changed( $comment_id );
		}
	}

	/**
	 * Recompute the listing aggregates from approved reviews.
	 *
	 * @param int $listing_id Listing id.
	 */
	public static function recalculate( int $listing_id ): void {
		$dist = array();
		$sum  = 0;
		$n    = 0;
		foreach ( self::reviews( $listing_id ) as $review ) {
			$r = (int) get_comment_meta( (int) $review->comment_ID, 'rating', true );
			if ( $r < 1 || $r > 5 ) {
				continue;
			}
			$dist[ $r ] = ( $dist[ $r ] ?? 0 ) + 1;
			$sum       += $r;
			++$n;
		}
		update_post_meta( $listing_id, '_rtcl_rating_count', $dist );
		update_post_meta( $listing_id, '_rtcl_review_count', $n );
		update_post_meta( $listing_id, '_rtcl_average_rating', $n ? round( $sum / $n, 2 ) : '' );
		if ( class_exists( \Torrehub\Data\Directory::class ) ) {
			\Torrehub\Data\Directory::flush();
		}
	}

	/**
	 * Prefix review text with its stars and title in wp-admin → Comments.
	 *
	 * @param string           $text    Comment text.
	 * @param \WP_Comment|null $comment Comment.
	 */
	public function admin_comment_text( $text, $comment = null ) {
		if ( ! is_admin() || ! $comment instanceof \WP_Comment || self::TYPE !== $comment->comment_type ) {
			return $text;
		}
		$rating = (int) get_comment_meta( (int) $comment->comment_ID, 'rating', true );
		$title  = (string) get_comment_meta( (int) $comment->comment_ID, 'th_review_title', true );
		$head   = '<p><strong>' . esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', max( 0, 5 - $rating ) ) ) . '</strong>' . ( $title ? ' — <strong>' . esc_html( $title ) . '</strong>' : '' ) . '</p>';
		return $head . $text;
	}
}
