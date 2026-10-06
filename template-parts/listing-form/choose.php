<?php
/**
 * S-02 — what are you listing: root categories, then a drill-in until a leaf category is picked.
 * Plain links (works without JS). Drafts in progress are listed above the roots.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\ListingForm\Workspace $ws }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Data\Directory;
use Torrehub\Modules\ListingForm\Module;
use Torrehub\Modules\ListingForm\Workspace;

$ws   = $args['ws'];
$base = Module::url();
$node = $ws->node;
?>
<div class="th-container th-lf-choose th-stack">
	<?php if ( ! $node ) : ?>
		<header class="th-lf-choose__head">
			<p class="th-lf-eyebrow"><?php esc_html_e( 'Post a listing', 'torrehub' ); ?></p>
			<h1 class="th-lf-choose__title"><?php esc_html_e( 'What are you listing?', 'torrehub' ); ?></h1>
			<p class="th-lf-choose__lead"><?php esc_html_e( 'Pick a category — the form only asks what matters for it. Every listing is checked by a person before it goes live.', 'torrehub' ); ?></p>
		</header>


		<?php get_template_part( 'template-parts/listing-form/drafts', null, array( 'back' => $base ) ); ?>

		<ul class="th-grid th-lf-roots" role="list">
			<?php foreach ( Directory::category_tree() as $root ) : ?>
				<?php $meta = th_category_meta( $root['slug'] ); ?>
				<li>
					<?php
					th_component(
						'cat-tile',
						array(
							'name' => $root['name'],
							'url'  => add_query_arg( 'th_cat', $root['id'], $base ),
							'icon' => $meta['icon'] ?? 'cat-services',
							'tint' => $meta['tint'] ?? 'grey',
							'meta' => $root['children']
								/* translators: %s: number of subcategories */
								? sprintf( _n( '%s category', '%s categories', count( $root['children'] ), 'torrehub' ), number_format_i18n( count( $root['children'] ) ) )
								: '',
						)
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<?php
		$crumbs = array(
			array(
				'label' => __( 'Post a listing', 'torrehub' ),
				'url'   => $base,
			),
		);
		foreach ( array_slice( $ws->chain, 0, -1 ) as $chain_term ) {
			$crumbs[] = array(
				'label' => html_entity_decode( $chain_term->name, ENT_QUOTES ),
				'url'   => add_query_arg( 'th_cat', $chain_term->term_id, $base ),
			);
		}
		$crumbs[] = array( 'label' => $node['name'] );
		th_component( 'breadcrumb', array( 'items' => $crumbs ) );

		$root_meta = $ws->root_meta();
		$form      = $root_meta ? Workspace::form_by_id( (int) $root_meta['form_id'] ) : null;
		$heading   = 1 === count( $ws->chain ) ? __( 'Which kind?', 'torrehub' ) : $node['name'];
		?>
		<header class="th-lf-choose__head">
			<h1 class="th-lf-choose__title"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( 1 === count( $ws->chain ) ) : ?>
				<p class="th-lf-choose__lead"><?php echo esc_html( $node['name'] ); ?></p>
			<?php endif; ?>
		</header>

		<ul class="th-lf-picks" role="list">
			<?php foreach ( $node['children'] as $child ) : ?>
				<li>
					<a class="th-lf-pick" href="<?php echo esc_url( add_query_arg( 'th_cat', $child['id'], $base ) ); ?>">
						<span class="th-lf-pick__radio" aria-hidden="true"></span>
						<span class="th-lf-pick__name"><?php echo esc_html( $child['name'] ); ?></span>
						<span class="th-lf-pick__count">
							<span class="th-sr-only"><?php esc_html_e( 'Listings:', 'torrehub' ); ?></span>
							<?php echo esc_html( number_format_i18n( (int) $child['count'] ) ); ?>
						</span>
						<?php th_icon( 'chevron-right', array( 'size' => 16 ) ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $form ) : ?>
			<?php
			$total       = count( array_filter( (array) $form->fields, static fn( $f ) => is_array( $f ) && \Torrehub\Modules\ListingForm\Fields::shown( $f ) ) );
			$conditional = (bool) array_filter( (array) $form->sections, static fn( $s ) => null !== \Torrehub\Modules\ListingForm\Fields::logics( $s['logics'] ?? null ) );
			?>
			<p class="th-lf-choose__note">
				<?php
				echo esc_html(
					$conditional
						/* translators: 1: form name, 2: number of fields */
						? sprintf( __( '%1$s form · %2$s fields — you’ll only see the ones that apply', 'torrehub' ), html_entity_decode( (string) $form->title, ENT_QUOTES ), number_format_i18n( $total ) )
						/* translators: 1: form name, 2: number of fields */
						: sprintf( __( '%1$s form · %2$s fields', 'torrehub' ), html_entity_decode( (string) $form->title, ENT_QUOTES ), number_format_i18n( $total ) )
				);
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
