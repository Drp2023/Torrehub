<?php
/**
 * Seller verification (replaces rtcl-seller-verification): a seller uploads an ID document, an admin reviews it,
 * approval sets the "Verified" badge (`rtcl_verified_seller` = 1 — the meta cards, listings and filters read).
 *
 * Privacy (DECISION, GDPR — the client dropped NIE numbers for the same reason):
 * - Documents are stored outside the Media Library in uploads/th-private/ under random names, wrapped as PHP
 *   files that start with `<?php exit; ?>`: a direct web request runs the guard and returns nothing — on Apache and
 *   nginx alike (.htaccess alone isn't enough: nginx ignores it). Admins see them through an authenticated endpoint.
 * - The file is **deleted as soon as the request is approved or rejected**; only the outcome is kept.
 *   Filter `th_verification_keep_documents` keeps them instead.
 *
 * User meta `th_verification`: [ status pending|approved|rejected, file, mime, submitted, reason ].
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Verification;

use Torrehub\Core\Mailer;
use Torrehub\Core\Module as BaseModule;
use Torrehub\Modules\Auth\Accounts;

defined( 'ABSPATH' ) || exit;

/**
 * Verification module.
 */
final class Module extends BaseModule {

	public const META     = 'th_verification';
	public const ENDPOINT = 'verification';
	public const MAX_SIZE = 10 * MB_IN_BYTES;

	/** Guard prepended to every stored document. */
	private const GUARD = '<?php exit; ?>
';
	public const TYPES  = array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
		'pdf'      => 'application/pdf',
	);

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'verification';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Seller verification', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Sellers upload an ID document; an admin checks it and the seller gets the Verified badge. Documents are deleted after review.', 'torrehub' );
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
		add_filter( 'rtcl_my_account_endpoint', array( $this, 'endpoint' ) );
		add_filter( 'th_account_sections', array( $this, 'section' ) );
		add_action( 'rtcl_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render' ) );
		add_action( 'th_account_dashboard_cards', array( $this, 'dashboard_card' ) );
		th_on_front_post( 'th_verification_upload', array( $this, 'upload' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'menu' ) );
			add_action( 'admin_post_th_verification_decide', array( $this, 'decide' ) );
			add_action( 'wp_ajax_th_verification_file', array( $this, 'stream' ) );
		}
	}

	/* ------------------------------------------------------------------ data */

	/**
	 * Verification record of a user.
	 *
	 * @param int $user_id User id.
	 * @return array{status:string,file:string,mime:string,submitted:int,reason:string}
	 */
	public static function record( int $user_id ): array {
		$r = get_user_meta( $user_id, self::META, true );
		$r = is_array( $r ) ? $r : array();
		if ( '1' === (string) get_user_meta( $user_id, 'rtcl_verified_seller', true ) ) {
			$r['status'] = 'approved';
		}
		return wp_parse_args(
			$r,
			array(
				'status'    => '',
				'file'      => '',
				'mime'      => '',
				'submitted' => 0,
				'reason'    => '',
			)
		);
	}

	/**
	 * Can this user ask for verification? (Sellers and businesses.)
	 *
	 * @param \WP_User $user User.
	 */
	public static function eligible( \WP_User $user ): bool {
		return in_array( Accounts::type( $user ), array( 'seller', 'business' ), true );
	}

	/**
	 * Private storage directory (created with deny rules on first use).
	 */
	public static function dir(): string {
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'th-private/verification';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			$root = dirname( $dir );
			// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny guard files in our own private dir.
			file_put_contents( $root . '/.htaccess', "Require all denied\nDeny from all\n" );
			file_put_contents( $root . '/index.php', "<?php\n// Silence.\n" );
			file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" );
			// phpcs:enable
		}
		return $dir;
	}

	/**
	 * Delete the stored document of a user (if any).
	 *
	 * @param int $user_id User id.
	 */
	private static function delete_file( int $user_id ): void {
		$r = self::record( $user_id );
		if ( $r['file'] ) {
			$path = self::dir() . '/' . basename( $r['file'] );
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}

	/* ------------------------------------------------------------------ account */

	/**
	 * Register the account endpoint with Classified Listing.
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
	 * Account navigation entry (sellers only).
	 *
	 * @param array<string,array> $sections Sections.
	 * @return array<string,array>
	 */
	public function section( $sections ) {
		$user = wp_get_current_user();
		if ( $user->exists() && self::eligible( $user ) ) {
			$sections[ self::ENDPOINT ] = array( __( 'Verification', 'torrehub' ), 'shield-check' );
		}
		return $sections;
	}

	/**
	 * Flush rewrite rules once after the endpoint was added (or the module version changes).
	 */
	public function maybe_flush_rewrites(): void {
		if ( '1' !== get_option( 'th_verification_rewrite' ) ) {
			flush_rewrite_rules( false );
			update_option( 'th_verification_rewrite', '1' );
		}
	}

	/**
	 * The account section.
	 */
	public function render(): void {
		get_template_part( 'template-parts/account/verification', null, array( 'record' => self::record( get_current_user_id() ) ) );
	}

	/**
	 * Dashboard card (G-03 "Get your verified badge").
	 *
	 * @param \WP_User $user User.
	 */
	public function dashboard_card( $user ): void {
		if ( ! $user instanceof \WP_User || ! self::eligible( $user ) ) {
			return;
		}
		$r = self::record( $user->ID );
		if ( 'approved' === $r['status'] ) {
			return;
		}
		get_template_part( 'template-parts/account/verification-card', null, array( 'record' => $r ) );
	}

	/**
	 * Handle the upload form.
	 */
	public function upload(): void {
		$back = \Rtcl\Helpers\Link::get_account_endpoint_url( self::ENDPOINT );
		check_admin_referer( 'th_verification_upload' );
		$user = wp_get_current_user();
		$fail = static function ( string $code ) use ( $back ) {
			wp_safe_redirect( add_query_arg( 'th_v', $code, $back ) );
			exit;
		};
		if ( ! $user->exists() || ! self::eligible( $user ) ) {
			$fail( 'denied' );
		}
		$current = self::record( $user->ID );
		if ( in_array( $current['status'], array( 'pending', 'approved' ), true ) ) {
			$fail( 'exists' );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file array checked below.
		$file = $_FILES['document'] ?? null;
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? 1 ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			$fail( 'missing' );
		}
		if ( (int) $file['size'] > self::MAX_SIZE ) {
			$fail( 'size' );
		}
		if ( '' === (string) get_user_meta( $user->ID, 'th_verification_consent', true ) && empty( $_POST['consent'] ) ) {
			$fail( 'consent' );
		}
		$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], sanitize_file_name( (string) $file['name'] ), self::TYPES );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ( str_starts_with( (string) $check['type'], 'image/' ) && ! @getimagesize( (string) $file['tmp_name'] ) ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid images return false.
			$fail( 'type' );
		}
		$name = bin2hex( random_bytes( 20 ) ) . '.' . $check['ext'] . '.php';
		$data = file_get_contents( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload tmp file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- private store.
		if ( false === $data || false === file_put_contents( self::dir() . '/' . $name, self::GUARD . $data ) ) {
			$fail( 'error' );
		}
		update_user_meta(
			$user->ID,
			self::META,
			array(
				'status'    => 'pending',
				'file'      => $name,
				'mime'      => (string) $check['type'],
				'submitted' => time(),
				'reason'    => '',
			)
		);
		update_user_meta( $user->ID, 'th_verification_consent', gmdate( 'c' ) );
		Mailer::send(
			(string) get_option( 'admin_email' ),
			__( 'Verification request', 'torrehub' ),
			__( 'A seller asked to be verified', 'torrehub' ),
			/* translators: 1: name, 2: e-mail */
			array( sprintf( __( '%1$s (%2$s) uploaded an ID document.', 'torrehub' ), $user->display_name, $user->user_email ) ),
			array( __( 'Review requests', 'torrehub' ), admin_url( 'users.php?page=th-verification' ) )
		);
		wp_safe_redirect( add_query_arg( 'th_v', 'sent', $back ) );
		exit;
	}

	/* ------------------------------------------------------------------ admin */

	/**
	 * Users → Verification.
	 */
	public function menu(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery -- tiny count for the menu badge.
		$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s", self::META, '%"pending"%' ) );
		add_users_page(
			__( 'Verification requests', 'torrehub' ),
			__( 'Verification', 'torrehub' ) . ( $pending ? ' <span class="awaiting-mod">' . (int) $pending . '</span>' : '' ),
			'edit_users',
			'th-verification',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Review screen.
	 */
	public function admin_page(): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		$users = get_users(
			array(
				'meta_key'     => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin screen.
				'meta_compare' => 'EXISTS',
				'number'       => 100,
			)
		);
		usort( $users, static fn( $a, $b ) => ( 'pending' === self::record( $b->ID )['status'] ) <=> ( 'pending' === self::record( $a->ID )['status'] ) );
		$done = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Verification requests', 'torrehub' ); ?></h1>
			<?php if ( $done ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'approve' === $done ? __( 'Seller verified — the document was deleted and the seller e-mailed.', 'torrehub' ) : __( 'Request rejected — the document was deleted and the seller e-mailed.', 'torrehub' ) ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Check that the document matches the account name. Documents are deleted as soon as you decide.', 'torrehub' ); ?></p>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Seller', 'torrehub' ); ?></th>
					<th><?php esc_html_e( 'Account', 'torrehub' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'torrehub' ); ?></th>
					<th><?php esc_html_e( 'Status', 'torrehub' ); ?></th>
					<th><?php esc_html_e( 'Decision', 'torrehub' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $users ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No requests yet.', 'torrehub' ); ?></td></tr>
				<?php endif; ?>
				<?php
				foreach ( $users as $user ) :
					$r = self::record( $user->ID );
					?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a><br><?php echo esc_html( $user->user_email ); ?></td>
						<td><?php echo esc_html( Accounts::type_label( Accounts::type( $user ) ) ); ?><br><?php echo esc_html( trim( $user->first_name . ' ' . $user->last_name ) ); ?></td>
						<td><?php echo $r['submitted'] ? esc_html( wp_date( get_option( 'date_format' ) . ' H:i', $r['submitted'] ) ) : '—'; ?></td>
						<td><?php echo esc_html( $r['status'] ); ?><?php echo $r['reason'] ? '<br><em>' . esc_html( $r['reason'] ) . '</em>' : ''; ?></td>
						<td>
							<?php if ( 'pending' === $r['status'] ) : ?>
								<?php if ( $r['file'] ) : ?>
									<p><a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=th_verification_file&user=' . $user->ID ), 'th_verification_file_' . $user->ID ) ); ?>"><?php esc_html_e( 'View document', 'torrehub' ); ?></a></p>
								<?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="th_verification_decide">
									<input type="hidden" name="user" value="<?php echo esc_attr( (string) $user->ID ); ?>">
									<?php wp_nonce_field( 'th_verification_decide_' . $user->ID ); ?>
									<button class="button button-primary" name="decision" value="approve"><?php esc_html_e( 'Approve', 'torrehub' ); ?></button>
									<input type="text" name="reason" placeholder="<?php esc_attr_e( 'Reason (for a rejection)', 'torrehub' ); ?>">
									<button class="button" name="decision" value="reject"><?php esc_html_e( 'Reject', 'torrehub' ); ?></button>
								</form>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Stream a document to an admin (never cached, never indexed).
	 */
	public function stream(): void {
		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		check_admin_referer( 'th_verification_file_' . $user_id );
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( '', 403 );
		}
		$r    = self::record( $user_id );
		$path = $r['file'] ? self::dir() . '/' . basename( $r['file'] ) : '';
		if ( '' === $path || ! is_file( $path ) ) {
			wp_die( esc_html__( 'The document is no longer stored.', 'torrehub' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: ' . ( in_array( $r['mime'], self::TYPES, true ) ? $r['mime'] : 'application/octet-stream' ) );
		$data = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- private store.
		if ( str_starts_with( $data, self::GUARD ) ) {
			$data = substr( $data, strlen( self::GUARD ) );
		}
		$ext = pathinfo( preg_replace( '/\.php$/', '', $path ), PATHINFO_EXTENSION );
		header( 'Content-Disposition: inline; filename="verification-' . $user_id . '.' . $ext . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Content-Length: ' . strlen( $data ) );
		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary document for an admin.
		exit;
	}

	/**
	 * Approve / reject.
	 */
	public function decide(): void {
		$user_id = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0;
		check_admin_referer( 'th_verification_decide_' . $user_id );
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( '', 403 );
		}
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$reason   = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$user     = get_userdata( $user_id );
		if ( ! $user || ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
			wp_safe_redirect( admin_url( 'users.php?page=th-verification' ) );
			exit;
		}
		$r = self::record( $user_id );
		if ( ! apply_filters( 'th_verification_keep_documents', false ) ) {
			self::delete_file( $user_id );
			$r['file'] = '';
			$r['mime'] = '';
		}
		if ( 'approve' === $decision ) {
			$r['status'] = 'approved';
			update_user_meta( $user_id, 'rtcl_verified_seller', '1' );
			Mailer::send(
				$user->user_email,
				__( 'You’re verified', 'torrehub' ),
				__( 'Your Verified badge is live', 'torrehub' ),
				array( __( 'We checked your document: your listings now show the Verified badge. As promised, the document has been deleted.', 'torrehub' ) ),
				array( __( 'Go to my account', 'torrehub' ), th_rtcl_page_url( 'myaccount', '/my-account/' ) )
			);
		} else {
			$r['status'] = 'rejected';
			$r['reason'] = $reason;
			delete_user_meta( $user_id, 'rtcl_verified_seller' );
			Mailer::send(
				$user->user_email,
				__( 'About your verification', 'torrehub' ),
				__( 'We couldn’t verify the document', 'torrehub' ),
				array_filter(
					array(
						__( 'Thanks for sending it. We couldn’t verify your account with this document, and it has been deleted.', 'torrehub' ),
						/* translators: %s: reason */
						'' !== $reason ? sprintf( __( 'Reason: %s', 'torrehub' ), $reason ) : '',
						__( 'You can upload another document from your account.', 'torrehub' ),
					)
				),
				array( __( 'Try again', 'torrehub' ), \Rtcl\Helpers\Link::get_account_endpoint_url( self::ENDPOINT ) )
			);
		}
		update_user_meta( $user_id, self::META, $r );
		\Torrehub\Data\Directory::flush(); // Verified-seller counts.
		wp_safe_redirect( admin_url( 'users.php?page=th-verification&done=' . $decision ) );
		exit;
	}
}
