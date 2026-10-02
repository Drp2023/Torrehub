<?php
/**
 * Theme supports, menus, image sizes, head cleanup.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		load_theme_textdomain( 'torrehub', TH_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 64,
				'width'       => 260,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/tokens.css', 'assets/css/editor.css' ) );

		// Tells Classified Listing to use the theme template loader (classified-listing/ overrides). Required.
		add_theme_support( 'rtcl' );

		register_nav_menus(
			array(
				'footer-browse'   => __( 'Footer · Browse', 'torrehub' ),
				'footer-discover' => __( 'Footer · Discover', 'torrehub' ),
				'footer-account'  => __( 'Footer · Account', 'torrehub' ),
				'footer-torrehub' => __( 'Footer · Torrehub', 'torrehub' ),
			)
		);

		// 4:3 card images (brief §7) + gallery and town sizes. Sizes in px; 2× variants keep retina sharp.
		add_image_size( 'th-card', 640, 480, true );
		add_image_size( 'th-card-sm', 320, 240, true );
		add_image_size( 'th-thumb', 192, 144, true );        // dashboard row 96×72 @2x.
		add_image_size( 'th-gallery', 1200, 760, true );     // design: "Main · 1200×760".
		add_image_size( 'th-gallery-sm', 600, 380, true );
		add_image_size( 'th-town', 532, 400, true );         // town card 266×200 @2x.
		add_image_size( 'th-hero', 1440, 680, true );        // guide hero.
	}
);

/* Head cleanup — fewer requests, no emoji script. */
add_action(
	'init',
	static function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
	}
);

/* Skip link target + body classes. */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		$classes[] = 'th';
		if ( is_user_logged_in() ) {
			$classes[] = 'th-logged-in';
		}
		return $classes;
	}
);
