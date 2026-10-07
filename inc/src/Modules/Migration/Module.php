<?php
/**
 * Tools › Torrehub migration: the go-live runbook's data steps from wp-admin, for hosts without SSH / WP-CLI.
 * Every step is a dry run first (report on the page), then "Apply" — the same code as `wp torrehub …`. Also the
 * before/after data check and a maintenance switch that, unlike WordPress's own `.maintenance`, keeps wp-admin
 * working for the person doing the migration.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Migration;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Migration module.
 */
final class Module extends BaseModule {

	public const PAGE        = 'torrehub-migration';
	public const MAINTENANCE = 'th_maintenance';
	public const LOG         = 'th_migration_log';
	public const BASELINE    = 'th_migration_baseline';

	/** A dry run unlocks "Apply" for this long. */
	private const DRY_TTL = HOUR_IN_SECONDS;

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'migration';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Go-live migration', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Tools › Torrehub migration: the go-live data steps (dry run, then apply), data check and maintenance mode.', 'torrehub' );
	}

	/**
	 * Always on.
	 */
	public function optional(): bool {
		return false;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_th_migration', array( $this, 'handle' ) );
		add_action( 'admin_notices', array( $this, 'maintenance_notice' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
		add_action( 'template_redirect', array( $this, 'maintenance_gate' ), 0 );
	}

	/* ------------------------------------------------------------------ maintenance */

	/**
	 * Is maintenance mode on?
	 */
	public static function maintenance(): bool {
		return '1' === (string) get_option( self::MAINTENANCE, '' );
	}

	/**
	 * Switch maintenance mode.
	 *
	 * @param bool $on On.
	 */
	public static function set_maintenance( bool $on ): void {
		update_option( self::MAINTENANCE, $on ? '1' : '', true );
	}

	/**
	 * Visitors get a 503 "back soon" page; administrators and the login pages work as usual.
	 */
	public function maintenance_gate(): void {
		if ( ! self::maintenance() || current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( class_exists( '\Torrehub\Modules\Auth\Pages' ) && '' !== \Torrehub\Modules\Auth\Pages::current() ) {
			return; // Let the administrator log in.
		}
		status_header( 503 );
		header( 'Retry-After: 1800' );
		nocache_headers();
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'th_page_css_bundles', static fn( $bundles ) => array_merge( (array) $bundles, array( 'auth' ) ) );
		add_filter( 'pre_get_document_title', static fn() => __( 'Back soon', 'torrehub' ) . ' – ' . get_bloginfo( 'name' ) );
		get_template_part( 'template-parts/maintenance' );
		exit;
	}

	/**
	 * Reminder on every admin screen while visitors are locked out.
	 */
	public function maintenance_notice(): void {
		if ( ! self::maintenance() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Maintenance mode is on:', 'torrehub' ),
			esc_html__( 'visitors see the “Back soon” page.', 'torrehub' ),
			esc_url( admin_url( 'tools.php?page=' . self::PAGE . '#maintenance' ) ),
			esc_html__( 'Switch off', 'torrehub' )
		);
	}

	/**
	 * Admin bar flag (front end and admin).
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public function admin_bar( $bar ): void {
		if ( self::maintenance() && current_user_can( 'manage_options' ) ) {
			$bar->add_node(
				array(
					'id'    => 'th-maintenance',
					'title' => esc_html__( 'Maintenance mode on', 'torrehub' ),
					'href'  => admin_url( 'tools.php?page=' . self::PAGE . '#maintenance' ),
				)
			);
		}
	}

	/* ------------------------------------------------------------------ log, baseline */

	/**
	 * Record a run (also from WP-CLI), newest first, 5 per step.
	 *
	 * @param string $key    Step key.
	 * @param string $mode   dry|apply|rollback.
	 * @param Report $report Report.
	 * @param string $via    'wp-admin' or 'wp-cli'.
	 */
	public static function log( string $key, string $mode, Report $report, string $via ): void {
		$log  = (array) get_option( self::LOG, array() );
		$runs = (array) ( $log[ $key ] ?? array() );
		$who  = wp_get_current_user();
		array_unshift(
			$runs,
			array(
				'mode'    => $mode,
				'time'    => time(),
				'user'    => $who->exists() ? $who->user_login : '',
				'via'     => $via,
				'summary' => $report->summary,
				'failed'  => $report->failed,
			)
		);
		$log[ $key ] = array_slice( $runs, 0, 5 );
		update_option( self::LOG, $log, false );
	}

	/**
	 * Store the current counts as the "before" baseline.
	 */
	public static function save_baseline(): void {
		update_option(
			self::BASELINE,
			array(
				'time'   => time(),
				'counts' => Steps::data_counts(),
			),
			false
		);
	}

	/**
	 * The baseline (empty array when none).
	 *
	 * @return array{time?:int,counts?:array<string,int>}
	 */
	public static function baseline(): array {
		return (array) get_option( self::BASELINE, array() );
	}

	/* ------------------------------------------------------------------ admin */

	/**
	 * Tools › Torrehub migration.
	 */
	public function menu(): void {
		add_management_page( __( 'Torrehub migration', 'torrehub' ), __( 'Torrehub migration', 'torrehub' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * Transient key of a user's recent dry run of a step.
	 *
	 * @param string $key Step key.
	 */
	private static function dry_key( string $key ): string {
		return 'th_migration_dry_' . get_current_user_id() . '_' . $key;
	}

	/**
	 * Run what the form asked for, keep the result for the page, go back.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'torrehub' ), 403 );
		}
		check_admin_referer( 'th_migration' );
		$do     = isset( $_POST['th_do'] ) ? sanitize_key( wp_unslash( $_POST['th_do'] ) ) : '';
		$key    = isset( $_POST['th_step'] ) ? sanitize_key( wp_unslash( $_POST['th_step'] ) ) : '';
		$anchor = $key;
		$report = null;

		if ( in_array( $do, array( 'maintenance_on', 'maintenance_off' ), true ) ) {
			self::set_maintenance( 'maintenance_on' === $do );
			$anchor = 'maintenance';
		} elseif ( 'baseline' === $do ) {
			self::save_baseline();
			$anchor = 'data-check';
		} elseif ( in_array( $do, array( 'dry', 'apply', 'rollback' ), true ) && isset( Steps::all()[ $key ] ) ) {
			$step = Steps::all()[ $key ];
			if ( 'apply' === $do && ! get_transient( self::dry_key( $key ) ) ) {
				$report = new Report();
				$report->fail( __( 'Run the dry run first (it unlocks “Apply” for one hour).', 'torrehub' ) );
			} elseif ( 'apply' === $do && $step['destructive'] && empty( $_POST['th_backup'] ) ) {
				$report = new Report();
				$report->fail( __( 'Tick “I have a fresh database backup” to run this step.', 'torrehub' ) );
			} else {
				$report = Steps::run( $key, $do );
				self::log( $key, $do, $report, 'wp-admin' );
				if ( 'dry' === $do && ! $report->failed ) {
					set_transient( self::dry_key( $key ), time(), self::DRY_TTL );
				} elseif ( 'apply' === $do ) {
					delete_transient( self::dry_key( $key ) );
				}
			}
		}
		if ( $report ) {
			set_transient(
				'th_migration_result_' . get_current_user_id(),
				array(
					'key'    => $key,
					'mode'   => $do,
					'report' => $report->to_array(),
				),
				10 * MINUTE_IN_SECONDS
			);
		}
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE ) . ( $anchor ? '#' . $anchor : '' ) );
		exit;
	}

	/**
	 * Hidden fields + submit button of one action form.
	 *
	 * @param string $action  Action.
	 * @param string $key     Step key.
	 * @param string $label   Button label.
	 * @param string $button  Button class.
	 * @param string $confirm Confirmation text ('' = none).
	 * @param string $extra   Extra (escaped) markup before the button.
	 * @param bool   $enabled Clickable.
	 */
	private static function action_form( string $action, string $key, string $label, string $button, string $confirm = '', string $extra = '', bool $enabled = true ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="th-migration__form"<?php echo $confirm ? ' onsubmit="return window.confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');"' : ''; ?>>
			<input type="hidden" name="action" value="th_migration">
			<input type="hidden" name="th_do" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="th_step" value="<?php echo esc_attr( $key ); ?>">
			<?php wp_nonce_field( 'th_migration' ); ?>
			<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts by the caller. ?>
			<button type="submit" class="button <?php echo esc_attr( $button ); ?>"<?php disabled( ! $enabled ); ?>><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * A stored report.
	 *
	 * @param array  $report Report::to_array().
	 * @param string $mode  dry|apply|rollback.
	 */
	private static function print_report( array $report, string $mode ): void {
		$titles = array(
			'dry'      => __( 'Dry run — nothing was changed', 'torrehub' ),
			'apply'    => __( 'Applied', 'torrehub' ),
			'rollback' => __( 'Rolled back', 'torrehub' ),
		);
		$class  = $report['failed'] ? 'notice-error' : ( 'dry' === $mode ? 'notice-info' : 'notice-success' );
		?>
		<div class="notice inline <?php echo esc_attr( $class ); ?> th-migration__result">
			<p><strong><?php echo esc_html( $report['failed'] ? __( 'Not run', 'torrehub' ) : ( $titles[ $mode ] ?? '' ) ); ?>:</strong> <?php echo esc_html( $report['summary'] ); ?></p>
			<?php
			if ( $report['lines'] ) {
				$text = '';
				foreach ( $report['lines'] as $line ) {
					$text .= ( 'warning' === $line[0] ? '⚠ ' : '' ) . $line[1] . PHP_EOL;
				}
				echo '<pre>' . esc_html( $text ) . '</pre>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * The page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$result = get_transient( 'th_migration_result_' . get_current_user_id() );
		delete_transient( 'th_migration_result_' . get_current_user_id() );
		$log      = (array) get_option( self::LOG, array() );
		$baseline = self::baseline();
		$now      = Steps::data_counts();
		$fmt      = static fn( int $t ) => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t );
		?>
		<div class="wrap th-migration">
			<style>
				.th-migration .card { max-width: 920px; }
				.th-migration__actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 12px 0 4px; }
				.th-migration__form { display: flex; gap: 8px; align-items: center; margin: 0; }
				.th-migration__result pre { white-space: pre-wrap; max-height: 320px; overflow: auto; margin: 0 0 8px; }
				.th-migration__last { color: #50575e; }
				.th-migration__changed td { background: #fcf9e8; }
			</style>
			<h1><?php esc_html_e( 'Torrehub migration', 'torrehub' ); ?></h1>
			<p><?php esc_html_e( 'The go-live data steps of the runbook (GO-LIVE-RUNBOOK.md, sections C–D and G). Each step runs as a dry run first: it reports what it would change and writes nothing. “Apply” unlocks for one hour after a successful dry run. The steps are the same code as the “wp torrehub …” commands and can be run again safely.', 'torrehub' ); ?></p>
			<p><strong><?php esc_html_e( 'Before the first “Apply”: a full database + files backup.', 'torrehub' ); ?></strong></p>

			<h2><?php esc_html_e( 'Maintenance mode', 'torrehub' ); ?></h2>
			<div class="card" id="maintenance">
				<p>
					<?php
					echo esc_html(
						self::maintenance()
							? __( 'On — visitors see a “Back soon” page (HTTP 503); administrators use the site and wp-admin as usual.', 'torrehub' )
							: __( 'Off — the site is public.', 'torrehub' )
					);
					?>
				</p>
				<div class="th-migration__actions">
					<?php
					self::maintenance()
						? self::action_form( 'maintenance_off', '', __( 'Switch off', 'torrehub' ), 'button-primary' )
						: self::action_form( 'maintenance_on', '', __( 'Switch on', 'torrehub' ), 'button-secondary' );
					?>
				</div>
				<p class="description"><?php esc_html_e( 'WP-CLI: wp torrehub maintenance on|off', 'torrehub' ); ?></p>
			</div>

			<h2><?php esc_html_e( 'Data check (before / after)', 'torrehub' ); ?></h2>
			<div class="card" id="data-check">
				<p><?php esc_html_e( 'Counts only, no personal data. Save the baseline before the first step; afterwards the table shows what changed. Expected: NIE entries 0 (C6), old verification documents 0 (D4), chat and saved searches = the old tables, demo posts/pages in the trash (C5) — everything else unchanged.', 'torrehub' ); ?></p>
				<?php if ( $baseline ) : ?>
					<p class="th-migration__last">
						<?php
						/* translators: %s: date and time */
						echo esc_html( sprintf( __( 'Baseline saved %s.', 'torrehub' ), $fmt( (int) $baseline['time'] ) ) );
						?>
					</p>
				<?php endif; ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'What', 'torrehub' ); ?></th><th><?php esc_html_e( 'Baseline', 'torrehub' ); ?></th><th><?php esc_html_e( 'Now', 'torrehub' ); ?></th></tr></thead>
					<tbody>
						<?php
						$before = (array) ( $baseline['counts'] ?? array() );
						foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $now ) ) ) as $label ) :
							$was = $before[ $label ] ?? null;
							$is  = $now[ $label ] ?? 0;
							?>
							<tr<?php echo null !== $was && $was !== $is ? ' class="th-migration__changed"' : ''; ?>>
								<td><?php echo esc_html( $label ); ?></td>
								<td><?php echo esc_html( null === $was ? '—' : number_format_i18n( $was ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $is ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="th-migration__actions">
					<?php self::action_form( 'baseline', '', $baseline ? __( 'Save again as baseline', 'torrehub' ) : __( 'Save as baseline (before)', 'torrehub' ), 'button-secondary', $baseline ? __( 'Replace the saved baseline with the current counts?', 'torrehub' ) : '' ); ?>
				</div>
				<p class="description"><?php esc_html_e( 'WP-CLI: wp torrehub data-check [--save-baseline]', 'torrehub' ); ?></p>
			</div>

			<h2><?php esc_html_e( 'Steps', 'torrehub' ); ?></h2>
			<?php
			foreach ( Steps::all() as $key => $step ) :
				$blocker = Steps::blocker( $key );
				$dry     = get_transient( self::dry_key( $key ) );
				$last    = (array) ( $log[ $key ][0] ?? array() );
				?>
				<div class="card" id="<?php echo esc_attr( $key ); ?>">
					<h3><?php echo esc_html( $step['code'] . ' · ' . $step['title'] ); ?></h3>
					<p><?php echo esc_html( $step['help'] ); ?></p>
					<p class="description"><code>wp torrehub <?php echo esc_html( $key ); ?> [--apply]<?php echo $step['rollback'] ? ' [--rollback]' : ''; ?></code></p>
					<?php if ( $last ) : ?>
						<p class="th-migration__last">
							<?php
							$modes = array(
								'dry'      => __( 'dry run', 'torrehub' ),
								'apply'    => __( 'applied', 'torrehub' ),
								'rollback' => __( 'rolled back', 'torrehub' ),
							);
							/* translators: 1: run type (dry run / applied), 2: date and time, 3: user, 4: wp-admin or wp-cli, 5: summary */
							echo esc_html( sprintf( __( 'Last: %1$s %2$s by %3$s (%4$s) — %5$s', 'torrehub' ), ( $last['failed'] ?? false ) ? __( 'not run', 'torrehub' ) : ( $modes[ $last['mode'] ] ?? '' ), $fmt( (int) $last['time'] ), $last['user'] ? $last['user'] : '—', $last['via'], $last['summary'] ) );
							?>
						</p>
					<?php endif; ?>
					<?php if ( '' !== $blocker ) : ?>
						<div class="notice inline notice-warning"><p><?php echo esc_html( $blocker ); ?></p></div>
					<?php endif; ?>
					<?php
					if ( is_array( $result ) && $result['key'] === $key ) {
						self::print_report( (array) $result['report'], (string) $result['mode'] );
					}
					?>
					<div class="th-migration__actions">
						<?php
						self::action_form( 'dry', $key, __( 'Dry run', 'torrehub' ), 'button-secondary' );
						$backup = $step['destructive']
							? '<label><input type="checkbox" name="th_backup" value="1" required> ' . esc_html__( 'I have a fresh database backup', 'torrehub' ) . '</label>'
							: '';
						self::action_form(
							'apply',
							$key,
							__( 'Apply', 'torrehub' ),
							'button-primary',
							$step['destructive'] ? __( 'This deletes data for good (only the backup can bring it back). Continue?', 'torrehub' ) : '',
							$backup,
							(bool) $dry && '' === $blocker
						);
						if ( $step['rollback'] ) {
							self::action_form( 'rollback', $key, __( 'Roll back', 'torrehub' ), 'button-link-delete', __( 'Restore the pages as they were before the migration?', 'torrehub' ) );
						}
						?>
						<?php if ( ! $dry ) : ?>
							<span class="description"><?php esc_html_e( '“Apply” unlocks after a dry run.', 'torrehub' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
