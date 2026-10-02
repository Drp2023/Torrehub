<?php
/**
 * Site footer. Phase 1: minimal shell (full footer arrives in phase 2).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="th-site-footer" role="contentinfo">
	<div class="th-container">
		<p class="th-meta">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
