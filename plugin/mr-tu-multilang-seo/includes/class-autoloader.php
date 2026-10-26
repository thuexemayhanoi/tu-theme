<?php
/**
 * Minimal class autoloader.
 *
 * MrTu\Multilang\Foo_Bar        → includes/class-foo-bar.php
 * MrTu\Multilang\Admin\Foo_Bar  → includes/admin/class-foo-bar.php
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Maps namespaced class names to WordPress-style file names.
 */
final class Autoloader {

	const PREFIX = 'MrTu\\Multilang\\';

	/**
	 * Registers the autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Loads a class file when it exists.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function load( $class_name ) {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( self::PREFIX ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );
		$dir      = '';
		foreach ( $parts as $part ) {
			$dir .= strtolower( str_replace( '_', '-', $part ) ) . '/';
		}
		$file = MRTU_ML_DIR . 'includes/' . $dir . 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
