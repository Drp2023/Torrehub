<?php
/**
 * Listing form page router (see Torrehub\Modules\ListingForm\Workspace for the screens).
 *
 * @package Torrehub
 */

defined( 'ABSPATH' ) || exit;

use Torrehub\Modules\ListingForm\Workspace;

$ws = Workspace::current();
get_header( 'submit', array( 'ws' => $ws ) );
?>
<main id="main" class="<?php echo esc_attr( 'th-main th-lf th-lf--' . $ws->view ); ?>" tabindex="-1">
	<?php get_template_part( 'template-parts/listing-form/' . $ws->view, null, array( 'ws' => $ws ) ); ?>
</main>
<?php
get_footer( 'auth' );
