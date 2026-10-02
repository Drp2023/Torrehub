<?php
/**
 * Appearance → Torrehub: module switches (feature settings get their own sections as modules arrive).
 *
 * @package Torrehub
 */

namespace Torrehub\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen + module enable/disable storage.
 */
final class Settings {

	public const OPTION = 'th_modules';
	private const PAGE  = 'torrehub';

	/**
	 * Set up with the theme kernel.
	 *
	 * @param Theme $theme Theme kernel.
	 */
	public function __construct( private Theme $theme ) {}

	/**
	 * Hook the admin page and setting.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Is a module switched on? Non-optional modules always are.
	 *
	 * @param Module $module Module.
	 */
	public static function module_enabled( Module $module ): bool {
		if ( ! $module->optional() ) {
			return true;
		}
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) && array_key_exists( $module->id(), $saved )
			? (bool) $saved[ $module->id() ]
			: $module->default_enabled();
	}

	/**
	 * Appearance → Torrehub.
	 */
	public function menu(): void {
		add_theme_page(
			__( 'Torrehub settings', 'torrehub' ),
			__( 'Torrehub', 'torrehub' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the th_modules option.
	 */
	public function register_setting(): void {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Keep only known optional module ids, as booleans.
	 *
	 * @param mixed $input Raw form input.
	 * @return array<string,bool>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		foreach ( $this->theme->modules() as $id => $module ) {
			if ( $module->optional() ) {
				$out[ $id ] = ! empty( $input[ $id ] );
			}
		}
		return $out;
	}

	/**
	 * Render the settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$skipped = $this->theme->skipped();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Torrehub settings', 'torrehub' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<h2><?php esc_html_e( 'Modules', 'torrehub' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( $this->theme->modules() as $id => $module ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $module->label() ); ?></th>
							<td>
								<?php if ( isset( $skipped[ $id ] ) ) : ?>
									<p><strong><?php esc_html_e( 'Unavailable:', 'torrehub' ); ?></strong> <?php echo esc_html( $skipped[ $id ] ); ?></p>
								<?php elseif ( ! $module->optional() ) : ?>
									<p><?php esc_html_e( 'Always on.', 'torrehub' ); ?></p>
								<?php else : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION . '[' . $id . ']' ); ?>" value="1" <?php checked( self::module_enabled( $module ) ); ?>>
										<?php esc_html_e( 'Enabled', 'torrehub' ); ?>
									</label>
								<?php endif; ?>
								<?php if ( $module->description() ) : ?>
									<p class="description"><?php echo esc_html( $module->description() ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
