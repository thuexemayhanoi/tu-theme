<?php
/**
 * Front-end assets: one stylesheet, one deferred script.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Version string for an asset: file modification time in development, theme version otherwise.
 *
 * @param string $relative Path relative to the theme directory.
 * @return string
 */
function mr_tu_asset_version( $relative ) {
	$file = MR_TU_DIR . '/' . ltrim( $relative, '/' );
	if ( 'production' !== wp_get_environment_type() && is_readable( $file ) ) {
		return MR_TU_VERSION . '.' . filemtime( $file );
	}
	return MR_TU_VERSION;
}

/**
 * Enqueues the theme stylesheet and script.
 */
function mr_tu_enqueue_assets() {
	wp_enqueue_style( 'mr-tu', MR_TU_URI . '/assets/css/main.css', array(), mr_tu_asset_version( 'assets/css/main.css' ) );

	if ( mr_tu_setting( 'view_transitions' ) ) {
		wp_add_inline_style( 'mr-tu', '@media (prefers-reduced-motion:no-preference){@view-transition{navigation:auto}}' );
	}

	$script = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ! is_readable( MR_TU_DIR . '/assets/js/theme.min.js' ) ? 'theme.js' : 'theme.min.js';
	wp_enqueue_script(
		'mr-tu',
		MR_TU_URI . '/assets/js/' . $script,
		array(),
		mr_tu_asset_version( 'assets/js/' . $script ),
		array(
			'strategy'  => 'defer',
			'in_footer' => false,
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	// The theme styles buttons, quotes and captions itself.
	wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'mr_tu_enqueue_assets' );

/**
 * Drops core's emoji polyfill on the front end (native emoji everywhere we target; saves a
 * render-blocking inline script on every page). Filter `mr_tu_disable_emoji` to keep it.
 */
function mr_tu_disable_emoji() {
	if ( is_admin() || ! apply_filters( 'mr_tu_disable_emoji', true ) ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
}
add_action( 'init', 'mr_tu_disable_emoji' );
