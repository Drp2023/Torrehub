<?php
/**
 * /styleguide — F-01…F-05 rebuilt from the real components (visual-regression baseline).
 * Routed by Torrehub\Modules\Styleguide; not a selectable page template.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

get_header();

$sg_section = static function ( string $id, string $code, string $title, callable $body ): void {
	?>
	<section class="sg-section" id="<?php echo esc_attr( $id ); ?>" aria-labelledby="<?php echo esc_attr( $id ); ?>-title">
		<header class="sg-section__head">
			<p class="th-micro"><?php echo esc_html( $code ); ?></p>
			<h2 id="<?php echo esc_attr( $id ); ?>-title"><?php echo esc_html( $title ); ?></h2>
		</header>
		<?php $body(); ?>
	</section>
	<?php
};

$sg_spec = static function ( string $label, callable $body, string $extra_class = '' ): void {
	?>
	<figure class="<?php echo esc_attr( th_classes( 'sg-spec', $extra_class ) ); ?>">
		<div class="sg-spec__body"><?php $body(); ?></div>
		<figcaption><?php echo esc_html( $label ); ?></figcaption>
	</figure>
	<?php
};

$colours = array(
	__( 'Brand', 'torrehub' )    => array( 'blue', 'blue-deep', 'blue-ink', 'orange', 'orange-pressed', 'clay', 'clay-text' ),
	__( 'Neutrals', 'torrehub' ) => array( 'ink', 'night', 'text', 'muted', 'disabled', 'line-3', 'line-2', 'line', 'surface-2', 'bg', 'white' ),
	__( 'Tints', 'torrehub' )    => array( 'tint-blue', 'tint-clay', 'tint-amber', 'success-bg', 'error-bg', 'skeleton' ),
	__( 'Semantic', 'torrehub' ) => array( 'success', 'success-text', 'error', 'error-text', 'tint-amber-text', 'tint-amber-icon', 'blue-label', 'success-label' ),
	__( 'On dark', 'torrehub' )  => array( 'footer-text', 'footer-muted', 'footer-chip', 'footer-accent', 'on-blue', 'on-photo-muted' ),
);

$categories = array(
	'services'              => __( 'Services', 'torrehub' ),
	'properties'            => __( 'Properties', 'torrehub' ),
	'auto-moto-boats'       => __( 'Auto / Moto / Boats', 'torrehub' ),
	'restaurants-nightlife' => __( 'Restaurants & Nightlife', 'torrehub' ),
	'events'                => __( 'Events', 'torrehub' ),
	'jobs'                  => __( 'Jobs', 'torrehub' ),
	'public-information'    => __( 'Public Information', 'torrehub' ),
	'marketplace'           => __( 'Marketplace', 'torrehub' ),
	'tourist-attractions'   => __( 'Tourist Attractions', 'torrehub' ),
	'leisure-sport'         => __( 'Leisure & Sport', 'torrehub' ),
);
$cat_map    = th_category_map();
?>
<main id="main" class="th-main sg" tabindex="-1">
	<div class="th-container">
		<header class="sg-hero">
			<p class="th-micro"><?php esc_html_e( 'Direction C · Modern Local Hub', 'torrehub' ); ?></p>
			<h1><?php esc_html_e( 'Styleguide', 'torrehub' ); ?></h1>
			<p class="th-lead"><?php esc_html_e( 'Every component and state, rendered by the theme itself. Used as the visual-regression baseline (1440 / 390).', 'torrehub' ); ?></p>
			<nav class="th-cluster" aria-label="<?php esc_attr_e( 'Styleguide sections', 'torrehub' ); ?>">
				<?php
				foreach ( array(
					'f01'     => 'F-01 Colour',
					'f02'     => 'F-02 Type · space · depth',
					'f03'     => 'F-03 Buttons',
					'f04'     => 'F-04 Inputs',
					'f05'     => 'F-05 Cards & feedback',
					'nav'     => 'Navigation',
					'modules' => 'Modules',
					'icons'   => 'Icons',
				) as $anchor => $label ) {
					th_component(
						'chip',
						array(
							'type'    => 'link',
							'variant' => 'topic',
							'label'   => $label,
							'href'    => '#' . $anchor,
						)
					);
				}
				?>
			</nav>
		</header>

		<?php
		/* ------------------------------------------------------------- F-01 Colour */
		$sg_section(
			'f01',
			'F-01',
			__( 'Colour', 'torrehub' ),
			static function () use ( $colours ) {
				foreach ( $colours as $group => $names ) {
					echo '<h3 class="sg-sub">' . esc_html( $group ) . '</h3><ul class="sg-swatches" role="list">';
					foreach ( $names as $name ) {
						printf(
							'<li class="sg-swatch"><span class="sg-swatch__chip" style="background:var(--th-%1$s)"></span><code>--th-%1$s</code><span class="sg-swatch__value" data-sg-var="--th-%1$s"></span></li>',
							esc_attr( $name )
						);
					}
					echo '</ul>';
				}
			}
		);

		/* ------------------------------------------------------------- F-02 Type, spacing, radius, depth, categories */
		$sg_section(
			'f02',
			'F-02',
			__( 'Type, spacing, radius, depth, category tiles', 'torrehub' ),
			static function () use ( $categories, $cat_map ) {
				?>
				<div class="sg-type th-stack">
					<p class="th-display sg-type__hero"><?php esc_html_e( 'Find your thing on the Costa Blanca', 'torrehub' ); ?> <small>Hero · Space Grotesk 700</small></p>
					<p class="th-display" style="font-size:var(--th-fs-h1-page)"><?php esc_html_e( 'About Torrehub', 'torrehub' ); ?> <small>H1 page</small></p>
					<h1 style="margin:0"><?php esc_html_e( 'Plumbing in Torrevieja', 'torrehub' ); ?> <small>H1</small></h1>
					<h2><?php esc_html_e( 'New near you', 'torrehub' ); ?> <small>H2 · 600</small></h2>
					<h3><?php esc_html_e( 'Car specifications', 'torrehub' ); ?> <small>H3</small></h3>
					<p class="th-lead"><?php esc_html_e( 'Lead — local businesses, classifieds and what’s on, in one place.', 'torrehub' ); ?> <small>17 / Figtree 400</small></p>
					<p><?php esc_html_e( 'Body — Árvíztűrő tükörfúrógép · Șase țânțari · Åsa är här · Señor · €12,450.', 'torrehub' ); ?> <small>15.5 · latin-ext check</small></p>
					<p class="th-meta"><?php esc_html_e( 'Meta — Torrevieja · 2 days ago', 'torrehub' ); ?> <small>12.5</small></p>
					<p class="th-micro"><?php esc_html_e( 'Micro label', 'torrehub' ); ?> <small>11.5 · 700 · caps — the floor</small></p>
				</div>

				<h3 class="sg-sub"><?php esc_html_e( 'Spacing (4px base)', 'torrehub' ); ?></h3>
				<ul class="sg-spacing" role="list">
					<?php
					foreach ( array(
						2 => 8,
						4 => 16,
						6 => 24,
						7 => 32,
						8 => 48,
						9 => 80,
					) as $step => $px ) :
						?>
						<li><span style="width:var(--th-s-<?php echo (int) $step; ?>)"></span><code>--th-s-<?php echo (int) $step; ?></code> <?php echo (int) $px; ?>px</li>
					<?php endforeach; ?>
				</ul>

				<h3 class="sg-sub"><?php esc_html_e( 'Radius & depth', 'torrehub' ); ?></h3>
				<ul class="sg-radii" role="list">
					<?php foreach ( array( 'pill', 'input', 'image', 'tile', 'card', 'hero' ) as $r ) : ?>
						<li><span style="border-radius:var(--th-r-<?php echo esc_attr( $r ); ?>)"></span><code>--th-r-<?php echo esc_html( $r ); ?></code></li>
					<?php endforeach; ?>
					<li><span class="sg-lift"></span><code>--th-lift</code> <?php esc_html_e( 'sheets, dropdowns, map overlays only', 'torrehub' ); ?></li>
				</ul>

				<h3 class="sg-sub"><?php esc_html_e( 'Category tiles (10 roots · 5 tints)', 'torrehub' ); ?></h3>
				<div class="th-grid" style="--th-cols-sm:2;--th-cols-md:3;--th-cols-lg:5">
					<?php
					foreach ( $categories as $slug => $name ) {
						th_component(
							'cat-tile',
							array(
								'name' => $name,
								'url'  => '#',
								'icon' => $cat_map[ $slug ]['icon'],
								'tint' => $cat_map[ $slug ]['tint'],
								'meta' => __( '12 listings · 8 categories', 'torrehub' ),
							)
						);
					}
					?>
				</div>
				<div class="th-grid sg-gap" style="--th-cols-sm:1;--th-cols-md:2;--th-cols-lg:3">
					<?php
					foreach ( array_slice( $categories, 0, 3, true ) as $slug => $name ) {
						th_component(
							'cat-tile',
							array(
								'name'   => $name,
								'url'    => '#',
								'icon'   => $cat_map[ $slug ]['icon'],
								'tint'   => $cat_map[ $slug ]['tint'],
								'layout' => 'row',
							)
						);
					}
					?>
				</div>
				<?php
			}
		);

		/* ------------------------------------------------------------- F-03 Buttons */
		$sg_section(
			'f03',
			'F-03',
			__( 'Buttons', 'torrehub' ),
			static function () use ( $sg_spec ) {
				$variants = array(
					'accent'       => __( 'Post a listing', 'torrehub' ),
					'primary'      => __( 'Contact', 'torrehub' ),
					'outline'      => __( 'Log in', 'torrehub' ),
					'soft'         => __( 'Show phone', 'torrehub' ),
					'destructive'  => __( 'Delete', 'torrehub' ),
					'ink'          => __( 'Write a review', 'torrehub' ),
					'clay'         => __( 'Upload document', 'torrehub' ),
					'whatsapp'     => __( 'WhatsApp', 'torrehub' ),
					'neutral'      => __( 'Save & exit', 'torrehub' ),
					'clay-outline' => __( 'My listings', 'torrehub' ),
					'text'         => __( 'Report', 'torrehub' ),
				);
				echo '<div class="sg-specs">';
				foreach ( $variants as $variant => $label ) {
					$sg_spec(
						$variant,
						static function () use ( $variant, $label ) {
							th_component(
								'button',
								array(
									'label'   => $label,
									'variant' => $variant,
									'icon'    => 'whatsapp' === $variant ? 'whatsapp' : '',
								)
							);
						}
					);
				}
				$sg_spec(
					'disabled',
					static fn() => th_component(
						'button',
						array(
							'label'    => __( 'Publish', 'torrehub' ),
							'variant'  => 'accent',
							'disabled' => true,
						)
					)
				);
				$sg_spec(
					'loading (primary)',
					static fn() => th_component(
						'button',
						array(
							'label'   => __( 'Logging you in…', 'torrehub' ),
							'variant' => 'primary',
							'loading' => true,
						)
					)
				);
				$sg_spec(
					'sizes sm / md / lg',
					static function () {
						th_component(
							'button',
							array(
								'label'   => 'Small',
								'variant' => 'accent',
								'size'    => 'sm',
							)
						);
						th_component(
							'button',
							array(
								'label'   => 'Medium',
								'variant' => 'accent',
							)
						);
						th_component(
							'button',
							array(
								'label'   => 'Large',
								'variant' => 'accent',
								'size'    => 'lg',
							)
						);
					}
				);
				$sg_spec(
					'icon buttons',
					static function () {
						th_component(
							'button',
							array(
								'variant'    => 'white',
								'icon'       => 'share',
								'icon_only'  => true,
								'aria_label' => __( 'Share', 'torrehub' ),
							)
						);
						th_component(
							'button',
							array(
								'variant'    => 'neutral',
								'size'       => 'sm',
								'icon'       => 'print',
								'icon_only'  => true,
								'aria_label' => __( 'Print', 'torrehub' ),
							)
						);
						th_component(
							'button',
							array(
								'variant'    => 'primary',
								'icon'       => 'chat',
								'icon_only'  => true,
								'aria_label' => __( 'Chat', 'torrehub' ),
							)
						);
						?>
					<button type="button" class="th-btn th-btn--icon th-btn--white th-btn--fav" aria-pressed="false"><?php th_icon( 'heart' ); ?><?php th_icon( 'heart-filled' ); ?><span class="th-sr-only"><?php esc_html_e( 'Save', 'torrehub' ); ?></span></button>
					<button type="button" class="th-btn th-btn--icon th-btn--white th-btn--fav" aria-pressed="true"><?php th_icon( 'heart' ); ?><?php th_icon( 'heart-filled' ); ?><span class="th-sr-only"><?php esc_html_e( 'Saved', 'torrehub' ); ?></span></button>
						<?php
					}
				);
				$sg_spec(
					'on dark (ghost / white)',
					static function () {
						th_component(
							'button',
							array(
								'label'   => __( 'How it works', 'torrehub' ),
								'variant' => 'ghost',
							)
						);
						th_component(
							'button',
							array(
								'label'   => __( 'Open map view', 'torrehub' ),
								'variant' => 'white',
							)
						);
					},
					'sg-spec--dark'
				);
				$sg_spec(
					'block (mobile CTA)',
					static fn() => th_component(
						'button',
						array(
							'label'   => __( 'Show 9 results', 'torrehub' ),
							'variant' => 'accent',
							'size'    => 'lg',
							'block'   => true,
						)
					),
					'sg-spec--wide'
				);
				echo '</div>';
				echo '<p class="th-meta sg-note">' . esc_html__( 'Hover is not drawn in the design — derived (12% ink mix). Accent pressed = #B25E00 with white text (press and hold).', 'torrehub' ) . '</p>';
			}
		);

		/* ------------------------------------------------------------- F-04 Inputs */
		$sg_section(
			'f04',
			'F-04',
			__( 'Inputs & controls', 'torrehub' ),
			static function () use ( $sg_spec ) {
				echo '<div class="sg-specs sg-specs--fields">';
				$sg_spec(
					'default',
					static fn() => th_component(
						'field',
						array(
							'id'          => 'sg-f1',
							'label'       => __( 'Email', 'torrehub' ),
							'type'        => 'email',
							'placeholder' => 'you@example.com',
							'help'        => __( 'We never show it publicly.', 'torrehub' ),
						)
					)
				);
				$sg_spec(
					'error',
					static fn() => th_component(
						'field',
						array(
							'id'    => 'sg-f2',
							'label' => __( 'Username', 'torrehub' ),
							'value' => 'marco',
							'error' => __( 'Already taken — try marco.torrevieja', 'torrehub' ),
						)
					)
				);
				$sg_spec(
					'verified',
					static fn() => th_component(
						'field',
						array(
							'id'    => 'sg-f3',
							'label' => 'NIE',
							'value' => 'X1234567L',
							'valid' => true,
							'help'  => __( 'Format and check letter OK', 'torrehub' ),
						)
					)
				);
				$sg_spec(
					'read-only',
					static fn() => th_component(
						'field',
						array(
							'id'       => 'sg-f4',
							'label'    => __( 'Category', 'torrehub' ),
							'value'    => 'Auto / Moto / Boats',
							'readonly' => true,
						)
					)
				);
				$sg_spec(
					'disabled (not drawn — derived)',
					static fn() => th_component(
						'field',
						array(
							'id'          => 'sg-f5',
							'label'       => __( 'Licence', 'torrehub' ),
							'value'       => '',
							'placeholder' => '—',
							'disabled'    => true,
						)
					)
				);
				$sg_spec(
					'select',
					static fn() => th_component(
						'field',
						array(
							'id'      => 'sg-f6',
							'label'   => __( 'Body type', 'torrehub' ),
							'type'    => 'select',
							'options' => array(
								''      => __( 'Choose…', 'torrehub' ),
								'suv'   => 'SUV',
								'sedan' => 'Sedan',
							),
						)
					)
				);
				$sg_spec(
					'prefix / suffix',
					static function () {
						th_component(
							'field',
							array(
								'id'        => 'sg-f7',
								'label'     => __( 'Price', 'torrehub' ),
								'type'      => 'number',
								'prefix'    => '€',
								'value'     => '12450',
								'inputmode' => 'numeric',
							)
						);
						th_component(
							'field',
							array(
								'id'        => 'sg-f8',
								'label'     => __( 'Mileage', 'torrehub' ),
								'type'      => 'number',
								'suffix'    => 'km',
								'value'     => '48000',
								'inputmode' => 'numeric',
							)
						);
					}
				);
				$sg_spec(
					'password + strength',
					static fn() => th_component(
						'field',
						array(
							'id'             => 'sg-f9',
							'label'          => __( 'Password', 'torrehub' ),
							'type'           => 'password',
							'value'          => 'Torrevieja-2026',
							'password_meter' => true,
							'autocomplete'   => 'new-password',
						)
					)
				);
				$sg_spec(
					'textarea + counter',
					static fn() => th_component(
						'field',
						array(
							'id'          => 'sg-f10',
							'label'       => __( 'Message', 'torrehub' ),
							'type'        => 'textarea',
							'maxlength'   => 2000,
							'placeholder' => __( 'Hi, is this still available?', 'torrehub' ),
						)
					),
					'sg-spec--wide'
				);
				$sg_spec(
					'pill input (on blue)',
					static fn() => th_component(
						'field',
						array(
							'id'          => 'sg-f11',
							'label'       => __( 'Email', 'torrehub' ),
							'type'        => 'email',
							'pill'        => true,
							'on_color'    => true,
							'placeholder' => 'you@example.com',
						)
					),
					'sg-spec--blue'
				);
				echo '</div>';

				echo '<div class="sg-specs">';
				$sg_spec(
					'checkbox',
					static function () {
						?>
					<div class="th-stack" style="--th-stack-gap:10px">
						<label class="th-check"><input type="checkbox" checked> <?php esc_html_e( 'Keep me logged in', 'torrehub' ); ?></label>
						<label class="th-check"><input type="checkbox"> <?php esc_html_e( 'I accept the terms', 'torrehub' ); ?></label>
						<label class="th-check"><input type="checkbox" disabled> <?php esc_html_e( 'Disabled', 'torrehub' ); ?></label>
					</div>
						<?php
					}
				);
				$sg_spec(
					'radio',
					static function () {
						?>
					<div class="th-stack" style="--th-stack-gap:10px">
						<label class="th-check"><input type="radio" name="sg-radio" checked> <?php esc_html_e( 'Sale', 'torrehub' ); ?></label>
						<label class="th-check"><input type="radio" name="sg-radio"> <?php esc_html_e( 'Rent', 'torrehub' ); ?></label>
					</div>
						<?php
					}
				);
				$sg_spec(
					'toggle on / off',
					static function () {
						?>
					<div class="th-stack" style="--th-stack-gap:4px">
						<label class="th-toggle"><span><?php esc_html_e( 'Emergency call-outs', 'torrehub' ); ?></span><input type="checkbox" role="switch" checked></label>
						<label class="th-toggle"><span><?php esc_html_e( 'Comes to you', 'torrehub' ); ?></span><input type="checkbox" role="switch"></label>
					</div>
						<?php
					}
				);
				$sg_spec(
					'segmented',
					static function () {
						?>
					<fieldset class="th-segmented">
						<legend class="th-sr-only"><?php esc_html_e( 'Transmission', 'torrehub' ); ?></legend>
						<label><input type="radio" name="sg-seg" checked><?php esc_html_e( 'Manual', 'torrehub' ); ?></label>
						<label><input type="radio" name="sg-seg"><?php esc_html_e( 'Automatic', 'torrehub' ); ?></label>
					</fieldset>
						<?php
					}
				);
				$sg_spec(
					'range',
					static function () {
						?>
					<div class="th-stack" data-th-range-group style="--th-stack-gap:10px;width:100%">
						<div class="th-range-values"><span data-th-range-out="lo"></span><span data-th-range-out="hi"></span></div>
						<div class="th-range" data-th-range data-prefix="€" data-plus="1">
							<span class="th-range__track"></span><span class="th-range__fill"></span>
							<input type="range" min="0" max="200" step="5" value="30" aria-label="<?php esc_attr_e( 'Minimum hourly rate', 'torrehub' ); ?>">
							<input type="range" min="0" max="200" step="5" value="90" aria-label="<?php esc_attr_e( 'Maximum hourly rate', 'torrehub' ); ?>">
						</div>
					</div>
						<?php
					},
					'sg-spec--wide'
				);
				$sg_spec(
					'star input',
					static function () {
						?>
					<fieldset class="th-stars">
						<legend class="th-sr-only"><?php esc_html_e( 'Your rating', 'torrehub' ); ?></legend>
						<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
							<input type="radio" id="sg-star-<?php echo (int) $i; ?>" name="sg-stars" value="<?php echo (int) $i; ?>" <?php checked( 4, $i ); ?>>
							<label for="sg-star-<?php echo (int) $i; ?>"><?php th_icon( 'star-filled', array( 'size' => 28 ) ); ?><span class="th-sr-only"><?php echo esc_html( sprintf( /* translators: %d: stars */ _n( '%d star', '%d stars', $i, 'torrehub' ), $i ) ); ?></span></label>
						<?php endfor; ?>
					</fieldset>
						<?php
					}
				);
				echo '</div>';

				echo '<h3 class="sg-sub">' . esc_html__( 'Choice cards (account type, item type)', 'torrehub' ) . '</h3>';
				?>
				<fieldset class="th-grid sg-reset" style="--th-cols-md:3;--th-cols-lg:3">
					<legend class="th-sr-only"><?php esc_html_e( 'Account type', 'torrehub' ); ?></legend>
					<?php
					foreach ( array(
						'member'   => array( 'user', __( 'Member', 'torrehub' ), __( 'Browse, save and message sellers.', 'torrehub' ) ),
						'seller'   => array( 'account-private-seller', __( 'Private Seller', 'torrehub' ), __( 'Sell your own things. NIE required.', 'torrehub' ) ),
						'business' => array( 'account-business-seller', __( 'Business Seller', 'torrehub' ), __( 'List your business. NIF required.', 'torrehub' ) ),
					) as $value => $c ) :
						?>
						<label class="th-choice">
							<input type="radio" name="sg-account" value="<?php echo esc_attr( $value ); ?>" <?php checked( 'seller', $value ); ?>>
							<span class="th-choice__radio"><?php th_icon( 'check', array( 'size' => 12 ) ); ?></span>
							<span class="th-icon-circle"><?php th_icon( $c[0] ); ?></span>
							<span class="th-choice__title"><?php echo esc_html( $c[1] ); ?></span>
							<span class="th-meta"><?php echo esc_html( $c[2] ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<?php
			}
		);

		/* ------------------------------------------------------------- F-05 Cards & feedback */
		$sg_section(
			'f05',
			'F-05',
			__( 'Chips, badges, cards, feedback', 'torrehub' ),
			static function () use ( $sg_spec ) {
				echo '<div class="sg-specs">';
				$sg_spec(
					'active filters',
					static function () {
						th_component(
							'chip',
							array(
								'type'    => 'link',
								'variant' => 'ink',
								'label'   => __( 'Filters', 'torrehub' ),
								'icon'    => 'filter',
								'count'   => 5,
								'href'    => '#',
							)
						);
						th_component(
							'chip',
							array(
								'type'  => 'filter',
								'label' => __( 'Plumbing', 'torrehub' ),
								'href'  => '#',
							)
						);
						th_component(
							'chip',
							array(
								'type'  => 'filter',
								'label' => __( 'Within 10 km', 'torrehub' ),
								'href'  => '#',
							)
						);
						th_component(
							'chip',
							array(
								'type'    => 'link',
								'variant' => 'success',
								'label'   => __( 'Verified only', 'torrehub' ),
								'href'    => '#',
							)
						);
						th_component(
							'chip',
							array(
								'type'    => 'link',
								'variant' => 'clear',
								'label'   => __( 'Clear all', 'torrehub' ),
								'href'    => '#',
							)
						);
					},
					'sg-spec--wide'
				);
				$sg_spec(
					'choice chips',
					static function () {
						foreach ( array(
							'EN' => true,
							'ES' => true,
							'SV' => false,
							'HU' => false,
						) as $l => $c ) {
							th_component(
								'chip',
								array(
									'type'    => 'choice',
									'label'   => $l,
									'name'    => 'sg-lang[]',
									'value'   => strtolower( $l ),
									'checked' => $c,
								)
							);
						}
					}
				);
				$sg_spec(
					'radius chips',
					static function () {
						foreach ( array(
							'5 km'  => false,
							'10 km' => true,
							'25 km' => false,
							'50 km' => false,
						) as $l => $c ) {
							th_component(
								'chip',
								array(
									'type'       => 'choice',
									'variant'    => 'radius',
									'input_type' => 'radio',
									'label'      => $l,
									'name'       => 'sg-radius',
									'value'      => $l,
									'checked'    => $c,
								)
							);
						}
					}
				);
				$sg_spec(
					'attribute / suggestion / amenity',
					static function () {
						th_component(
							'chip',
							array(
								'label'   => '2019',
								'variant' => 'attr',
							)
						);
						th_component(
							'chip',
							array(
								'label'   => 'Diesel',
								'variant' => 'attr',
							)
						);
						th_component(
							'chip',
							array(
								'label'   => __( 'Plumber', 'torrehub' ),
								'variant' => 'suggest',
							)
						);
						th_component(
							'chip',
							array(
								'label'   => __( 'Sea view', 'torrehub' ),
								'variant' => 'amenity',
								'icon'    => 'check',
							)
						);
					},
					'sg-spec--wide'
				);
				$sg_spec(
					'badges',
					static function () {
						foreach ( array(
							array( 'Featured', 'featured', '' ),
							array( 'Urgent', 'urgent', '' ),
							array( 'Verified seller', 'verified', 'check' ),
							array( 'Pending', 'pending', '' ),
							array( 'Expired', 'expired', '' ),
							array( 'Expired', 'expired-solid', '' ),
							array( 'Open now · until 20:00', 'open', '' ),
							array( 'Active', 'active', '' ),
							array( 'Not verified', 'neutral', '' ),
							array( 'Member', 'member', '' ),
							array( 'Private seller', 'seller', '' ),
							array( 'Business', 'business', '' ),
							array( 'Selected', 'selected', '' ),
							array( 'Cover', 'cover', '' ),
						) as $b ) {
							th_component(
								'badge',
								array(
									'label'   => $b[0],
									'variant' => $b[1],
									'icon'    => $b[2],
								)
							);
						}
						echo '<span class="th-count">3</span>';
					},
					'sg-spec--wide'
				);
				$sg_spec(
					'rating',
					static function () {
						th_component(
							'rating',
							array(
								'average' => 4.9,
								'count'   => 28,
							)
						);
						th_component(
							'rating',
							array(
								'label'      => __( 'Excellent', 'torrehub' ),
								'average'    => 4.9,
								'show_count' => false,
							)
						);
					}
				);
				echo '</div>';

				echo '<h3 class="sg-sub">' . esc_html__( 'Listing cards', 'torrehub' ) . '</h3>';
				echo '<div class="th-grid" style="--th-cols-md:2;--th-cols-lg:4">';
				th_component(
					'listing-card',
					array(
						'title'        => 'Bright 3-bed apartment with sea view',
						'url'          => '#',
						'price'        => '€1,150',
						'price_suffix' => '/ month',
						'attrs'        => array( '3 bed', '2 bath', '92 m²' ),
						'location'     => 'Torrevieja',
						'age'          => '2 days ago',
						'fav'          => true,
					)
				);
				th_component(
					'listing-card',
					array(
						'variant'     => 'featured',
						'title'       => 'Seat Ibiza FR 1.0 TSI, one owner',
						'url'         => '#',
						'price'       => '€12,450',
						'badges'      => array(
							array(
								'label'   => 'Featured',
								'variant' => 'featured',
							),
						),
						'attrs'       => array( '2019', 'Petrol', '48,000 km' ),
						'location'    => 'Orihuela Costa',
						'verified'    => true,
						'fav'         => true,
						'fav_pressed' => true,
						'photo_count' => 5,
					)
				);
				th_component(
					'listing-card',
					array(
						'variant'  => 'urgent',
						'title'    => 'Moving sale — sofa and dining set',
						'url'      => '#',
						'price'    => '€320',
						'badges'   => array(
							array(
								'label'   => 'Urgent',
								'variant' => 'urgent',
							),
						),
						'attrs'    => array( 'Like new' ),
						'location' => 'Alicante',
						'meta_end' => 'Ends in 2 days',
						'fav'      => true,
					)
				);
				th_component(
					'listing-card',
					array(
						'variant'      => 'expired',
						'title'        => 'Garden services — weekly maintenance',
						'url'          => '#',
						'price'        => '€25',
						'price_suffix' => '/ hour',
						'badges'       => array(
							array(
								'label'   => 'Expired',
								'variant' => 'expired-solid',
							),
						),
						'location'     => 'Altea',
						'meta_end'     => 'Listing ended 4 days ago',
					)
				);
				echo '</div>';

				echo '<div class="th-grid sg-gap" style="--th-cols-md:2;--th-cols-lg:3">';
				th_component(
					'listing-card',
					array(
						'title'        => 'Ramón Plumbing — 24/7 emergency call-outs',
						'url'          => '#',
						'price'        => 'From €45',
						'price_suffix' => '/ hour',
						'badges'       => array(
							array(
								'label'   => 'Verified',
								'variant' => 'verified',
								'icon'    => 'check',
							),
						),
						'rating'       => 4.9,
						'rating_count' => 28,
						'attrs'        => array( 'Emergency', 'EN · ES' ),
						'location'     => 'Torrevieja · 0.8 km',
						'age'          => 'Today',
						'photo_count'  => 6,
						'fav'          => true,
						'actions'      => array(
							array(
								'label'   => 'Contact',
								'variant' => 'primary',
								'icon'    => 'phone',
							),
							array(
								'variant'    => 'white',
								'icon'       => 'chat',
								'icon_only'  => true,
								'aria_label' => 'Chat',
							),
						),
					)
				);
				th_component( 'skeleton-card' );
				th_component(
					'end-of-results',
					array(
						'total'  => 9,
						'text'   => __( 'Widen the search to see more plumbers nearby.', 'torrehub' ),
						'action' => array( 'label' => __( 'Search within 25 km', 'torrehub' ) ),
					)
				);
				echo '</div>';

				echo '<div class="th-grid sg-gap" style="--th-cols-md:1;--th-cols-lg:2">';
				th_component(
					'listing-card',
					array(
						'variant'  => 'row',
						'title'    => 'Tapas & terrace — La Zenia',
						'url'      => '#',
						'price'    => '€€',
						'attrs'    => array( 'Spanish', 'Sea view' ),
						'location' => 'La Zenia',
						'age'      => '3 days ago',
					)
				);
				th_component(
					'listing-card',
					array(
						'variant'      => 'map',
						'title'        => 'Ramón Plumbing',
						'url'          => '#',
						'price'        => 'From €45',
						'location'     => 'Torrevieja · 0.8 km',
						'rating'       => 4.9,
						'rating_count' => 28,
					)
				);
				echo '</div>';

				echo '<h3 class="sg-sub">' . esc_html__( 'Dashboard row', 'torrehub' ) . '</h3>';
				th_component(
					'dash-row',
					array(
						'title'      => 'Seat Ibiza FR 1.0 TSI',
						'url'        => '#',
						'path'       => 'Auto / Moto / Boats › Cars',
						'price'      => '€12,450',
						'status'     => array(
							'label'   => 'Active',
							'variant' => 'active',
						),
						'views'      => 412,
						'edit_url'   => '#',
						'listing_id' => 1,
					)
				);

				echo '<h3 class="sg-sub">' . esc_html__( 'Feedback', 'torrehub' ) . '</h3><div class="th-grid" style="--th-cols-md:2;--th-cols-lg:2">';
				th_component(
					'alert',
					array(
						'variant' => 'success',
						'title'   => __( 'Message sent', 'torrehub' ),
						'text'    => __( 'We usually reply within one working day.', 'torrehub' ),
					)
				);
				th_component(
					'alert',
					array(
						'variant' => 'error',
						'title'   => __( 'Email or password is incorrect', 'torrehub' ),
						'text'    => __( 'Two attempts left before a short pause.', 'torrehub' ),
						'role'    => '',
					)
				);
				th_component(
					'alert',
					array(
						'variant' => 'attention',
						'title'   => __( 'Pending approval — only you can see this', 'torrehub' ),
						'actions' => array(
							array(
								'label'   => __( 'Edit listing', 'torrehub' ),
								'variant' => 'clay',
							),
							array(
								'label'   => __( 'My listings', 'torrehub' ),
								'variant' => 'clay-outline',
							),
						),
					)
				);
				th_component(
					'alert',
					array(
						'variant' => 'neutral',
						'title'   => __( 'This listing has expired', 'torrehub' ),
						'actions' => array(
							array(
								'label'   => __( 'See 8 similar plumbers', 'torrehub' ),
								'variant' => 'primary',
							),
						),
					)
				);
				echo '</div>';
				?>
				<div class="sg-specs sg-gap">
					<?php
					$sg_spec(
						'toast (not drawn — derived)',
						static function () {
							th_component(
								'button',
								array(
									'label'   => __( 'Show success toast', 'torrehub' ),
									'variant' => 'neutral',
									'size'    => 'sm',
									'attrs'   => array(
										'data-th-toast-demo' => __( 'Saved to favourites', 'torrehub' ),
										'data-th-toast-type' => 'success',
									),
								)
							);
							th_component(
								'button',
								array(
									'label'   => __( 'Show error toast', 'torrehub' ),
									'variant' => 'neutral',
									'size'    => 'sm',
									'attrs'   => array(
										'data-th-toast-demo' => __( 'Could not save — try again', 'torrehub' ),
										'data-th-toast-type' => 'error',
									),
								)
							);
						}
					);
					$sg_spec(
						'bottom sheet / modal',
						static function () {
							th_component(
								'button',
								array(
									'label'   => __( 'Filters', 'torrehub' ),
									'variant' => 'ink',
									'icon'    => 'filter',
									'attrs'   => array(
										'data-th-dialog-open' => 'sg-sheet',
										'aria-controls' => 'sg-sheet',
										'aria-expanded' => 'false',
										'aria-haspopup' => 'dialog',
									),
								)
							);
						}
					);
					?>
				</div>
				<?php
				th_component(
					'empty-state',
					array(
						'title'   => __( 'No results here', 'torrehub' ),
						'text'    => __( 'Nothing matches all your filters in Torrevieja. Try a wider area or fewer filters.', 'torrehub' ),
						'actions' => array(
							array(
								'label'   => __( 'Search within 25 km', 'torrehub' ),
								'variant' => 'primary',
								'size'    => 'lg',
							),
							array(
								'label'   => __( 'Clear all filters', 'torrehub' ),
								'variant' => 'outline',
								'size'    => 'lg',
							),
						),
					)
				);
			}
		);

		/* ------------------------------------------------------------- Navigation */
		$sg_section(
			'nav',
			'G / P / L',
			__( 'Navigation & structure', 'torrehub' ),
			static function () use ( $sg_spec ) {
				th_component(
					'breadcrumb',
					array(
						'items' => array(
							array(
								'label' => 'Home',
								'url'   => '#',
							),
							array(
								'label' => 'Services',
								'url'   => '#',
							),
							array( 'label' => 'Plumbing in Torrevieja' ),
						),
					)
				);
				echo '<div class="sg-gap">';
				th_component(
					'section-head',
					array(
						'title' => __( 'New near you', 'torrehub' ),
						'chip'  => __( 'Within 10 km', 'torrehub' ),
						'link'  => '#',
					)
				);
				echo '</div>';
				?>
				<nav class="th-tabs" aria-label="<?php esc_attr_e( 'Listing sections', 'torrehub' ); ?>">
					<a href="#f05" aria-current="true"><?php esc_html_e( 'Details', 'torrehub' ); ?></a>
					<a href="#f05"><?php esc_html_e( 'About', 'torrehub' ); ?></a>
					<a href="#f05"><?php esc_html_e( 'Amenities', 'torrehub' ); ?></a>
					<a href="#f05"><?php esc_html_e( 'Hours', 'torrehub' ); ?></a>
					<a href="#f05"><?php esc_html_e( 'Location', 'torrehub' ); ?></a>
					<a href="#f05"><?php esc_html_e( 'Reviews', 'torrehub' ); ?></a>
				</nav>
				<div class="th-toolbar sg-gap">
					<p class="th-result-count" aria-live="polite"><?php echo wp_kses( __( '<strong>9 plumbers</strong> in Torrevieja', 'torrehub' ), array( 'strong' => array() ) ); ?></p>
					<div class="th-cluster">
						<label class="th-sort"><span class="th-sr-only"><?php esc_html_e( 'Sort by', 'torrehub' ); ?></span>
							<select><option><?php esc_html_e( 'Sort: Nearest', 'torrehub' ); ?></option><option><?php esc_html_e( 'Newest', 'torrehub' ); ?></option></select>
							<?php th_icon( 'chevron-down', array( 'size' => 16 ) ); ?>
						</label>
						<nav class="th-view-toggle" aria-label="<?php esc_attr_e( 'View', 'torrehub' ); ?>">
							<a href="#" aria-current="true"><?php th_icon( 'view-grid' ); ?><span class="th-sr-only"><?php esc_html_e( 'Grid', 'torrehub' ); ?></span></a>
							<a href="#"><?php th_icon( 'view-list' ); ?><span class="th-sr-only"><?php esc_html_e( 'List', 'torrehub' ); ?></span></a>
							<a href="#"><?php th_icon( 'map-pin' ); ?><?php esc_html_e( 'Map', 'torrehub' ); ?></a>
						</nav>
					</div>
				</div>
				<div class="th-load-more">
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Load 6 more', 'torrehub' ),
							'variant' => 'outline',
						)
					);
					?>
					<p class="th-load-more__status"><?php esc_html_e( 'Showing 6 of 9', 'torrehub' ); ?></p>
				</div>
				<div class="th-grid sg-gap" style="--th-cols-md:2;--th-cols-lg:2">
					<div class="th-accordion">
						<details open><summary><?php esc_html_e( 'Do I need a NIE to sell?', 'torrehub' ); ?><?php th_icon( 'plus' ); ?><?php th_icon( 'minus' ); ?></summary><div class="th-accordion__body"><p><?php esc_html_e( 'Private sellers need a NIE; businesses a NIF. It keeps the marketplace accountable.', 'torrehub' ); ?></p></div></details>
						<details><summary><?php esc_html_e( 'How long does approval take?', 'torrehub' ); ?><?php th_icon( 'plus' ); ?><?php th_icon( 'minus' ); ?></summary><div class="th-accordion__body"><p><?php esc_html_e( 'Usually within 48 hours.', 'torrehub' ); ?></p></div></details>
					</div>
					<table class="th-hours">
						<caption class="th-sr-only"><?php esc_html_e( 'Opening hours', 'torrehub' ); ?></caption>
						<tbody>
							<tr><th scope="row"><?php esc_html_e( 'Monday', 'torrehub' ); ?></th><td>08:00 – 20:00</td></tr>
							<tr class="is-today"><th scope="row"><?php esc_html_e( 'Tuesday (today)', 'torrehub' ); ?></th><td>08:00 – 20:00</td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Sunday', 'torrehub' ); ?></th><td><?php esc_html_e( 'Closed', 'torrehub' ); ?></td></tr>
						</tbody>
					</table>
				</div>
				<div class="sg-specs sg-gap">
					<?php
					$sg_spec(
						'progress (free listings)',
						static function () {
							?>
						<div class="th-stack" style="--th-stack-gap:6px;width:100%">
							<span class="th-meta"><?php esc_html_e( '3 of 5 free listings used', 'torrehub' ); ?></span>
							<div class="th-progress" role="progressbar" aria-valuemin="0" aria-valuemax="5" aria-valuenow="3"><span class="th-progress__fill" style="--pct:60%"></span></div>
						</div>
							<?php
						},
						'sg-spec--wide'
					);
					$sg_spec(
						'section pills (listing form)',
						static function () {
							?>
						<div class="th-stack" style="--th-stack-gap:6px;width:100%">
							<a class="th-step-pill is-done" href="#"><?php esc_html_e( 'Basics', 'torrehub' ); ?> <small>5/5</small></a>
							<a class="th-step-pill" href="#" aria-current="step"><?php esc_html_e( 'Car specifications', 'torrehub' ); ?> <small>7/13</small></a>
							<span class="th-step-pill is-hidden"><?php esc_html_e( 'Boat', 'torrehub' ); ?> <small><?php esc_html_e( 'Hidden', 'torrehub' ); ?></small></span>
						</div>
							<?php
						},
						'sg-spec--wide'
					);
				?>
				</div>
				<?php
			}
		);

		/* ------------------------------------------------------------- Modules */
		$sg_section(
			'modules',
			'P-01 / L-01 / T',
			__( 'Modules & panels', 'torrehub' ),
			static function () {
				?>
				<div class="th-grid" style="--th-cols-md:1;--th-cols-lg:2">
					<div class="th-module th-module--hero">
						<span class="th-module__eyebrow-pill"><?php esc_html_e( 'Costa Blanca · 34 towns', 'torrehub' ); ?></span>
						<p class="th-module__title"><?php esc_html_e( 'Find your thing on the Costa Blanca', 'torrehub' ); ?></p>
						<p class="th-lead"><?php esc_html_e( 'Local services, homes, cars, events and places — from people who live here.', 'torrehub' ); ?></p>
					</div>
					<div class="th-stack">
						<div class="th-module th-module--accent">
							<p class="th-module__eyebrow"><?php esc_html_e( 'This weekend', 'torrehub' ); ?></p>
							<p class="th-module__title"><?php esc_html_e( '11 events near Torrevieja', 'torrehub' ); ?></p>
							<a href="#"><?php esc_html_e( "See what's on", 'torrehub' ); ?></a>
						</div>
						<div class="th-module th-module--bordered">
							<dl class="th-stats">
								<div><dt><?php esc_html_e( 'listings', 'torrehub' ); ?></dt><dd>1,240</dd></div>
								<div><dt><?php esc_html_e( 'verified', 'torrehub' ); ?></dt><dd>318</dd></div>
								<div><dt><?php esc_html_e( 'categories', 'torrehub' ); ?></dt><dd>152</dd></div>
							</dl>
						</div>
					</div>
					<div class="th-module th-module--dark th-module--large">
						<p class="th-module__eyebrow"><?php esc_html_e( 'For sellers', 'torrehub' ); ?></p>
						<p class="th-module__title"><?php esc_html_e( 'Five free listings every 30 days', 'torrehub' ); ?></p>
						<div class="th-cluster">
							<?php
							th_component(
								'button',
								array(
									'label'   => __( 'Create a seller account', 'torrehub' ),
									'variant' => 'accent',
								)
							);
							?>
							<?php
							th_component(
								'button',
								array(
									'label'   => __( 'How it works', 'torrehub' ),
									'variant' => 'ghost',
								)
							);
							?>
						</div>
					</div>
					<div class="th-module th-module--contact">
						<p class="th-module__eyebrow"><?php esc_html_e( 'Hourly rate', 'torrehub' ); ?></p>
						<p class="th-module__title"><?php esc_html_e( 'From €45', 'torrehub' ); ?></p>
						<?php
						th_component(
							'button',
							array(
								'label'   => __( 'Show phone number', 'torrehub' ),
								'variant' => 'accent',
								'icon'    => 'phone',
								'block'   => true,
							)
						);
						?>
						<div class="th-cluster" style="--th-cluster-gap:8px">
							<?php
							th_component(
								'button',
								array(
									'label'   => 'WhatsApp',
									'variant' => 'whatsapp',
									'icon'    => 'whatsapp',
								)
							);
							?>
							<?php
							th_component(
								'button',
								array(
									'label'   => __( 'Chat', 'torrehub' ),
									'variant' => 'white',
									'icon'    => 'chat',
								)
							);
							?>
						</div>
					</div>
					<div class="th-module th-module--clay">
						<p class="th-module__title" style="font-size:1.1875rem"><?php esc_html_e( 'Staying safe', 'torrehub' ); ?></p>
						<p><?php esc_html_e( 'Meet in public, never pay in advance by transfer, and keep the chat on Torrehub.', 'torrehub' ); ?></p>
					</div>
					<div class="th-module th-module--hero" style="min-height:0">
						<p class="th-module__title" style="font-size:var(--th-fs-h2)"><?php esc_html_e( 'What happens next', 'torrehub' ); ?></p>
						<ol class="th-steps">
							<li><div><strong><?php esc_html_e( 'Confirm your email', 'torrehub' ); ?></strong><?php esc_html_e( 'We send a link straight away.', 'torrehub' ); ?></div></li>
							<li><div><strong><?php esc_html_e( 'We check your NIE', 'torrehub' ); ?></strong><?php esc_html_e( 'Usually within 48 hours.', 'torrehub' ); ?></div></li>
							<li><div><strong><?php esc_html_e( 'Start listing', 'torrehub' ); ?></strong><?php esc_html_e( 'Five free listings every 30 days.', 'torrehub' ); ?></div></li>
						</ol>
					</div>
				</div>

				<h3 class="sg-sub"><?php esc_html_e( 'Detail tiles', 'torrehub' ); ?></h3>
				<dl class="th-details">
					<div class="th-detail"><dt><?php esc_html_e( 'Item type', 'torrehub' ); ?></dt><dd><?php esc_html_e( 'Car', 'torrehub' ); ?></dd></div>
					<div class="th-detail"><dt><?php esc_html_e( 'Year', 'torrehub' ); ?></dt><dd>2019</dd></div>
					<div class="th-detail th-detail--yes"><dt><?php esc_html_e( 'With licence', 'torrehub' ); ?></dt><dd><?php esc_html_e( 'Yes', 'torrehub' ); ?></dd></div>
					<div class="th-detail th-detail--plain"><dt><?php esc_html_e( 'Fuel', 'torrehub' ); ?></dt><dd><?php esc_html_e( 'Petrol', 'torrehub' ); ?></dd></div>
				</dl>

				<h3 class="sg-sub"><?php esc_html_e( 'Town cards', 'torrehub' ); ?></h3>
				<div class="th-grid" style="--th-cols-sm:2;--th-cols-md:3;--th-cols-lg:5">
					<?php
					foreach ( array(
						'Torrevieja'     => 128,
						'Alicante'       => 96,
						'Benidorm'       => 74,
						'Orihuela Costa' => 51,
						'Altea'          => 22,
					) as $town => $n ) {
						th_component(
							'town-card',
							array(
								'name'  => $town,
								'url'   => '#',
								'count' => $n,
							)
						);
					}
					?>
				</div>

				<h3 class="sg-sub"><?php esc_html_e( 'Error panels (404 / 401 / 403)', 'torrehub' ); ?></h3>
				<div class="th-grid" style="--th-cols-md:1;--th-cols-lg:3">
					<div class="th-error-panel"><span class="th-error-panel__code">404</span><h3 class="th-error-panel__title"><?php esc_html_e( 'This page has moved on', 'torrehub' ); ?></h3>
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Back to homepage', 'torrehub' ),
							'variant' => 'ink',
						)
					);
					?>
					</div>
					<div class="th-error-panel th-error-panel--401"><span class="th-icon-circle"><?php th_icon( 'lock' ); ?></span><h3 class="th-error-panel__title"><?php esc_html_e( 'Log in to see your favourites', 'torrehub' ); ?></h3><div class="th-cluster">
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Log in', 'torrehub' ),
							'variant' => 'accent',
						)
					);
					?>
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Create an account', 'torrehub' ),
							'variant' => 'white',
						)
					);
					?>
					</div></div>
					<div class="th-error-panel th-error-panel--403"><span class="th-icon-circle"><?php th_icon( 'ban' ); ?></span><h3 class="th-error-panel__title"><?php esc_html_e( 'You need a seller account', 'torrehub' ); ?></h3><div class="th-cluster">
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Contact us to upgrade', 'torrehub' ),
							'variant' => 'clay',
						)
					);
					?>
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Back to browsing', 'torrehub' ),
							'variant' => 'white',
						)
					);
					?>
					</div></div>
				</div>
				<?php
			}
		);

		/* ------------------------------------------------------------- Icons */
		$sg_section(
			'icons',
			'Icons',
			__( 'Icon sprite', 'torrehub' ),
			static function () {
				echo '<ul class="sg-icons" role="list">';
				foreach ( th_icon_names() as $name ) {
					echo '<li>';
					th_icon( $name, array( 'size' => 24 ) );
					echo '<code>' . esc_html( $name ) . '</code></li>';
				}
				echo '</ul>';
			}
		);
		?>
	</div>

	<dialog class="th-dialog th-dialog--sheet" id="sg-sheet" data-th-dialog aria-labelledby="sg-sheet-title">
		<div class="th-dialog__head">
			<h2 class="th-dialog__title" id="sg-sheet-title"><?php esc_html_e( 'Filters', 'torrehub' ); ?></h2>
			<?php
			th_component(
				'button',
				array(
					'variant'    => 'neutral',
					'size'       => 'sm',
					'icon'       => 'close',
					'icon_only'  => true,
					'aria_label' => __( 'Close', 'torrehub' ),
					'attrs'      => array( 'data-th-dialog-close' => '' ),
				)
			);
			?>
		</div>
		<div class="th-dialog__body">
			<div class="th-sheet-section">
				<p class="th-sheet-section__title"><?php esc_html_e( 'Where', 'torrehub' ); ?></p>
				<div class="th-cluster">
					<?php
					foreach ( array(
						'5 km'  => false,
						'10 km' => true,
						'25 km' => false,
					) as $l => $c ) {
						th_component(
							'chip',
							array(
								'type'       => 'choice',
								'variant'    => 'radius',
								'input_type' => 'radio',
								'label'      => $l,
								'name'       => 'sg-sheet-radius',
								'value'      => $l,
								'checked'    => $c,
							)
						);
					}
					?>
				</div>
			</div>
			<div class="th-sheet-section">
				<label class="th-toggle"><span><?php esc_html_e( 'Verified sellers only', 'torrehub' ); ?></span><input type="checkbox" role="switch" checked></label>
			</div>
		</div>
		<div class="th-dialog__foot">
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Reset', 'torrehub' ),
					'variant' => 'outline',
					'attrs'   => array( 'data-th-dialog-close' => '' ),
				)
			);
			?>
			<?php
			th_component(
				'button',
				array(
					'label'   => __( 'Show 9 results', 'torrehub' ),
					'variant' => 'accent',
					'attrs'   => array( 'data-th-dialog-close' => '' ),
				)
			);
			?>
		</div>
	</dialog>
</main>
<script>
	// Print the computed value next to each swatch (styleguide only).
	document.querySelectorAll('[data-sg-var]').forEach((el) => {
		el.textContent = getComputedStyle(document.documentElement).getPropertyValue(el.dataset.sgVar).trim();
	});
</script>
<?php
get_footer();
