<?php
/**
 * Focused footer for the auth pages: legal links only.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

$rtcl    = (array) get_option( 'rtcl_account_settings', array() );
$terms   = (int) ( $rtcl['page_for_terms_and_conditions'] ?? 0 );
$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
$privacy = $privacy ? $privacy : (int) ( $rtcl['page_for_privacy_policy'] ?? 0 );
?>
<footer class="th-auth-footer">
	<div class="th-container th-cluster">
		<span>© <?php echo esc_html( wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?></span>
		<?php if ( $terms ) : ?>
			<a href="<?php echo esc_url( (string) get_permalink( $terms ) ); ?>"><?php esc_html_e( 'Terms & Conditions', 'torrehub' ); ?></a>
		<?php endif; ?>
		<?php if ( $privacy ) : ?>
			<a href="<?php echo esc_url( (string) get_permalink( $privacy ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'torrehub' ); ?></a>
		<?php endif; ?>
		<?php
		/** This action is documented in template-parts/footer/footer.php */
		do_action( 'th_footer_legal_links' );
		?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
