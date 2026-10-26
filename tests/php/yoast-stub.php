<?php
/**
 * Yoast SEO stub for tests/php/test-seo-yoast.php.
 *
 * Yoast cannot be installed in the offline test env, and Seo::yoast_active() only looks at the
 * `WPSEO_VERSION` constant, which the real plugin defines before `plugins_loaded`. tests/run-php.sh
 * copies this file into <ENV>/wordpress/wp-content/mu-plugins/ for that one test run and removes it
 * afterwards, so every other test keeps running without Yoast.
 *
 * @package MrTu\Multilang\Tests
 */

if ( ! defined( 'WPSEO_VERSION' ) ) {
	define( 'WPSEO_VERSION', '0.0.0-mrtu-stub' );
}
if ( ! defined( 'MRTU_YOAST_STUB' ) ) {
	define( 'MRTU_YOAST_STUB', true );
}
