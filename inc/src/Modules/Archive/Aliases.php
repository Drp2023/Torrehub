<?php
/**
 * Language-independent keyword search (client request 2026-11). Listings are written in English and GTranslate only
 * translates the interface, so a search for "fontanero" found nothing although there are plumbers.
 *
 * - Every listing category has search keywords per site language (term meta `th_alias_{code}`, comma-separated;
 *   the languages come from GTranslate), editable on the category's screen (Listings › Categories). The English
 *   field holds the base words. Starter keywords: Migration step U2 (inc/data/category-aliases.php).
 * - A keyword search that contains one of them (any language; case, accents and a short ending such as a plural
 *   ignored) also finds the listings of that category and its subcategories, and the listings that contain one of
 *   the category's English base words. Everything else about the search (filters, town, order) is unchanged; a
 *   search without a keyword match runs exactly as before. Applies to the archive (and its live count) and to saved
 *   searches (both use WordPress's search on `s`).
 * - DECISION: other words of the search don't narrow the added results ("fontanero urgente" also lists every
 *   plumber) — a word the visitor typed in their language rarely appears in an English listing.
 * - The keyword map is cached (object cache + option) and rebuilt when a category or its keywords change.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Archive;

defined( 'ABSPATH' ) || exit;

/**
 * Category search keywords.
 */
final class Aliases {

	public const META   = 'th_alias_';
	public const CACHE  = 'th_category_aliases';
	public const LOCALE = 'en_US'; // remove_accents() without locale rules (de_DE would make ä → ae).

	/** The GTranslate languages of torrehub.com — used when no translation plugin is active. */
	private const FALLBACK = array( 'en', 'fi', 'de', 'hu', 'ro', 'ru', 'es', 'sv', 'uk' );

	/**
	 * Keyword map for this request.
	 *
	 * @var array{aliases:array<string,array<int,int>>,base:array<int,array<int,string>>}|null
	 */
	private static ?array $map = null;

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'posts_search', array( $this, 'expand' ), 10, 2 );
		foreach ( array( 'created_rtcl_category', 'edited_rtcl_category', 'delete_rtcl_category' ) as $hook ) {
			add_action( $hook, array( self::class, 'flush' ) );
		}
		foreach ( array( 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ) as $hook ) {
			add_action(
				$hook,
				static function ( $meta_id, $term_id, $key ) {
					if ( is_string( $key ) && str_starts_with( $key, self::META ) ) {
						self::flush();
					}
				},
				10,
				3
			);
		}
		if ( is_admin() ) {
			add_action( 'rtcl_category_add_form_fields', array( $this, 'add_fields' ) );
			add_action( 'rtcl_category_edit_form_fields', array( $this, 'edit_fields' ) );
			add_action( 'created_rtcl_category', array( $this, 'save' ) );
			add_action( 'edited_rtcl_category', array( $this, 'save' ) );
		}
	}

	/* ------------------------------------------------------------------ languages */

	/**
	 * Keyword languages: code => name (the site's GTranslate languages, English first).
	 *
	 * @return array<string,string>
	 */
	public static function languages(): array {
		$out = array();
		foreach ( function_exists( 'th_get_languages' ) ? th_get_languages() : array() as $lang ) {
			$out[ sanitize_key( $lang['code'] ) ] = (string) $lang['name'];
		}
		if ( ! $out ) {
			$out = array_combine( self::FALLBACK, array_map( 'strtoupper', self::FALLBACK ) );
		}
		if ( ! isset( $out['en'] ) ) {
			$out = array( 'en' => 'English' ) + $out; // The base words are always English (the listings are).
		}
		return $out;
	}

	/* ------------------------------------------------------------------ admin */

	/**
	 * Fields on Listings › Categories › Add.
	 */
	public function add_fields(): void {
		wp_nonce_field( 'th_aliases', 'th_aliases_nonce' );
		?>
		<div class="form-field th-aliases">
			<p><strong><?php esc_html_e( 'Search keywords', 'torrehub' ); ?></strong></p>
			<p class="description"><?php echo esc_html( self::help() ); ?></p>
			<?php foreach ( self::languages() as $code => $name ) : ?>
				<label for="th-alias-<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></label>
				<input type="text" id="th-alias-<?php echo esc_attr( $code ); ?>" name="th_alias[<?php echo esc_attr( $code ); ?>]" value="">
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Fields on the category's edit screen.
	 *
	 * @param \WP_Term $term Term.
	 */
	public function edit_fields( $term ): void {
		$langs = self::languages();
		?>
		<tr class="form-field th-aliases">
			<th scope="row"><?php esc_html_e( 'Search keywords', 'torrehub' ); ?></th>
			<td>
				<?php wp_nonce_field( 'th_aliases', 'th_aliases_nonce' ); ?>
				<p class="description" style="margin-top:0"><?php echo esc_html( self::help() ); ?></p>
				<table role="presentation" style="width:100%;max-width:760px">
					<?php foreach ( $langs as $code => $name ) : ?>
						<tr>
							<th scope="row" style="padding:6px 10px 6px 0;width:120px;font-weight:400"><label for="th-alias-<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></label></th>
							<td style="padding:6px 0"><input type="text" class="large-text" id="th-alias-<?php echo esc_attr( $code ); ?>" name="th_alias[<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, self::META . $code, true ) ); ?>"></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</td>
		</tr>
		<?php
	}

	/**
	 * Help text under the fields.
	 */
	private static function help(): string {
		return __( 'Comma-separated words people may type in each language (e.g. Plumbing: Spanish “fontanero, fontanería”). A search with one of them also finds this category’s listings and the listings containing the English words. Accents and capitals don’t matter.', 'torrehub' );
	}

	/**
	 * Save the fields.
	 *
	 * @param int $term_id Term.
	 */
	public function save( $term_id ): void {
		$tax = get_taxonomy( 'rtcl_category' );
		if ( ! isset( $_POST['th_aliases_nonce'], $_POST['th_alias'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['th_aliases_nonce'] ) ), 'th_aliases' ) || ! $tax || ! current_user_can( $tax->cap->edit_terms ) ) {
			return;
		}
		$posted = (array) wp_unslash( $_POST['th_alias'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value goes through clean().
		foreach ( array_keys( self::languages() ) as $code ) {
			$value = self::clean( (string) ( $posted[ $code ] ?? '' ) );
			if ( '' === $value ) {
				delete_term_meta( (int) $term_id, self::META . $code );
			} else {
				update_term_meta( (int) $term_id, self::META . $code, $value );
			}
		}
	}

	/**
	 * Tidy a keyword list: one line, comma-separated, no empties or duplicates (as typed — matching normalises).
	 *
	 * @param string $input Raw input.
	 */
	public static function clean( string $input ): string {
		$parts = preg_split( '/[,;\n]+/u', $input );
		$words = array_map( static fn( $w ) => trim( (string) preg_replace( '/\s+/u', ' ', sanitize_text_field( $w ) ) ), is_array( $parts ) ? $parts : array() );
		return implode( ', ', array_values( array_unique( array_filter( $words, static fn( $w ) => '' !== $w ) ) ) );
	}

	/* ------------------------------------------------------------------ map */

	/**
	 * Lower case, no accents, words separated by single spaces ("Vízvezeték-szerelő" → "vizvezetek szerelo").
	 *
	 * @param string $text Text.
	 */
	public static function normalise( string $text ): string {
		$text = remove_accents( mb_strtolower( $text, 'UTF-8' ), self::LOCALE );
		return trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text ) );
	}

	/**
	 * Keyword → categories, and each category's English base words. Cached until a category changes.
	 *
	 * @return array{aliases:array<string,array<int,int>>,base:array<int,array<int,string>>}
	 */
	public static function map(): array {
		if ( null !== self::$map ) {
			return self::$map;
		}
		$cached = wp_cache_get( self::CACHE, 'torrehub' );
		if ( ! is_array( $cached ) ) {
			$cached = get_option( self::CACHE );
		}
		if ( ! is_array( $cached ) || ! isset( $cached['aliases'], $cached['base'] ) ) {
			$cached = self::build();
			update_option( self::CACHE, $cached, false );
		}
		wp_cache_set( self::CACHE, $cached, 'torrehub' );
		self::$map = $cached;
		return $cached;
	}

	/**
	 * Build the map from the term meta.
	 *
	 * @return array{aliases:array<string,array<int,int>>,base:array<int,array<int,string>>}
	 */
	private static function build(): array {
		global $wpdb;
		$out  = array(
			'aliases' => array(),
			'base'    => array(),
		);
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- built once, then cached.
			$wpdb->prepare(
				"SELECT tm.term_id, tm.meta_key, tm.meta_value FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id AND tt.taxonomy = 'rtcl_category' WHERE tm.meta_key LIKE %s",
				$wpdb->esc_like( self::META ) . '%'
			)
		);
		foreach ( (array) $rows as $row ) {
			$term = (int) $row->term_id;
			foreach ( explode( ',', (string) $row->meta_value ) as $word ) {
				$norm = self::normalise( $word );
				if ( '' === $norm ) {
					continue;
				}
				$out['aliases'][ $norm ][] = $term;
				if ( self::META . 'en' === $row->meta_key ) {
					$out['base'][ $term ][] = trim( $word );
				}
			}
		}
		foreach ( $out['aliases'] as $alias => $terms ) {
			$out['aliases'][ $alias ] = array_values( array_unique( $terms ) );
		}
		return $out;
	}

	/**
	 * Forget the cached map (a category or its keywords changed).
	 */
	public static function flush(): void {
		self::$map = null;
		wp_cache_delete( self::CACHE, 'torrehub' );
		delete_option( self::CACHE );
	}

	/**
	 * Categories whose keyword occurs in the search: whole words; a search word may add a short ending to a keyword
	 * word of 4+ letters ("fontaneros" ← "fontanero", not "training" ← "train"). The longest keyword wins: a keyword
	 * inside a longer one that matched the same words doesn't count ("alquiler de coches" → car rental, without
	 * every property for rent from "alquiler").
	 *
	 * @param string $search Search phrase.
	 * @return array<int,int> Term ids.
	 */
	public static function match( string $search ): array {
		$words = explode( ' ', self::normalise( $search ) );
		$count = count( $words );
		if ( array( '' ) === $words ) {
			return array();
		}
		$hits = array(); // [ first word, number of words, term ids ].
		foreach ( self::map()['aliases'] as $alias => $terms ) {
			$parts = explode( ' ', (string) $alias );
			$n     = count( $parts );
			for ( $i = 0; $i + $n <= $count; $i++ ) {
				foreach ( $parts as $k => $part ) {
					$word = $words[ $i + $k ];
					if ( $word !== $part && ! ( mb_strlen( $part ) >= 4 && str_starts_with( $word, $part ) && mb_strlen( $word ) - mb_strlen( $part ) <= 2 ) ) {
						continue 2;
					}
				}
				$hits[] = array( $i, $n, $terms );
			}
		}
		$found = array();
		foreach ( $hits as $hit ) {
			foreach ( $hits as $other ) {
				if ( $other[1] > $hit[1] && $other[0] <= $hit[0] && $other[0] + $other[1] >= $hit[0] + $hit[1] ) {
					continue 2; // Inside a longer match.
				}
			}
			array_push( $found, ...$hit[2] );
		}
		return array_values( array_unique( array_map( 'intval', $found ) ) );
	}

	/* ------------------------------------------------------------------ search */

	/**
	 * Widen a listing search: (WordPress's own match) OR (in a matched category or below) OR (contains an English
	 * base word of one).
	 *
	 * @param string    $search The search SQL (" AND ((…)) [AND (post_password = '')]").
	 * @param \WP_Query $q      Query.
	 */
	public function expand( $search, $q ) {
		global $wpdb;
		if ( ! is_string( $search ) || '' === trim( $search ) || ! $q instanceof \WP_Query || ( is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) ) {
			return $search;
		}
		$types = (array) $q->get( 'post_type' );
		if ( ! in_array( 'rtcl_listing', $types, true ) && ! $q->is_tax( array( 'rtcl_category', 'rtcl_location', 'rtcl_tag' ) ) ) {
			return $search;
		}
		$terms = self::match( (string) $q->get( 's' ) );
		if ( ! $terms ) {
			return $search;
		}

		$ids = $terms;
		foreach ( $terms as $term ) {
			$ids = array_merge( $ids, array_map( 'intval', (array) get_term_children( $term, 'rtcl_category' ) ) );
		}
		$ids   = array_values( array_unique( array_filter( $ids ) ) );
		$extra = array(
			"{$wpdb->posts}.ID IN ( SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'rtcl_category' AND tt.term_id IN (" . implode( ',', $ids ) . ') )',
		);
		$base  = array();
		foreach ( $terms as $term ) {
			$base = array_merge( $base, self::map()['base'][ $term ] ?? array() );
		}
		foreach ( array_unique( $base ) as $word ) {
			// Whole words, plural allowed ("tour", "tours" — not "tourist"); POSIX classes work on MySQL 8 and MariaDB.
			$re      = '(^|[^[:alnum:]])' . preg_replace( '/[.\\\\+*?\\[^\\]$(){}=!<>|:#-]/', '\\\\$0', $word ) . '(s|es)?([^[:alnum:]]|$)';
			$extra[] = $wpdb->prepare( "({$wpdb->posts}.post_title REGEXP %s OR {$wpdb->posts}.post_excerpt REGEXP %s OR {$wpdb->posts}.post_content REGEXP %s)", $re, $re, $re );
		}

		// Keep WordPress's own clause (and its password condition for visitors) and OR the additions into it.
		$trim   = trim( $search );
		$suffix = "AND ({$wpdb->posts}.post_password = '')";
		$pw     = '';
		if ( str_ends_with( $trim, $suffix ) ) {
			$trim = trim( substr( $trim, 0, -strlen( $suffix ) ) );
			$pw   = ' ' . $suffix;
		}
		if ( ! str_starts_with( $trim, 'AND ' ) ) {
			return $search;
		}
		return ' AND ( ' . substr( $trim, 4 ) . ' OR ' . implode( ' OR ', $extra ) . ' )' . $pw . ' ';
	}
}
