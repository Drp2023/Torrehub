<?php
/**
 * Customizer: home blocks (on/off + copy), footer, default town.
 *
 * Copy fields are optional overrides: empty = the translatable default from th_mod_defaults().
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Translatable defaults (resolved at read time so the active locale applies).
 *
 * @return array<string,mixed>
 */
function th_mod_defaults(): array {
	return array(
		'th_home_hero'           => true,
		'th_home_weekend'        => true,
		'th_home_stats'          => true,
		'th_home_explore'        => true,
		'th_home_new'            => true,
		'th_home_towns'          => true,
		'th_home_sellers'        => true,
		'th_home_why'            => true,
		'th_hero_title'          => __( 'What’s around you, all in one place.', 'torrehub' ),
		'th_hero_lead'           => __( 'Tradespeople, homes, cars, jobs, restaurants and what’s on this weekend — from Alicante down to Pilar de la Horadada.', 'torrehub' ),
		'th_hero_chips'          => __( 'Plumber, Rentals, Beach bars, Jobs', 'torrehub' ),
		// Empty = automatic: the free-listing allowance while the Quota module is on (th_sellers_title()).
		'th_sellers_title'       => '',
		'th_sellers_text'        => '', // Empty: th_sellers_text() follows the lifetime settings.
		'th_sellers_link'        => '',
		'th_why_1_title'         => __( 'Search where you actually are', 'torrehub' ),
		'th_why_1_text'          => __( 'Every listing is tied to a Costa Blanca town.', 'torrehub' ),
		'th_why_2_title'         => __( 'Verified sellers', 'torrehub' ),
		'th_why_2_text'          => __( 'Every account is checked by a person before it goes live.', 'torrehub' ),
		'th_why_3_title'         => __( 'Contact directly', 'torrehub' ),
		'th_why_3_text'          => __( 'Phone, WhatsApp, chat or email. No middleman taking a cut.', 'torrehub' ),
		'th_footer_blurb'        => __( 'The Costa Blanca local hub — directory, classifieds and local discovery in one place.', 'torrehub' ),
		'th_social_facebook'     => '',
		'th_social_instagram'    => '',
		'th_default_town'        => 'torrevieja',
		'th_archive_per_page'    => 12,
		'th_contact_email_login' => true,
		'th_contact_phone_login' => false,
		'th_safety_title'        => __( 'Staying safe', 'torrehub' ),
		'th_safety_text'         => __( 'Meet in a public place or agree a written quote before work starts. Never pay in advance for something you haven’t seen. Torrehub is a directory: we don’t take part in deals or verify every claim.', 'torrehub' ),
		'th_use_custom_logo'     => false,
		'th_chat_reply_time'     => false,
		'th_guides_title'        => __( 'Living and getting things done on the Costa Blanca', 'torrehub' ),
		// DECISION: the address from the old Contact page; office and hours stay empty (hidden) until the client fills them.
		'th_contact_email'       => 'info@torrehub.com',
		'th_contact_office'      => '',
		'th_contact_hours'       => '',
		'th_contact_to'          => '',
	);
}

/**
 * Theme mod with the translatable default.
 *
 * @param string $key Mod key.
 * @return mixed
 */
function th_mod( string $key ) {
	$defaults = th_mod_defaults();
	$value    = get_theme_mod( $key, null );
	if ( null === $value || '' === $value ) {
		return $defaults[ $key ] ?? '';
	}
	return $value;
}

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$defaults = th_mod_defaults();

		$wp_customize->add_panel(
			'th_panel',
			array(
				'title'    => __( 'Torrehub', 'torrehub' ),
				'priority' => 30,
			)
		);

		// Home blocks.
		$wp_customize->add_section(
			'th_home',
			array(
				'title' => __( 'Home page', 'torrehub' ),
				'panel' => 'th_panel',
			)
		);
		$blocks = array(
			'th_home_hero'    => __( 'Show hero search', 'torrehub' ),
			'th_home_weekend' => __( 'Show “This weekend”', 'torrehub' ),
			'th_home_stats'   => __( 'Show stats card', 'torrehub' ),
			'th_home_explore' => __( 'Show “Explore the hub”', 'torrehub' ),
			'th_home_new'     => __( 'Show “New near you”', 'torrehub' ),
			'th_home_towns'   => __( 'Show “Towns worth exploring”', 'torrehub' ),
			'th_home_sellers' => __( 'Show seller call-to-action', 'torrehub' ),
			'th_home_why'     => __( 'Show “Why Torrehub”', 'torrehub' ),
		);
		foreach ( $blocks as $key => $label ) {
			$wp_customize->add_setting(
				$key,
				array(
					'default'           => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
				)
			);
			$wp_customize->add_control(
				$key,
				array(
					'label'   => $label,
					'section' => 'th_home',
					'type'    => 'checkbox',
				)
			);
		}

		$texts = array(
			'th_hero_title'    => array( __( 'Hero title', 'torrehub' ), 'text' ),
			'th_hero_lead'     => array( __( 'Hero lead', 'torrehub' ), 'textarea' ),
			'th_hero_chips'    => array( __( 'Suggestion chips (comma separated)', 'torrehub' ), 'text' ),
			'th_sellers_title' => array( __( 'Seller CTA title (empty: automatic, follows the free-listing allowance)', 'torrehub' ), 'text' ),
			'th_sellers_text'  => array( __( 'Seller CTA text (empty: automatic, follows the listing lifetime)', 'torrehub' ), 'textarea' ),
			'th_sellers_link'  => array( __( '“How it works” URL', 'torrehub' ), 'url' ),
			'th_why_1_title'   => array( __( 'Why Torrehub · 1 title', 'torrehub' ), 'text' ),
			'th_why_1_text'    => array( __( 'Why Torrehub · 1 text', 'torrehub' ), 'text' ),
			'th_why_2_title'   => array( __( 'Why Torrehub · 2 title', 'torrehub' ), 'text' ),
			'th_why_2_text'    => array( __( 'Why Torrehub · 2 text', 'torrehub' ), 'text' ),
			'th_why_3_title'   => array( __( 'Why Torrehub · 3 title', 'torrehub' ), 'text' ),
			'th_why_3_text'    => array( __( 'Why Torrehub · 3 text', 'torrehub' ), 'text' ),
		);
		foreach ( $texts as $key => list( $label, $type ) ) {
			$wp_customize->add_setting(
				$key,
				array(
					'default'           => '',
					'sanitize_callback' => 'url' === $type ? 'esc_url_raw' : ( 'textarea' === $type ? 'sanitize_textarea_field' : 'sanitize_text_field' ),
				)
			);
			$wp_customize->add_control(
				$key,
				array(
					'label'       => $label,
					'section'     => 'th_home',
					'type'        => $type,
					/* translators: %s: default text */
					'description' => 'url' === $type ? '' : sprintf( __( 'Empty = “%s”', 'torrehub' ), wp_trim_words( (string) $defaults[ $key ], 12 ) ),
				)
			);
		}

		// Logo source (Site Identity).
		$wp_customize->add_setting(
			'th_use_custom_logo',
			array(
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		$wp_customize->add_control(
			'th_use_custom_logo',
			array(
				'label'    => __( 'Use the logo above instead of the Torrehub brand logo', 'torrehub' ),
				'section'  => 'title_tagline',
				'type'     => 'checkbox',
				'priority' => 9,
			)
		);

		// Footer.
		$wp_customize->add_section(
			'th_footer',
			array(
				'title' => __( 'Footer', 'torrehub' ),
				'panel' => 'th_panel',
			)
		);
		$footer = array(
			'th_footer_blurb'     => array( __( 'Footer text', 'torrehub' ), 'textarea' ),
			'th_social_facebook'  => array( __( 'Facebook URL', 'torrehub' ), 'url' ),
			'th_social_instagram' => array( __( 'Instagram URL', 'torrehub' ), 'url' ),
		);
		foreach ( $footer as $key => list( $label, $type ) ) {
			$wp_customize->add_setting(
				$key,
				array(
					'default'           => '',
					'sanitize_callback' => 'url' === $type ? 'esc_url_raw' : 'sanitize_textarea_field',
				)
			);
			$wp_customize->add_control(
				$key,
				array(
					'label'   => $label,
					'section' => 'th_footer',
					'type'    => $type,
				)
			);
		}

		// Location.
		$wp_customize->add_section(
			'th_location',
			array(
				'title' => __( 'Location', 'torrehub' ),
				'panel' => 'th_panel',
			)
		);
		$choices = array();
		if ( th_has_rtcl() ) {
			foreach ( Torrehub\Data\Directory::towns() as $town ) {
				$choices[ $town['slug'] ] = $town['name'];
			}
		}
		$wp_customize->add_setting(
			'th_default_town',
			array(
				'default'           => 'torrevieja',
				'sanitize_callback' => static fn( $v ) => isset( $choices[ $v ] ) ? $v : 'torrevieja',
			)
		);
		$wp_customize->add_control(
			'th_default_town',
			array(
				'label'       => __( 'Default town', 'torrehub' ),
				'description' => __( 'Used until the visitor picks a town.', 'torrehub' ),
				'section'     => 'th_location',
				'type'        => 'select',
				'choices'     => $choices,
			)
		);

		// Listing archive.
		$wp_customize->add_section(
			'th_archive',
			array(
				'title'       => __( 'Listing archive', 'torrehub' ),
				'description' => __( 'Which filters each category offers is set under Appearance → Archive filters.', 'torrehub' ),
				'panel'       => 'th_panel',
			)
		);
		$wp_customize->add_setting(
			'th_archive_per_page',
			array(
				'default'           => 12,
				'sanitize_callback' => static fn( $v ) => min( 48, max( 3, absint( $v ) ) ),
			)
		);
		$wp_customize->add_control(
			'th_archive_per_page',
			array(
				'label'       => __( 'Listings per page', 'torrehub' ),
				'description' => __( '“Load more” adds this many at a time. Multiples of 3 fill the desktop grid.', 'torrehub' ),
				'section'     => 'th_archive',
				'type'        => 'number',
				'input_attrs' => array(
					'min'  => 3,
					'max'  => 48,
					'step' => 3,
				),
			)
		);

		// Listing page.
		$wp_customize->add_section(
			'th_listing',
			array(
				'title' => __( 'Listing page', 'torrehub' ),
				'panel' => 'th_panel',
			)
		);
		// DECISION (CLIENT A5, open): design says "Chat and email need an account. Phone and WhatsApp are open to everyone".
		foreach ( array(
			'th_contact_email_login' => array( __( 'E-mail enquiries need an account', 'torrehub' ), true ),
			'th_contact_phone_login' => array( __( 'Phone and WhatsApp need an account', 'torrehub' ), false ),
		) as $key => $conf ) {
			$wp_customize->add_setting(
				$key,
				array(
					'default'           => $conf[1],
					'sanitize_callback' => 'rest_sanitize_boolean',
				)
			);
			$wp_customize->add_control(
				$key,
				array(
					'label'   => $conf[0],
					'section' => 'th_listing',
					'type'    => 'checkbox',
				)
			);
		}
		$wp_customize->add_setting(
			'th_safety_title',
			array(
				'default'           => th_mod_defaults()['th_safety_title'],
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'th_safety_title',
			array(
				'label'   => __( 'Safety note — title', 'torrehub' ),
				'section' => 'th_listing',
			)
		);
		$wp_customize->add_setting(
			'th_safety_text',
			array(
				'default'           => th_mod_defaults()['th_safety_text'],
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);
		$wp_customize->add_control(
			'th_safety_text',
			array(
				'label'       => __( 'Safety note — text', 'torrehub' ),
				'description' => __( 'Shown under the seller card.', 'torrehub' ),
				'section'     => 'th_listing',
				'type'        => 'textarea',
			)
		);
	}
);

/* Guides & static pages. */
add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_section(
			'th_pages',
			array(
				'title' => __( 'Guides & pages', 'torrehub' ),
				'panel' => 'th_panel',
			)
		);
		$fields = array(
			'th_guides_title'   => array( __( 'Guides — page title', 'torrehub' ), 'text', 'sanitize_text_field', '' ),
			'th_contact_email'  => array( __( 'Contact — public e-mail address', 'torrehub' ), 'email', 'sanitize_email', __( 'Shown on the Contact page.', 'torrehub' ) ),
			'th_contact_to'     => array( __( 'Contact form — send messages to', 'torrehub' ), 'email', 'sanitize_email', __( 'Empty: the site administration e-mail.', 'torrehub' ) ),
			'th_contact_office' => array( __( 'Contact — office address', 'torrehub' ), 'textarea', 'sanitize_textarea_field', __( 'Empty: not shown.', 'torrehub' ) ),
			'th_contact_hours'  => array( __( 'Contact — opening hours', 'torrehub' ), 'text', 'sanitize_text_field', __( 'Empty: not shown.', 'torrehub' ) ),
		);
		foreach ( $fields as $key => $conf ) {
			$wp_customize->add_setting(
				$key,
				array(
					'default'           => th_mod_defaults()[ $key ],
					'sanitize_callback' => $conf[2],
				)
			);
			$wp_customize->add_control(
				$key,
				array(
					'label'       => $conf[0],
					'type'        => $conf[1],
					'section'     => 'th_pages',
					'description' => $conf[3],
				)
			);
		}
	}
);

/**
 * Home "For sellers" title: the Customizer text when set; otherwise the free-listing allowance while the Quota
 * module is on ("5 free listings every 30 days"), and a plain line while it is off (no allowance to promise).
 */
function th_sellers_title(): string {
	$custom = trim( (string) get_theme_mod( 'th_sellers_title', '' ) );
	if ( '' !== $custom ) {
		return $custom;
	}
	$quota = Torrehub\Core\Theme::instance()->module( 'quota' );
	if ( $quota && Torrehub\Core\Settings::module_enabled( $quota ) ) {
		$s = Torrehub\Modules\Quota\Module::settings();
		/* translators: 1: number of listings, 2: number of days */
		return sprintf( _n( '%1$s free listing every %2$s days', '%1$s free listings every %2$s days', $s['limit'], 'torrehub' ), number_format_i18n( $s['limit'] ), number_format_i18n( $s['days'] ) );
	}
	return __( 'Post your listings for free', 'torrehub' );
}

/**
 * Text of the home page's seller call-to-action: the Customizer text, else one that follows the lifetime settings
 * ("… Listings run 15 days (30 for businesses) and renew for free in one tap …").
 */
function th_sellers_text(): string {
	$custom = trim( (string) get_theme_mod( 'th_sellers_text', '' ) );
	if ( '' !== $custom ) {
		return $custom;
	}
	$lifetime = Torrehub\Core\Theme::instance()->module( 'lifetime' );
	$duration = $lifetime && Torrehub\Core\Settings::module_enabled( $lifetime ) ? Torrehub\Modules\Lifetime\Module::duration_text() : '';
	return '' !== $duration
		/* translators: %s: "Listings run 15 days (30 for businesses)" */
		? sprintf( __( 'Private sellers just need an e-mail address, businesses add their NIF. %s and renew for free in one tap. No commission, no subscription.', 'torrehub' ), $duration )
		: __( 'Private sellers just need an e-mail address, businesses add their NIF. No commission, no subscription.', 'torrehub' );
}
