<?php
/**
 * /compare/: up to 3 listings side by side — photo, title, price, town, then the category and the listing's details
 * (Form Builder fields, as on the listing page) row by row; a row nobody filled in is left out. Each value cell is
 * tied to its listing and row heading (headers), so the table reads well at any width without a label column.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type Torrehub\Modules\Listing\View[] $views  Selected listings.
 *     @type array|null                       $notice [ text, type ] after a change without JS.
 * }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Compare\Module as Compare;

$views    = (array) ( $args['views'] ?? array() );
$notice   = $args['notice'] ?? null;
$category = Compare::category();
$count    = count( $views );

// Rows: label => [ column => value ], in the order they first appear.
$rows = array(
	__( 'Price', 'torrehub' )    => array(),
	__( 'Town', 'torrehub' )     => array(),
	__( 'Category', 'torrehub' ) => array(),
);
foreach ( $views as $col => $view ) {
	$rows[ __( 'Price', 'torrehub' ) ][ $col ]    = '' !== $view->price['amount'] ? trim( $view->price['amount'] . ' ' . $view->price['suffix'] ) : '';
	$rows[ __( 'Town', 'torrehub' ) ][ $col ]     = $view->town ? html_entity_decode( $view->town->name, ENT_QUOTES ) : '';
	$rows[ __( 'Category', 'torrehub' ) ][ $col ] = $view->category_path();
	foreach ( (array) $view->fields['details'] as $tile ) {
		$rows[ (string) $tile['label'] ][ $col ] = (string) $tile['value'];
	}
}
$rows = array_filter( $rows, static fn( $values ) => '' !== implode( '', $values ) );
?>
<main id="main" class="th-main th-compare" tabindex="-1">
	<div class="th-container">
		<header class="th-compare__head">
			<?php
			get_template_part(
				'template-parts/components/breadcrumb',
				null,
				array(
					'items' => array(
						array(
							'label' => __( 'Home', 'torrehub' ),
							'url'   => home_url( '/' ),
						),
						array(
							'label' => __( 'Compare listings', 'torrehub' ),
							'url'   => '',
						),
					),
				)
			);
			?>
			<div class="th-compare__title-row">
				<div class="th-stack" style="--th-stack-gap:8px">
					<h1 class="th-compare__title"><?php esc_html_e( 'Compare listings', 'torrehub' ); ?></h1>
					<?php if ( $count ) : ?>
						<p class="th-compare__intro">
							<?php
							echo esc_html(
								$category
									/* translators: 1: category name, 2: listings selected, 3: maximum */
									? sprintf( __( '%1$s · %2$d of %3$d listings', 'torrehub' ), html_entity_decode( $category->name, ENT_QUOTES ), $count, Compare::MAX )
									/* translators: 1: listings selected, 2: maximum */
									: sprintf( __( '%1$d of %2$d listings', 'torrehub' ), $count, Compare::MAX )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
				<?php if ( $count ) : ?>
					<div class="th-cluster">
						<?php
						if ( $category && $count < Compare::MAX ) {
							th_component(
								'button',
								array(
									/* translators: %s: category name */
									'label'   => sprintf( __( 'Add from %s', 'torrehub' ), html_entity_decode( $category->name, ENT_QUOTES ) ),
									'variant' => 'soft',
									'size'    => 'sm',
									'icon'    => 'plus',
									'href'    => (string) get_term_link( $category ),
								)
							);
						}
						th_component(
							'button',
							array(
								'label'   => __( 'Clear', 'torrehub' ),
								'variant' => 'text',
								'size'    => 'sm',
								'href'    => Compare::url( array( 'clear' => 1 ) ),
								'attrs'   => array( 'rel' => 'nofollow' ),
							)
						);
						?>
					</div>
				<?php endif; ?>
			</div>
		</header>

		<?php
		if ( $notice ) {
			th_component(
				'alert',
				array(
					'variant'   => $notice['type'],
					'text'      => $notice['text'],
					'aria_role' => 'status',
				)
			);
		}
		?>

		<?php if ( ! $count ) : ?>
			<?php
			th_component(
				'empty-state',
				array(
					'icon'    => 'compare',
					'title'   => __( 'Nothing to compare yet', 'torrehub' ),
					'text'    => __( 'Press Compare on up to 3 listings from the same category — on the cards or on a listing’s page — and they line up here side by side.', 'torrehub' ),
					'actions' => array(
						array(
							'label'   => __( 'Browse listings', 'torrehub' ),
							'variant' => 'primary',
							'href'    => th_url_listings(),
						),
					),
				)
			);
			?>
		<?php else : ?>
			<div class="th-compare__card" style="--th-compare-cols:<?php echo (int) $count; ?>">
				<div class="th-compare__scroll" role="region" aria-labelledby="th-compare-caption" tabindex="0">
					<table class="th-compare__table">
						<caption class="th-sr-only" id="th-compare-caption">
							<?php
							/* translators: %d: number of listings */
							echo esc_html( sprintf( _n( 'Comparison of %d listing', 'Comparison of %d listings', $count, 'torrehub' ), $count ) );
							?>
						</caption>
						<thead>
							<tr>
								<?php foreach ( $views as $col => $view ) : ?>
									<th class="th-compare__listing" id="<?php echo esc_attr( 'th-cmp-c' . $col ); ?>">
										<a class="th-compare__media" href="<?php echo esc_url( (string) get_permalink( $view->id ) ); ?>" tabindex="-1" aria-hidden="true">
											<?php
											if ( $view->images ) {
												echo wp_get_attachment_image(
													$view->images[0],
													'th-card',
													false,
													array(
														'alt'     => '',
														'loading' => 'lazy',
														'sizes'   => '(min-width: 1000px) 360px, 33vw',
													)
												);
											}
											?>
										</a>
										<a class="th-compare__name" href="<?php echo esc_url( (string) get_permalink( $view->id ) ); ?>"><?php echo esc_html( $view->title ); ?></a>
										<a class="th-compare__remove" href="<?php echo esc_url( Compare::url( array( 'remove' => $view->id ) ) ); ?>" rel="nofollow">
											<?php th_icon( 'close', array( 'size' => 14 ) ); ?>
											<span><?php esc_html_e( 'Remove', 'torrehub' ); ?></span>
											<span class="th-sr-only"><?php echo esc_html( $view->title ); ?></span>
										</a>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_keys( $rows ) as $row_no => $label ) : ?>
								<tr class="th-compare__label">
									<th colspan="<?php echo (int) $count; ?>" id="<?php echo esc_attr( 'th-cmp-r' . $row_no ); ?>"><?php echo esc_html( $label ); ?></th>
								</tr>
								<tr class="<?php echo esc_attr( __( 'Price', 'torrehub' ) === $label ? 'th-compare__values th-compare__values--price' : 'th-compare__values' ); ?>">
									<?php foreach ( array_keys( $views ) as $col ) : ?>
										<?php $value = (string) ( $rows[ $label ][ $col ] ?? '' ); ?>
										<td headers="<?php echo esc_attr( 'th-cmp-c' . $col . ' th-cmp-r' . $row_no ); ?>">
											<?php if ( '' === $value ) : ?>
												<span aria-hidden="true">—</span><span class="th-sr-only"><?php esc_html_e( 'Not given', 'torrehub' ); ?></span>
											<?php else : ?>
												<?php echo esc_html( $value ); ?>
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>
	</div>
</main>
