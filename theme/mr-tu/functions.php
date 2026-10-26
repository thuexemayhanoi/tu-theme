<?php
/**
 * Mr Tu theme bootstrap.
 *
 * The theme is presentation only. Languages, translations, SEO, business data (NAP, prices,
 * vehicles), blocks, the TOC and Quick Contact belong to the Mr Tu Multilang SEO plugin and are
 * reached exclusively through its public `mrtu_*` API behind `function_exists()` guards
 * (see inc/plugin-bridge.php), so the theme keeps working when the plugin is inactive.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

define( 'MR_TU_VERSION', '1.0.0' );
define( 'MR_TU_DIR', get_template_directory() );
define( 'MR_TU_URI', get_template_directory_uri() );

foreach (
	array(
		'plugin-bridge',
		'setup',
		'settings',
		'head',
		'assets',
		'icons',
		'template-tags',
		'navigation',
		'images',
		'block-patterns',
	) as $mr_tu_file
) {
	require_once MR_TU_DIR . '/inc/' . $mr_tu_file . '.php';
}
unset( $mr_tu_file );
