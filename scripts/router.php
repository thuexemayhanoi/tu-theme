<?php
/**
 * Router for PHP's built-in web server (local QA only — never deployed).
 * Serves static files directly and sends everything else to WordPress' index.php,
 * emulating Apache/LiteSpeed rewrite rules for pretty permalinks.
 */
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = realpath( $root . $path );
if ( $file && is_file( $file ) && strpos( $file, realpath( $root ) ) === 0 ) {
	if ( substr( $file, -4 ) === '.php' ) {
		chdir( dirname( $file ) );
		require $file;
		return true;
	}
	return false;
}
if ( $file && is_dir( $file ) && is_file( $file . '/index.php' ) && substr( $path, -1 ) === '/' && $path !== '/' ) {
	chdir( $file );
	require $file . '/index.php';
	return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir( $root );
require $root . '/index.php';
