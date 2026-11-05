<?php
/**
 * Verified badge in the admin (client request 2026-11): Users list "Verified" column with a per-row switch
 * (verified ↔ not verified), bulk actions and an All / Verified / Not verified filter; the same filter on the
 * Listings list (by the listing's seller). Every change goes through Module::set_verified() (badge everywhere,
 * caches cleared). No e-mail goes out from here — the review screen (Users › Verification) keeps its e-mails.
 *
 * Only seller accounts (Private / Business Seller) can carry the badge; other rows show "—".
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Verification;

defined( 'ABSPATH' ) || exit;

/**
 * Verified admin.
 */
final class VerifiedAdmin {

	private const PARAM = 'th_verified';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'manage_users_columns', array( $this, 'columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'column' ), 10, 3 );
		add_action( 'restrict_manage_users', array( $this, 'users_filter' ) );
		add_action( 'pre_get_users', array( $this, 'filter_users' ) );
		add_filter( 'bulk_actions-users', array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk' ), 10, 3 );
		add_action( 'admin_action_th_verified', array( $this, 'handle_row' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'restrict_manage_posts', array( $this, 'listings_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_listings' ) );
	}

	/**
	 * Current filter value: yes|no|'' (all).
	 */
	private static function current(): string {
		$v = isset( $_GET[ self::PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		return in_array( $v, array( 'yes', 'no' ), true ) ? $v : '';
	}

	/**
	 * The filter drop-down.
	 *
	 * @param string $id Element id suffix.
	 */
	private static function select( string $id ): void {
		$current = self::current();
		?>
		<label class="screen-reader-text" for="th-verified-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Filter by Verified badge', 'torrehub' ); ?></label>
		<select name="<?php echo esc_attr( self::PARAM ); ?>" id="th-verified-<?php echo esc_attr( $id ); ?>">
			<option value=""><?php echo esc_html_x( 'All', 'verified filter', 'torrehub' ); ?></option>
			<option value="yes" <?php selected( 'yes', $current ); ?>><?php esc_html_e( 'Verified', 'torrehub' ); ?></option>
			<option value="no" <?php selected( 'no', $current ); ?>><?php esc_html_e( 'Not verified', 'torrehub' ); ?></option>
		</select>
		<?php
	}

	/* ------------------------------------------------------------------ users */

	/**
	 * Add the column.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public function columns( $cols ) {
		$cols['th_verified'] = __( 'Verified', 'torrehub' );
		return $cols;
	}

	/**
	 * Column: state + switch.
	 *
	 * @param string $out     Output.
	 * @param string $col     Column.
	 * @param int    $user_id User.
	 */
	public function column( $out, $col, $user_id ) {
		if ( 'th_verified' !== $col ) {
			return $out;
		}
		$user = get_userdata( (int) $user_id );
		if ( ! $user || ! Module::eligible( $user ) ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Not a seller account', 'torrehub' ) . '</span>';
		}
		$on    = Module::is_verified( (int) $user_id );
		$state = $on
			? '<strong style="color:#0b5f49">✓ ' . esc_html__( 'Verified', 'torrehub' ) . '</strong>'
			: '<span style="color:#5f6a8a">' . esc_html__( 'Not verified', 'torrehub' ) . '</span>';
		if ( ! current_user_can( 'edit_user', (int) $user_id ) ) {
			return $state;
		}
		$url = wp_nonce_url( admin_url( 'users.php?action=th_verified&do=' . ( $on ? 'off' : 'on' ) . '&user=' . (int) $user_id ), 'th_verified_' . (int) $user_id );
		/* translators: %s: user name */
		$label = $on ? sprintf( __( 'Remove the Verified badge of %s', 'torrehub' ), $user->display_name ) : sprintf( __( 'Give %s the Verified badge', 'torrehub' ), $user->display_name );
		return $state . '<br><a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $label ) . '"' . ( $on ? ' style="color:#b3261e"' : '' ) . '>'
			. esc_html( $on ? __( 'Remove badge', 'torrehub' ) : __( 'Mark verified', 'torrehub' ) ) . '</a>';
	}

	/**
	 * Filter above the Users list.
	 *
	 * @param string $which top|bottom.
	 */
	public function users_filter( $which = 'top' ): void {
		if ( 'top' !== $which ) {
			return;
		}
		echo '<div class="alignleft actions">';
		self::select( 'users' );
		submit_button( __( 'Filter', 'torrehub' ), '', 'th_verified_go', false );
		echo '</div>';
	}

	/**
	 * Apply the filter on users.php.
	 *
	 * @param \WP_User_Query $query Query.
	 */
	public function filter_users( $query ): void {
		global $pagenow;
		$v = self::current();
		if ( 'users.php' !== $pagenow || '' === $v ) {
			return;
		}
		$meta   = (array) $query->get( 'meta_query' );
		$meta[] = 'yes' === $v
			? array(
				'key'   => 'rtcl_verified_seller',
				'value' => '1',
			)
			: array(
				'relation' => 'OR',
				array(
					'key'     => 'rtcl_verified_seller',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'rtcl_verified_seller',
					'value'   => '1',
					'compare' => '!=',
				),
			);
		$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin list.
		if ( 'no' === $v ) {
			$query->set( 'role__in', array_merge( (array) $query->get( 'role__in' ), self::seller_roles() ) ); // "Not verified" = sellers without the badge.
		}
	}

	/**
	 * Roles of seller accounts (the badge only applies to them).
	 *
	 * @return array<int,string>
	 */
	private static function seller_roles(): array {
		/**
		 * Roles listed under "Not verified".
		 *
		 * @param array<int,string> $roles Role slugs.
		 */
		return (array) apply_filters( 'th_verified_seller_roles', array( 'seller', 'business' ) );
	}

	/**
	 * Row switch.
	 */
	public function handle_row(): void {
		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		$todo    = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		check_admin_referer( 'th_verified_' . $user_id );
		$user = get_userdata( $user_id );
		if ( ! $user || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'torrehub' ), 403 );
		}
		if ( Module::eligible( $user ) && in_array( $todo, array( 'on', 'off' ), true ) ) {
			Module::set_verified( $user_id, 'on' === $todo );
		}
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'users.php' );
		wp_safe_redirect( add_query_arg( 'th_verified_done', $todo . '-1', remove_query_arg( 'th_verified_done', $back ) ) );
		exit;
	}

	/**
	 * Bulk actions.
	 *
	 * @param array<string,string> $actions Actions.
	 * @return array<string,string>
	 */
	public function bulk_actions( $actions ) {
		$actions['th_verify']   = __( 'Mark verified', 'torrehub' );
		$actions['th_unverify'] = __( 'Remove Verified badge', 'torrehub' );
		return $actions;
	}

	/**
	 * Bulk handler (WP has checked the nonce).
	 *
	 * @param string         $redirect Redirect.
	 * @param string         $action   Action.
	 * @param array<int,int> $ids      User ids.
	 */
	public function handle_bulk( $redirect, $action, $ids ) {
		if ( ! in_array( $action, array( 'th_verify', 'th_unverify' ), true ) || ! current_user_can( 'edit_users' ) ) {
			return $redirect;
		}
		$done = 0;
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			$user = get_userdata( $id );
			if ( $user && Module::eligible( $user ) && current_user_can( 'edit_user', $id ) ) {
				Module::set_verified( $id, 'th_verify' === $action );
				++$done;
			}
		}
		$skipped = count( (array) $ids ) - $done;
		return add_query_arg( 'th_verified_done', ( 'th_verify' === $action ? 'on' : 'off' ) . '-' . $done . '-' . $skipped, remove_query_arg( 'th_verified_done', $redirect ) );
	}

	/**
	 * Result notice.
	 */
	public function notice(): void {
		$done = isset( $_GET['th_verified_done'] ) ? sanitize_text_field( wp_unslash( $_GET['th_verified_done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! preg_match( '/^(on|off)-(\d+)(?:-(\d+))?$/', $done, $m ) ) {
			return;
		}
		$n    = (int) $m[2];
		$text = 'on' === $m[1]
			/* translators: %d: number of sellers */
			? sprintf( _n( '%d seller marked verified — the badge shows on their listings.', '%d sellers marked verified — the badge shows on their listings.', $n, 'torrehub' ), $n )
			/* translators: %d: number of sellers */
			: sprintf( _n( 'Verified badge removed from %d seller — it no longer shows anywhere.', 'Verified badge removed from %d sellers — it no longer shows anywhere.', $n, 'torrehub' ), $n );
		if ( ! empty( $m[3] ) ) {
			/* translators: %d: number of accounts */
			$text .= ' ' . sprintf( _n( '%d account skipped (not a seller account).', '%d accounts skipped (not seller accounts).', (int) $m[3], 'torrehub' ), (int) $m[3] );
		}
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}

	/* ------------------------------------------------------------------ listings */

	/**
	 * Filter above Listings.
	 *
	 * @param string $post_type Post type.
	 */
	public function listings_filter( $post_type ): void {
		if ( 'rtcl_listing' === $post_type ) {
			self::select( 'listings' );
		}
	}

	/**
	 * Listings by verified / not verified sellers.
	 *
	 * @param \WP_Query $q Query.
	 */
	public function filter_listings( $q ): void {
		global $pagenow;
		$v = self::current();
		if ( 'edit.php' !== $pagenow || '' === $v || ! $q->is_main_query() || 'rtcl_listing' !== $q->get( 'post_type' ) ) {
			return;
		}
		$verified = get_users(
			array(
				'meta_key'   => 'rtcl_verified_seller', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin list.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
			)
		);
		$ids      = array_map( 'intval', $verified );
		if ( 'yes' === $v ) {
			$q->set( 'author__in', $ids ? $ids : array( 0 ) );
		} else {
			$q->set( 'author__not_in', $ids );
		}
	}
}
