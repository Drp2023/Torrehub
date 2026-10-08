<?php
/**
 * Elementor pages → block-editor content + the theme's page templates (`wp torrehub migrate-pages`).
 *
 * Elementor keeps a rendered HTML copy of every page in post_content. The migrator reads it, keeps the text
 * structure (headings, paragraphs, lists, tables, images, button links) as core blocks, drops Elementor's layout
 * wrappers, sets the theme template and switches the Elementor editor off for the page. The original content and
 * template are kept in post meta (and a revision) — `--rollback` puts them back.
 *
 * DECISION: the FAQ page held demo text (lorem ipsum); it gets a draft FAQ written from how the site actually
 * works — the client reviews it. The Contact page's text ("Don't hesitate to contact us", e-mail) moves to the
 * template (Customizer address); the page keeps a one-line intro.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Content;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP's DOM API (childNodes, nodeType …).

/**
 * Page migrator.
 */
final class Migrator {

	public const DONE     = 'th_migrated';
	public const CONTENT  = 'th_premigration_content';
	public const TEMPLATE = 'th_premigration_template';
	public const EXCERPT  = 'th_premigration_excerpt';

	/**
	 * Pages by slug → template + how to build the content.
	 *
	 * @return array<string,array{template:string,mode:string}>
	 */
	public static function plan(): array {
		/**
		 * Pages to migrate: slug => [ template, mode (convert|about|legal|faq|contact) ].
		 *
		 * @param array $plan Plan.
		 */
		return (array) apply_filters(
			'th_migrate_pages_plan',
			array(
				'about-us'         => array(
					'template' => 'page-templates/about.php',
					'mode'     => 'about',
				),
				'contact'          => array(
					'template' => 'page-templates/contact.php',
					'mode'     => 'contact',
				),
				'faq'              => array(
					'template' => 'page-templates/faq.php',
					'mode'     => 'faq',
				),
				'privacy-policy'   => array(
					'template' => 'page-templates/legal.php',
					'mode'     => 'legal',
				),
				'terms-conditions' => array(
					'template' => 'page-templates/legal.php',
					'mode'     => 'legal',
				),
				'aviso-legal'      => array(
					'template' => 'page-templates/legal.php',
					'mode'     => 'legal',
				),
			)
		);
	}

	/**
	 * Migrate (or report) one page.
	 *
	 * @param \WP_Post                           $page  Page.
	 * @param array{template:string,mode:string} $conf  Plan entry.
	 * @param bool                               $apply Write.
	 * @return array{blocks:int,words_before:int,words_after:int,skipped:string}
	 */
	public static function migrate( \WP_Post $page, array $conf, bool $apply ): array {
		if ( get_post_meta( $page->ID, self::DONE, true ) ) {
			return array(
				'blocks'       => 0,
				'words_before' => 0,
				'words_after'  => 0,
				'skipped'      => 'already migrated',
			);
		}
		$excerpt = null;
		if ( 'faq' === $conf['mode'] ) {
			$content = self::faq_blocks();
			$excerpt = __( 'Quick answers about accounts, listings, photos and the verified badge.', 'torrehub' );
		} elseif ( 'contact' === $conf['mode'] ) {
			$content = '';
			$excerpt = __( 'Questions, a listing to report or an account to upgrade? Write to us — we reply within one working day.', 'torrehub' );
		} else {
			$content = self::convert( (string) $page->post_content, (string) $page->post_title );
			if ( 'legal' === $conf['mode'] ) {
				$content = self::legal_structure( $content );
			}
		}
		$thumb = 0;
		if ( 'about' === $conf['mode'] ) {
			// T-01 layout: a leading image becomes the featured image, a short first line the hero intro.
			$parts = explode(
				'

',
				$content
			);
			if ( isset( $parts[0] ) && str_starts_with( $parts[0], '<!-- wp:image' ) && preg_match( '/src="([^"]+)"/', $parts[0], $m ) ) {
				$thumb = self::attachment_id( $m[1] );
				if ( $thumb ) {
					array_shift( $parts );
				}
			}
			$first = $thumb || ! isset( $parts[0] ) || ! str_starts_with( $parts[0], '<!-- wp:image' ) ? 0 : 1;
			if ( isset( $parts[ $first ] ) && str_starts_with( $parts[ $first ], '<!-- wp:paragraph' ) ) {
				$line = trim( wp_strip_all_tags( $parts[ $first ] ) );
				if ( '' !== $line && mb_strlen( $line ) <= 120 ) {
					$excerpt = $line;
					array_splice( $parts, $first, 1 );
				}
			}
			$content = implode(
				'

',
				$parts
			);
		}
		$result = array(
			'blocks'       => substr_count( $content, '<!-- wp:' ) - substr_count( $content, '<!-- wp:list-item' ),
			'words_before' => str_word_count( wp_strip_all_tags( (string) $page->post_content ) ),
			'words_after'  => str_word_count( wp_strip_all_tags( $content ) ),
			'skipped'      => '',
		);
		if ( ! $apply ) {
			return $result;
		}
		update_post_meta( $page->ID, self::CONTENT, wp_slash( (string) $page->post_content ) );
		update_post_meta( $page->ID, self::TEMPLATE, (string) get_post_meta( $page->ID, '_wp_page_template', true ) );
		update_post_meta( $page->ID, self::EXCERPT, wp_slash( (string) $page->post_excerpt ) );
		$data = array(
			'ID'           => $page->ID,
			'post_content' => wp_slash( $content ),
		);
		if ( null !== $excerpt ) {
			$data['post_excerpt'] = $excerpt;
		}
		wp_update_post( $data );
		update_post_meta( $page->ID, '_wp_page_template', $conf['template'] );
		delete_post_meta( $page->ID, '_elementor_edit_mode' );
		if ( $thumb && ! has_post_thumbnail( $page->ID ) ) {
			set_post_thumbnail( $page->ID, $thumb );
			update_post_meta( $page->ID, 'th_premigration_thumb', 'none' );
		}
		update_post_meta( $page->ID, self::DONE, gmdate( 'c' ) );
		return $result;
	}

	/**
	 * Attachment id of an uploads URL — also when the library holds another format or size of the same file
	 * (the theme stores WebP; old content may point at the original JPEG or a resized copy).
	 *
	 * @param string $url Image URL.
	 */
	private static function attachment_id( string $url ): int {
		$id = (int) attachment_url_to_postid( $url );
		if ( $id ) {
			return $id;
		}
		$base = (string) wp_get_upload_dir()['baseurl'];
		if ( ! str_starts_with( $url, $base . '/' ) ) {
			return 0;
		}
		$file = (string) preg_replace( '/(-\d+x\d+)?\.[a-z0-9]+$/i', '', substr( $url, strlen( $base ) + 1 ) );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off migration lookup.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1", $wpdb->esc_like( $file ) . '.%' ) );
	}

	/**
	 * Undo a migration.
	 *
	 * @param \WP_Post $page Page.
	 */
	public static function rollback( \WP_Post $page ): bool {
		if ( ! get_post_meta( $page->ID, self::DONE, true ) ) {
			return false;
		}
		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => wp_slash( (string) get_post_meta( $page->ID, self::CONTENT, true ) ),
				'post_excerpt' => wp_slash( (string) get_post_meta( $page->ID, self::EXCERPT, true ) ),
			)
		);
		update_post_meta( $page->ID, '_wp_page_template', (string) get_post_meta( $page->ID, self::TEMPLATE, true ) );
		update_post_meta( $page->ID, '_elementor_edit_mode', 'builder' );
		if ( 'none' === get_post_meta( $page->ID, 'th_premigration_thumb', true ) ) {
			delete_post_thumbnail( $page->ID );
			delete_post_meta( $page->ID, 'th_premigration_thumb' );
		}
		foreach ( array( self::DONE, self::CONTENT, self::TEMPLATE, self::EXCERPT ) as $key ) {
			delete_post_meta( $page->ID, $key );
		}
		return true;
	}

	/**
	 * Rendered Elementor HTML → block markup.
	 *
	 * @param string $html  post_content.
	 * @param string $title Page title (headings repeating it are dropped: the template shows the title).
	 */
	public static function convert( string $html, string $title ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="th-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		$root = $doc->getElementById( 'th-root' );
		if ( ! $root ) {
			return '';
		}
		$blocks = array();
		$walk   = static function ( \DOMNode $node ) use ( &$walk, &$blocks, $doc, $title ): void {
			foreach ( iterator_to_array( $node->childNodes ) as $child ) {
				if ( XML_TEXT_NODE === $child->nodeType ) {
					$text = trim( (string) $child->textContent );
					// Loose text directly in a layout div (Elementor widgets without a <p>).
					if ( '' !== $text && 'div' === strtolower( $node->nodeName ) ) {
						$blocks[] = self::paragraph( esc_html( $text ) );
					}
					continue;
				}
				if ( XML_ELEMENT_NODE !== $child->nodeType ) {
					continue;
				}
				$tag = strtolower( $child->nodeName );
				if ( in_array( $tag, array( 'script', 'style', 'noscript', 'svg', 'form', 'iframe', 'button', 'input', 'select', 'textarea' ), true ) ) {
					continue;
				}
				if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
					$text = trim( (string) $child->textContent );
					if ( '' === $text || 0 === strcasecmp( wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES ) ), $text ) ) {
						continue;
					}
					$level    = max( 2, min( 4, (int) $m[1] ) );
					$blocks[] = self::heading( esc_html( $text ), $level );
				} elseif ( 'p' === $tag ) {
					$inner = self::inline( $doc, $child );
					if ( '' !== trim( wp_strip_all_tags( $inner ) ) ) {
						$blocks[] = self::paragraph( $inner );
					}
				} elseif ( 'ul' === $tag || 'ol' === $tag ) {
					$items = array();
					foreach ( $child->childNodes as $li ) {
						if ( XML_ELEMENT_NODE === $li->nodeType && 'li' === strtolower( $li->nodeName ) ) {
							$inner = self::inline( $doc, $li );
							if ( '' !== trim( wp_strip_all_tags( $inner ) ) ) {
								$items[] = $inner;
							}
						}
					}
					if ( $items ) {
						$blocks[] = self::lst( $items, 'ol' === $tag );
					}
				} elseif ( 'img' === $tag ) {
					$src = (string) $child->getAttribute( 'src' );
					if ( $src && str_contains( $src, '/wp-content/uploads/' ) ) {
						$blocks[] = '<!-- wp:image --><figure class="wp-block-image"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( (string) $child->getAttribute( 'alt' ) ) . '"/></figure><!-- /wp:image -->';
					}
				} elseif ( 'table' === $tag ) {
					$blocks[] = '<!-- wp:table --><figure class="wp-block-table">' . wp_kses_post( (string) $doc->saveHTML( $child ) ) . '</figure><!-- /wp:table -->';
				} elseif ( 'a' === $tag && str_contains( (string) $child->getAttribute( 'class' ), 'elementor-button' ) ) {
					$text = trim( (string) $child->textContent );
					$href = (string) $child->getAttribute( 'href' );
					if ( '' !== $text && '' !== $href && '#' !== $href ) {
						$blocks[] = self::paragraph( '<a href="' . esc_url( $href ) . '">' . esc_html( $text ) . '</a>' );
					}
				} elseif ( 'blockquote' === $tag ) {
					$blocks[] = '<!-- wp:quote --><blockquote class="wp-block-quote">' . self::paragraph( self::inline( $doc, $child ) ) . '</blockquote><!-- /wp:quote -->';
				} else {
					$walk( $child );
				}
			}
		};
		$walk( $root );
		// Elementor prints some widgets twice (desktop / mobile variants): drop consecutive duplicates.
		$out = array();
		foreach ( $blocks as $block ) {
			if ( end( $out ) !== $block ) {
				$out[] = $block;
			}
		}
		return implode( "\n\n", $out );
	}

	/**
	 * Legal texts typed as plain paragraphs: short title lines become h2 headings (contents + anchors), runs of
	 * "· item" paragraphs become lists, "---" lines separators.
	 *
	 * @param string $content Block markup from convert().
	 */
	public static function legal_structure( string $content ): string {
		$blocks = explode(
			'

',
			$content
		);
		$text   = static fn( string $b ): ?string => preg_match(
			'#^<!-- wp:paragraph -->
<p>(.*)</p>
<!-- /wp:paragraph -->$#s',
			$b,
			$m
		) ? $m[1] : null;
		$out    = array();
		$items  = array();
		$flush  = static function () use ( &$items, &$out ): void {
			if ( $items ) {
				$out[] = self::lst( $items, false );
				$items = array();
			}
		};
		foreach ( $blocks as $i => $block ) {
			$inner = $text( $block );
			if ( null !== $inner && preg_match( '/^\s*[·•]\s*/u', $inner ) ) {
				$items[] = (string) preg_replace( '/^\s*[·•]\s*/u', '', $inner );
				continue;
			}
			$flush();
			$plain = null === $inner ? '' : trim( html_entity_decode( wp_strip_all_tags( $inner ), ENT_QUOTES ) );
			$next  = $blocks[ $i + 1 ] ?? '';
			if ( null !== $inner && preg_match( '/^[-–—_*=\s]{3,}$/u', $plain ) ) {
				$out[] = implode(
					"\n",
					array( '<!-- wp:separator -->', '<hr class="wp-block-separator has-alpha-channel-opacity"/>', '<!-- /wp:separator -->' )
				);
				continue;
			}
			if ( null !== $inner && '' !== $plain && mb_strlen( $plain ) <= 80 && str_word_count( $plain ) <= 12
				&& ! preg_match( '/[.:;,!]$/u', $plain ) && '' !== $next && ! str_contains( $inner, '<a ' ) ) {
				$out[] = self::heading( esc_html( $plain ), 2 );
				continue;
			}
			$out[] = $block;
		}
		$flush();
		return implode(
			'

',
			$out
		);
	}

	/**
	 * Inner HTML of an element, reduced to inline formatting and links.
	 *
	 * @param \DOMDocument $doc  Document.
	 * @param \DOMNode     $node Element.
	 */
	private static function inline( \DOMDocument $doc, \DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $doc->saveHTML( $child );
		}
		$html = wp_kses(
			$html,
			array(
				'a'      => array( 'href' => true ),
				'strong' => array(),
				'b'      => array(),
				'em'     => array(),
				'i'      => array(),
				'br'     => array(),
			)
		);
		return trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $html ) ) );
	}

	/**
	 * Paragraph block.
	 *
	 * @param string $inner Inline HTML (escaped).
	 */
	private static function paragraph( string $inner ): string {
		return "<!-- wp:paragraph -->\n<p>" . $inner . "</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * Heading block.
	 *
	 * @param string $text  Escaped text.
	 * @param int    $level 2–4.
	 */
	private static function heading( string $text, int $level ): string {
		$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';
		return "<!-- wp:heading{$attrs} -->\n<h{$level} class=\"wp-block-heading\">{$text}</h{$level}>\n<!-- /wp:heading -->";
	}

	/**
	 * List block.
	 *
	 * @param array<int,string> $items   Inline HTML per item.
	 * @param bool              $ordered Ordered.
	 */
	private static function lst( array $items, bool $ordered ): string {
		$tag = $ordered ? 'ol' : 'ul';
		$out = '<!-- wp:list' . ( $ordered ? ' {"ordered":true}' : '' ) . " -->\n<{$tag} class=\"wp-block-list\">";
		foreach ( $items as $item ) {
			$out .= "<!-- wp:list-item -->\n<li>{$item}</li>\n<!-- /wp:list-item -->";
		}
		return $out . "</{$tag}>\n<!-- /wp:list -->";
	}

	/**
	 * Draft FAQ (Details blocks) written from how the site works today. To be reviewed by the client.
	 */
	public static function faq_blocks(): string {
		$items = array(
			array(
				__( 'What’s the difference between a Member, a Private Seller and a Business Seller?', 'torrehub' ),
				__( 'A Member browses, saves searches, chats with sellers and leaves reviews, but can’t post listings. A Private Seller does everything a Member does and posts listings as an individual. A Business Seller is the same for companies and tradespeople, and registers with the company’s NIF.', 'torrehub' ),
			),
			array(
				__( 'Why does my account need approval?', 'torrehub' ),
				__( 'Every new account is checked by a person before it goes live — usually within 48 hours. It keeps fake sellers off the site. Confirm your e-mail address first; we write to you as soon as the account is approved.', 'torrehub' ),
			),
			array(
				__( 'Does posting a listing cost anything?', 'torrehub' ),
				__( 'No. Posting and renewing are free for Private and Business Sellers, and there is no commission on what you sell.', 'torrehub' ),
			),
			// The two answers below follow Appearance › Torrehub › Listing lifetime (shortcode, rendered on the page and in the JSON-LD).
			array(
				__( 'How long does a listing stay online?', 'torrehub' ),
				'[torrehub_listing_lifetime show=duration]',
			),
			array(
				__( 'How do I renew a listing?', 'torrehub' ),
				'[torrehub_listing_lifetime show=renewal]',
			),
			array(
				__( 'Why is my listing “Pending review”?', 'torrehub' ),
				__( 'Every new listing — and every change to a live one — is checked before it shows on the site. You can follow its status under My account › My listings.', 'torrehub' ),
			),
			array(
				__( 'What are the photo limits?', 'torrehub' ),
				__( 'Up to 5 photos per listing, in JPG, PNG or WebP, each up to 10 MB. The first photo is the cover; you can choose another one in the listing form.', 'torrehub' ),
			),
			array(
				__( 'What is the verified seller badge?', 'torrehub' ),
				__( 'Sellers can upload an identity document in their account. An administrator checks it and, if it matches, the account gets the Verified badge. The document is deleted as soon as the decision is made — only the result is kept.', 'torrehub' ),
			),
			array(
				__( 'How do I contact a seller?', 'torrehub' ),
				__( 'Each listing shows the seller’s phone and WhatsApp where they gave them. Chat and e-mail enquiries need a free account, so the seller always knows who is writing.', 'torrehub' ),
			),
			array(
				__( 'Can I get an e-mail when something new is listed?', 'torrehub' ),
				__( 'Yes. Search or filter the listings, then use “Save this search” and choose instantly, daily or weekly. You can change or stop each alert in your account, or with the link in every e-mail.', 'torrehub' ),
			),
		);
		$out = array();
		foreach ( $items as $item ) {
			$out[] = "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . esc_html( $item[0] ) . "</summary><!-- wp:paragraph -->\n<p>" . esc_html( $item[1] ) . "</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->";
		}
		return implode( "\n\n", $out );
	}
}
