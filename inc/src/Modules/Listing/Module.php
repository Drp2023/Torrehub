<?php
/**
 * Single listing page (L-01, L-15, L-17, L-18): replaces rtcl-elementor-builder + Elementor.
 *
 * - Template: classified-listing/single-rtcl_listing.php → template-parts/listing/*.
 * - Contact: phone/WhatsApp revealed on request (not in the HTML, counted in RTCL's `_rtcl_reveal_phone_whatsapp`);
 *   e-mail enquiry through Classified Listing's own contact e-mails, for logged-in users (design: "Chat and email
 *   need an account"; DECISION: Customizer `th_contact_email_login`).
 * - Report: see Report (TLRS port). JSON-LD: see Schema.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Listing;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Listing module.
 */
final class Module extends BaseModule {

	/** Sizes attribute of the main gallery image (shared with the template). */
	public const GALLERY_SIZES = '(min-width: 1200px) 700px, (min-width: 900px) 60vw, 100vw';

	/** Classified Listing's statistics recorder (free). */
	private const STATS = 'Rtcl\Helpers\ListingStats';

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
		return 'listing';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Listing page', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'The single listing page: gallery, details, hours, map, contact, seller, reports.', 'torrehub' );
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
		( new Report() )->register();
		( new Schema() )->register();

		add_action( 'wp_ajax_th_reveal_contact', array( $this, 'reveal' ) );
		add_action( 'wp_ajax_nopriv_th_reveal_contact', array( $this, 'reveal' ) );
		add_action( 'wp_ajax_th_contact_seller', array( $this, 'contact_ajax' ) );
		th_on_front_post( 'th_contact_seller', array( $this, 'contact_post' ) );

		add_filter( 'the_posts', array( $this, 'reveal_hidden_listing' ), 10, 2 ); // After WP's own status check.
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'th_page_css_bundles', array( $this, 'css_bundles' ) );
		add_filter( 'th_rtcl_assets_needed', array( $this, 'no_rtcl_assets' ) );
		add_action( 'wp_head', array( $this, 'preload_lcp_image' ), 2 );
		add_filter( 'script_module_data_th-app', array( $this, 'module_data' ) );
	}

	/**
	 * Show listings WordPress would 404 (L-17, L-18): an expired listing to everyone (with "See similar"), and a
	 * pending/draft listing to its author. Classified Listing gives sellers no core edit capability, so WP's own
	 * "author may preview" rule never applies to them.
	 *
	 * @param array<int,\WP_Post> $posts Results.
	 * @param \WP_Query           $q     Query.
	 * @return array<int,\WP_Post>
	 */
	public function reveal_hidden_listing( $posts, $q ) {
		if ( $posts || ! $q instanceof \WP_Query || ! $q->is_main_query() || ! $q->is_singular() || is_admin() ) {
			return $posts;
		}
		// Pending listings saved by sellers have no slug yet, so they're reached by id (?post_type=rtcl_listing&p=ID).
		$name = (string) $q->get( 'name' );
		$id   = (int) $q->get( 'p' );
		$type = $q->get( 'post_type' );
		if ( ( '' === $name && ! $id ) || ( 'rtcl_listing' !== $type && ! ( is_array( $type ) && in_array( 'rtcl_listing', $type, true ) ) ) ) {
			return $posts;
		}
		$found = get_posts(
			array_filter(
				array(
					'name'           => $name,
					'p'              => $id,
					'post_type'      => 'rtcl_listing',
					'post_status'    => array( 'rtcl-expired', 'pending', 'draft' ),
					'posts_per_page' => 1,
				)
			)
		);
		$post  = $found[0] ?? null;
		if ( ! $post ) {
			return $posts;
		}
		$own = is_user_logged_in() && get_current_user_id() === (int) $post->post_author;
		if ( 'rtcl-expired' === $post->post_status || $own ) {
			return array( $post );
		}
		return $posts;
	}

	/**
	 * Non-public listings shown by reveal_hidden_listing() are noindex.
	 *
	 * @param array<string,bool|string> $robots Directives.
	 * @return array<string,bool|string>
	 */
	public function robots( $robots ) {
		if ( self::is_single() && 'publish' !== get_post_status( get_queried_object_id() ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * Single listing request?
	 */
	public static function is_single(): bool {
		return is_singular( 'rtcl_listing' );
	}

	/**
	 * Listing CSS bundle (inlined with the theme bundle).
	 *
	 * @param array<int,string> $bundles Page bundles.
	 * @return array<int,string>
	 */
	public function css_bundles( $bundles ): array {
		$bundles = (array) $bundles;
		if ( self::is_single() ) {
			$bundles[] = 'listing';
		}
		return $bundles;
	}

	/**
	 * The theme renders the page itself: Classified Listing's front-end kit isn't needed.
	 *
	 * @param bool $needed Needed so far.
	 */
	public function no_rtcl_assets( $needed ): bool {
		return self::is_single() ? false : (bool) $needed;
	}

	/**
	 * Preload the first gallery image (LCP).
	 */
	public function preload_lcp_image(): void {
		if ( ! self::is_single() ) {
			return;
		}
		$images = \Rtcl\Helpers\Functions::get_listing_images( (int) get_queried_object_id() );
		if ( ! $images ) {
			return;
		}
		$src = wp_get_attachment_image_src( (int) $images[0]->ID, 'th-gallery' );
		if ( $src ) {
			$srcset = wp_get_attachment_image_srcset( (int) $images[0]->ID, 'th-gallery' );
			printf(
				'<link rel="preload" as="image" href="%s"%s imagesizes="%s" fetchpriority="high">' . PHP_EOL,
				esc_url( $src[0] ),
				$srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : '',
				esc_attr( self::GALLERY_SIZES )
			);
		}
	}


	/**
	 * Front-end strings.
	 *
	 * @param array<string,mixed> $data Module data.
	 * @return array<string,mixed>
	 */
	public function module_data( array $data ): array {
		if ( ! self::is_single() ) {
			return $data;
		}
		$id              = (int) get_queried_object_id();
		$data['listing'] = array(
			'id'          => $id,
			'revealNonce' => wp_create_nonce( 'th_reveal_' . $id ),
			'reportNonce' => wp_create_nonce( 'th_report_' . $id ),
			'i18n'        => array(
				'copied'   => __( 'Link copied.', 'torrehub' ),
				'sending'  => __( 'Sending…', 'torrehub' ),
				'error'    => __( 'Something went wrong. Please try again.', 'torrehub' ),
				'tooMany'  => __( 'Too many requests. Try again in a few minutes.', 'torrehub' ),
				/* translators: 1: photo number, 2: number of photos */
				'photo'    => __( 'Photo %1$s of %2$s', 'torrehub' ),
				'backTo'   => __( 'Back to results', 'torrehub' ),
				'readMore' => __( 'Read more', 'torrehub' ),
				'readLess' => __( 'Show less', 'torrehub' ),
			),
		);
		return $data;
	}

	/* ------------------------------------------------------------------ contact */

	/**
	 * Phone and WhatsApp for a listing, or an error code.
	 *
	 * @param int $listing_id Listing id.
	 * @return array{phone:string,whatsapp:string,wa_link:string}|string
	 */
	public static function contact_numbers( int $listing_id ) {
		if ( 'rtcl_listing' !== get_post_type( $listing_id ) || 'publish' !== get_post_status( $listing_id ) ) {
			return 'invalid';
		}
		if ( th_mod( 'th_contact_phone_login' ) && ! is_user_logged_in() ) {
			return 'login';
		}
		// Rate limit per visitor (IP hash): 30 reveals / 10 minutes, against number harvesting.
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key  = 'th_reveal_' . md5( $ip . wp_salt( 'nonce' ) );
		$hits = (int) get_transient( $key );
		if ( $hits >= 30 && ! current_user_can( 'edit_others_posts' ) ) {
			return 'rate';
		}
		set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		$phone    = trim( (string) get_post_meta( $listing_id, 'phone', true ) );
		$whatsapp = trim( (string) get_post_meta( $listing_id, '_rtcl_whatsapp_number', true ) );
		update_post_meta( $listing_id, '_rtcl_reveal_phone_whatsapp', absint( get_post_meta( $listing_id, '_rtcl_reveal_phone_whatsapp', true ) ) + 1 );
		if ( class_exists( self::STATS ) ) {
			call_user_func( array( self::STATS, 'record' ), $listing_id, 'reveal' );
		}
		$digits = preg_replace( '/\D/', '', $whatsapp );
		return array(
			'phone'    => $phone,
			'tel'      => preg_replace( '/[^\d+]/', '', $phone ),
			'whatsapp' => $whatsapp,
			/* translators: %s: listing title */
			'wa_link'  => $digits ? 'https://wa.me/' . $digits . '?text=' . rawurlencode( sprintf( __( 'Hi! I saw your listing on Torrehub: %s', 'torrehub' ), get_the_title( $listing_id ) ) ) : '',
		);
	}

	/**
	 * AJAX: reveal phone/WhatsApp.
	 */
	public function reveal(): void {
		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		check_ajax_referer( 'th_reveal_' . $listing_id );
		$result = self::contact_numbers( $listing_id );
		if ( is_string( $result ) ) {
			wp_send_json_error( array( 'code' => $result ), 'rate' === $result ? 429 : 403 );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Send an enquiry to the seller through Classified Listing's contact e-mails. Returns an error code or ''.
	 *
	 * @param int    $listing_id Listing id.
	 * @param string $message    Message.
	 * @param string $phone      Optional phone.
	 */
	private function send_enquiry( int $listing_id, string $message, string $phone ): string {
		if ( th_mod( 'th_contact_email_login' ) && ! is_user_logged_in() ) {
			return 'login';
		}
		if ( 'rtcl_listing' !== get_post_type( $listing_id ) || 'publish' !== get_post_status( $listing_id ) ) {
			return 'invalid';
		}
		if ( mb_strlen( trim( $message ) ) < 10 || mb_strlen( $message ) > 3000 ) {
			return 'message';
		}
		$user = wp_get_current_user();
		$key  = 'th_enquiry_' . ( $user->ID ? $user->ID : md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- hashed, never output.
		$hits = (int) get_transient( $key );
		if ( $hits >= 5 ) {
			return 'rate';
		}
		set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

		$data = array(
			'post_id' => $listing_id,
			'name'    => $user->ID ? $user->display_name : '',
			'email'   => $user->ID ? $user->user_email : '',
			'phone'   => $phone,
			'message' => nl2br( esc_html( $message ) ),
		);
		/** This filter is documented in Classified Listing (PublicUser::send_contact_email). */
		$data = apply_filters( 'rtcl_listing_seller_contact_form_data', $data, $_POST, array(), new \WP_Error() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the callers.

		$opts = 'rtcl_email_notifications_settings';
		if ( ! \Rtcl\Helpers\Functions::get_option_item( $opts, 'notify_users', 'disable_contact_email', 'multi_checkbox' ) ) {
			rtcl()->mailer()->emails['Listing_Contact_Email_To_Owner']->trigger( $listing_id, $data );
		}
		if ( \Rtcl\Helpers\Functions::get_option_item( $opts, 'notify_admin', 'listing_contact', 'multi_checkbox' ) ) {
			rtcl()->mailer()->emails['Listing_Contact_Email_To_Admin']->trigger( $listing_id, $data );
		}
		// Same bookkeeping as Classified Listing's own contact form.
		update_post_meta( $listing_id, '_notification_by_visitor', absint( get_post_meta( $listing_id, '_notification_by_visitor', true ) ) + 1 );
		if ( class_exists( self::STATS ) ) {
			call_user_func( array( self::STATS, 'record' ), $listing_id, 'contact' );
		}
		return '';
	}

	/**
	 * Read the enquiry fields from POST (callers verify the nonce).
	 *
	 * @return array{0:int,1:string,2:string}
	 */
	private static function enquiry_input(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the callers.
		return array(
			isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0,
			isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
			isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
		);
		// phpcs:enable
	}

	/**
	 * AJAX enquiry.
	 */
	public function contact_ajax(): void {
		list( $listing_id, $message, $phone ) = self::enquiry_input();
		check_ajax_referer( 'th_enquiry_' . $listing_id );
		$error = $this->send_enquiry( $listing_id, $message, $phone );
		if ( $error ) {
			wp_send_json_error( array( 'message' => self::enquiry_message( $error ) ), 'rate' === $error ? 429 : 400 );
		}
		wp_send_json_success( array( 'message' => self::enquiry_message( 'sent' ) ) );
	}

	/**
	 * No-JS enquiry.
	 */
	public function contact_post(): void {
		list( $listing_id, $message, $phone ) = self::enquiry_input();
		$back                                 = $listing_id ? (string) get_permalink( $listing_id ) : home_url( '/' );
		if ( ! is_user_logged_in() && th_mod( 'th_contact_email_login' ) ) {
			wp_safe_redirect( th_url_login( $back . '#contact' ) );
			exit;
		}
		check_admin_referer( 'th_enquiry_' . $listing_id );
		$error = $this->send_enquiry( $listing_id, $message, $phone );
		wp_safe_redirect( add_query_arg( 'th_enquiry', $error ? $error : 'sent', $back ) . '#contact' );
		exit;
	}

	/**
	 * Enquiry result text.
	 *
	 * @param string $code Code.
	 */
	public static function enquiry_message( string $code ): string {
		$messages = array(
			'sent'    => __( 'Message sent. The seller will reply to your e-mail address.', 'torrehub' ),
			'login'   => __( 'Log in to send a message.', 'torrehub' ),
			'message' => __( 'Write a message of at least 10 characters.', 'torrehub' ),
			'rate'    => __( 'You have sent several messages in a short time. Please wait a few minutes.', 'torrehub' ),
			'invalid' => __( 'This listing can’t be contacted.', 'torrehub' ),
		);
		return $messages[ $code ] ?? $messages['invalid'];
	}
}
