<?php
/**
 * Chat between a member and a seller about one listing — replaces Classified Listing Pro's chat.
 *
 * - Storage: own tables (Store), one thread per listing + buyer.
 * - Account section "Messages" (`/my-account/chat/`): thread list + conversation; works without JS (plain forms),
 *   chat.js adds sending in place and polling.
 * - REST (`torrehub/v1/chat/…`, cookie auth + wp_rest nonce) for chat.js.
 * - "Chat" button on listings (`th_listing_contact_buttons`), unread badge in the header, the mobile bottom nav
 *   ("Chats") and the dashboard ("Unread").
 * - E-mail to the recipient when a message arrives in a conversation they had fully read (no mail per message).
 *
 * DECISION: polling, no third-party push service — every 5 s while a conversation is open and visible, 20 s for the
 * thread list, paused in background tabs. A push service can listen on `th_chat_message_sent`.
 * DECISION: "Typical reply within …" is measured (median first reply, 90 days, ≥ 3 conversations) but shown only
 * when the Customizer switch is on (BUILD-PLAN Q6).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Chat;

use Torrehub\Core\Mailer;
use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Chat module.
 */
final class Module extends BaseModule {

	public const ENDPOINT = 'chat';
	private const NS      = 'torrehub/v1';
	private const RATE    = 30; // Messages per 10 minutes per user.

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'chat';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Chat', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Messages between members and sellers about a listing, with e-mail notifications.', 'torrehub' );
	}

	/**
	 * Needs Classified Listing (listings, account page).
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
	 * Tables.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		return array(
			'chat_threads'  => 'CREATE TABLE {table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				listing_id bigint(20) unsigned NOT NULL,
				buyer_id bigint(20) unsigned NOT NULL,
				seller_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				last_message_id bigint(20) unsigned NOT NULL DEFAULT 0,
				buyer_unread int(10) unsigned NOT NULL DEFAULT 0,
				seller_unread int(10) unsigned NOT NULL DEFAULT 0,
				buyer_read_id bigint(20) unsigned NOT NULL DEFAULT 0,
				seller_read_id bigint(20) unsigned NOT NULL DEFAULT 0,
				buyer_deleted tinyint(1) NOT NULL DEFAULT 0,
				seller_deleted tinyint(1) NOT NULL DEFAULT 0,
				notified_at datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY listing_buyer (listing_id,buyer_id),
				KEY buyer (buyer_id,updated_at),
				KEY seller (seller_id,updated_at)
			) {charset};',
			'chat_messages' => 'CREATE TABLE {table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				thread_id bigint(20) unsigned NOT NULL,
				sender_id bigint(20) unsigned NOT NULL,
				body text NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY thread (thread_id,id)
			) {charset};',
		);
	}

	/**
	 * Schema version.
	 */
	public function schema_version(): int {
		return 1;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'rtcl_my_account_endpoint', array( $this, 'endpoint' ) );
		add_filter( 'th_account_sections', array( $this, 'section' ) );
		add_action( 'rtcl_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_action( 'template_redirect', array( $this, 'mark_open_thread_read' ) );
		th_on_front_post( 'th_chat_send', array( $this, 'post_send' ) );
		th_on_front_post( 'th_chat_hide', array( $this, 'post_hide' ) );

		add_action( 'th_listing_contact_buttons', array( $this, 'listing_button' ), 10, 2 );
		add_action( 'th_listing_seller_facts', array( $this, 'reply_time' ) );
		add_action( 'th_header_actions', array( $this, 'header_button' ) );
		add_filter( 'th_bottom_nav_items', array( $this, 'bottom_nav' ) );
		add_filter( 'th_account_stats', array( $this, 'stats_tile' ), 10, 2 );
		add_filter( 'script_module_data_th-app', array( $this, 'module_data' ) );
		add_action( 'th_chat_message_sent', array( $this, 'notify' ), 10, 3 );
		add_action( 'delete_user', array( Store::class, 'forget_user' ) );
		add_action( 'customize_register', array( $this, 'customizer' ), 20 );
	}

	/* ------------------------------------------------------------------ urls */

	/**
	 * Messages page URL, optionally for a thread or a listing (new conversation).
	 *
	 * @param array<string,int> $args thread|listing.
	 */
	public static function url( array $args = array() ): string {
		$url = \Rtcl\Helpers\Link::get_account_endpoint_url( self::ENDPOINT );
		return $args ? add_query_arg( array_map( 'absint', $args ), $url ) : $url;
	}

	/* ------------------------------------------------------------------ account */

	/**
	 * Register the account endpoint.
	 *
	 * @param array<string,string> $endpoints Endpoints.
	 * @return array<string,string>
	 */
	public function endpoint( $endpoints ) {
		$endpoints                   = (array) $endpoints;
		$endpoints[ self::ENDPOINT ] = self::ENDPOINT;
		return $endpoints;
	}

	/**
	 * Account navigation entry (everyone) with the unread count.
	 *
	 * @param array<string,array> $sections Sections.
	 * @return array<string,array>
	 */
	public function section( $sections ) {
		$sections                   = (array) $sections;
		$sections[ self::ENDPOINT ] = array( __( 'Messages', 'torrehub' ), 'chat', Store::unread( get_current_user_id() ) );
		return $sections;
	}

	/**
	 * Flush rewrite rules once after the endpoint was added.
	 */
	public function maybe_flush_rewrites(): void {
		if ( '1' !== get_option( 'th_chat_rewrite' ) ) {
			flush_rewrite_rules( false );
			update_option( 'th_chat_rewrite', '1' );
		}
	}

	/**
	 * Opening a conversation marks it read before the header renders (badge already up to date).
	 */
	public function mark_open_thread_read(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation; ownership is checked.
		$id = isset( $_GET['thread'] ) ? absint( $_GET['thread'] ) : 0;
		if ( ! $id || ! is_user_logged_in() || ! \Rtcl\Helpers\Functions::is_account_page( self::ENDPOINT ) ) {
			return;
		}
		$thread = Store::get( $id );
		if ( $thread && Store::side( $thread, get_current_user_id() ) ) {
			Store::mark_read( $thread, get_current_user_id() );
		}
	}

	/**
	 * The account section.
	 */
	public function render(): void {
		get_template_part( 'template-parts/account/chat' );
	}

	/**
	 * Thread rows → view data for the list.
	 *
	 * @param object $thread  Thread row (with last_* columns from Store::threads()).
	 * @param int    $user_id Me.
	 * @return array<string,mixed>
	 */
	public static function thread_view( object $thread, int $user_id ): array {
		$side    = Store::side( $thread, $user_id );
		$other   = get_userdata( Store::other( $thread, $user_id ) );
		$listing = get_post( (int) $thread->listing_id );
		$at      = isset( $thread->last_at ) && $thread->last_at ? $thread->last_at : $thread->updated_at;
		return array(
			'id'      => (int) $thread->id,
			'side'    => $side,
			'other'   => array(
				'id'   => $other ? (int) $other->ID : 0,
				'name' => $other ? $other->display_name : __( 'Deleted user', 'torrehub' ),
			),
			'listing' => array(
				'id'    => $listing ? (int) $listing->ID : 0,
				// post_title, not get_the_title(): Classified Listing renames titles on account pages.
				'title' => $listing ? html_entity_decode( $listing->post_title, ENT_QUOTES ) : __( 'Listing removed', 'torrehub' ),
				'url'   => $listing && 'publish' === $listing->post_status ? (string) get_permalink( $listing ) : '',
				'image' => $listing ? (int) get_post_thumbnail_id( $listing ) : 0,
			),
			'last'    => array(
				'body' => isset( $thread->last_body ) ? wp_html_excerpt( (string) $thread->last_body, 90, '…' ) : '',
				'mine' => isset( $thread->last_sender ) && (int) $thread->last_sender === $user_id,
			),
			'unread'  => 'buyer' === $side ? (int) $thread->buyer_unread : (int) $thread->seller_unread,
			'read_id' => 'buyer' === $side ? (int) $thread->seller_read_id : (int) $thread->buyer_read_id, // The other side's.
			'when'    => self::when( (string) $at ),
			'url'     => self::url( array( 'thread' => (int) $thread->id ) ),
		);
	}

	/**
	 * Message row → view data.
	 *
	 * @param object $message Message row.
	 * @param int    $user_id Me.
	 * @return array{id:int,mine:bool,body:string,at:string,time:string}
	 */
	public static function message_view( object $message, int $user_id ): array {
		return array(
			'id'   => (int) $message->id,
			'mine' => (int) $message->sender_id === $user_id,
			'body' => (string) $message->body,
			'at'   => mysql_to_rfc3339( (string) $message->created_at ),
			'time' => self::when( (string) $message->created_at ),
		);
	}

	/**
	 * GMT datetime → "12:41" today, "Mon 12:41" this week, "3 Oct" otherwise (site time).
	 *
	 * @param string $gmt GMT datetime.
	 */
	public static function when( string $gmt ): string {
		$ts = strtotime( $gmt . ' UTC' );
		if ( ! $ts ) {
			return '';
		}
		$time = (string) get_option( 'time_format', 'H:i' );
		if ( wp_date( 'Ymd', $ts ) === wp_date( 'Ymd' ) ) {
			return wp_date( $time, $ts );
		}
		return time() - $ts < 6 * DAY_IN_SECONDS ? wp_date( 'D ' . $time, $ts ) : wp_date( 'j M', $ts );
	}

	/**
	 * Rate limit: true when the user may send another message.
	 *
	 * @param int $user_id User.
	 */
	private static function rate_ok( int $user_id ): bool {
		$key   = 'th_chat_rate_' . $user_id;
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Send (or start) from a plain form: thread id or listing id + message.
	 *
	 * @param int    $thread_id  Thread.
	 * @param int    $listing_id Listing (new conversation).
	 * @param string $body       Message.
	 * @return int|\WP_Error Thread id.
	 */
	public static function deliver( int $thread_id, int $listing_id, string $body ) {
		$user = get_current_user_id();
		if ( ! $user ) {
			return new \WP_Error( 'th_chat_login', __( 'Log in to send messages.', 'torrehub' ) );
		}
		if ( ! self::rate_ok( $user ) ) {
			return new \WP_Error( 'th_chat_rate', __( 'You’re sending messages too fast. Wait a few minutes.', 'torrehub' ) );
		}
		if ( $thread_id ) {
			$sent = Store::send( $thread_id, $user, $body );
			return is_wp_error( $sent ) ? $sent : $thread_id;
		}
		$started = Store::start( $listing_id, $user, $body );
		return is_wp_error( $started ) ? $started : $started['thread'];
	}

	/**
	 * Front-end POST (no JS): send a message, back to the conversation.
	 */
	public function post_send(): void {
		check_admin_referer( 'th_chat_send' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$thread  = isset( $_POST['thread'] ) ? absint( $_POST['thread'] ) : 0;
		$listing = isset( $_POST['listing'] ) ? absint( $_POST['listing'] ) : 0;
		$body    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		// phpcs:enable
		$result = self::deliver( $thread, $listing, $body );
		if ( is_wp_error( $result ) ) {
			$back = self::url( $thread ? array( 'thread' => $thread ) : array( 'listing' => $listing ) );
			wp_safe_redirect( add_query_arg( 'th_chat_error', rawurlencode( $result->get_error_code() ), $back ) );
			exit;
		}
		wp_safe_redirect( self::url( array( 'thread' => (int) $result ) ) . '#th-chat-compose' );
		exit;
	}

	/**
	 * Front-end POST: hide a conversation.
	 */
	public function post_hide(): void {
		$id = isset( $_POST['thread'] ) ? absint( $_POST['thread'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified next.
		check_admin_referer( 'th_chat_hide_' . $id );
		$thread = Store::get( $id );
		if ( $thread ) {
			Store::hide( $thread, get_current_user_id() );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Error codes → messages (no-JS redirects).
	 *
	 * @param string $code Error code.
	 */
	public static function error_text( string $code ): string {
		$map = array(
			'th_chat_rate'      => __( 'You’re sending messages too fast. Wait a few minutes.', 'torrehub' ),
			'th_chat_empty'     => __( 'Write a message first.', 'torrehub' ),
			'th_chat_self'      => __( 'You can’t message yourself.', 'torrehub' ),
			'th_chat_listing'   => __( 'This listing isn’t available any more.', 'torrehub' ),
			'th_chat_forbidden' => __( 'This conversation isn’t yours.', 'torrehub' ),
		);
		return $map[ $code ] ?? __( 'The message couldn’t be sent. Try again.', 'torrehub' );
	}

	/* ------------------------------------------------------------------ REST */

	/**
	 * REST routes.
	 */
	public function routes(): void {
		$auth = static fn() => is_user_logged_in();
		register_rest_route(
			self::NS,
			'/chat/threads',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_threads' ),
					'permission_callback' => $auth,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_start' ),
					'permission_callback' => $auth,
					'args'                => array(
						'listing_id' => array(
							'type'     => 'integer',
							'required' => true,
						),
						'message'    => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/chat/threads/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_messages' ),
					'permission_callback' => $auth,
					'args'                => array(
						'after' => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_send' ),
					'permission_callback' => $auth,
					'args'                => array(
						'message' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'rest_hide' ),
					'permission_callback' => $auth,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/chat/unread',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static fn() => rest_ensure_response( array( 'unread' => Store::unread( get_current_user_id() ) ) ),
				'permission_callback' => $auth,
			)
		);
	}

	/**
	 * GET threads.
	 */
	public function rest_threads(): \WP_REST_Response {
		$user = get_current_user_id();
		return rest_ensure_response(
			array(
				'threads' => array_map( static fn( $t ) => self::thread_view( $t, $user ), Store::threads( $user ) ),
				'unread'  => Store::unread( $user ),
			)
		);
	}

	/**
	 * Thread the current user takes part in, or a 404 error.
	 *
	 * @param int $id Thread id.
	 * @return object|\WP_Error
	 */
	private static function mine( int $id ) {
		$thread = Store::get( $id );
		if ( ! $thread || ! Store::side( $thread, get_current_user_id() ) ) {
			return new \WP_Error( 'th_chat_not_found', __( 'Conversation not found.', 'torrehub' ), array( 'status' => 404 ) );
		}
		return $thread;
	}

	/**
	 * GET messages (after an id) — marks the thread read.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_messages( \WP_REST_Request $request ) {
		$thread = self::mine( (int) $request['id'] );
		if ( is_wp_error( $thread ) ) {
			return $thread;
		}
		$user     = get_current_user_id();
		$messages = Store::messages( (int) $thread->id, (int) $request['after'] );
		if ( $messages ) {
			Store::mark_read( $thread, $user );
		}
		$side = Store::side( $thread, $user );
		return rest_ensure_response(
			array(
				'messages' => array_map( static fn( $m ) => self::message_view( $m, $user ), $messages ),
				'seen'     => 'buyer' === $side ? (int) $thread->seller_read_id : (int) $thread->buyer_read_id,
				'unread'   => Store::unread( $user ),
			)
		);
	}

	/**
	 * POST a message to a thread.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_send( \WP_REST_Request $request ) {
		$thread = self::mine( (int) $request['id'] );
		if ( is_wp_error( $thread ) ) {
			return $thread;
		}
		$result = self::deliver( (int) $thread->id, 0, (string) $request['message'] );
		return is_wp_error( $result ) ? self::rest_error( $result ) : $this->rest_messages_after( (int) $thread->id, (int) $request['after'] );
	}

	/**
	 * POST a new conversation about a listing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_start( \WP_REST_Request $request ) {
		$result = self::deliver( 0, (int) $request['listing_id'], (string) $request['message'] );
		if ( is_wp_error( $result ) ) {
			return self::rest_error( $result );
		}
		return rest_ensure_response(
			array(
				'thread' => (int) $result,
				'url'    => self::url( array( 'thread' => (int) $result ) ),
			)
		);
	}

	/**
	 * Messages after an id (response for send).
	 *
	 * @param int $thread_id Thread.
	 * @param int $after     Last id the client has.
	 */
	private function rest_messages_after( int $thread_id, int $after ): \WP_REST_Response {
		$user = get_current_user_id();
		return rest_ensure_response( array( 'messages' => array_map( static fn( $m ) => self::message_view( $m, $user ), Store::messages( $thread_id, $after ) ) ) );
	}

	/**
	 * DELETE: hide a conversation for me.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_hide( \WP_REST_Request $request ) {
		$thread = self::mine( (int) $request['id'] );
		if ( is_wp_error( $thread ) ) {
			return $thread;
		}
		Store::hide( $thread, get_current_user_id() );
		return rest_ensure_response( array( 'hidden' => true ) );
	}

	/**
	 * WP_Error → REST error with a status.
	 *
	 * @param \WP_Error $error Error.
	 */
	private static function rest_error( \WP_Error $error ): \WP_Error {
		$status = array(
			'th_chat_rate'      => 429,
			'th_chat_forbidden' => 403,
			'th_chat_login'     => 401,
		);
		$error->add_data( array( 'status' => $status[ $error->get_error_code() ] ?? 400 ) );
		return $error;
	}

	/* ------------------------------------------------------------------ entry points */

	/**
	 * "Chat" button on a listing (contact module and mobile contact sheet).
	 *
	 * @param \Torrehub\Modules\Listing\View $view    Listing.
	 * @param string                         $context module|sheet.
	 */
	public function listing_button( $view, $context = 'module' ): void {
		if ( ! is_object( $view ) || ! empty( $view->is_owner ) || 'publish' !== get_post_status( (int) $view->id ) ) {
			return;
		}
		$user = get_current_user_id();
		if ( ! $user ) {
			$url = th_url_login( (string) get_permalink( (int) $view->id ) );
		} else {
			$thread = Store::find( (int) $view->id, $user );
			$url    = self::url( $thread ? array( 'thread' => (int) $thread->id ) : array( 'listing' => (int) $view->id ) );
		}
		th_component(
			'button',
			array(
				'label'   => 'sheet' === $context ? __( 'Chat on Torrehub', 'torrehub' ) : __( 'Chat', 'torrehub' ),
				'variant' => 'sheet' === $context ? 'primary' : 'white',
				'icon'    => 'chat',
				'block'   => true,
				'size'    => 'sheet' === $context ? 'lg' : 'md',
				'href'    => $url,
				'attrs'   => array( 'rel' => 'nofollow' ),
			)
		);
	}

	/**
	 * Seller card: "Typical reply within …" (Customizer, off by default).
	 *
	 * @param \Torrehub\Modules\Listing\View $view Listing.
	 */
	public function reply_time( $view ): void {
		if ( ! th_mod( 'th_chat_reply_time' ) || ! is_object( $view ) ) {
			return;
		}
		$seconds = Store::typical_reply( (int) ( $view->seller['id'] ?? 0 ) );
		if ( null === $seconds ) {
			return;
		}
		$text = $seconds < HOUR_IN_SECONDS
			? __( 'Within an hour', 'torrehub' )
			: ( $seconds < DAY_IN_SECONDS
				/* translators: %s: number of hours */
				? sprintf( _n( 'Within %s hour', 'Within %s hours', (int) ceil( $seconds / HOUR_IN_SECONDS ), 'torrehub' ), number_format_i18n( (int) ceil( $seconds / HOUR_IN_SECONDS ) ) )
				: __( 'Within a few days', 'torrehub' ) );
		printf( '<div><dt>%1$s</dt><dd>%2$s</dd></div>', esc_html__( 'Typical reply', 'torrehub' ), esc_html( $text ) );
	}

	/**
	 * Header chat button with the unread badge (logged-in).
	 */
	public function header_button(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$unread = Store::unread( get_current_user_id() );
		$label  = $unread
			/* translators: %s: number of unread messages */
			? sprintf( _n( 'Messages, %s unread', 'Messages, %s unread', $unread, 'torrehub' ), number_format_i18n( $unread ) )
			: __( 'Messages', 'torrehub' );
		?>
		<a class="th-btn th-btn--icon th-btn--neutral th-header__chat" href="<?php echo esc_url( self::url() ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-th-chat-badge-link>
			<?php th_icon( 'chat', array( 'size' => 20 ) ); ?>
			<span class="th-badge-count" data-th-chat-badge<?php echo $unread ? '' : ' hidden'; ?>><?php echo esc_html( $unread > 99 ? '99+' : (string) $unread ); ?></span>
		</a>
		<?php
	}

	/**
	 * Bottom nav (logged in): the 4th item becomes "Chats" with the unread count (G-10).
	 *
	 * @param array<int,array<string,mixed>> $items Items.
	 * @return array<int,array<string,mixed>>
	 */
	public function bottom_nav( $items ): array {
		$items = (array) $items;
		if ( ! is_user_logged_in() || count( $items ) < 5 ) {
			return $items;
		}
		$items[3] = array(
			'label'   => __( 'Chats', 'torrehub' ),
			'icon'    => 'chat',
			'url'     => self::url(),
			'current' => \Rtcl\Helpers\Functions::is_account_page( self::ENDPOINT ),
			'badge'   => Store::unread( get_current_user_id() ),
		);
		return $items;
	}

	/**
	 * Dashboard tile "Unread".
	 *
	 * @param array<int,array> $tiles Tiles.
	 * @param \WP_User         $user  User.
	 * @return array<int,array>
	 */
	public function stats_tile( $tiles, $user ): array {
		$tiles   = (array) $tiles;
		$tiles[] = array( __( 'Unread', 'torrehub' ), Store::unread( $user instanceof \WP_User ? $user->ID : 0 ), 'clay' );
		return $tiles;
	}

	/**
	 * Endpoints + strings for chat.js.
	 *
	 * @param array<string,mixed> $data Module data.
	 * @return array<string,mixed>
	 */
	public function module_data( array $data ): array {
		if ( ! is_user_logged_in() ) {
			return $data;
		}
		$data['chat'] = array(
			'rest'  => esc_url_raw( rest_url( self::NS . '/chat/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'  => array(
				'sending' => __( 'Sending…', 'torrehub' ),
				'failed'  => __( 'Not sent. Try again.', 'torrehub' ),
				'seen'    => __( 'Seen', 'torrehub' ),
				'you'     => __( 'You', 'torrehub' ),
			),
		);
		return $data;
	}

	/* ------------------------------------------------------------------ e-mail */

	/**
	 * E-mail the recipient when a message arrives in a conversation they had fully read (one mail per burst).
	 *
	 * @param int    $message_id Message id.
	 * @param object $thread     Thread row before the message.
	 * @param int    $sender_id  Sender.
	 */
	public function notify( $message_id, $thread, $sender_id ): void {
		$side      = Store::side( $thread, (int) $sender_id );
		$other_key = 'buyer' === $side ? 'seller_unread' : 'buyer_unread';
		if ( ! $side || (int) $thread->{$other_key} > 0 ) {
			return; // Already has unread messages here: they were mailed about the first one.
		}
		$recipient = get_userdata( Store::other( $thread, (int) $sender_id ) );
		$sender    = get_userdata( (int) $sender_id );
		if ( ! $recipient || ! $sender ) {
			return;
		}
		/**
		 * Send chat e-mail notifications.
		 *
		 * @param bool     $send      Send.
		 * @param \WP_User $recipient Recipient.
		 */
		if ( ! apply_filters( 'th_chat_email_notify', true, $recipient ) ) {
			return;
		}
		$messages = Store::messages( (int) $thread->id, (int) $message_id - 1, 1 );
		$excerpt  = $messages ? wp_html_excerpt( (string) $messages[0]->body, 300, '…' ) : '';
		$listing  = html_entity_decode( (string) get_post_field( 'post_title', (int) $thread->listing_id ), ENT_QUOTES );
		Mailer::send(
			$recipient->user_email,
			/* translators: %s: sender name */
			sprintf( __( 'New message from %s', 'torrehub' ), $sender->display_name ),
			/* translators: %s: listing title */
			sprintf( __( 'A new message about “%s”', 'torrehub' ), $listing ),
			array_filter(
				array(
					/* translators: %s: sender name */
					sprintf( __( '%s wrote:', 'torrehub' ), $sender->display_name ),
					$excerpt ? '“' . $excerpt . '”' : '',
				)
			),
			array( __( 'Reply on Torrehub', 'torrehub' ), self::url( array( 'thread' => (int) $thread->id ) ) ),
			__( 'Reply on Torrehub rather than by e-mail — your address stays private.', 'torrehub' )
		);
		global $wpdb;
		$wpdb->update( Store::tables()['threads'], array( 'notified_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $thread->id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/* ------------------------------------------------------------------ customizer */

	/**
	 * Customizer: show "Typical reply" on listings.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer.
	 */
	public function customizer( $wp_customize ): void {
		if ( ! $wp_customize->get_section( 'th_listing' ) ) {
			return;
		}
		$wp_customize->add_setting(
			'th_chat_reply_time',
			array(
				'default'           => false,
				'sanitize_callback' => 'wp_validate_boolean',
			)
		);
		$wp_customize->add_control(
			'th_chat_reply_time',
			array(
				'type'        => 'checkbox',
				'section'     => 'th_listing',
				'label'       => __( 'Show “Typical reply” on the seller card', 'torrehub' ),
				'description' => __( 'Median time to the first chat reply in the last 90 days; shown once a seller has 3 or more conversations.', 'torrehub' ),
			)
		);
	}
}
