<?php
/**
 * Minimal head meta when no SEO plugin is active (brief §7: never override Yoast / Rank Math & co.).
 * Phase 8 adds schema (Article, FAQPage, BreadcrumbList, ItemList); listing schema comes with the Reviews module.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is a dedicated SEO plugin handling meta tags?
 */
function th_seo_plugin_active(): bool {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'The_SEO_Framework\Load' );
}

/**
 * Description for the current request ('' when nothing sensible exists).
 */
function th_meta_description(): string {
	$text = '';
	if ( is_front_page() ) {
		$text = (string) th_mod( 'th_hero_lead' );
		if ( '' === $text ) {
			$text = (string) get_bloginfo( 'description' );
		}
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$text = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = (string) term_description();
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				/* translators: 1: term name, 2: site name */
				$text = sprintf( __( '%1$s on %2$s — local listings across the Costa Blanca.', 'torrehub' ), $term->name, get_bloginfo( 'name' ) );
			}
		}
	}
	$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	return '' === $text ? '' : wp_html_excerpt( $text, 155, '…' );
}

add_action(
	'wp_head',
	static function () {
		if ( th_seo_plugin_active() ) {
			return;
		}
		$description = th_meta_description();
		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		}
	},
	2
);
