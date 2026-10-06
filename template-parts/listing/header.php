<?php
/**
 * Listing header card: badges, logo, H1, price (mobile), rating, address, age, views, actions (save, share, print, report).
 *
 * @package Torrehub
 *
 * @var array $args { @type Torrehub\Modules\Listing\View $view }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Listing\Report;

$view     = $args['view'];
$badges   = $view->badges();
$town     = $view->town ? html_entity_decode( $view->town->name, ENT_QUOTES ) : '';
$address  = $view->fields['address'];
$place    = trim( implode( ', ', array_filter( array( $address, $address && $town && false !== stripos( $address, $town ) ? '' : $town ) ) ) );
$reported = Report::reported_by_current_user( $view->id );
?>
<header class="th-listing-card th-listing-head">
	<?php if ( $badges ) : ?>
		<div class="th-cluster th-listing-head__badges">
			<?php
			foreach ( $badges as $badge ) {
				th_component( 'badge', $badge );
			}
			?>
		</div>
	<?php endif; ?>

	<div class="th-listing-head__title-row">
		<?php if ( $view->fields['logo'] ) : ?>
			<span class="th-listing-head__logo"><?php echo wp_get_attachment_image( $view->fields['logo'], 'th-thumb', false, array( 'alt' => '' ) ); ?></span>
		<?php endif; ?>
		<div class="th-stack" style="--th-stack-gap:8px">
			<?php if ( '' !== $view->price['amount'] ) : ?>
				<p class="th-listing-head__price">
					<?php echo esc_html( $view->price['amount'] ); ?>
					<?php if ( $view->price['suffix'] ) : ?>
						<small><?php echo esc_html( $view->price['suffix'] ); ?></small>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<h1 class="th-listing-head__title"><?php echo esc_html( $view->title ); ?></h1>
			<ul class="th-listing-head__meta" role="list">
				<?php if ( $view->reviews['count'] ) : ?>
					<li>
						<?php
						th_component(
							'rating',
							array(
								'average'    => $view->reviews['average'],
								'count'      => $view->reviews['count'],
								'show_count' => false,
							)
						);
						?>
						<a href="#reviews">
							<?php
							/* translators: %s: number of reviews */
							echo esc_html( sprintf( _n( '%s review', '%s reviews', $view->reviews['count'], 'torrehub' ), number_format_i18n( $view->reviews['count'] ) ) );
							?>
						</a>
					</li>
				<?php endif; ?>
				<?php if ( $place ) : ?>
					<li><?php th_icon( 'map-pin', array( 'size' => 14 ) ); ?> <?php echo esc_html( $place ); ?></li>
				<?php endif; ?>
				<li><?php echo esc_html( $view->age ); ?></li>
				<?php if ( $view->views ) : ?>
					<li>
						<?php
						/* translators: %s: number of views */
						echo esc_html( sprintf( _n( '%s view', '%s views', $view->views, 'torrehub' ), number_format_i18n( $view->views ) ) );
						?>
					</li>
				<?php endif; ?>
			</ul>
		</div>
	</div>

	<div class="th-listing-head__actions">
		<div class="th-cluster">
			<?php if ( th_favourites_enabled() ) : ?>
				<button type="button" class="th-chip th-listing-head__action" aria-pressed="<?php echo th_is_favourite( $view->id ) ? 'true' : 'false'; ?>" data-th-fav="<?php echo esc_attr( (string) $view->id ); ?>">
					<?php th_icon( 'heart', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Save', 'torrehub' ); ?></span>
				</button>
			<?php endif; ?>
			<button type="button" class="th-chip th-listing-head__action" data-th-share data-title="<?php echo esc_attr( $view->title ); ?>" data-url="<?php echo esc_url( (string) get_permalink( $view->id ) ); ?>" hidden>
				<?php th_icon( 'share', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Share', 'torrehub' ); ?></span>
			</button>
			<button type="button" class="th-chip th-listing-head__action th-listing-head__print" data-th-print hidden>
				<?php th_icon( 'print', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Print', 'torrehub' ); ?></span>
			</button>
		</div>
		<?php if ( $view->is_public() && ! $view->is_owner ) : ?>
			<?php if ( $reported ) : ?>
				<span class="th-listing-head__report is-done"><?php th_icon( 'check', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Reported', 'torrehub' ); ?></span>
			<?php elseif ( is_user_logged_in() ) : ?>
				<a class="th-listing-head__report" href="<?php echo esc_url( add_query_arg( 'th_report_form', '1' ) . '#th-report' ); ?>" data-th-dialog-open="th-report" aria-controls="th-report" aria-haspopup="dialog"><?php th_icon( 'warning', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Report', 'torrehub' ); ?></a>
			<?php else : ?>
				<a class="th-listing-head__report" href="<?php echo esc_url( th_url_login( (string) get_permalink( $view->id ) ) ); ?>"><?php th_icon( 'warning', array( 'size' => 14 ) ); ?> <?php esc_html_e( 'Report', 'torrehub' ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</header>
