<?php
/**
 * Listing drafts: "Draft saved 12:41", "Save & exit", "Continue a draft".
 *
 * A draft is Classified Listing's own temporary listing post (`rtcl-temp`, the same post its uploaders create for
 * photos before the first save) + the form state as a query string in `th_draft`. Publishing goes through
 * Classified Listing's `rtcl_update_listing`, which turns the temp post into a pending listing; the draft meta is
 * then removed.
 *
 * DECISION: Classified Listing deletes temp posts after 2 hours; drafts are kept for 30 days instead
 * (filter `th_listing_draft_days`), then deleted with their photos by the daily cron.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\ListingForm;

defined( 'ABSPATH' ) || exit;

/**
 * Draft storage on rtcl-temp posts.
 */
final class Drafts {

	public const DATA  = 'th_draft';
	public const SAVED = 'th_draft_saved';
	public const FORM  = 'th_draft_form';
	public const CAT   = 'th_draft_cat';

	/**
	 * Largest form state accepted (bytes). A long description + every field is well below.
	 */
	private const MAX_BYTES = 65536;

	/**
	 * AJAX: create the temp post (first save) and/or store the form state.
	 */
	public static function ajax_save(): void {
		check_ajax_referer( 'th_listing_draft' );
		if ( ! th_user_can_post() ) {
			wp_send_json_error( array( 'message' => __( 'You can’t post listings with this account.', 'torrehub' ) ), 403 );
		}

		$id      = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$cat     = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
		// The form state is stored as posted (a query string Classified Listing parses itself on publish).
		$data = isset( $_POST['form_data'] ) && is_string( $_POST['form_data'] ) ? wp_unslash( $_POST['form_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- kept verbatim, parsed + sanitised by RTCL on publish; escaped on output.

		if ( strlen( $data ) > self::MAX_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'This draft is too large to save.', 'torrehub' ) ), 413 );
		}

		if ( $id ) {
			if ( ! self::owned( $id ) ) {
				wp_send_json_error( array( 'message' => __( 'This draft no longer exists.', 'torrehub' ) ), 404 );
			}
		} else {
			if ( ! $form_id || ! \Rtcl\Models\Form\Form::query()->find( $form_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Form not found.', 'torrehub' ) ), 400 );
			}
			$blocked = (string) apply_filters( 'th_listing_form_blocked', '', get_current_user_id() );
			if ( $blocked ) {
				wp_send_json_error( array( 'message' => $blocked ), 403 );
			}
			$id = self::create( get_current_user_id() );
			if ( ! $id ) {
				wp_send_json_error( array( 'message' => __( 'The draft couldn’t be saved. Try again.', 'torrehub' ) ), 500 );
			}
			update_post_meta( $id, self::FORM, $form_id );
			update_post_meta( $id, self::CAT, $cat );
		}

		update_post_meta( $id, self::DATA, wp_slash( $data ) );
		update_post_meta( $id, self::SAVED, time() );

		wp_send_json_success(
			array(
				'listing_id' => $id,
				/* translators: %s: time, e.g. 12:41 */
				'label'      => sprintf( __( 'Draft saved %s', 'torrehub' ), wp_date( (string) get_option( 'time_format', 'H:i' ) ) ),
			)
		);
	}

	/**
	 * New temp listing for a user (same arguments as Classified Listing's uploaders).
	 *
	 * @param int $user_id Author.
	 */
	public static function create( int $user_id ): int {
		add_filter( 'post_type_link', '__return_empty_string' );
		$id = wp_insert_post(
			array(
				'post_title'     => __( 'Untitled draft', 'torrehub' ),
				'post_content'   => '',
				'post_status'    => 'rtcl-temp',
				'post_author'    => $user_id,
				'post_type'      => 'rtcl_listing',
				'comment_status' => 'closed',
			),
			true
		);
		remove_filter( 'post_type_link', '__return_empty_string' );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Is this one of the current user's temp listings?
	 *
	 * @param int $id Post id.
	 */
	public static function owned( int $id ): bool {
		$post = get_post( $id );
		return $post && 'rtcl_listing' === $post->post_type && 'rtcl-temp' === $post->post_status
			&& get_current_user_id() === (int) $post->post_author && is_user_logged_in();
	}

	/**
	 * A draft of the current user.
	 *
	 * @param int $id Post id.
	 * @return array{id:int,values:array<string,mixed>,form_id:int,category:int,saved:int}|null
	 */
	public static function get( int $id ): ?array {
		if ( ! self::owned( $id ) ) {
			return null;
		}
		$values = array();
		parse_str( (string) get_post_meta( $id, self::DATA, true ), $values );
		return array(
			'id'       => $id,
			'values'   => $values,
			'form_id'  => (int) get_post_meta( $id, self::FORM, true ),
			'category' => (int) get_post_meta( $id, self::CAT, true ),
			'saved'    => (int) get_post_meta( $id, self::SAVED, true ),
		);
	}

	/**
	 * The user's drafts, newest first.
	 *
	 * @param int $user_id User.
	 * @return array<int,array{id:int,title:string,category:string,saved:int,url:string}>
	 */
	public static function for_user( int $user_id ): array {
		$posts = get_posts(
			array(
				'post_type'        => 'rtcl_listing',
				'post_status'      => 'rtcl-temp',
				'author'           => $user_id,
				'posts_per_page'   => 10,
				'meta_key'         => self::SAVED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a handful of temp posts per user.
				'orderby'          => 'meta_value_num',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
		$out   = array();
		foreach ( $posts as $post ) {
			$values = array();
			parse_str( (string) get_post_meta( $post->ID, self::DATA, true ), $values );
			$term  = get_term( (int) get_post_meta( $post->ID, self::CAT, true ), 'rtcl_category' );
			$title = isset( $values['title'] ) && is_string( $values['title'] ) ? trim( sanitize_text_field( $values['title'] ) ) : '';
			$out[] = array(
				'id'       => (int) $post->ID,
				'title'    => '' !== $title ? $title : __( 'Untitled draft', 'torrehub' ),
				'category' => $term instanceof \WP_Term ? html_entity_decode( $term->name, ENT_QUOTES ) : '',
				'saved'    => (int) get_post_meta( $post->ID, self::SAVED, true ),
				'url'      => add_query_arg( 'th_draft', $post->ID, Module::url() ),
			);
		}
		return $out;
	}

	/**
	 * Published through Classified Listing → no longer a draft.
	 *
	 * @param mixed $listing Rtcl Listing object.
	 */
	public static function clear( $listing ): void {
		$id = is_object( $listing ) && method_exists( $listing, 'get_id' ) ? (int) $listing->get_id() : 0;
		if ( $id ) {
			foreach ( array( self::DATA, self::SAVED, self::FORM, self::CAT ) as $key ) {
				delete_post_meta( $id, $key );
			}
		}
	}

	/**
	 * Keep drafts out of Classified Listing's 2-hour temp cleanup.
	 *
	 * @param array<string,mixed> $args WP_Query args.
	 * @return array<string,mixed>
	 */
	public static function keep_in_cleanup( $args ): array {
		$args               = (array) $args;
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- daily cron.
			array(
				'key'     => self::SAVED,
				'compare' => 'NOT EXISTS',
			),
		);
		return $args;
	}

	/**
	 * Daily: delete drafts untouched for 30 days, with their photos and files.
	 */
	public static function purge_old(): void {
		/**
		 * Days an untouched draft is kept.
		 *
		 * @param int $days Days.
		 */
		$days = max( 1, (int) apply_filters( 'th_listing_draft_days', 30 ) );
		$ids  = get_posts(
			array(
				'post_type'        => 'rtcl_listing',
				'post_status'      => 'rtcl-temp',
				'fields'           => 'ids',
				'posts_per_page'   => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- daily cron batch.
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- daily cron.
					array(
						'key'     => self::SAVED,
						'value'   => time() - $days * DAY_IN_SECONDS,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			self::delete( (int) $id );
		}
	}

	/**
	 * Delete a draft and its attachments.
	 *
	 * @param int $id Post id.
	 */
	public static function delete( int $id ): void {
		$attachments = get_children(
			array(
				'post_parent' => $id,
				'post_type'   => 'attachment',
				'fields'      => 'ids',
			)
		);
		foreach ( $attachments as $attachment_id ) {
			wp_delete_attachment( (int) $attachment_id, true );
		}
		wp_delete_post( $id, true );
	}

	/**
	 * Front-end POST: delete one of my drafts ("Continue a draft" list).
	 */
	public static function delete_action(): void {
		$id = isset( $_POST['draft_id'] ) ? absint( $_POST['draft_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified next.
		check_admin_referer( 'th_delete_draft_' . $id );
		$ok = self::owned( $id );
		if ( $ok ) {
			self::delete( $id );
		}
		$back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : Module::url();
		wp_safe_redirect( add_query_arg( 'th_draft_deleted', $ok ? '1' : '0', $back ) );
		exit;
	}
}
