<?php
/**
 * Plugin Name:       Mr Tu Multilang SEO
 * Plugin URI:        https://hanoi.thuexemaynguyentu.com/
 * Description:       Five-language (VI · EN · DE · FR · ES) routing, translation groups, SEO (canonical, hreflang, sitemaps, schema), business data (NAP, prices, vehicles), table of contents and quick contact for the Mr Tu website. One WordPress, one domain, no external services.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Mr Tu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mr-tu-multilang-seo
 * Domain Path:       /languages
 *
 * @package MrTu\Multilang
 */

defined( 'ABSPATH' ) || exit;

define( 'MRTU_ML_VERSION', '1.0.0' );
define( 'MRTU_ML_FILE', __FILE__ );
define( 'MRTU_ML_DIR', plugin_dir_path( __FILE__ ) );
define( 'MRTU_ML_URL', plugin_dir_url( __FILE__ ) );

require_once MRTU_ML_DIR . 'includes/class-autoloader.php';
\MrTu\Multilang\Autoloader::register();

foreach ( array( 'functions', 'functions-business', 'functions-seo', 'functions-toc' ) as $mrtu_ml_file ) {
	if ( is_readable( MRTU_ML_DIR . 'includes/' . $mrtu_ml_file . '.php' ) ) {
		require_once MRTU_ML_DIR . 'includes/' . $mrtu_ml_file . '.php';
	}
}
unset( $mrtu_ml_file );

// Boot immediately: the request language must be known before themes and text domains load.
\MrTu\Multilang\Plugin::instance()->boot();

register_activation_hook( __FILE__, array( '\MrTu\Multilang\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\MrTu\Multilang\Plugin', 'deactivate' ) );
