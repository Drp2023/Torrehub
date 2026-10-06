<?php
/**
 * "Save this search" (SearchAlerts): guests → login and back; members → small dialog (name + how often);
 * an already saved search shows "Search saved" linking to Account › Saved searches.
 * Without JS the link reloads the page with `th_save=1`, which renders the dialog open.
 *
 * @package Torrehub
 *
 * @var array $args {
 *     @type Torrehub\Modules\Archive\Search $search
 *     @type array                           $params   Parameters to save.
 *     @type object|null                     $existing Saved alert for these parameters.
 * }
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\SearchAlerts\Matcher;
use Torrehub\Modules\SearchAlerts\Module as Alerts;

$th_search = $args['search'];
$params    = $args['params'];
$existing  = $args['existing'];
$here      = $th_search->url( array( 'page' => $th_search->page > 1 ? $th_search->page : null ) );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display state.
$open   = ! empty( $_GET['th_save'] ) && is_user_logged_in();
$notice = isset( $_GET['th_alert'] ) ? sanitize_key( wp_unslash( $_GET['th_alert'] ) ) : '';
// phpcs:enable
?>
<div class="th-save-search">
	<?php if ( ! is_user_logged_in() ) : ?>
		<a class="th-btn th-btn--outline th-btn--sm" href="<?php echo esc_url( th_url_login( $here ) ); ?>" rel="nofollow">
			<?php th_icon( 'bell', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Save this search', 'torrehub' ); ?></span>
		</a>
	<?php elseif ( $existing && 'off' !== $existing->frequency ) : ?>
		<a class="th-btn th-btn--soft th-btn--sm" href="<?php echo esc_url( Alerts::url() ); ?>">
			<?php th_icon( 'check', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Search saved', 'torrehub' ); ?></span>
		</a>
	<?php else : ?>
		<a class="th-btn th-btn--outline th-btn--sm" href="<?php echo esc_url( add_query_arg( 'th_save', '1', $here ) ); ?>" data-th-dialog-open="th-save-search" aria-controls="th-save-search" aria-haspopup="dialog" aria-expanded="false" rel="nofollow">
			<?php th_icon( 'bell', array( 'size' => 16 ) ); ?><span><?php esc_html_e( 'Save this search', 'torrehub' ); ?></span>
		</a>
		<dialog class="th-dialog th-save-search__dialog" id="th-save-search" data-th-dialog aria-labelledby="th-save-search-title"<?php echo $open ? ' open' : ''; ?>>
			<form class="th-dialog__form" method="post" action="<?php echo esc_url( $here ); ?>">
				<input type="hidden" name="th_action" value="th_save_search">
				<input type="hidden" name="params" value="<?php echo esc_attr( (string) wp_json_encode( $params ) ); ?>">
				<input type="hidden" name="back" value="<?php echo esc_url( $here ); ?>">
				<?php wp_nonce_field( 'th_save_search' ); ?>
				<div class="th-dialog__head">
					<h2 class="th-dialog__title" id="th-save-search-title"><?php esc_html_e( 'Save this search', 'torrehub' ); ?></h2>
					<?php
					th_component(
						'button',
						array(
							'variant'    => 'neutral',
							'size'       => 'sm',
							'icon'       => 'close',
							'icon_only'  => true,
							'aria_label' => __( 'Close', 'torrehub' ),
							'href'       => $open ? $here : '',
							'attrs'      => array( 'data-th-dialog-close' => '' ),
						)
					);
					?>
				</div>
				<div class="th-dialog__body th-stack">
					<p class="th-save-search__summary"><?php echo esc_html( Matcher::summary( $params ) ); ?></p>
					<?php
					th_component(
						'field',
						array(
							'id'        => 'th-save-search-label',
							'name'      => 'label',
							'label'     => __( 'Name', 'torrehub' ),
							'value'     => $th_search->heading(),
							'maxlength' => 0,
						)
					);
					?>
					<fieldset class="th-stack" style="--th-stack-gap:8px">
						<legend class="th-label"><?php esc_html_e( 'E-mail me new listings', 'torrehub' ); ?></legend>
						<div class="th-segmented th-segmented--block">
							<label><input type="radio" name="frequency" value="instant"><?php esc_html_e( 'Instantly', 'torrehub' ); ?></label>
							<label><input type="radio" name="frequency" value="daily" checked><?php esc_html_e( 'Daily', 'torrehub' ); ?></label>
							<label><input type="radio" name="frequency" value="weekly"><?php esc_html_e( 'Weekly', 'torrehub' ); ?></label>
						</div>
					</fieldset>
					<p class="th-help">
						<?php
						/* translators: %s: e-mail address */
						echo esc_html( sprintf( __( 'We’ll write to %s. Every e-mail has a link to stop them.', 'torrehub' ), wp_get_current_user()->user_email ) );
						?>
					</p>
				</div>
				<div class="th-dialog__foot">
					<?php
					th_component(
						'button',
						array(
							'label'   => __( 'Save search', 'torrehub' ),
							'variant' => 'accent',
							'type'    => 'submit',
							'block'   => true,
						)
					);
					?>
				</div>
			</form>
		</dialog>
	<?php endif; ?>
	<?php if ( 'saved' === $notice || 'limit' === $notice ) : ?>
		<p class="<?php echo esc_attr( 'th-save-search__notice' . ( 'limit' === $notice ? ' is-error' : '' ) ); ?>" role="status">
			<?php
			echo esc_html(
				'saved' === $notice
					? __( 'Saved. We’ll e-mail you new matches.', 'torrehub' )
					/* translators: %d: maximum number of saved searches */
					: sprintf( __( 'You can save up to %d searches. Delete one in your account first.', 'torrehub' ), \Torrehub\Modules\SearchAlerts\Store::MAX_PER_USER )
			);
			?>
		</p>
	<?php endif; ?>
</div>
