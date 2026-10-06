<?php
/**
 * Focused header for the listing form (S-04): logo, "New listing", draft status, Save & exit, Publish.
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\ListingForm\Workspace $ws }
 */

defined( 'ABSPATH' ) || exit;

$ws        = $args['ws'];
$workspace = 'workspace' === $ws->view;
$listings  = \Rtcl\Helpers\Link::get_account_endpoint_url( 'listings' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'th-auth-body th-lf-body' ); ?>>
<?php wp_body_open(); ?>
<a class="th-skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'torrehub' ); ?></a>

<header class="th-auth-header th-lf-header">
	<div class="th-container th-lf-header__bar">
		<?php get_template_part( 'template-parts/header/logo' ); ?>
		<p class="th-lf-header__title"><?php echo esc_html( $ws->title() ); ?></p>

		<div class="th-lf-header__actions">
			<?php if ( $workspace ) : ?>
				<?php if ( 'edit' !== $ws->mode ) : ?>
					<span class="<?php echo esc_attr( th_classes( 'th-lf-status', array( 'is-saved' => (bool) $ws->saved ) ) ); ?>" data-th-lf-status role="status">
						<?php th_icon( 'check', array( 'size' => 14 ) ); ?>
						<span data-th-lf-status-text>
							<?php
							if ( $ws->saved ) {
								/* translators: %s: time, e.g. 12:41 */
								echo esc_html( sprintf( __( 'Draft saved %s', 'torrehub' ), wp_date( (string) get_option( 'time_format', 'H:i' ), $ws->saved ) ) );
							}
							?>
						</span>
					</span>
					<button type="button" class="th-btn th-btn--neutral th-btn--sm th-lf-header__exit" data-th-lf-exit aria-label="<?php esc_attr_e( 'Save & exit', 'torrehub' ); ?>">
						<?php th_icon( 'save', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Save & exit', 'torrehub' ); ?></span>
					</button>
				<?php else : ?>
					<a class="th-btn th-btn--neutral th-btn--sm" href="<?php echo esc_url( $listings ); ?>"><?php esc_html_e( 'Cancel', 'torrehub' ); ?></a>
				<?php endif; ?>
				<button type="submit" form="th-lf-form" class="th-btn th-btn--accent th-btn--sm" data-th-lf-publish>
					<?php echo esc_html( 'edit' === $ws->mode ? __( 'Save changes', 'torrehub' ) : __( 'Publish listing', 'torrehub' ) ); ?>
				</button>
			<?php else : ?>
				<a class="th-lf-header__link" href="<?php echo esc_url( th_url_account() ); ?>"><?php esc_html_e( 'My account', 'torrehub' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>
