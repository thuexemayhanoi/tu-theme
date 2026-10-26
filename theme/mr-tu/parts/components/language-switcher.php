<?php
/**
 * Language switcher rendered from mrtu_switcher_links().
 *
 * Variants: `popover` (header button + list; a <details> element so it works without JS) and
 * `list` (inline chips for the menu sheet and footer). Native language names carry their own
 * `lang`; languages without a translation of this page link to that language's home and say so.
 * Renders nothing without the plugin or with a single language.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_links = mr_tu_switcher_links();
if ( ! $mr_tu_links ) {
	return;
}
$mr_tu_variant = isset( $args['variant'] ) && 'list' === $args['variant'] ? 'list' : 'popover';
$mr_tu_current = null;
foreach ( $mr_tu_links as $mr_tu_link ) {
	if ( ! empty( $mr_tu_link['current'] ) ) {
		$mr_tu_current = $mr_tu_link;
		break;
	}
}

$mr_tu_render_items = static function ( $links, $item_class ) {
	foreach ( $links as $code => $link ) {
		$code       = isset( $link['code'] ) ? (string) $link['code'] : (string) $code;
		$is_current = ! empty( $link['current'] );
		$is_home    = empty( $link['available'] ) || ! empty( $link['fallback'] );
		$hreflang   = isset( $link['hreflang'] ) ? (string) $link['hreflang'] : $code;
		$name       = isset( $link['name'] ) ? (string) $link['name'] : strtoupper( $code );
		$url        = isset( $link['url'] ) ? (string) $link['url'] : '';
		if ( '' === $url ) {
			continue;
		}
		echo '<li>';
		printf(
			'<a class="%1$s%2$s" href="%3$s" hreflang="%4$s"%5$s%6$s>',
			esc_attr( $item_class ),
			$is_current ? ' is-current' : '',
			esc_url( $url ),
			esc_attr( $hreflang ),
			$is_current ? ' aria-current="true"' : '',
			$is_home && ! $is_current ? ' data-fallback' : ''
		);
		echo '<span class="mrtu-lang__code" aria-hidden="true">' . esc_html( strtoupper( $code ) ) . '</span>';
		echo '<span class="mrtu-lang__name" lang="' . esc_attr( $hreflang ) . '">' . esc_html( $name ) . '</span>';
		if ( $is_home && ! $is_current ) {
			echo '<span class="mrtu-lang__note">' . esc_html__( 'Home page', 'mr-tu' ) . '<span class="screen-reader-text"> ' . esc_html__( '(this page is not translated yet)', 'mr-tu' ) . '</span></span>';
		}
		if ( $is_current ) {
			echo mr_tu_icon( 'check', 'mrtu-lang__check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
		}
		echo '</a></li>';
	}
};
?>
<?php if ( 'list' === $mr_tu_variant ) : ?>
	<ul class="mrtu-lang mrtu-lang--list" role="list">
		<?php $mr_tu_render_items( $mr_tu_links, 'mrtu-lang__link' ); ?>
	</ul>
<?php else : ?>
	<details class="mrtu-lang mrtu-lang--popover" data-mrtu-popover>
		<summary class="mrtu-lang__toggle">
			<?php mr_tu_the_icon( 'globe' ); ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Language:', 'mr-tu' ); ?></span>
			<span class="mrtu-lang__current"><?php echo esc_html( strtoupper( $mr_tu_current && isset( $mr_tu_current['code'] ) ? $mr_tu_current['code'] : mr_tu_current_language() ) ); ?></span>
			<?php if ( $mr_tu_current && isset( $mr_tu_current['name'] ) ) : ?>
				<span class="screen-reader-text" lang="<?php echo esc_attr( isset( $mr_tu_current['hreflang'] ) ? $mr_tu_current['hreflang'] : '' ); ?>"><?php echo esc_html( $mr_tu_current['name'] ); ?></span>
			<?php endif; ?>
			<?php mr_tu_the_icon( 'chevron' ); ?>
		</summary>
		<div class="mrtu-lang__panel">
			<ul class="mrtu-lang__list" role="list">
				<?php $mr_tu_render_items( $mr_tu_links, 'mrtu-lang__link' ); ?>
			</ul>
		</div>
	</details>
<?php endif; ?>
