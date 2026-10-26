<?php
/**
 * Sticky desktop table of contents (≥ 1200 px), rendered from mrtu_toc_items() — the same data
 * as the plugin's inline `.mrtu-toc`. Shown only with three or more headings.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_items = mr_tu_toc_items( get_queried_object_id() );
if ( count( $mr_tu_items ) < 3 ) {
	return;
}
?>
<aside class="toc-aside" aria-labelledby="mrtu-toc-aside-title">
	<div class="toc-aside__card">
		<p class="toc-aside__title" id="mrtu-toc-aside-title"><?php esc_html_e( 'On this page', 'mr-tu' ); ?></p>
		<nav aria-labelledby="mrtu-toc-aside-title">
			<ol class="toc-aside__list" role="list" data-mrtu-toc-aside>
				<?php foreach ( $mr_tu_items as $mr_tu_item ) : ?>
					<?php
					if ( empty( $mr_tu_item['id'] ) || empty( $mr_tu_item['text'] ) ) {
						continue;
					}
					$mr_tu_level = isset( $mr_tu_item['level'] ) && 3 === (int) $mr_tu_item['level'] ? 3 : 2;
					?>
					<li class="toc-aside__item toc-aside__item--h<?php echo esc_attr( (string) $mr_tu_level ); ?>">
						<a href="#<?php echo esc_attr( $mr_tu_item['id'] ); ?>"><?php echo esc_html( wp_strip_all_tags( (string) $mr_tu_item['text'] ) ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<div class="toc-aside__progress" aria-hidden="true"><span></span></div>
	</div>
</aside>
