<?php
/**
 * Users › Notify all users: compose (subject, message, roles), preview, test to yourself, start; sending settings;
 * campaigns with progress, log and controls; the account-sidebar notice. Administrators only.
 *
 * "Start sending" works only after a preview of exactly that subject, message and role choice (checked on the
 * server, not just in the browser).
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Broadcast;

use Torrehub\Core\Mailer;

defined( 'ABSPATH' ) || exit;

/**
 * Broadcast admin.
 */
final class Admin {

	public const PAGE   = 'th-notify-users';
	private const CAP   = 'manage_options';
	private const DRAFT = 'th_broadcast_draft';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_th_broadcast', array( $this, 'handle' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu(): void {
		add_users_page( __( 'Notify all users', 'torrehub' ), __( 'Notify all users', 'torrehub' ), self::CAP, self::PAGE, array( $this, 'page' ) );
	}

	/**
	 * Page URL.
	 *
	 * @param array<string,string|int> $args Query args.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'users.php' ) );
	}

	/**
	 * The current user's draft.
	 *
	 * @return array{subject:string,body:string,roles:array<int,string>,previewed:string}
	 */
	private static function draft(): array {
		$d = (array) get_user_meta( get_current_user_id(), self::DRAFT, true );
		return array(
			'subject'   => (string) ( $d['subject'] ?? '' ),
			'body'      => (string) ( $d['body'] ?? '' ),
			'roles'     => array_map( 'strval', (array) ( $d['roles'] ?? array() ) ),
			'previewed' => (string) ( $d['previewed'] ?? '' ),
		);
	}

	/**
	 * Fingerprint of what was previewed.
	 *
	 * @param array<string,mixed> $d Draft.
	 */
	private static function fingerprint( array $d ): string {
		return md5( $d['subject'] . "\n" . $d['body'] . "\n" . implode( ',', $d['roles'] ) );
	}

	/**
	 * Roles that have users: slug => [ name, count ].
	 *
	 * @return array<string,array{0:string,1:int}>
	 */
	private static function roles(): array {
		$counts = count_users();
		$out    = array();
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$n = (int) ( $counts['avail_roles'][ $slug ] ?? 0 );
			if ( $n ) {
				$out[ $slug ] = array( translate_user_role( $name ), $n );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ actions */

	/**
	 * Form posts.
	 */
	public function handle(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'torrehub' ), 403 );
		}
		check_admin_referer( 'th_broadcast' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$id  = isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0;
		$msg = '';

		if ( in_array( $do, array( 'preview', 'test', 'start' ), true ) ) {
			$known              = array_keys( self::roles() );
			$draft              = array(
				'subject' => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
				'body'    => isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '',
				'roles'   => array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ?? array() ) ), $known ) ),
			);
			$old                = self::draft();
			$draft['previewed'] = $old['previewed'];
			if ( '' === trim( $draft['subject'] ) || '' === trim( $draft['body'] ) ) {
				$msg = 'empty';
			} elseif ( 'preview' === $do ) {
				$draft['previewed'] = self::fingerprint( $draft );
				$msg                = 'preview';
			} elseif ( 'test' === $do ) {
				$me  = wp_get_current_user();
				$msg = Module::send( $me, '[' . __( 'Test', 'torrehub' ) . '] ' . $draft['subject'], $draft['body'] ) ? 'test' : 'test-failed';
			} elseif ( self::fingerprint( $draft ) !== $draft['previewed'] ) {
				$msg = 'preview-first';
			} else {
				$id  = Queue::create( $draft['subject'], $draft['body'], $draft['roles'] );
				$msg = $id ? 'started' : 'nobody';
				if ( $id ) {
					$draft = array(
						'subject'   => '',
						'body'      => '',
						'roles'     => array(),
						'previewed' => '',
					);
				}
			}
			update_user_meta( get_current_user_id(), self::DRAFT, $draft );
		} elseif ( 'settings' === $do ) {
			update_option(
				Module::OPTION,
				array(
					'batch'    => isset( $_POST['batch'] ) ? max( 1, min( 500, absint( $_POST['batch'] ) ) ) : 25,
					'interval' => isset( $_POST['interval'] ) ? max( 1, min( 60, absint( $_POST['interval'] ) ) ) : 5,
				),
				false
			);
			$msg = 'saved';
		} elseif ( 'notice' === $do ) {
			Notice::save(
				array(
					'enabled' => ! empty( $_POST['notice_enabled'] ),
					'title'   => isset( $_POST['notice_title'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_title'] ) ) : '',
					'text'    => isset( $_POST['notice_text'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_text'] ) ) : '',
					'date'    => isset( $_POST['notice_date'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_date'] ) ) : '',
					'label'   => isset( $_POST['notice_label'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_label'] ) ) : '',
					'url'     => isset( $_POST['notice_url'] ) ? esc_url_raw( wp_unslash( $_POST['notice_url'] ) ) : '',
				)
			);
			$msg = 'saved';
		} elseif ( $id && Queue::get( $id ) ) {
			if ( 'pause' === $do ) {
				Queue::set_status( $id, 'paused' );
				Queue::log( $id, __( 'Paused.', 'torrehub' ) );
			} elseif ( 'resume' === $do ) {
				Queue::set_status( $id, 'running' );
				Queue::log( $id, __( 'Resumed.', 'torrehub' ) );
			} elseif ( 'cancel' === $do ) {
				Queue::set_status( $id, 'cancelled' );
				Queue::log( $id, __( 'Cancelled — the rest will not be sent.', 'torrehub' ) );
			} elseif ( 'retry' === $do ) {
				Queue::retry_failed( $id );
			} elseif ( 'now' === $do ) {
				Queue::run( $id );
			}
			$msg = 'updated';
		}
		// phpcs:enable
		wp_safe_redirect( self::url( array( 'th_msg' => $msg ) ) . ( 'preview' === $msg ? '#th-preview' : '' ) );
		exit;
	}

	/* ------------------------------------------------------------------ page */

	/**
	 * The page.
	 */
	public function page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$draft    = self::draft();
		$roles    = self::roles();
		$settings = Module::settings();
		$msg      = isset( $_GET['th_msg'] ) ? sanitize_key( wp_unslash( $_GET['th_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$notes    = array(
			'empty'         => array( 'error', __( 'Write a subject and a message first.', 'torrehub' ) ),
			'preview'       => array( 'info', __( 'Preview below. Check it, then start sending.', 'torrehub' ) ),
			'preview-first' => array( 'error', __( 'Preview this exact message before sending it (the subject, text or roles changed since the last preview).', 'torrehub' ) ),
			/* translators: %s: e-mail address */
			'test'          => array( 'success', sprintf( __( 'Test sent to %s.', 'torrehub' ), wp_get_current_user()->user_email ) ),
			'test-failed'   => array( 'error', __( 'The test e-mail could not be sent. Check the mail settings (FluentSMTP).', 'torrehub' ) ),
			'started'       => array( 'success', __( 'Sending started. The first batch goes out within a minute; progress is below.', 'torrehub' ) ),
			'nobody'        => array( 'error', __( 'No user matches these roles.', 'torrehub' ) ),
			'saved'         => array( 'success', __( 'Saved.', 'torrehub' ) ),
			'updated'       => array( 'success', __( 'Done.', 'torrehub' ) ),
		);
		$fp    = self::fingerprint( $draft );
		$ready = '' !== $draft['previewed'] && $fp === $draft['previewed'];
		$count = $ready || 'preview' === $msg ? count( Queue::recipients( $draft['roles'] ) ) : 0;
		?>
		<div class="wrap th-broadcast">
			<h1><?php esc_html_e( 'Notify all users', 'torrehub' ); ?></h1>
			<?php if ( isset( $notes[ $msg ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notes[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notes[ $msg ][1] ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'New message', 'torrehub' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="th_broadcast">
				<?php wp_nonce_field( 'th_broadcast' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="th-bc-subject"><?php esc_html_e( 'Subject', 'torrehub' ); ?></label></th>
						<td><input type="text" class="large-text" id="th-bc-subject" name="subject" maxlength="150" value="<?php echo esc_attr( $draft['subject'] ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="th-bc-body"><?php esc_html_e( 'Message', 'torrehub' ); ?></label></th>
						<td>
							<textarea class="large-text" id="th-bc-body" name="body" rows="10" required><?php echo esc_textarea( $draft['body'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Plain text. A blank line starts a new paragraph; {name} becomes the user’s name.', 'torrehub' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Send to', 'torrehub' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Roles', 'torrehub' ); ?></legend>
								<?php foreach ( $roles as $slug => $role ) : ?>
									<label style="margin-right:16px"><input type="checkbox" name="roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $draft['roles'], true ) ); ?>> <?php echo esc_html( $role[0] ); ?> <span style="color:#646970">(<?php echo esc_html( number_format_i18n( $role[1] ) ); ?>)</span></label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Nothing ticked = everyone. Only accounts that can log in get it (not unconfirmed, awaiting approval or rejected ones).', 'torrehub' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button" name="do" value="preview"><?php esc_html_e( 'Preview', 'torrehub' ); ?></button>
					<button type="submit" class="button" name="do" value="test">
						<?php
						/* translators: %s: e-mail address */
						echo esc_html( sprintf( __( 'Send a test to %s', 'torrehub' ), wp_get_current_user()->user_email ) );
						?>
					</button>
					<button type="submit" class="button button-primary" name="do" value="start"<?php disabled( ! $ready ); ?> onclick="return confirm(this.dataset.confirm)" data-confirm="<?php echo esc_attr( sprintf( /* translators: %s: number of users */ __( 'Send this message to %s users?', 'torrehub' ), number_format_i18n( $count ) ) ); ?>">
						<?php
						echo esc_html(
							$ready
								/* translators: %s: number of users */
								? sprintf( _n( 'Start sending to %s user', 'Start sending to %s users', $count, 'torrehub' ), number_format_i18n( $count ) )
								: __( 'Start sending (preview first)', 'torrehub' )
						);
						?>
					</button>
				</p>
			</form>

			<?php if ( $ready ) : ?>
				<?php list( $heading, $paras, $button, $foot, $extra ) = Module::parts( $draft['subject'], $draft['body'], wp_get_current_user()->display_name ); ?>
				<h2 id="th-preview"><?php esc_html_e( 'Preview', 'torrehub' ); ?></h2>
				<p>
					<?php
					/* translators: 1: subject, 2: number of users */
					echo esc_html( sprintf( _n( 'Subject: “%1$s” — to %2$s user.', 'Subject: “%1$s” — to %2$s users.', $count, 'torrehub' ), $draft['subject'], number_format_i18n( $count ) ) );
					?>
				</p>
				<iframe title="<?php esc_attr_e( 'E-mail preview', 'torrehub' ); ?>" style="width:100%;max-width:720px;height:520px;border:1px solid #c3c4c7;background:#fff" sandbox srcdoc="<?php echo esc_attr( Mailer::preview( $heading, $paras, $button, $foot, $extra ) ); ?>"></iframe>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Sending', 'torrehub' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="th_broadcast">
				<input type="hidden" name="do" value="settings">
				<?php wp_nonce_field( 'th_broadcast' ); ?>
				<p>
					<label><?php esc_html_e( 'E-mails per batch', 'torrehub' ); ?> <input type="number" class="small-text" name="batch" min="1" max="500" value="<?php echo esc_attr( (string) $settings['batch'] ); ?>"></label>
					&nbsp;
					<label><?php esc_html_e( 'Minutes between batches', 'torrehub' ); ?> <input type="number" class="small-text" name="interval" min="1" max="60" value="<?php echo esc_attr( (string) $settings['interval'] ); ?>"></label>
					<?php submit_button( __( 'Save', 'torrehub' ), 'secondary', 'submit', false ); ?>
				</p>
				<p class="description"><?php esc_html_e( 'Batches run on WP-Cron. Keep them small enough for your mail service’s hourly limit.', 'torrehub' ); ?></p>
			</form>

			<?php $this->campaigns(); ?>
			<?php Notice::settings_form(); ?>
		</div>
		<?php
	}

	/**
	 * Campaign table.
	 */
	private function campaigns(): void {
		$rows = Queue::all();
		if ( ! $rows ) {
			return;
		}
		$labels = array(
			'running'   => __( 'Sending', 'torrehub' ),
			'paused'    => __( 'Paused', 'torrehub' ),
			'cancelled' => __( 'Cancelled', 'torrehub' ),
			'done'      => __( 'Finished', 'torrehub' ),
		);
		?>
		<h2><?php esc_html_e( 'Messages sent', 'torrehub' ); ?></h2>
		<table class="widefat striped th-broadcast__campaigns">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Subject', 'torrehub' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Started', 'torrehub' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'torrehub' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Progress', 'torrehub' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'torrehub' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $c ) : ?>
					<?php
					$counts = Queue::counts( (int) $c->id );
					$bad    = $counts['failed'] + $counts['unknown'] + $counts['skipped'];
					$next   = wp_next_scheduled( Queue::HOOK, array( (int) $c->id ) );
					$button = static function ( string $todo, string $label, string $extra_class = '' ) use ( $c ): void {
						?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
							<input type="hidden" name="action" value="th_broadcast">
							<input type="hidden" name="do" value="<?php echo esc_attr( $todo ); ?>">
							<input type="hidden" name="campaign" value="<?php echo esc_attr( (string) $c->id ); ?>">
							<?php wp_nonce_field( 'th_broadcast' ); ?>
							<button type="submit" class="button button-small <?php echo esc_attr( $extra_class ); ?>"><?php echo esc_html( $label ); ?></button>
						</form>
						<?php
					};
	?>
					<tr data-campaign="<?php echo esc_attr( (string) $c->id ); ?>">
						<td>
							<strong><?php echo esc_html( (string) $c->subject ); ?></strong>
							<?php if ( '' !== (string) $c->roles ) : ?>
								<br><span style="color:#646970"><?php echo esc_html( implode( ', ', array_map( static fn( $r ) => translate_user_role( wp_roles()->get_names()[ $r ] ?? $r ), explode( ',', (string) $c->roles ) ) ) ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) get_date_from_gmt( (string) $c->created_at, 'U' ) ) ); ?></td>
						<td class="th-broadcast__status">
							<?php echo esc_html( $labels[ $c->status ] ?? $c->status ); ?>
							<?php if ( 'running' === $c->status && $next ) : ?>
								<br><span style="color:#646970">
								<?php
								/* translators: %s: time */
								echo esc_html( sprintf( __( 'next batch %s', 'torrehub' ), wp_date( get_option( 'time_format' ), $next ) ) );
								?>
								</span>
							<?php endif; ?>
						</td>
						<td class="th-broadcast__progress">
							<?php
							/* translators: 1: sent, 2: total */
							echo esc_html( sprintf( __( '%1$s of %2$s sent', 'torrehub' ), number_format_i18n( $counts['sent'] ), number_format_i18n( (int) $c->total ) ) );
							if ( $bad ) {
								/* translators: %s: number of e-mails */
								echo '<br><span style="color:#b3261e">' . esc_html( sprintf( _n( '%s not delivered', '%s not delivered', $bad, 'torrehub' ), number_format_i18n( $bad ) ) ) . '</span>';
							}
							?>
						</td>
						<td>
							<?php
							if ( 'running' === $c->status ) {
								$button( 'now', __( 'Send next batch now', 'torrehub' ) );
								$button( 'pause', __( 'Pause', 'torrehub' ) );
							} elseif ( 'paused' === $c->status ) {
								$button( 'resume', __( 'Resume', 'torrehub' ), 'button-primary' );
							}
							if ( in_array( $c->status, array( 'running', 'paused' ), true ) ) {
								$button( 'cancel', __( 'Cancel', 'torrehub' ) );
							}
							if ( $counts['failed'] && 'cancelled' !== $c->status ) {
								$button( 'retry', __( 'Retry failed', 'torrehub' ) );
							}
							?>
						</td>
					</tr>
					<tr>
						<td colspan="5">
							<details>
								<summary><?php esc_html_e( 'Log and problems', 'torrehub' ); ?></summary>
								<ul class="th-broadcast__log">
									<?php foreach ( (array) json_decode( (string) $c->log, true ) as $line ) : ?>
										<li><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $line[0] ) . ' — ' . (string) $line[1] ); ?></li>
									<?php endforeach; ?>
								</ul>
								<?php $problems = Queue::problems( (int) $c->id ); ?>
								<?php if ( $problems ) : ?>
									<table class="widefat" style="max-width:900px">
										<thead><tr><th><?php esc_html_e( 'User', 'torrehub' ); ?></th><th><?php esc_html_e( 'Status', 'torrehub' ); ?></th><th><?php esc_html_e( 'Reason', 'torrehub' ); ?></th></tr></thead>
										<tbody>
											<?php foreach ( $problems as $p ) : ?>
												<?php $u = get_userdata( (int) $p->user_id ); ?>
												<tr>
													<td><?php echo $u ? '<a href="' . esc_url( get_edit_user_link( (int) $p->user_id ) ) . '">' . esc_html( $u->user_login ) . '</a>' : esc_html( '#' . $p->user_id ); ?></td>
													<td><?php echo esc_html( (string) $p->status ); ?></td>
													<td><?php echo esc_html( (string) $p->error ); ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>
							</details>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
