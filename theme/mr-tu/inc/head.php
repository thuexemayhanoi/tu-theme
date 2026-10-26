<?php
/**
 * <head> output: theme-color, the no-flash colour-mode script, font preloads and the brand icon.
 *
 * Never prints a title, meta description, canonical or robots tag — those belong to the plugin.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Two theme-color metas (light/dark); the mode script pins both when a visitor chose a mode.
 */
function mr_tu_head_theme_color() {
	echo '<meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">' . "\n";
	echo '<meta name="theme-color" content="#101114" media="(prefers-color-scheme: dark)">' . "\n";
}
add_action( 'wp_head', 'mr_tu_head_theme_color', 0 );

/**
 * The colour-mode script, inlined before any stylesheet so the first paint is already correct.
 *
 * Reads localStorage['mrtu-theme'] (system|light|dark), falls back to the site default, sets
 * <html data-theme> for explicit choices and swaps the `no-js` class for `js`.
 *
 * @return string JavaScript source.
 */
function mr_tu_mode_script_source() {
	$default = (string) mr_tu_setting( 'color_mode' );
	$script  = '(function(d){var r=d.documentElement,m;r.className=r.className.replace(/\bno-js\b/,"js");'
		. 'try{m=localStorage.getItem("mrtu-theme")}catch(e){}'
		. 'if(m!=="light"&&m!=="dark"&&m!=="system"){m=' . wp_json_encode( $default ) . '}'
		. 'if(m==="light"||m==="dark"){r.setAttribute("data-theme",m);'
		. 'd.querySelectorAll("meta[name=theme-color]").forEach(function(t){t.content=m==="dark"?"#101114":"#FFFFFF"})}'
		. 'r.setAttribute("data-mode",m)})(document);';
	return $script;
}

/**
 * Prints the colour-mode script (CSP-friendly: core helper, filterable attributes).
 */
function mr_tu_head_mode_script() {
	wp_print_inline_script_tag( mr_tu_mode_script_source(), array( 'id' => 'mr-tu-mode' ) );
}
add_action( 'wp_head', 'mr_tu_head_mode_script', 1 );

/**
 * Preloads the two fonts used above the fold (800 for headlines, 400 for body copy).
 */
function mr_tu_head_preload_fonts() {
	foreach ( array( 'bevietnampro-extrabold', 'bevietnampro-regular' ) as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( MR_TU_URI . '/assets/fonts/' . $font . '.woff2' )
		);
	}
}
add_action( 'wp_head', 'mr_tu_head_preload_fonts', 2 );

/**
 * Brand icon (kilometre stone) when no Site Icon has been set.
 */
function mr_tu_head_icon() {
	if ( has_site_icon() ) {
		return;
	}
	printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( MR_TU_URI . '/assets/images/icon.svg' ) );
}
add_action( 'wp_head', 'mr_tu_head_icon', 99 );
