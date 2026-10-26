<?php
/**
 * Plugin Name: Mr Tu API mock (theme QA only)
 * Description: TEST FIXTURE — never deploy. Emulates the public API of the Mr Tu Multilang SEO plugin
 * (docs/architecture-contract.md §2.4) and renders the plugin block markup documented in
 * docs/theme-markup-reference.md, so the theme can be designed and tested before the real plugin
 * lands. Toggle with `wp option update mrtu_theme_mock 0|1`. Symlink into wp-content/mu-plugins.
 *
 * @package MrTu\Theme\Tests
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	static function () {
		if ( function_exists( 'mrtu_current_language' ) || '0' === (string) get_option( 'mrtu_theme_mock', '1' ) ) {
			return; // Real plugin API present, or mock disabled for "no plugin" QA.
		}
		require __DIR__ . '/mrtu-api-mock-functions.php';
	},
	20
);
