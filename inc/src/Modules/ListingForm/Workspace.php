<?php
/**
 * State of the listing form page: which screen, which form, which listing, which values.
 *
 * Screens (view):
 *   choose     S-02 — root categories, then subcategories (`?th_cat=`) until a leaf is picked
 *   workspace  S-04 / S-14 — the form (`?th_cat=<leaf>` new, `?th_draft=<id>` draft, `/edit/<id>/` edit)
 *   submitted  S-18 — after saving (`?th_submitted=<id>`)
 *   denied     members (no seller account) or someone else's listing
 *   blocked    no posting allowance left (Quota module, off by default)
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\ListingForm;

use Rtcl\Models\Form\Form;
use Torrehub\Data\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Listing form view model.
 */
final class Workspace {

	/**
	 * Screen.
	 *
	 * @var string choose|workspace|submitted|denied|blocked
	 */
	public string $view = 'choose';

	/**
	 * Workspace mode.
	 *
	 * @var string new|draft|edit
	 */
	public string $mode = 'new';

	/**
	 * Listing (or temp post) id; 0 before the first save.
	 *
	 * @var int
	 */
	public int $listing_id = 0;

	/**
	 * The Form Builder form.
	 *
	 * @var Form|null
	 */
	public ?Form $form = null;

	/**
	 * Category chain, root → chosen category.
	 *
	 * @var array<int,\WP_Term>
	 */
	public array $chain = array();

	/**
	 * Drill-in node (Directory::category_node) for the choose screen; null = roots.
	 *
	 * @var array<string,mixed>|null
	 */
	public ?array $node = null;

	/**
	 * Values keyed by field name.
	 *
	 * @var array<string,mixed>
	 */
	public array $values = array();

	/**
	 * Sections: [ uuid, title, logics, fields[] ].
	 *
	 * @var array<int,array{uuid:string,title:string,logics:array|null,fields:array<int,array<string,mixed>>}>
	 */
	public array $sections = array();

	/**
	 * Listing type (ad_type) sent with the form.
	 *
	 * @var string
	 */
	public string $listing_type = '';

	/**
	 * Why the page is denied/blocked (message).
	 *
	 * @var string
	 */
	public string $reason = '';

	/**
	 * Draft last-saved time (draft mode).
	 *
	 * @var int
	 */
	public int $saved = 0;

	/**
	 * Request-wide instance.
	 */
	public static function current(): self {
		static $ws = null;
		if ( null === $ws ) {
			$ws = new self();
			$ws->resolve();
		}
		return $ws;
	}

	/**
	 * Work out the screen from the request.
	 */
	private function resolve(): void {
		if ( ! th_user_can_post() ) {
			$this->view   = 'denied';
			$this->reason = 'member';
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation parameters; every id is ownership-checked.
		$edit_id   = 'edit' === get_query_var( 'rtcl_action' ) ? absint( get_query_var( 'rtcl_listing_id' ) ) : 0;
		$submitted = isset( $_GET['th_submitted'] ) ? absint( $_GET['th_submitted'] ) : 0;
		$draft_id  = isset( $_GET['th_draft'] ) ? absint( $_GET['th_draft'] ) : 0;
		$cat_id    = isset( $_GET['th_cat'] ) ? absint( $_GET['th_cat'] ) : 0;
		// phpcs:enable

		if ( $edit_id ) {
			$this->resolve_edit( $edit_id );
			return;
		}

		if ( $submitted ) {
			$post = get_post( $submitted );
			if ( $post && 'rtcl_listing' === $post->post_type && get_current_user_id() === (int) $post->post_author ) {
				$this->view       = 'submitted';
				$this->listing_id = $submitted;
				return;
			}
		}

		if ( $draft_id ) {
			$draft = Drafts::get( $draft_id );
			if ( $draft ) {
				$this->mode       = 'draft';
				$this->listing_id = $draft_id;
				$this->values     = $draft['values'];
				$this->saved      = $draft['saved'];
				$this->set_chain( $draft['category'] );
				$this->form = self::form_by_id( $draft['form_id'] ) ?? $this->form_for_chain();
				$this->open_workspace();
				return;
			}
		}

		if ( $cat_id ) {
			$node = Directory::category_node( $cat_id );
			if ( $node ) {
				if ( $node['children'] ) {
					$this->node = $node;
					$this->set_chain( $cat_id );
					return; // Drill in further.
				}
				$this->set_chain( $cat_id );
				$this->form = $this->form_for_chain();
				if ( $this->form ) {
					$this->values = (array) \Rtcl\Services\FormBuilder\FBHelper::getFormDefaultData( $this->form );
					$this->open_workspace();
					return;
				}
			}
		}

		$this->view = 'choose';
	}

	/**
	 * Edit an existing listing.
	 *
	 * @param int $id Listing id.
	 */
	private function resolve_edit( int $id ): void {
		$post = get_post( $id );
		if ( ! $post || 'rtcl_listing' !== $post->post_type || ! \Rtcl\Helpers\Functions::current_user_can( 'edit_rtcl_listing', $id ) ) {
			$this->view   = 'denied';
			$this->reason = 'forbidden';
			return;
		}
		$this->mode       = 'edit';
		$this->listing_id = $id;

		$terms = get_the_terms( $id, 'rtcl_category' );
		$deep  = 0;
		$depth = -1;
		foreach ( ( $terms && ! is_wp_error( $terms ) ) ? $terms : array() as $term ) {
			$d = count( get_ancestors( $term->term_id, 'rtcl_category', 'taxonomy' ) );
			if ( $d > $depth ) {
				$depth = $d;
				$deep  = (int) $term->term_id;
			}
		}
		$this->set_chain( $deep );

		$this->form = self::form_by_id( (int) get_post_meta( $id, '_rtcl_form_id', true ) ) ?? $this->form_for_chain();
		if ( ! $this->form ) {
			$this->view   = 'denied';
			$this->reason = 'form';
			return;
		}
		$this->values       = (array) \Rtcl\Services\FormBuilder\FBHelper::getFormData( $id, $this->form );
		$this->listing_type = (string) get_post_meta( $id, 'ad_type', true );
		$this->open_workspace();
	}

	/**
	 * Category chain from a term id (root first).
	 *
	 * @param int $term_id Category.
	 */
	private function set_chain( int $term_id ): void {
		$term = $term_id ? get_term( $term_id, 'rtcl_category' ) : null;
		if ( ! $term instanceof \WP_Term ) {
			$this->chain = array();
			return;
		}
		$chain = array( $term );
		foreach ( get_ancestors( $term->term_id, 'rtcl_category', 'taxonomy' ) as $ancestor ) {
			$parent = get_term( (int) $ancestor, 'rtcl_category' );
			if ( $parent instanceof \WP_Term ) {
				array_unshift( $chain, $parent );
			}
		}
		$this->chain = $chain;
	}

	/**
	 * Root category map entry of the chain.
	 *
	 * @return array<string,mixed>|null
	 */
	public function root_meta(): ?array {
		return $this->chain ? th_category_meta( $this->chain[0] ) : null;
	}

	/**
	 * Form for the chosen root category (category map), else Classified Listing's default form.
	 */
	private function form_for_chain(): ?Form {
		$meta = $this->root_meta();
		$form = $meta ? self::form_by_id( (int) $meta['form_id'] ) : null;
		if ( ! $form && $meta ) {
			$form = \Rtcl\Services\FormBuilder\FBHelper::getFormBySlug( (string) $meta['form_slug'] );
		}
		if ( ! $form ) {
			$form = \Rtcl\Services\FormBuilder\FBHelper::getDefaultForm();
		}
		return $form instanceof Form ? $form : null;
	}

	/**
	 * A published form by id.
	 *
	 * @param int $id Form id.
	 */
	public static function form_by_id( int $id ): ?Form {
		if ( ! $id ) {
			return null;
		}
		$form = Form::query()->find( $id );
		$form = $form ? apply_filters( 'rtcl_fb_form', $form ) : null;
		return $form instanceof Form ? $form : null;
	}

	/**
	 * Switch to the workspace (unless a posting allowance blocks new listings).
	 */
	private function open_workspace(): void {
		if ( ! $this->form ) {
			$this->view = 'choose';
			return;
		}
		if ( 'edit' !== $this->mode ) {
			/**
			 * Block new listings (Quota module): return a message to show instead of the form.
			 *
			 * @param string $message Empty = allowed.
			 * @param int    $user_id User.
			 */
			$this->reason = (string) apply_filters( 'th_listing_form_blocked', '', get_current_user_id() );
			if ( $this->reason ) {
				$this->view = 'blocked';
				return;
			}
			$meta               = $this->root_meta();
			$this->listing_type = $meta ? (string) ( $meta['type'] ?? '' ) : '';
		}
		$this->view     = 'workspace';
		$this->sections = $this->build_sections();
		if ( 'new' === $this->mode ) {
			$this->preselect_switches();
		}
	}

	/**
	 * A new "Cars" listing starts with Item type = Car: section switches whose option matches the chosen
	 * category (first four letters, either way round: cars/car, motorcycles/motorbike, boats/boat) are preselected.
	 */
	private function preselect_switches(): void {
		$leaf = $this->chain ? end( $this->chain ) : null;
		if ( ! $leaf ) {
			return;
		}
		$key    = static fn( string $s ): string => substr( preg_replace( '/[^a-z]/', '', strtolower( remove_accents( $s ) ) ), 0, 4 );
		$target = $key( html_entity_decode( $leaf->name, ENT_QUOTES ) );
		$fields = (array) $this->form->fields;
		foreach ( Fields::section_switches( (array) $this->form->sections ) as $id ) {
			$field = $fields[ $id ] ?? null;
			if ( ! is_array( $field ) || 'select' !== ( $field['element'] ?? '' ) || '' !== (string) ( $this->values[ $field['name'] ] ?? '' ) ) {
				continue;
			}
			foreach ( Fields::options( $field ) as $value => $label ) {
				$match = static fn( string $k ): bool => strlen( $k ) >= 3 && strlen( $target ) >= 3 && ( str_starts_with( $target, $k ) || str_starts_with( $k, $target ) );
				if ( $match( $key( (string) $value ) ) || $match( $key( $label ) ) ) {
					$this->values[ $field['name'] ] = (string) $value;
					break;
				}
			}
		}
	}

	/**
	 * Sections with the fields the front-end form shows.
	 *
	 * @return array<int,array{uuid:string,title:string,logics:array|null,fields:array<int,array<string,mixed>>}>
	 */
	private function build_sections(): array {
		$fields = (array) $this->form->fields;
		$out    = array();
		foreach ( (array) $this->form->sections as $index => $section ) {
			$list = array();
			foreach ( (array) ( $section['containers'] ?? array() ) as $container ) {
				foreach ( (array) ( $container['fields'] ?? array() ) as $field_id ) {
					$field = $fields[ $field_id ] ?? null;
					if ( is_array( $field ) && Fields::shown( $field ) ) {
						$field['uuid'] = (string) ( $field['uuid'] ?? $field_id );
						$list[]        = $field;
					}
				}
			}
			if ( ! $list ) {
				continue;
			}
			$out[] = array(
				'uuid'   => (string) ( $section['uuid'] ?? 'section-' . $index ),
				'title'  => html_entity_decode( (string) ( $section['title'] ?? '' ), ENT_QUOTES ),
				'logics' => Fields::logics( $section['logics'] ?? null ),
				'fields' => $list,
			);
		}
		return $out;
	}

	/**
	 * Configuration for listing-form.js: field rules, conditions, endpoints.
	 *
	 * @return array<string,mixed>
	 */
	public function client_config(): array {
		$fields = array();
		foreach ( (array) $this->form->fields as $id => $field ) {
			if ( ! is_array( $field ) || empty( $field['name'] ) ) {
				continue;
			}
			$fields[ (string) ( $field['uuid'] ?? $id ) ] = array(
				'name'    => (string) $field['name'],
				'element' => (string) ( $field['element'] ?? '' ),
				'label'   => html_entity_decode( (string) ( $field['label'] ?? '' ), ENT_QUOTES ),
				'rules'   => Fields::shown( $field ) ? Fields::rules( $field ) : array(),
				'logics'  => Fields::logics( $field['logics'] ?? null ),
				'format'  => 'date' === ( $field['element'] ?? '' ) ? (string) ( $field['date_format'] ?? 'Y-m-d' ) : null,
			);
		}
		$sections = array();
		foreach ( $this->sections as $section ) {
			$sections[ $section['uuid'] ] = $section['logics'];
		}
		return array(
			'mode'       => $this->mode,
			'listingId'  => $this->listing_id,
			'formId'     => (int) $this->form->id,
			'category'   => $this->chain ? (int) end( $this->chain )->term_id : 0,
			'fields'     => $fields,
			'sections'   => $sections,
			'nonceId'    => rtcl()->nonceId,
			'nonce'      => wp_create_nonce( rtcl()->nonceText ),
			'draftNonce' => wp_create_nonce( 'th_listing_draft' ),
			'exitUrl'    => \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' ),
			'doneUrl'    => add_query_arg( 'th_submitted', '%d', Module::url() ),
		);
	}

	/**
	 * Header title.
	 */
	public function title(): string {
		if ( 'edit' === $this->mode && 'workspace' === $this->view ) {
			return __( 'Edit listing', 'torrehub' );
		}
		return 'workspace' === $this->view ? __( 'New listing', 'torrehub' ) : __( 'Post a listing', 'torrehub' );
	}

	/**
	 * Images already attached (edit / draft).
	 *
	 * @return array<int,array{id:int,url:string,featured:bool}>
	 */
	public function images(): array {
		if ( ! $this->listing_id ) {
			return array();
		}
		$featured = (int) get_post_thumbnail_id( $this->listing_id );
		$ids      = get_children(
			array(
				'post_parent' => $this->listing_id,
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one listing's attachments.
					'relation' => 'OR',
					array(
						'key'   => '_rtcl_attachment_type',
						'value' => 'image',
					),
					array(
						'key'     => '_rtcl_attachment_type',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$out      = array();
		foreach ( $ids as $id ) {
			$src   = wp_get_attachment_image_src( (int) $id, 'th-thumb' );
			$out[] = array(
				'id'       => (int) $id,
				'url'      => $src ? (string) $src[0] : (string) wp_get_attachment_url( (int) $id ),
				'featured' => $featured === (int) $id,
			);
		}
		return $out;
	}
}
