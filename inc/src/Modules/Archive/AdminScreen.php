<?php
/**
 * Appearance → Archive filters: which Form Builder fields each category offers as filters.
 *
 * Deliberately plain: one table per configured category (fields on/off, label, order) and a selector to add a
 * category. Sub-categories inherit from the nearest configured ancestor.
 *
 * @package Torrehub
 */

namespace Torrehub\Modules\Archive;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen for FilterConfig.
 */
final class AdminScreen {

	private const PAGE = 'torrehub-filters';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_th_archive_filters', array( $this, 'save' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu(): void {
		add_theme_page(
			__( 'Archive filters', 'torrehub' ),
			__( 'Archive filters', 'torrehub' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Handle the form post.
	 */
	public function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'torrehub' ), 403 );
		}
		check_admin_referer( 'th_archive_filters' );

		$redirect = admin_url( 'themes.php?page=' . self::PAGE );

		if ( isset( $_POST['th_reimport'] ) ) {
			delete_option( FilterConfig::OPTION );
			FilterConfig::maybe_import();
			wp_safe_redirect( add_query_arg( 'th_msg', 'imported', $redirect ) );
			exit;
		}

		$groups = FilterConfig::groups();
		$posted = isset( $_POST['th_groups'] ) && is_array( $_POST['th_groups'] ) ? wp_unslash( $_POST['th_groups'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
		$clean  = array();
		foreach ( $posted as $slug => $rows ) {
			$slug = sanitize_title( (string) $slug );
			$term = get_term_by( 'slug', $slug, 'rtcl_category' );
			if ( ! $term instanceof \WP_Term || ! is_array( $rows ) ) {
				continue;
			}
			$fields = FilterConfig::form_fields_for_term( $term );
			$items  = array();
			foreach ( $rows as $name => $row ) {
				$name = sanitize_key( (string) $name );
				if ( empty( $row['on'] ) || ! isset( $fields[ $name ] ) ) {
					continue;
				}
				$items[] = array(
					'field' => $name,
					'label' => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
					'order' => (int) ( $row['order'] ?? 0 ),
				);
			}
			usort( $items, static fn( $a, $b ) => $a['order'] <=> $b['order'] );
			$clean[ $slug ] = array_map(
				static fn( $i ) => array(
					'field' => $i['field'],
					'label' => $i['label'],
				),
				$items
			);
		}
		// Removing a category = deleting its group (inherits from its parent again).
		$remove = isset( $_POST['th_remove'] ) ? sanitize_title( wp_unslash( (string) $_POST['th_remove'] ) ) : '';
		if ( $remove ) {
			unset( $clean[ $remove ] );
		}
		$add = isset( $_POST['th_add'] ) ? sanitize_title( wp_unslash( (string) $_POST['th_add'] ) ) : '';
		if ( $add && ! isset( $clean[ $add ] ) && get_term_by( 'slug', $add, 'rtcl_category' ) ) {
			$clean[ $add ] = array();
		}
		FilterConfig::save( $clean + array_diff_key( $groups, $posted, array( $remove => true ) ) );
		wp_safe_redirect( add_query_arg( 'th_msg', 'saved', $redirect ) );
		exit;
	}

	/**
	 * Render the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$groups  = FilterConfig::groups();
		$summary = FilterConfig::import_summary();
		$msg     = isset( $_GET['th_msg'] ) ? sanitize_key( wp_unslash( $_GET['th_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		ksort( $groups );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Archive filters', 'torrehub' ); ?></h1>
			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Filters saved.', 'torrehub' ); ?></p></div>
			<?php elseif ( 'imported' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Filters imported again from the Filter Builder settings.', 'torrehub' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Choose which listing-form fields appear as filters on a category’s archive. Sub-categories use the nearest configured parent. Price, town + radius, keyword and “Verified sellers only” are always available.', 'torrehub' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="th_archive_filters">
				<?php wp_nonce_field( 'th_archive_filters' ); ?>

				<?php
				foreach ( $groups as $slug => $items ) :
					$term = get_term_by( 'slug', $slug, 'rtcl_category' );
					if ( ! $term instanceof \WP_Term ) {
						continue;
					}
					$fields  = FilterConfig::form_fields_for_term( $term );
					$enabled = array();
					foreach ( $items as $i => $item ) {
						$enabled[ $item['field'] ] = array(
							'label' => $item['label'],
							'order' => ( $i + 1 ) * 10,
						);
					}
					?>
					<h2><?php echo esc_html( html_entity_decode( $term->name, ENT_QUOTES ) ); ?> <code><?php echo esc_html( $slug ); ?></code></h2>
					<?php if ( ! $fields ) : ?>
						<p><?php esc_html_e( 'This category has no listing form with filterable fields.', 'torrehub' ); ?></p>
					<?php else : ?>
						<table class="widefat striped" style="max-width:900px">
							<thead><tr>
								<th style="width:60px"><?php esc_html_e( 'Show', 'torrehub' ); ?></th>
								<th><?php esc_html_e( 'Form field', 'torrehub' ); ?></th>
								<th><?php esc_html_e( 'Filter label', 'torrehub' ); ?></th>
								<th style="width:90px"><?php esc_html_e( 'Order', 'torrehub' ); ?></th>
							</tr></thead>
							<tbody>
							<?php
							$n = 0;
							foreach ( $fields as $name => $raw ) :
								++$n;
								$base = 'th_groups[' . $slug . '][' . $name . ']';
								?>
								<tr>
									<td><input type="checkbox" name="<?php echo esc_attr( $base . '[on]' ); ?>" value="1" <?php checked( isset( $enabled[ $name ] ) ); ?> aria-label="<?php echo esc_attr( (string) ( $raw['label'] ?? $name ) ); ?>"></td>
									<td><?php echo esc_html( (string) ( $raw['label'] ?? $name ) ); ?> <code><?php echo esc_html( (string) ( $raw['element'] ?? '' ) ); ?></code></td>
									<td><input type="text" class="regular-text" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $enabled[ $name ]['label'] ?? '' ); ?>" placeholder="<?php echo esc_attr( (string) ( $raw['label'] ?? '' ) ); ?>"></td>
									<td><input type="number" class="small-text" name="<?php echo esc_attr( $base . '[order]' ); ?>" value="<?php echo esc_attr( (string) ( $enabled[ $name ]['order'] ?? ( 1000 + $n ) ) ); ?>"></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
					<p><button type="submit" class="button-link button-link-delete" name="th_remove" value="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Remove this category’s filters (use the parent’s)', 'torrehub' ); ?></button></p>
				<?php endforeach; ?>

				<h2><?php esc_html_e( 'Add a category', 'torrehub' ); ?></h2>
				<p>
					<?php
					wp_dropdown_categories(
						array(
							'taxonomy'          => 'rtcl_category',
							'name'              => 'th_add',
							'value_field'       => 'slug',
							'hide_empty'        => false,
							'hierarchical'      => true,
							'show_option_none'  => __( '— Select —', 'torrehub' ),
							'option_none_value' => '',
						)
					);
					?>
				</p>
				<?php submit_button( __( 'Save filters', 'torrehub' ) ); ?>

				<?php if ( $summary ) : ?>
					<h2><?php esc_html_e( 'Import from Filter Builder', 'torrehub' ); ?></h2>
					<p>
						<?php
						/* translators: 1: source option, 2: date */
						echo esc_html( sprintf( __( 'Imported from %1$s on %2$s.', 'torrehub' ), (string) ( $summary['source'] ? $summary['source'] : '—' ), (string) ( $summary['at'] ?? '' ) ) );
						?>
					</p>
					<?php if ( ! empty( $summary['skipped'] ) ) : ?>
						<details><summary><?php esc_html_e( 'Skipped entries', 'torrehub' ); ?></summary><ul style="list-style:disc;padding-left:20px">
							<?php foreach ( (array) $summary['skipped'] as $line ) : ?>
								<li><?php echo esc_html( (string) $line ); ?></li>
							<?php endforeach; ?>
						</ul></details>
					<?php endif; ?>
					<p><button type="submit" class="button" name="th_reimport" value="1" onclick="return confirm(this.dataset.confirm)" data-confirm="<?php esc_attr_e( 'Replace the current filter settings with a fresh import?', 'torrehub' ); ?>"><?php esc_html_e( 'Import again', 'torrehub' ); ?></button></p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}
