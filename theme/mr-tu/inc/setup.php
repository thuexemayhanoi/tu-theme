<?php
/**
 * Theme supports, menus, text domain, image sizes and editor styles.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers theme features.
 */
function mr_tu_setup() {
	load_theme_textdomain( 'mr-tu', MR_TU_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'               => 96,
			'width'                => 320,
			'flex-height'          => true,
			'flex-width'           => true,
			'unlink-homepage-logo' => false,
		)
	);
	add_post_type_support( 'page', 'excerpt' );

	// Patterns ship with the theme; remote/core patterns would not match the design system.
	remove_theme_support( 'core-block-patterns' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'mr-tu' ),
			'footer'  => __( 'Footer navigation', 'mr-tu' ),
		)
	);

	add_image_size( 'mr-tu-card', 720, 480, true );
	add_image_size( 'mr-tu-wide', 1600, 900, true );

	add_editor_style( 'assets/css/editor.css' );
}
add_action( 'after_setup_theme', 'mr_tu_setup' );

/**
 * Content width for embeds and full-size images (the prose column).
 */
function mr_tu_content_width() {
	$GLOBALS['content_width'] = (int) apply_filters( 'mr_tu_content_width', 720 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core contract for themes.
}
add_action( 'after_setup_theme', 'mr_tu_content_width', 0 );

/**
 * Image size names offered in the editor.
 *
 * @param array $sizes Size names.
 * @return array
 */
function mr_tu_image_size_names( $sizes ) {
	$sizes['mr-tu-card'] = __( 'Card (3:2)', 'mr-tu' );
	$sizes['mr-tu-wide'] = __( 'Wide (16:9)', 'mr-tu' );
	return $sizes;
}
add_filter( 'image_size_names_choose', 'mr_tu_image_size_names' );

/**
 * Body classes for layout and motion hooks.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function mr_tu_body_class( $classes ) {
	if ( mr_tu_setting( 'reveal' ) ) {
		$classes[] = 'mrtu-reveal';
	}
	if ( is_singular() && ! is_front_page() && mr_tu_has_sticky_toc() ) {
		$classes[] = 'has-sticky-toc';
	}
	$classes[] = mr_tu_has_plugin() ? 'mrtu-plugin' : 'mrtu-no-plugin';
	return $classes;
}
add_filter( 'body_class', 'mr_tu_body_class' );

/**
 * Shorter, cleaner excerpts for cards.
 *
 * @return int
 */
function mr_tu_excerpt_length() {
	return 26;
}
add_filter( 'excerpt_length', 'mr_tu_excerpt_length' );

/**
 * Plain ellipsis instead of "[…]".
 *
 * @return string
 */
function mr_tu_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'mr_tu_excerpt_more' );

/**
 * Archive titles without the "Category:" prefix (the hero shows a label instead).
 *
 * @param string $title Title.
 * @return string
 */
function mr_tu_archive_title( $title ) {
	if ( is_category() || is_tag() || is_tax() ) {
		return single_term_title( '', false );
	}
	return $title;
}
add_filter( 'get_the_archive_title', 'mr_tu_archive_title' );

/**
 * Story section art: injects the Old Quarter street-grid graphic (or the owner's story photo)
 * into `.mrtu-section--story` groups so content editors only write text.
 *
 * @param string $block_content Rendered block.
 * @param array  $block         Parsed block.
 * @return string
 */
function mr_tu_render_story_section( $block_content, $block ) {
	$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	if ( false === strpos( $class, 'mrtu-section--story' ) || false !== strpos( $block_content, 'mrtu-story-art' ) ) {
		return $block_content;
	}
	$art = mr_tu_get_template_part_html( 'parts/components/story-art' );
	if ( '' === $art ) {
		return $block_content;
	}
	return (string) preg_replace_callback(
		'/^(\s*<(?:section|div)\b[^>]*>)/',
		static function ( $matches ) use ( $art ) {
			return $matches[1] . $art;
		},
		$block_content,
		1
	);
}
add_filter( 'render_block_core/group', 'mr_tu_render_story_section', 10, 2 );

/**
 * Captures a template part's output.
 *
 * @param string $slug Template slug.
 * @param array  $args Arguments.
 * @return string
 */
function mr_tu_get_template_part_html( $slug, $args = array() ) {
	ob_start();
	get_template_part( $slug, null, $args );
	return (string) ob_get_clean();
}
