<?php
/**
 * Dashboard listing row (My listings).
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type string     $title
 *     @type string     $url
 *     @type string     $path     Category path "Services › Plumbing".
 *     @type int|string $image    Attachment ID or URL.
 *     @type string     $price
 *     @type array      $status   Badge component args.
 *     @type int        $views
 *     @type string     $edit_url
 *     @type string     $delete_url  Rendered as a form-less button with data attribute (JS confirms + AJAX).
 *     @type int        $listing_id
 * }
 */

defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'title'      => '',
		'url'        => '',
		'path'       => '',
		'image'      => '',
		'price'      => '',
		'status'     => array(),
		'views'      => null,
		'edit_url'   => '',
		'delete_url' => '',
		'listing_id' => 0,
	)
);
?>
<article class="th-dash-row">
	<div class="th-dash-row__thumb">
		<?php
		if ( is_numeric( $a['image'] ) && (int) $a['image'] > 0 ) {
			echo wp_get_attachment_image(
				(int) $a['image'],
				'th-thumb',
				false,
				array(
					'alt'     => '',
					'loading' => 'lazy',
				)
			);
		} elseif ( $a['image'] ) {
			printf( '<img src="%s" alt="" loading="lazy" width="192" height="144">', esc_url( (string) $a['image'] ) );
		}
		?>
	</div>
	<div class="th-stack" style="--th-stack-gap:4px">
		<h3 class="th-dash-row__title"><a href="<?php echo esc_url( $a['url'] ); ?>"><?php echo esc_html( $a['title'] ); ?></a></h3>
		<?php if ( $a['path'] ) : ?>
			<p class="th-dash-row__path"><?php echo esc_html( $a['path'] ); ?></p>
		<?php endif; ?>
		<div class="th-dash-row__stats">
			<?php if ( $a['price'] ) : ?>
				<span class="th-dash-row__price"><?php echo esc_html( $a['price'] ); ?></span>
			<?php endif; ?>
			<?php if ( $a['status'] ) : ?>
				<?php th_component( 'badge', (array) $a['status'] ); ?>
			<?php endif; ?>
			<?php if ( null !== $a['views'] ) : ?>
				<span>
					<?php
					/* translators: %s: number of views */
					echo esc_html( sprintf( _n( '%s view', '%s views', (int) $a['views'], 'torrehub' ), number_format_i18n( (int) $a['views'] ) ) );
					?>
				</span>
			<?php endif; ?>
		</div>
	</div>
	<div class="th-dash-row__actions">
		<?php if ( $a['edit_url'] ) : ?>
			<?php
			th_component(
				'button',
				array(
					'variant'    => 'neutral',
					'size'       => 'sm',
					'icon'       => 'edit',
					'icon_only'  => true,
					'href'       => $a['edit_url'],
					/* translators: %s: listing title */
					'aria_label' => sprintf( __( 'Edit %s', 'torrehub' ), $a['title'] ),
				)
			);
			?>
		<?php endif; ?>
		<?php if ( $a['delete_url'] || $a['listing_id'] ) : ?>
			<?php
			th_component(
				'button',
				array(
					'variant'    => 'destructive',
					'size'       => 'sm',
					'icon'       => 'trash',
					'icon_only'  => true,
					/* translators: %s: listing title */
					'aria_label' => sprintf( __( 'Delete %s', 'torrehub' ), $a['title'] ),
					'attrs'      => array( 'data-th-delete-listing' => (string) (int) $a['listing_id'] ),
				)
			);
			?>
		<?php endif; ?>
	</div>
</article>
