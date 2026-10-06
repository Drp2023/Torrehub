<?php
/**
 * Users screen: "Account" column (type + status), status views (Awaiting approval …), Approve / Reject row and bulk
 * actions; user profile: status, rejection reason and the business NIF (validated).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Users admin.
 */
final class UsersAdmin {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'manage_users_columns', array( $this, 'columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'column' ), 10, 3 );
		add_filter( 'views_users', array( $this, 'views' ) );
		add_action( 'pre_get_users', array( $this, 'filter' ) );
		add_filter( 'user_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-users', array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk' ), 10, 3 );
		add_action( 'admin_action_th_account', array( $this, 'handle_row' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'show_user_profile', array( $this, 'profile' ) );
		add_action( 'edit_user_profile', array( $this, 'profile' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile' ) );
		add_action( 'user_profile_update_errors', array( $this, 'validate_profile' ), 10, 3 );
	}

	/**
	 * Status labels.
	 *
	 * @return array<string,string>
	 */
	private static function labels(): array {
		return array(
			Accounts::UNCONFIRMED => __( 'E-mail not confirmed', 'torrehub' ),
			Accounts::PENDING     => __( 'Awaiting approval', 'torrehub' ),
			Accounts::ACTIVE      => __( 'Active', 'torrehub' ),
			Accounts::REJECTED    => __( 'Rejected', 'torrehub' ),
		);
	}

	/**
	 * Add the column.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public function columns( $cols ) {
		$cols['th_account'] = __( 'Account', 'torrehub' );
		return $cols;
	}

	/**
	 * Column content.
	 *
	 * @param string $out     Output.
	 * @param string $col     Column.
	 * @param int    $user_id User id.
	 */
	public function column( $out, $col, $user_id ) {
		if ( 'th_account' !== $col ) {
			return $out;
		}
		$user   = get_userdata( (int) $user_id );
		$type   = $user ? Accounts::type_label( Accounts::type( $user ) ) : '';
		$status = Accounts::status( (int) $user_id );
		$color  = array(
			Accounts::PENDING     => '#8a5309',
			Accounts::UNCONFIRMED => '#5f6a8a',
			Accounts::REJECTED    => '#b3261e',
			Accounts::ACTIVE      => '#0b5f49',
		);
		$nif    = 'business' === ( $user ? Accounts::type( $user ) : '' ) && get_user_meta( (int) $user_id, 'custom_field_2', true ) ? ' · ' . esc_html__( 'NIF on file', 'torrehub' ) : '';
		return sprintf( '%s<br><strong style="color:%s">%s</strong>%s', esc_html( $type ? $type : '—' ), esc_attr( $color[ $status ] ), esc_html( self::labels()[ $status ] ), $nif );
	}

	/**
	 * Status views above the list.
	 *
	 * @param array<string,string> $views Views.
	 * @return array<string,string>
	 */
	public function views( $views ) {
		global $wpdb;
		$current = isset( $_GET['th_status'] ) ? sanitize_key( wp_unslash( $_GET['th_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		foreach ( array( Accounts::PENDING, Accounts::UNCONFIRMED, Accounts::REJECTED ) as $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- cheap count on the admin users screen.
			$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'th_account_status' AND meta_value = %s", $status ) );
			if ( ! $n ) {
				continue;
			}
			$views[ 'th_' . $status ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'th_status', $status, admin_url( 'users.php' ) ) ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( self::labels()[ $status ] ),
				$n
			);
		}
		return $views;
	}

	/**
	 * Apply the status view.
	 *
	 * @param \WP_User_Query $query Query.
	 */
	public function filter( $query ): void {
		global $pagenow;
		$status = isset( $_GET['th_status'] ) ? sanitize_key( wp_unslash( $_GET['th_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		if ( 'users.php' !== $pagenow || ! isset( self::labels()[ $status ] ) || Accounts::ACTIVE === $status ) {
			return;
		}
		$query->set( 'meta_key', 'th_account_status' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query->set( 'meta_value', $status ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	}

	/**
	 * Approve / Reject links on rows that need them.
	 *
	 * @param array<string,string> $actions Actions.
	 * @param \WP_User             $user    User.
	 * @return array<string,string>
	 */
	public function row_actions( $actions, $user ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return $actions;
		}
		$status = Accounts::status( $user->ID );
		$link   = static fn( string $todo ) => wp_nonce_url( admin_url( 'users.php?action=th_account&do=' . $todo . '&user=' . $user->ID ), 'th_account_' . $user->ID );
		if ( in_array( $status, array( Accounts::PENDING, Accounts::UNCONFIRMED, Accounts::REJECTED ), true ) ) {
			$actions['th_approve'] = '<a href="' . esc_url( $link( 'approve' ) ) . '">' . esc_html__( 'Approve', 'torrehub' ) . '</a>';
		}
		if ( in_array( $status, array( Accounts::PENDING, Accounts::UNCONFIRMED ), true ) ) {
			$actions['th_reject'] = '<a href="' . esc_url( $link( 'reject' ) ) . '" style="color:#b3261e">' . esc_html__( 'Reject', 'torrehub' ) . '</a>';
		}
		if ( Accounts::UNCONFIRMED === $status ) {
			$actions['th_resend'] = '<a href="' . esc_url( $link( 'resend' ) ) . '">' . esc_html__( 'Resend confirmation', 'torrehub' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Row action handler.
	 */
	public function handle_row(): void {
		$user_id = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		$todo    = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		check_admin_referer( 'th_account_' . $user_id );
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'torrehub' ), 403 );
		}
		if ( 'approve' === $todo ) {
			Accounts::approve( $user_id );
		} elseif ( 'reject' === $todo ) {
			Accounts::reject( $user_id );
		} elseif ( 'resend' === $todo && get_userdata( $user_id ) ) {
			Accounts::send_confirmation( get_userdata( $user_id ) );
		}
		wp_safe_redirect( add_query_arg( 'th_done', $todo, wp_get_referer() ? wp_get_referer() : admin_url( 'users.php' ) ) );
		exit;
	}

	/**
	 * Bulk actions.
	 *
	 * @param array<string,string> $actions Actions.
	 * @return array<string,string>
	 */
	public function bulk_actions( $actions ) {
		$actions['th_approve'] = __( 'Approve accounts', 'torrehub' );
		$actions['th_reject']  = __( 'Reject accounts', 'torrehub' );
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
		if ( ! in_array( $action, array( 'th_approve', 'th_reject' ), true ) || ! current_user_can( 'edit_users' ) ) {
			return $redirect;
		}
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			if ( current_user_can( 'edit_user', $id ) && ! user_can( $id, 'edit_others_posts' ) ) {
				'th_approve' === $action ? Accounts::approve( $id ) : Accounts::reject( $id );
			}
		}
		return add_query_arg( 'th_done', substr( $action, 3 ), $redirect );
	}

	/**
	 * Confirmation notice.
	 */
	public function notice(): void {
		$done = isset( $_GET['th_done'] ) ? sanitize_key( wp_unslash( $_GET['th_done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$msgs = array(
			'approve' => __( 'Account approved — the user was e-mailed.', 'torrehub' ),
			'reject'  => __( 'Account rejected — the user was e-mailed.', 'torrehub' ),
			'resend'  => __( 'Confirmation e-mail sent again.', 'torrehub' ),
		);
		if ( isset( $msgs[ $done ] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msgs[ $done ] ) );
		}
	}

	/**
	 * Profile section (admins: status + reason; business accounts: NIF).
	 *
	 * @param \WP_User $user User.
	 */
	public function profile( $user ): void {
		$is_admin = current_user_can( 'edit_users' );
		$business = 'business' === Accounts::type( $user );
		if ( ! $is_admin && ! $business ) {
			return;
		}
		wp_nonce_field( 'th_profile_' . $user->ID, 'th_profile_nonce' );
		?>
		<h2><?php esc_html_e( 'Torrehub account', 'torrehub' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php if ( $is_admin && ! user_can( $user, 'edit_others_posts' ) ) : ?>
				<tr>
					<th><label for="th_account_status"><?php esc_html_e( 'Status', 'torrehub' ); ?></label></th>
					<td>
						<select name="th_account_status" id="th_account_status">
							<?php foreach ( self::labels() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( Accounts::status( $user->ID ), $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Changing to Active or Rejected e-mails the user.', 'torrehub' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="th_reject_reason"><?php esc_html_e( 'Rejection reason', 'torrehub' ); ?></label></th>
					<td><input type="text" class="regular-text" name="th_reject_reason" id="th_reject_reason" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, 'th_reject_reason', true ) ); ?>"><p class="description"><?php esc_html_e( 'Optional; included in the rejection e-mail.', 'torrehub' ); ?></p></td>
				</tr>
			<?php endif; ?>
			<?php if ( $business ) : ?>
				<tr>
					<th><label for="th_nif"><?php esc_html_e( 'NIF', 'torrehub' ); ?></label></th>
					<td><input type="text" class="regular-text" name="th_nif" id="th_nif" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, 'custom_field_2', true ) ); ?>" autocomplete="off"><p class="description"><?php esc_html_e( 'Spanish tax id of the business (DNI, NIE-form NIF or CIF). Never shown publicly.', 'torrehub' ); ?></p></td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Validate the NIF before saving.
	 *
	 * @param \WP_Error $errors Errors.
	 * @param bool      $update Update.
	 * @param \stdClass $user   User data.
	 */
	public function validate_profile( $errors, $update, $user ): void {
		if ( ! isset( $_POST['th_nif'], $_POST['th_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['th_profile_nonce'] ) ), 'th_profile_' . ( $user->ID ?? 0 ) ) ) {
			return;
		}
		$nif = Accounts::normalise_nif( sanitize_text_field( wp_unslash( $_POST['th_nif'] ) ) );
		if ( '' === $nif || ! Accounts::valid_nif( $nif ) ) {
			$errors->add( 'th_nif', __( '<strong>Error:</strong> the NIF isn’t valid.', 'torrehub' ) );
		}
	}

	/**
	 * Save the profile section.
	 *
	 * @param int $user_id User id.
	 */
	public function save_profile( $user_id ): void {
		if ( ! isset( $_POST['th_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['th_profile_nonce'] ) ), 'th_profile_' . $user_id ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( isset( $_POST['th_nif'] ) ) {
			$nif = Accounts::normalise_nif( sanitize_text_field( wp_unslash( $_POST['th_nif'] ) ) );
			if ( Accounts::valid_nif( $nif ) ) {
				update_user_meta( $user_id, 'custom_field_2', $nif );
			}
		}
		if ( ! current_user_can( 'edit_users' ) || ! isset( $_POST['th_account_status'] ) ) {
			return;
		}
		$reason = isset( $_POST['th_reject_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['th_reject_reason'] ) ) : '';
		$new    = sanitize_key( wp_unslash( $_POST['th_account_status'] ) );
		$old    = Accounts::status( (int) $user_id );
		if ( $new === $old || ! isset( self::labels()[ $new ] ) ) {
			if ( Accounts::REJECTED === $old ) {
				update_user_meta( $user_id, 'th_reject_reason', $reason );
			}
			return;
		}
		if ( Accounts::ACTIVE === $new ) {
			Accounts::approve( (int) $user_id );
		} elseif ( Accounts::REJECTED === $new ) {
			Accounts::reject( (int) $user_id, $reason );
		} else {
			update_user_meta( $user_id, 'th_account_status', $new );
		}
	}
}
