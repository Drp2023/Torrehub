<?php
/**
 * Listing form (S-02 category drill-in, S-04 / S-14 workspace, S-18 submitted).
 *
 * The theme renders the form from Classified Listing's Form Builder definitions (the `rtcl_forms` table) and saves
 * through Classified Listing's own AJAX endpoints — `rtcl_update_listing` for the listing, `rtcl_fb_gallery_*` for
 * photos and `rtcl_fb_file_*` for file fields — so validation, sanitising, meta keys and hooks stay Classified
 * Listing's. Its React app is not loaded.
 *
 * DECISION (BUILD-PLAN §20): the workspace replaces the React form instead of restyling it — section pills,
 * conditional sections, the live preview and the pin picker need markup the React app doesn't expose. Editing
 * needs JavaScript (as before); without it the page says so.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\ListingForm;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Listing form module.
 */
final class Module extends BaseModule {

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'listing-form';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Listing form', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Category drill-in, form workspace with drafts, photo uploader and map pin for new and edited listings.', 'torrehub' );
	}

	/**
	 * Core building block.
	 */
	public function optional(): bool {
		return false;
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
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'guard' ), 5 );
		add_filter( 'template_include', array( $this, 'template' ), 100 );
		add_filter( 'th_page_css_bundles', array( $this, 'css_bundles' ) );
		add_filter( 'th_rtcl_assets_needed', array( $this, 'no_rtcl_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_react_form' ), 1001 );
		add_filter( 'script_module_data_th-app', array( $this, 'module_data' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'document_title_parts', array( $this, 'document_title' ), 20 );

		add_action( 'wp_ajax_th_listing_draft', array( Drafts::class, 'ajax_save' ) );
		th_on_front_post( 'th_delete_draft', array( Drafts::class, 'delete_action' ) );
		add_action( 'rtcl_listing_form_after_save_or_update', array( Drafts::class, 'clear' ), 5 );
		add_filter( 'rtcl_cron_cleanup_temp_listings_args', array( Drafts::class, 'keep_in_cleanup' ) );
		add_action( 'rtcl_cron_daily_scheduled_events', array( Drafts::class, 'purge_old' ) );

		add_filter( 'wp_handle_upload_prefilter', array( $this, 'limit_gallery_upload' ) );
	}

	/**
	 * The listing form page URL.
	 */
	public static function url(): string {
		return th_rtcl_page_url( 'listing_form', '/listing-form/' );
	}

	/**
	 * Is the listing form page being viewed?
	 */
	public static function is_form_page(): bool {
		return \Rtcl\Helpers\Functions::is_listing_form_page();
	}

	/**
	 * Guests go to the login page (and come back); old `?_fb={form}` links go to the category's drill-in.
	 */
	public function guard(): void {
		if ( ! self::is_form_page() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			$here = home_url( add_query_arg( array() ) );
			wp_safe_redirect( th_url_login( $here ) );
			exit;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- legacy link redirect.
		$fb = isset( $_GET['_fb'] ) ? sanitize_title( wp_unslash( $_GET['_fb'] ) ) : '';
		if ( $fb ) {
			foreach ( th_category_map() as $slug => $meta ) {
				$root = $meta['form_slug'] === $fb ? get_term_by( 'slug', $slug, 'rtcl_category' ) : null;
				if ( $root instanceof \WP_Term ) {
					wp_safe_redirect( add_query_arg( 'th_cat', $root->term_id, self::url() ) );
					exit;
				}
			}
		}
		nocache_headers();
		if ( in_array( Workspace::current()->view, array( 'denied' ), true ) ) {
			status_header( 403 );
		}
	}

	/**
	 * Theme template for the page.
	 *
	 * @param string $template Template.
	 */
	public function template( $template ) {
		if ( self::is_form_page() && is_user_logged_in() ) {
			$file = locate_template( 'template-parts/listing-form/page.php' );
			return $file ? $file : $template;
		}
		return $template;
	}

	/**
	 * CSS bundle.
	 *
	 * @param array<int,string> $bundles Bundles.
	 * @return array<int,string>
	 */
	public function css_bundles( $bundles ): array {
		$bundles = (array) $bundles;
		if ( self::is_form_page() ) {
			$bundles[] = 'listing-form';
		}
		return $bundles;
	}

	/**
	 * The page renders its own form: none of Classified Listing's front-end kit is needed.
	 *
	 * @param bool $needed Needed.
	 */
	public function no_rtcl_assets( $needed ): bool {
		return self::is_form_page() ? false : (bool) $needed;
	}

	/**
	 * Drop the React form app, its styles and the classic editor Classified Listing enqueues for it.
	 */
	public function dequeue_react_form(): void {
		if ( ! self::is_form_page() ) {
			return;
		}
		foreach ( array( 'rtcl-form-builder', 'rtcl-fb-public', 'rtcl-public-add-post', 'rtcl-gallery', 'rtcl-validator', 'jquery-validator', 'select2', 'rt-field-dependency', 'editor', 'quicktags', 'wplink', 'jquery-ui-autocomplete', 'media-upload', 'thickbox', 'wp-embed' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
		foreach ( array( 'rtcl-form-builder', 'rtcl-fb-public', 'editor-buttons', 'buttons', 'thickbox', 'rtcl-gallery' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
		// wp_enqueue_editor() (called by Classified Listing for its React form) prints TinyMCE in the footer.
		remove_action( 'wp_print_footer_scripts', array( '_WP_Editors', 'force_uncompressed_tinymce' ), 1 );
		remove_action( 'wp_print_footer_scripts', array( '_WP_Editors', 'print_default_editor_scripts' ), 45 );
	}

	/**
	 * Strings for listing-form.js, the uploader and the pin picker.
	 *
	 * @param array<string,mixed> $data Module data.
	 * @return array<string,mixed>
	 */
	public function module_data( array $data ): array {
		if ( ! self::is_form_page() || 'workspace' !== Workspace::current()->view ) {
			return $data;
		}
		$data['listingForm'] = Workspace::current()->client_config() + array(
			'towns' => self::town_points(),
			'i18n'  => array(
				'required'     => __( 'This field is required.', 'torrehub' ),
				'email'        => __( 'Enter a valid e-mail address.', 'torrehub' ),
				'url'          => __( 'Enter a full web address, starting with https://', 'torrehub' ),
				'number'       => __( 'Enter a number.', 'torrehub' ),
				/* translators: %s: minimum */
				'min'          => __( 'At least %s.', 'torrehub' ),
				/* translators: %s: maximum */
				'max'          => __( 'At most %s.', 'torrehub' ),
				'pattern'      => __( 'Check the format.', 'torrehub' ),
				/* translators: %d: number of fields */
				'left'         => _n_noop( '%d required field left', '%d required fields left', 'torrehub' ),
				'allDone'      => __( 'All required fields done', 'torrehub' ),
				/* translators: %s: section title */
				'continueTo'   => __( 'Continue to %s', 'torrehub' ),
				'publish'      => __( 'Publish listing', 'torrehub' ),
				'saveChanges'  => __( 'Save changes', 'torrehub' ),
				'saving'       => __( 'Saving…', 'torrehub' ),
				'publishing'   => __( 'Publishing…', 'torrehub' ),
				'saveFailed'   => __( 'Not saved — check your connection', 'torrehub' ),
				'fixErrors'    => __( 'Some fields need your attention.', 'torrehub' ),
				'serverError'  => __( 'The listing couldn’t be saved. Try again in a moment.', 'torrehub' ),
				'hidden'       => __( 'Hidden', 'torrehub' ),
				'untitled'     => __( 'Your listing title', 'torrehub' ),
				'leave'        => __( 'You have unsaved changes.', 'torrehub' ),
				/* translators: 1: file name, 2: size in MB, 3: limit in MB */
				'tooBig'       => __( '%1$s is %2$s MB — the limit is %3$s MB per image.', 'torrehub' ),
				/* translators: %s: file name */
				'badType'      => __( '%s isn’t a supported image type.', 'torrehub' ),
				/* translators: %d: maximum number of photos */
				'tooMany'      => __( 'You can add up to %d photos.', 'torrehub' ),
				'uploadFailed' => __( 'The upload failed. Try again.', 'torrehub' ),
				'waitUploads'  => __( 'Wait until the uploads finish, then publish.', 'torrehub' ),
				'remove'       => __( 'Remove', 'torrehub' ),
				'makeCover'    => __( 'Make cover', 'torrehub' ),
				'cover'        => __( 'Cover', 'torrehub' ),
				/* translators: %d: upload progress percentage */
				'uploading'    => __( 'Uploading %d%%', 'torrehub' ),
				'pinSet'       => __( 'Pin placed. Drag it to fine-tune.', 'torrehub' ),
				'pinRemoved'   => __( 'Pin removed.', 'torrehub' ),
				'locating'     => __( 'Finding your location…', 'torrehub' ),
				'locateFailed' => __( 'Your location isn’t available. Click the map instead.', 'torrehub' ),
				/* translators: 1: latitude, 2: longitude */
				'pinAt'        => __( 'Pin at %1$s, %2$s', 'torrehub' ),
				'noPin'        => __( 'No pin yet — the town centre is used.', 'torrehub' ),
				'mapLabel'     => __( 'Map: click to place the listing’s pin', 'torrehub' ),
				'previewTitle' => __( 'Preview', 'torrehub' ),
			),
		);
		return $data;
	}

	/**
	 * Town centres keyed by rtcl_location term id (pin picker starts there).
	 *
	 * @return array<int,array{0:float,1:float}>
	 */
	private static function town_points(): array {
		$coords = th_town_coordinates();
		$out    = array();
		foreach ( \Torrehub\Data\Directory::towns() as $town ) {
			if ( isset( $coords[ $town['slug'] ] ) ) {
				$out[ $town['id'] ] = $coords[ $town['slug'] ];
			}
		}
		return $out;
	}

	/**
	 * Never index the form.
	 *
	 * @param array<string,bool|string> $robots Directives.
	 * @return array<string,bool|string>
	 */
	public function robots( $robots ): array {
		$robots = (array) $robots;
		if ( self::is_form_page() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['follow'] );
		}
		return $robots;
	}

	/**
	 * Document title follows the screen ("New listing", "Edit listing").
	 *
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public function document_title( $parts ): array {
		$parts = (array) $parts;
		if ( self::is_form_page() && is_user_logged_in() ) {
			$parts['title'] = Workspace::current()->title();
		}
		return $parts;
	}

	/**
	 * Server-side limits for Classified Listing's gallery upload (its React app only checked them in the browser):
	 * image types, size and count from the form's Images field (defaults 10 MB / 5 photos).
	 *
	 * @param array<string,mixed> $file Upload.
	 * @return array<string,mixed>
	 */
	public function limit_gallery_upload( $file ) {
		// phpcs:disable WordPress.Security.NonceVerification -- Classified Listing verifies the nonce before uploading.
		if ( ! wp_doing_ajax() || ! isset( $_REQUEST['action'] ) || 'rtcl_fb_gallery_image_upload' !== $_REQUEST['action'] || ! is_array( $file ) ) {
			return $file;
		}
		$listing_id = isset( $_POST['listingId'] ) ? absint( $_POST['listingId'] ) : 0;
		// phpcs:enable
		$limits = self::image_limits( $listing_id );

		$type = wp_check_filetype_and_ext( (string) ( $file['tmp_name'] ?? '' ), (string) ( $file['name'] ?? '' ) );
		$ext  = strtolower( (string) ( $type['ext'] ? $type['ext'] : pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) );
		if ( ! in_array( $ext, $limits['types'], true ) ) {
			/* translators: %s: allowed types */
			$file['error'] = sprintf( __( 'Only %s images can be uploaded.', 'torrehub' ), strtoupper( implode( ', ', $limits['types'] ) ) );
			return $file;
		}
		if ( (int) ( $file['size'] ?? 0 ) > $limits['bytes'] ) {
			/* translators: %s: size limit in MB */
			$file['error'] = sprintf( __( 'Images can be up to %s MB.', 'torrehub' ), number_format_i18n( $limits['bytes'] / MB_IN_BYTES ) );
			return $file;
		}
		if ( $listing_id && count( get_children( array( 'post_parent' => $listing_id, 'post_type' => 'attachment', 'post_mime_type' => 'image', 'fields' => 'ids' ) ) ) >= $limits['count'] ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			/* translators: %d: maximum number of photos */
			$file['error'] = sprintf( __( 'You can add up to %d photos.', 'torrehub' ), $limits['count'] );
		}
		return $file;
	}

	/**
	 * Image limits for a listing's form (Images field validation), with safe defaults.
	 *
	 * @param int                         $listing_id Listing (or temp post) id.
	 * @param \Rtcl\Models\Form\Form|null $form       Form, when already known.
	 * @return array{bytes:int,count:int,types:array<int,string>}
	 */
	public static function image_limits( int $listing_id = 0, ?\Rtcl\Models\Form\Form $form = null ): array {
		$out = array(
			'bytes' => 10 * MB_IN_BYTES,
			'count' => max( 1, (int) ( get_option( 'rtcl_moderation_settings', array() )['maximum_images_per_listing'] ?? 5 ) ),
			'types' => array( 'jpg', 'jpeg', 'png', 'webp' ),
		);
		if ( ! $form && $listing_id ) {
			$form_id = (int) get_post_meta( $listing_id, '_rtcl_form_id', true );
			$form_id = $form_id ? $form_id : (int) get_post_meta( $listing_id, Drafts::FORM, true );
			$form    = Workspace::form_by_id( $form_id );
		}
		foreach ( $form ? (array) $form->fields : array() as $field ) {
			if ( is_array( $field ) && 'images' === ( $field['element'] ?? '' ) ) {
				$v = (array) ( $field['validation'] ?? array() );
				if ( ! empty( $v['max_file_size']['value'] ) ) {
					$out['bytes'] = (int) round( (float) $v['max_file_size']['value'] * MB_IN_BYTES );
				}
				if ( ! empty( $v['max_file_count']['value'] ) ) {
					$out['count'] = max( 1, (int) $v['max_file_count']['value'] );
				}
				if ( ! empty( $v['allowed_image_types']['value'] ) && is_array( $v['allowed_image_types']['value'] ) ) {
					$out['types'] = array_values( array_intersect( array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), array_map( 'strtolower', $v['allowed_image_types']['value'] ) ) );
					$out['types'] = $out['types'] ? $out['types'] : array( 'jpg', 'jpeg', 'png', 'webp' );
				}
				break;
			}
		}
		return $out;
	}
}
