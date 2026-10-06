<?php
/**
 * S-04 / S-14 — listing form workspace: rail (category, section pills, allowance, live preview) + one section at a
 * time (all sections stacked without JS) + footer bar (Back, Preview, required fields left, Continue / Publish).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\ListingForm\Workspace $ws }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\ListingForm\Fields;
use Torrehub\Modules\ListingForm\Module;

$ws             = $args['ws'];
$chain          = $ws->chain;
$leaf           = $chain ? end( $chain ) : null;
$root_meta      = $ws->root_meta();
$switches       = Fields::section_switches( (array) $ws->form->sections );
$first          = $ws->sections[0]['uuid'] ?? '';
$listing_status = $ws->listing_id ? get_post_status( $ws->listing_id ) : '';
$re_review      = 'edit' === $ws->mode && 'publish' === $listing_status && 'pending' === ( get_option( 'rtcl_moderation_settings', array() )['edited_listing_status'] ?? '' );
?>
<form id="th-lf-form" class="th-container th-lf-workspace" method="post" action="<?php echo esc_url( Module::url() ); ?>" novalidate data-th-listing-form>
	<input type="hidden" name="listing_type" value="<?php echo esc_attr( $ws->listing_type ); ?>">
	<?php foreach ( $chain as $i => $chain_term ) : ?>
		<input type="hidden" name="<?php echo esc_attr( 'category[' . $i . '][term_id]' ); ?>" value="<?php echo esc_attr( (string) $chain_term->term_id ); ?>">
	<?php endforeach; ?>

	<aside class="th-lf-rail" aria-label="<?php esc_attr_e( 'Listing overview', 'torrehub' ); ?>">
		<section class="th-lf-card th-lf-cat" aria-labelledby="th-lf-cat-title">
			<h2 class="th-lf-eyebrow" id="th-lf-cat-title"><?php esc_html_e( 'Category', 'torrehub' ); ?></h2>
			<?php if ( $chain ) : ?>
				<p class="th-lf-cat__root">
					<?php if ( $root_meta ) : ?>
						<?php th_icon( (string) $root_meta['icon'], array( 'size' => 18 ) ); ?>
					<?php endif; ?>
					<span><?php echo esc_html( html_entity_decode( $chain[0]->name, ENT_QUOTES ) ); ?></span>
				</p>
				<?php if ( count( $chain ) > 1 ) : ?>
					<p class="th-lf-cat__path"><?php echo esc_html( implode( ' › ', array_map( static fn( $t ) => html_entity_decode( $t->name, ENT_QUOTES ), array_slice( $chain, 1 ) ) ) ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( 'edit' !== $ws->mode ) : ?>
				<a class="th-lf-cat__change" href="<?php echo esc_url( Module::url() ); ?>" data-th-lf-change><?php esc_html_e( 'Change', 'torrehub' ); ?></a>
			<?php endif; ?>
		</section>

		<nav class="th-lf-card th-lf-nav" aria-labelledby="th-lf-nav-title">
			<div class="th-lf-nav__head">
				<h2 class="th-lf-eyebrow" id="th-lf-nav-title"><?php esc_html_e( 'Sections', 'torrehub' ); ?></h2>
				<span class="th-lf-nav__total" data-th-lf-total></span>
			</div>
			<ol class="th-lf-nav__list" role="list">
				<?php foreach ( $ws->sections as $section ) : ?>
					<li>
						<a class="th-step-pill" href="<?php echo esc_attr( '#th-lf-sec-' . $section['uuid'] ); ?>" data-th-lf-goto="<?php echo esc_attr( $section['uuid'] ); ?>"<?php echo $first === $section['uuid'] ? ' aria-current="step"' : ''; ?>>
							<span><?php echo esc_html( $section['title'] ); ?></span>
							<small data-th-lf-count></small>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>

		<?php
		/**
		 * Rail cards (Quota: free-listing allowance).
		 *
		 * @param Torrehub\Modules\ListingForm\Workspace $ws Workspace.
		 */
		do_action( 'th_listing_form_rail', $ws );
		?>

		<section class="th-lf-card th-lf-preview" aria-labelledby="th-lf-preview-title" data-th-lf-preview>
			<h2 class="th-lf-eyebrow" id="th-lf-preview-title"><?php esc_html_e( 'Live preview', 'torrehub' ); ?></h2>
			<div class="th-lf-preview__cover" data-th-lf-preview-cover>
				<span class="th-lf-preview__empty"><?php th_icon( 'image', array( 'size' => 24 ) ); ?><span><?php esc_html_e( 'Cover', 'torrehub' ); ?></span></span>
			</div>
			<p class="th-lf-preview__price" data-th-lf-preview-price></p>
			<p class="th-lf-preview__title" data-th-lf-preview-title><?php esc_html_e( 'Your listing title', 'torrehub' ); ?></p>
			<p class="th-lf-preview__meta" data-th-lf-preview-meta><?php echo esc_html( $leaf ? html_entity_decode( $leaf->name, ENT_QUOTES ) : '' ); ?></p>
		</section>
	</aside>

	<div class="th-lf-main">
		<div class="th-lf-alert" data-th-lf-alert tabindex="-1" hidden></div>

		<noscript>
			<?php
			th_component(
				'alert',
				array(
					'variant' => 'error',
					'title'   => __( 'JavaScript is needed to post a listing', 'torrehub' ),
					'text'    => __( 'Photos, the map pin and saving all need it. Turn JavaScript on and reload the page.', 'torrehub' ),
				)
			);
			?>
		</noscript>

		<?php if ( $re_review ) : ?>
			<?php
			th_component(
				'alert',
				array(
					'variant' => 'neutral',
					'text'    => __( 'Your listing is live. When you save changes, it goes back for a quick review before the new version shows.', 'torrehub' ),
				)
			);
			?>
		<?php endif; ?>

		<?php foreach ( $ws->sections as $index => $section ) : ?>
			<?php $title_id = 'th-lf-sec-' . $section['uuid'] . '-title'; ?>
			<section class="th-lf-card th-lf-section" id="<?php echo esc_attr( 'th-lf-sec-' . $section['uuid'] ); ?>" data-th-lf-section="<?php echo esc_attr( $section['uuid'] ); ?>" aria-labelledby="<?php echo esc_attr( $title_id ); ?>" tabindex="-1">
				<h2 class="th-lf-section__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $section['title'] ); ?></h2>
				<div class="th-lf-grid">
					<?php
					foreach ( $section['fields'] as $field ) {
						get_template_part(
							'template-parts/listing-form/field',
							null,
							array(
								'ws'     => $ws,
								'field'  => $field,
								'value'  => Fields::value( $ws->values, $field ),
								'switch' => in_array( $field['uuid'], $switches, true ),
							)
						);
					}
					?>
				</div>
			</section>
		<?php endforeach; ?>

		<div class="th-lf-footer">
			<button type="button" class="th-btn th-btn--outline" data-th-lf-prev hidden><?php th_icon( 'arrow-left', array( 'size' => 18 ) ); ?><span><?php esc_html_e( 'Back', 'torrehub' ); ?></span></button>
			<button type="button" class="th-btn th-btn--outline" data-th-dialog-open="th-lf-preview-dialog" aria-controls="th-lf-preview-dialog" aria-haspopup="dialog"><?php esc_html_e( 'Preview listing', 'torrehub' ); ?></button>
			<span class="th-lf-footer__left" data-th-lf-left aria-live="polite"></span>
			<button type="button" class="th-btn th-btn--accent" data-th-lf-next hidden></button>
			<button type="submit" class="th-btn th-btn--accent" data-th-lf-submit><?php echo esc_html( 'edit' === $ws->mode ? __( 'Save changes', 'torrehub' ) : __( 'Publish listing', 'torrehub' ) ); ?></button>
		</div>
	</div>
</form>

<dialog class="th-dialog th-lf-preview-dialog" id="th-lf-preview-dialog" data-th-dialog aria-labelledby="th-lf-preview-dialog-title">
	<div class="th-dialog__head">
		<h2 class="th-dialog__title" id="th-lf-preview-dialog-title"><?php esc_html_e( 'Preview', 'torrehub' ); ?></h2>
		<?php
		th_component(
			'button',
			array(
				'variant'    => 'neutral',
				'icon'       => 'close',
				'icon_only'  => true,
				'size'       => 'sm',
				'aria_label' => __( 'Close', 'torrehub' ),
				'attrs'      => array( 'data-th-dialog-close' => '' ),
			)
		);
		?>
	</div>
	<div class="th-dialog__body" data-th-lf-preview-full></div>
</dialog>
