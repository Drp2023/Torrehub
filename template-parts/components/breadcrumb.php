<?php
/**
 * Breadcrumb. The last item is the current page. (Schema is emitted separately by the Seo module.)
 *
 * @package Torrehub
 *
 * @var array $args { @type array $items List of [ 'label' => string, 'url' => string ]; last item's url is ignored. }
 */

defined( 'ABSPATH' ) || exit;

$items = (array) ( $args['items'] ?? array() );
if ( ! $items ) {
	return;
}
$last = count( $items ) - 1;
?>
<nav class="th-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'torrehub' ); ?>">
	<ol>
		<?php foreach ( array_values( $items ) as $i => $item ) : ?>
			<li>
				<?php if ( $i === $last || empty( $item['url'] ) ) : ?>
					<span<?php echo $i === $last ? ' aria-current="page"' : ''; ?>><?php echo esc_html( (string) $item['label'] ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( (string) $item['url'] ); ?>"><?php echo esc_html( (string) $item['label'] ); ?></a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
