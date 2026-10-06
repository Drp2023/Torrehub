<?php
/**
 * Auth page router: /login/, /register/, /lost-password/.
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\Auth\Pages;

$auth_page = Pages::current();
get_header( 'auth', array( 'page' => $auth_page ) );
?>
<main id="main" class="th-main th-auth th-auth--<?php echo esc_attr( $auth_page ); ?>" tabindex="-1">
	<div class="th-container">
		<?php get_template_part( 'template-parts/auth/' . ( 'lost' === $auth_page ? 'lost' : $auth_page ) ); ?>
	</div>
</main>
<?php
get_footer( 'auth' );
