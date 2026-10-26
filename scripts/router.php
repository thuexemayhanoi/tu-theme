<?php
/**
 * Router for PHP's built-in web server (local QA only — never deployed).
 *
 * Serves static files directly and sends everything else to WordPress' index.php, emulating
 * Apache/LiteSpeed rewrite rules for pretty permalinks. The dev env symlinks the repo's theme and
 * plugin folders into wp-content, so their real paths live OUTSIDE the document root: such assets
 * are served explicitly (with a content type) as long as the requested path itself stays inside the
 * root (no ".." segments).
 */
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = realpath( $root . $path );
$safe = $file && false === strpos( (string) $path, '..' );

if ( $safe && is_file( $file ) ) {
	if ( substr( $file, -4 ) === '.php' ) {
		chdir( dirname( $file ) );
		require $file;
		return true;
	}
	if ( strpos( $file, realpath( $root ) ) === 0 ) {
		return false; // Inside the docroot: let the built-in server serve it.
	}
	$types = array(
		'css'   => 'text/css',
		'js'    => 'text/javascript',
		'mjs'   => 'text/javascript',
		'json'  => 'application/json',
		'woff2' => 'font/woff2',
		'woff'  => 'font/woff',
		'svg'   => 'image/svg+xml',
		'png'   => 'image/png',
		'jpg'   => 'image/jpeg',
		'jpeg'  => 'image/jpeg',
		'webp'  => 'image/webp',
		'avif'  => 'image/avif',
		'ico'   => 'image/x-icon',
		'txt'   => 'text/plain',
		'html'  => 'text/html',
		'map'   => 'application/json',
	);
	$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	header( 'Content-Type: ' . ( isset( $types[ $ext ] ) ? $types[ $ext ] : 'application/octet-stream' ) );
	header( 'Content-Length: ' . filesize( $file ) );
	header( 'Cache-Control: no-cache' );
	readfile( $file );
	return true;
}
if ( $safe && is_dir( $file ) && is_file( $file . '/index.php' ) && substr( $path, -1 ) === '/' && $path !== '/' ) {
	chdir( $file );
	require $file . '/index.php';
	return true;
}
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir( $root );
require $root . '/index.php';
