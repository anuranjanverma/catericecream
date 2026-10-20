<?php
/**
 * Router for PHP's built-in web server, used only for the disposable staging rehearsal:
 *   php -S 127.0.0.1:8090 -t <site> <repo>/dev/tools/release/router.php
 * Existing files are served directly; everything else goes to WordPress (pretty permalinks).
 * Real staging uses Apache/Nginx with the rewrite rules in DEPLOYMENT.md.
 *
 * @package CIC\Tools
 */

$path = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if ( '/' !== $path && is_file( $file ) && ! str_ends_with( $path, '.php' ) ) {
	return false;
}
if ( str_ends_with( $path, '.php' ) && is_file( $file ) ) {
	$_SERVER['SCRIPT_NAME'] = $path;
	require $file;
	return;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
