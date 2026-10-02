<?php
/**
 * Keeps the free Classified Listing plugin whole once the paid add-ons are gone.
 *
 * - Form Builder `repeater` element: Pro-only in the field registry, but the free plugin already loads,
 *   validates and sanitises repeater values (FBHelper.php:557, 1195, 1444). Without the registry entry the
 *   React form skips the field and the save loop writes '' over the stored rows (data loss on every edit of a
 *   Service / Property listing). Registering the element restores the full chain.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\RtclCompat;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Free Classified Listing compatibility module.
 */
final class Module extends BaseModule {

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'rtcl-compat';
	}

	/**
	 * Settings label.
	 */
	public function label(): string {
		return __( 'Classified Listing compatibility', 'torrehub' );
	}

	/**
	 * Settings description.
	 */
	public function description(): string {
		return __( 'Restores Form Builder field types the free plugin can store but no longer registers (repeater).', 'torrehub' );
	}

	/**
	 * Always on.
	 */
	public function optional(): bool {
		return false;
	}

	/**
	 * Needs the Classified Listing plugin.
	 */
	public function requirements_met(): bool {
		return th_has_rtcl();
	}

	/**
	 * Settings-screen message when the plugin is missing.
	 */
	public function requirement_message(): string {
		return __( 'Requires the Classified Listing plugin.', 'torrehub' );
	}

	/**
	 * Hook everything up.
	 */
	public function register(): void {
		add_filter( 'rtcl_fb_fields', array( $this, 'register_repeater' ), 5 );
	}

	/**
	 * Add the repeater element unless something already provides it.
	 *
	 * @param array<string,array<string,mixed>> $fields Available Form Builder elements.
	 * @return array<string,array<string,mixed>>
	 */
	public function register_repeater( $fields ) {
		if ( ! is_array( $fields ) || isset( $fields['repeater'] ) ) {
			return $fields; // Pro (or someone else) already provides it.
		}

		$fields['repeater'] = array(
			'element'          => 'repeater',
			'css_class'        => '',
			'id'               => '',
			'label'            => __( 'Repeater', 'torrehub' ),
			'label_placement'  => '',
			'help_message'     => '',
			'logics'           => '',
			'max_repeat_field' => '',
			'single_view'      => true,
			'archive_view'     => false,
			'fields'           => array(
				array(
					'element'       => 'text',
					'name'          => 'text_input',
					'default_value' => '',
					'label'         => __( 'Text', 'torrehub' ),
					'placeholder'   => '',
					'validation'    => array(
						'required' => array(
							'value'   => false,
							'message' => __( 'This field is required', 'torrehub' ),
						),
					),
				),
			),
			'editor'           => array(
				'title'      => __( 'Repeater', 'torrehub' ),
				'icon_class' => 'rtcl-icon-ccw',
				'template'   => 'repeater', // Template name understood by the RTCL React bundle.
			),
		);

		return $fields;
	}
}
