<?php
/**
 * Head meta when no SEO plugin is active (brief §7: never override Yoast / Rank Math & co.): description, archive
 * canonicals, Open Graph / Twitter card, Organization + WebSite JSON-LD on the front page. Always: a core sitemap
 * without the users list and without the pages that are never indexed.
 * Page-type schema lives with its module (Listing\Schema, Guides Article, Content FAQPage).
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
 * Is the request a listing archive rendered by the theme?
 */
function th_is_listing_archive(): bool {
	return class_exists( '\Torrehub\Modules\Archive\Module' ) && Torrehub\Modules\Archive\Module::is_archive();
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
	} elseif ( is_home() ) {
		$page = (int) get_option( 'page_for_posts' );
		$text = $page && has_excerpt( $page ) ? get_the_excerpt( $page ) : '';
		if ( '' === $text ) {
			/* translators: 1: guides page title ("Living and getting things done on the Costa Blanca"), 2: site name */
			$text = sprintf( __( '%1$s — practical guides from %2$s.', 'torrehub' ), (string) th_mod( 'th_guides_title' ), get_bloginfo( 'name' ) );
		}
	} elseif ( ! is_tax() && th_is_listing_archive() ) {
		/* translators: %s: site name */
		$text = sprintf( __( 'Browse local listings on %s: tradespeople, homes, cars, jobs, restaurants and events across the Costa Blanca.', 'torrehub' ), get_bloginfo( 'name' ) );
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$text = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = (string) term_description();
		$term = get_queried_object();
		if ( '' === trim( wp_strip_all_tags( $text ) ) && $term instanceof WP_Term ) {
			$text = is_category() || is_tag()
				/* translators: 1: guide topic, 2: site name */
				? sprintf( __( 'Guides about %1$s from %2$s — living and getting things done on the Costa Blanca.', 'torrehub' ), $term->name, get_bloginfo( 'name' ) )
				/* translators: 1: category or town, 2: site name */
				: sprintf( __( '%1$s on %2$s — local listings across the Costa Blanca.', 'torrehub' ), $term->name, get_bloginfo( 'name' ) );
		}
	}
	$text = (string) preg_replace( '/\[\/?[a-z][a-z0-9_-]*[^\]]*\]/i', ' ', $text ); // Shortcodes of removed plugins.
	$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES ) ) ) );
	return '' === $text ? '' : wp_html_excerpt( $text, 155, '…' );
}

/**
 * Canonical URL of an archive (core prints it for singular content only); '' when the page isn't indexable —
 * filtered / sorted listing results are noindex and point nowhere.
 */
function th_archive_canonical(): string {
	if ( is_singular() || is_404() || is_search() ) {
		return '';
	}
	if ( th_is_listing_archive() ) {
		$robots = (array) apply_filters( 'wp_robots', array() );
		return empty( $robots['noindex'] ) ? Torrehub\Modules\Archive\Search::current()->url() : '';
	}
	$url = '';
	if ( is_home() ) {
		$page = (int) get_option( 'page_for_posts' );
		$url  = $page ? (string) get_permalink( $page ) : home_url( '/' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$link = get_term_link( get_queried_object() );
		$url  = is_wp_error( $link ) ? '' : $link;
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( '' !== $url && $paged > 1 ) {
		$url = user_trailingslashit( trailingslashit( $url ) . 'page/' . $paged, 'paged' );
	}
	return $url;
}

/**
 * Share image: the listing's / post's first image, else the site icon, else the brand logo.
 */
function th_share_image(): string {
	$id   = 0;
	$post = is_singular() ? get_queried_object() : null;
	if ( $post instanceof WP_Post ) {
		if ( 'rtcl_listing' === $post->post_type && class_exists( '\Rtcl\Helpers\Functions' ) ) {
			$images = \Rtcl\Helpers\Functions::get_listing_images( $post->ID );
			$first  = $images ? reset( $images ) : null;
			$id     = $first ? (int) $first->ID : 0;
		}
		if ( ! $id && has_post_thumbnail( $post ) ) {
			$id = (int) get_post_thumbnail_id( $post );
		}
	}
	$url = $id ? (string) wp_get_attachment_image_url( $id, 'large' ) : '';
	if ( '' === $url ) {
		$url = (string) get_site_icon_url( 512 );
	}
	/**
	 * Share image (og:image) of the current page.
	 *
	 * @param string $url Image URL ('' = the brand logo).
	 */
	$url = (string) apply_filters( 'th_share_image', $url );
	return '' !== $url ? $url : TH_URI . '/assets/img/torrehub-logo.png';
}

/**
 * Organization + WebSite graph for the front page (the site search runs on the listings archive).
 *
 * @return array<int,array<string,mixed>>
 */
function th_site_graph(): array {
	$home    = home_url( '/' );
	$name    = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$logo_id = th_mod( 'th_use_custom_logo' ) ? (int) get_theme_mod( 'custom_logo' ) : 0;
	$logo    = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	$org     = array(
		'@type' => 'Organization',
		'@id'   => $home . '#organization',
		'name'  => $name,
		'url'   => $home,
		'logo'  => '' !== $logo ? $logo : TH_URI . '/assets/img/torrehub-logo.png',
	);
	$email   = sanitize_email( (string) th_mod( 'th_contact_email' ) );
	if ( '' !== $email ) {
		$org['email'] = $email;
	}
	$same = array_values( array_filter( array( (string) th_mod( 'th_social_facebook' ), (string) th_mod( 'th_social_instagram' ) ) ) );
	if ( $same ) {
		$org['sameAs'] = $same;
	}
	$site = array(
		'@type'      => 'WebSite',
		'@id'        => $home . '#website',
		'url'        => $home,
		'name'       => $name,
		'inLanguage' => get_bloginfo( 'language' ),
		'publisher'  => array( '@id' => $home . '#organization' ),
	);
	if ( function_exists( 'th_url_listings' ) ) {
		$site['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => th_url_listings() . '?q={search_term_string}',
			),
			'query-input' => 'required name=search_term_string',
		);
	}
	return array( $org, $site );
}

/**
 * The page's own title part of the last document title built (after every module's document_title_parts filter).
 *
 * @param string|null $set Value to store (from the filter below).
 */
function th_document_title_part( ?string $set = null ): string {
	static $title = '';
	if ( null !== $set ) {
		$title = $set;
	}
	return $title;
}

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		th_document_title_part( wp_strip_all_tags( (string) ( $parts['title'] ?? '' ) ) );
		return $parts;
	},
	PHP_INT_MAX
);

/**
 * Open Graph / Twitter card tags of an indexable page.
 *
 * @param string $canonical Archive canonical ('' on singular pages).
 * @return array<string,string>
 */
function th_share_tags( string $canonical ): array {
	$title = wp_get_document_title();
	if ( ! is_front_page() ) {
		$title = th_document_title_part(); // Without " – Torrehub": og:site_name carries it.
	}
	$image = th_share_image();
	$tags  = array(
		'og:type'        => is_singular( 'post' ) ? 'article' : 'website',
		'og:site_name'   => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'og:locale'      => get_locale(),
		'og:url'         => is_singular() ? (string) wp_get_canonical_url() : $canonical,
		'og:title'       => html_entity_decode( $title, ENT_QUOTES ),
		'og:description' => th_meta_description(),
		'og:image'       => $image,
	);
	if ( is_singular( 'post' ) ) {
		$tags['article:published_time'] = (string) get_post_time( 'c', true );
		$tags['article:modified_time']  = (string) get_post_modified_time( 'c', true );
	}
	$tags['twitter:card'] = str_contains( $image, '/assets/img/torrehub-logo' ) ? 'summary' : 'summary_large_image';
	return array_filter( $tags );
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
		$canonical = th_archive_canonical();
		if ( '' !== $canonical ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
		}
		$robots = (array) apply_filters( 'wp_robots', array() );
		if ( ! empty( $robots['noindex'] ) || is_404() ) {
			return;
		}
		foreach ( th_share_tags( $canonical ) as $key => $content ) {
			$is_url = in_array( $key, array( 'og:url', 'og:image' ), true );
			printf(
				'<meta %1$s="%2$s" content="%3$s">' . "\n",
				str_starts_with( $key, 'twitter:' ) ? 'name' : 'property',
				esc_attr( $key ),
				$is_url ? esc_url( $content ) : esc_attr( $content )
			);
		}
		if ( is_front_page() ) {
			printf(
				'<script type="application/ld+json">%s</script>' . "\n",
				wp_json_encode(
					array(
						'@context' => 'https://schema.org',
						'@graph'   => th_site_graph(),
					),
					JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
				)
			);
		}
	},
	2
);

// Classified Listing prints its own listing-only Open Graph tags; the theme's cover every page.
add_action(
	'wp',
	static function () {
		if ( class_exists( '\Rtcl\Controllers\PageController' ) && ! th_seo_plugin_active() ) {
			remove_action( 'wp_head', array( \Rtcl\Controllers\PageController::class, 'og_metatags' ), 1 );
		}
	}
);

/**
 * Pages that are never indexed (account, listing form, checkout, login / register / password).
 *
 * @return int[]
 */
function th_noindex_page_ids(): array {
	$ids = array();
	if ( class_exists( '\Rtcl\Helpers\Functions' ) ) {
		foreach ( array( 'myaccount', 'listing_form', 'checkout' ) as $key ) {
			$ids[] = (int) \Rtcl\Helpers\Functions::get_page_id( $key );
		}
	}
	if ( class_exists( '\Torrehub\Modules\Auth\Pages' ) ) {
		foreach ( array_keys( Torrehub\Modules\Auth\Pages::defs() ) as $key ) {
			$ids[] = Torrehub\Modules\Auth\Pages::id( (string) $key );
		}
	}
	/**
	 * Page IDs kept out of the sitemap.
	 *
	 * @param int[] $ids Page IDs.
	 */
	$ids = (array) apply_filters( 'th_noindex_page_ids', $ids );
	return array_values( array_filter( array_unique( array_map( 'intval', $ids ) ) ) );
}

// Core sitemap: no users list (it exposes login names), no pages that are never indexed.
add_filter(
	'wp_sitemaps_add_provider',
	static fn( $provider, $name ) => 'users' === $name ? false : $provider,
	10,
	2
);
add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), th_noindex_page_ids() );
		}
		return $args;
	},
	10,
	2
);
