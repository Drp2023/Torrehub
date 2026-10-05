<?php
/**
 * Language switcher (G-06). Languages come from th_get_languages() (GTranslate settings) — nothing hard-coded.
 * Links carry `data-gt-lang` and `notranslate`; GTranslate's base.js performs the switch and marks the active one.
 *
 * @package Torrehub
 *
 * @var array $args { @type string $variant dropdown|chips|row. Default 'dropdown'. }
 */

defined( 'ABSPATH' ) || exit;

$languages = th_get_languages();
if ( count( $languages ) < 2 ) {
	return;
}
$variant = $args['variant'] ?? 'dropdown';
$current = $languages[0];
$menu_id = 'th-lang-' . wp_unique_id();

$item = static function ( array $lang, string $css_class ) {
	printf(
		'<a href="#" class="%1$s" data-gt-lang="%2$s" lang="%2$s" hreflang="%2$s">%3$s</a>',
		esc_attr( th_classes( $css_class, 'notranslate', array( 'gt-current-lang' => $lang['default'] ) ) ),
		esc_attr( $lang['code'] ),
		esc_html( $lang['name'] )
	);
};

if ( 'dropdown' === $variant ) : ?>
	<div class="th-lang notranslate" data-th-lang>
		<button type="button" class="th-lang__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $menu_id ); ?>">
			<span class="th-sr-only"><?php esc_html_e( 'Language:', 'torrehub' ); ?></span>
			<span data-th-lang-current><?php echo esc_html( $current['short'] ); ?></span>
			<?php th_icon( 'chevron-down', array( 'size' => 14 ) ); ?>
		</button>
		<ul class="th-lang__menu" id="<?php echo esc_attr( $menu_id ); ?>" role="list" hidden>
			<?php foreach ( $languages as $lang ) : ?>
				<li><?php $item( $lang, 'th-lang__item' ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php else : ?>
	<ul class="<?php echo esc_attr( th_classes( 'th-lang-list', 'th-lang-list--' . $variant, 'notranslate' ) ); ?>" role="list" aria-label="<?php esc_attr_e( 'Language', 'torrehub' ); ?>">
		<?php foreach ( $languages as $lang ) : ?>
			<li><?php $item( $lang, 'th-lang-list__item' ); ?></li>
		<?php endforeach; ?>
	</ul>
	<?php
endif;
