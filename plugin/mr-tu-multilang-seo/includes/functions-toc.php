<?php
/**
 * Public TOC API (contract §2.4 / §2.9). Themes call these behind function_exists() checks.
 *
 * @package MrTu\Multilang
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mrtu_toc_items' ) ) {
	/**
	 * Table-of-contents items of a post: exactly the items of the inline TOC, or an empty array when the post
	 * shows no TOC (disabled, `_mrtu_toc` off, below the heading/word thresholds, password protected, front page).
	 *
	 * Theme usage (sticky desktop aside — scroll-spy marks the active link with aria-current="true"):
	 *
	 *     $items = function_exists( 'mrtu_toc_items' ) ? mrtu_toc_items() : array();
	 *     // <nav data-mrtu-toc-aside aria-label="…"> … <a href="#<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a>
	 *
	 * @param int|WP_Post|null $post Post (default: current post).
	 * @return array[] [ [ 'id' => string, 'text' => string (plain text, escape on output), 'level' => 2|3 ], … ]
	 */
	function mrtu_toc_items( $post = null ) {
		return \MrTu\Multilang\Toc::items( $post );
	}
}

if ( ! function_exists( 'mrtu_toc_enabled_for' ) ) {
	/**
	 * Whether a post may show a TOC (settings, post type, `_mrtu_toc`, password, front page and the
	 * `mrtu_toc_enabled` filter) — before the heading/word thresholds, which need the rendered content.
	 *
	 * @param int|WP_Post|null $post Post (default: current post).
	 * @return bool
	 */
	function mrtu_toc_enabled_for( $post = null ) {
		return \MrTu\Multilang\Toc::enabled_for( $post );
	}
}
