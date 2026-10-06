<?php
/**
 * Guides (B-01 index, B-02 article) — the blog, presented as local guides.
 *
 * - DECISION (open question Q2): a "Guides" page at /guides/ is created and set as the posts page when no posts page
 *   is set (Settings › Reading can point it elsewhere). The posts keep their URLs and categories.
 * - Featured guide = the newest sticky post, else the newest post.
 * - Article: reading time (200 words/min), "In this guide" from the h2 headings (ids added on output), share/print,
 *   Article JSON-LD. "Related listing categories" box on the post editor → the "Need help with this?" card and the
 *   "Mentioned in this guide" tiles with live listing counts.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Guides;

use Torrehub\Core\Module as BaseModule;

defined( 'ABSPATH' ) || exit;

/**
 * Guides module.
 */
final class Module extends BaseModule {

	public const META_CATS = 'th_guide_categories';

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'guides';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return __( 'Guides', 'torrehub' );
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return __( 'Blog index and articles in the Guides layout, with related listing categories.', 'torrehub' );
	}

	/**
	 * Core building block (it is the blog's template).
	 */
	public function optional(): bool {
		return false;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'ensure_page' ) );
		add_action( 'pre_get_posts', array( $this, 'per_page' ) );
		add_filter( 'the_content', array( $this, 'heading_ids' ), 20 );
		add_filter( 'th_page_css_bundles', array( $this, 'css_bundles' ) );
		add_action( 'wp_head', array( $this, 'schema' ), 30 );
		add_action( 'add_meta_boxes_post', array( $this, 'meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Is the Guides index / a guide category / a guide being viewed?
	 */
	public static function is_guides(): bool {
		return is_home() || is_category() || is_tag() || is_singular( 'post' );
	}

	/**
	 * Guides index URL.
	 */
	public static function url(): string {
		return th_url_guides();
	}

	/**
	 * Create /guides/ and make it the posts page when none is set.
	 */
	public function ensure_page(): void {
		if ( (int) get_option( 'page_for_posts' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = get_page_by_path( 'guides' );
		$id   = $page ? (int) $page->ID : (int) wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => __( 'Guides', 'torrehub' ),
				'post_name'   => 'guides',
			)
		);
		if ( $id && (int) get_option( 'page_on_front' ) !== $id ) {
			update_option( 'page_for_posts', $id );
		}
	}

	/**
	 * 10 guides per page on the index and category pages.
	 *
	 * @param \WP_Query $q Query.
	 */
	public function per_page( $q ): void {
		if ( ! is_admin() && $q->is_main_query() && ( $q->is_home() || $q->is_category() || $q->is_tag() ) ) {
			$q->set( 'posts_per_page', 10 );
		}
	}

	/**
	 * Content bundle (guides; static pages and 404 add it in the Content module).
	 *
	 * @param array<int,string> $bundles Bundles.
	 * @return array<int,string>
	 */
	public function css_bundles( $bundles ): array {
		$bundles = (array) $bundles;
		if ( self::is_guides() ) {
			$bundles[] = 'content';
		}
		return $bundles;
	}

	/* ------------------------------------------------------------------ article helpers */

	/**
	 * Reading time in minutes (200 words a minute, at least 1).
	 *
	 * @param \WP_Post|int $post Post.
	 */
	public static function reading_time( $post ): int {
		$post = get_post( $post );
		return $post ? max( 1, (int) round( str_word_count( wp_strip_all_tags( (string) $post->post_content ) ) / 200 ) ) : 1;
	}

	/**
	 * Slug for a heading text.
	 *
	 * @param string $text Heading text.
	 */
	private static function anchor( string $text ): string {
		$slug = sanitize_title( wp_strip_all_tags( $text ) );
		return '' !== $slug ? $slug : 'section';
	}

	/**
	 * "In this guide": the h2 headings of a post.
	 *
	 * @param \WP_Post|int $post Post.
	 * @return array<int,array{id:string,text:string}>
	 */
	public static function toc( $post ): array {
		$post = get_post( $post );
		if ( ! $post || ! preg_match_all( '#<h2([^>]*)>(.*?)</h2>#is', (string) $post->post_content, $m, PREG_SET_ORDER ) ) {
			return array();
		}
		$out  = array();
		$used = array();
		foreach ( $m as $h ) {
			$text = trim( wp_strip_all_tags( $h[2] ) );
			if ( '' === $text ) {
				continue;
			}
			$id            = preg_match( '/\sid="([^"]+)"/', $h[1], $idm ) ? $idm[1] : self::anchor( $text );
			$id            = isset( $used[ $id ] ) ? $id . '-' . ( ++$used[ $id ] ) : $id;
			$used[ $id ] ??= 1;
			$out[]         = array(
				'id'   => $id,
				'text' => $text,
			);
		}
		return $out;
	}

	/**
	 * Give h2 headings of guides and content pages an id (TOC targets) when they have none.
	 *
	 * @param string $content Content.
	 */
	public function heading_ids( $content ) {
		if ( ! is_singular( array( 'post', 'page' ) ) || ! in_the_loop() ) {
			return $content;
		}
		$used = array();
		return (string) preg_replace_callback(
			'#<h2((?:(?!\sid=)[^>])*)>(.*?)</h2>#is',
			static function ( $m ) use ( &$used ) {
				$id            = self::anchor( $m[2] );
				$id            = isset( $used[ $id ] ) ? $id . '-' . ( ++$used[ $id ] ) : $id;
				$used[ $id ] ??= 1;
				return '<h2' . $m[1] . ' id="' . esc_attr( $id ) . '">' . $m[2] . '</h2>';
			},
			(string) $content
		);
	}

	/**
	 * Related listing categories of a guide (term ids, ≤ 2).
	 *
	 * @param int $post_id Post.
	 * @return array<int,\WP_Term>
	 */
	public static function related( int $post_id ): array {
		$out = array();
		foreach ( array_slice( array_map( 'absint', (array) get_post_meta( $post_id, self::META_CATS, true ) ), 0, 2 ) as $id ) {
			$term = $id ? get_term( $id, 'rtcl_category' ) : null;
			if ( $term instanceof \WP_Term ) {
				$out[] = $term;
			}
		}
		return $out;
	}

	/**
	 * Published listings in a category subtree.
	 *
	 * @param \WP_Term $term Category.
	 */
	public static function listing_count( \WP_Term $term ): int {
		$node = \Torrehub\Data\Directory::category_node( (int) $term->term_id );
		return $node ? (int) $node['count'] : 0;
	}

	/**
	 * First category of a post (the chip on cards).
	 *
	 * @param \WP_Post|int $post Post.
	 */
	public static function primary_category( $post ): ?\WP_Term {
		$cats = get_the_category( is_object( $post ) ? $post->ID : (int) $post );
		foreach ( $cats as $cat ) {
			if ( 'uncategorized' !== $cat->slug ) {
				return $cat;
			}
		}
		return null;
	}

	/**
	 * The featured guide: newest sticky post, else the newest post.
	 */
	public static function featured(): ?\WP_Post {
		$sticky = array_filter( array_map( 'absint', (array) get_option( 'sticky_posts', array() ) ) );
		$posts  = get_posts(
			array(
				'post_type'           => 'post',
				'posts_per_page'      => 1,
				'post__in'            => $sticky ? $sticky : null,
				'ignore_sticky_posts' => true,
			)
		);
		return $posts[0] ?? null;
	}

	/* ------------------------------------------------------------------ schema */

	/**
	 * Article JSON-LD on a guide.
	 */
	public function schema(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$post   = get_queried_object();
		$image  = get_the_post_thumbnail_url( $post, 'large' );
		$author = get_userdata( (int) $post->post_author );
		$data   = array_filter(
			array(
				'@context'         => 'https://schema.org',
				'@type'            => 'Article',
				'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
				'description'      => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'image'            => $image ? array( $image ) : null,
				'datePublished'    => get_post_time( 'c', true, $post ),
				'dateModified'     => get_post_modified_time( 'c', true, $post ),
				'author'           => $author ? array(
					'@type' => 'Person',
					'name'  => $author->display_name,
				) : null,
				'publisher'        => array(
					'@type' => 'Organization',
					'name'  => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
					'url'   => home_url( '/' ),
				),
				'mainEntityOfPage' => (string) get_permalink( $post ),
			)
		);
		printf( '<script type="application/ld+json">%s</script>' . "\n", wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/* ------------------------------------------------------------------ editor box */

	/**
	 * "Related listing categories" box on the post editor.
	 */
	public function meta_box(): void {
		if ( ! th_has_rtcl() ) {
			return;
		}
		add_meta_box(
			'th-guide-categories',
			__( 'Related listing categories', 'torrehub' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side'
		);
	}

	/**
	 * Box markup: two category selects.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_meta_box( $post ): void {
		$current = array_map( 'absint', (array) get_post_meta( $post->ID, self::META_CATS, true ) );
		$terms   = get_terms(
			array(
				'taxonomy'   => 'rtcl_category',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		wp_nonce_field( 'th_guide_categories', 'th_guide_categories_nonce' );
		echo '<p>' . esc_html__( 'Shown under the guide (“Mentioned in this guide”); the first also as “Need help with this?”.', 'torrehub' ) . '</p>';
		for ( $i = 0; $i < 2; $i++ ) {
			printf( '<p><label class="screen-reader-text" for="th-guide-cat-%1$d">%2$s</label><select id="th-guide-cat-%1$d" name="th_guide_categories[]" style="width:100%%"><option value="">%3$s</option>', (int) $i, esc_html__( 'Listing category', 'torrehub' ), esc_html__( '— None —', 'torrehub' ) );
			foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
				$depth = count( get_ancestors( $term->term_id, 'rtcl_category', 'taxonomy' ) );
				printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $term->term_id, selected( $current[ $i ] ?? 0, (int) $term->term_id, false ), esc_html( str_repeat( '— ', $depth ) . html_entity_decode( $term->name, ENT_QUOTES ) ) );
			}
			echo '</select></p>';
		}
	}

	/**
	 * Save the box.
	 *
	 * @param int      $post_id Post.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta( $post_id, $post ): void {
		if ( ! isset( $_POST['th_guide_categories_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['th_guide_categories_nonce'] ) ), 'th_guide_categories' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$ids = isset( $_POST['th_guide_categories'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['th_guide_categories'] ) ) ) ) ) : array();
		if ( $ids ) {
			update_post_meta( $post_id, self::META_CATS, array_slice( $ids, 0, 2 ) );
		} else {
			delete_post_meta( $post_id, self::META_CATS );
		}
	}
}
