<?php
/**
 * Writes wp-config.php for a DISPOSABLE copy of the site (release build / staging rehearsal /
 * restore drill). Plain PHP CLI, not WordPress:
 *
 *   php make-disposable-config.php <source wp-config.php> <target site dir> <default db> <url> <log file>
 *
 * - Database credentials are reused from the source file but never printed.
 * - DB_NAME becomes getenv('CIC_DRILL_DB') or <default db>; it must start with "cic_" and can never
 *   be the working database name.
 * - Fresh salts, WP_ENVIRONMENT_TYPE 'staging', DISABLE_WP_CRON (cron is run explicitly, like a real
 *   server cron), its own debug log, no hard-coded WP_HOME/WP_SITEURL (the URL lives in the
 *   database after search-replace) and CIC_DISPOSABLE, which the release tools require.
 * - Refuses to write inside C:\laragon\www (the web root of the working site).
 *
 * @package CIC\Tools
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

[ , $source, $target, $db, $url, $log ] = array_pad( $argv, 6, '' );
$fail = static function ( string $msg ): void {
	fwrite( STDERR, "make-disposable-config: {$msg}\n" );
	exit( 1 );
};

if ( ! is_readable( $source ) || ! is_dir( $target ) ) {
	$fail( 'source config or target directory missing' );
}
$real_target = strtolower( str_replace( '\\', '/', (string) realpath( $target ) ) );
if ( str_starts_with( $real_target, 'c:/laragon/www' ) ) {
	$fail( 'target is inside the web root; disposable copies live outside C:\\laragon\\www' );
}
if ( ! preg_match( '/^cic_[a-z0-9_]+$/', $db ) || ! preg_match( '#^https?://[^/\s]+$#', $url ) ) {
	$fail( 'invalid database name (must start with cic_) or URL' );
}

$config = (string) file_get_contents( $source );

// The working database name must never be reused.
if ( ! preg_match( "/define\(\s*'DB_NAME'\s*,\s*'([^']*)'\s*\)\s*;/", $config, $m ) ) {
	$fail( 'DB_NAME not found' );
}
if ( $m[1] === $db ) {
	$fail( 'disposable database name equals the working database name' );
}

$replace = static function ( string $name, string $php ) use ( &$config, $fail ): void {
	$count  = 0;
	$config = preg_replace( "/define\(\s*'" . $name . "'\s*,\s*[^;]*\)\s*;/", $php, $config, 1, $count );
	if ( 1 !== $count ) {
		$fail( "{$name} not found" );
	}
};

$replace( 'DB_NAME', "define( 'DB_NAME', getenv( 'CIC_DRILL_DB' ) ?: '" . $db . "' );" );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $salt ) {
	$replace( $salt, "define( '{$salt}', '" . bin2hex( random_bytes( 32 ) ) . "' );" );
}
$replace( 'WP_ENVIRONMENT_TYPE', "define( 'WP_ENVIRONMENT_TYPE', 'staging' );" );
$replace( 'WP_DEBUG_LOG', "define( 'WP_DEBUG_LOG', " . var_export( str_replace( '\\', '/', $log ), true ) . ' );' );
$replace( 'WP_SITEURL', '/* WP_SITEURL: stored in the database (disposable copy). */' );
$replace( 'WP_HOME', '/* WP_HOME: stored in the database (disposable copy). */' );

$extra  = "/* Disposable copy written by dev/tools/release/make-disposable-config.php. */\n";
$extra .= "define( 'CIC_DISPOSABLE', true );\n";
$extra .= "define( 'DISABLE_WP_CRON', true );\n\n";
$marker = "/* That's all, stop editing!";
if ( ! str_contains( $config, $marker ) ) {
	$fail( 'end-of-config marker not found' );
}
$config = str_replace( $marker, $extra . $marker, $config );

file_put_contents( rtrim( $target, '/\\' ) . '/wp-config.php', $config );
file_put_contents( rtrim( $target, '/\\' ) . '/wp-cli.yml', "# Disposable copy (no secrets).\npath: .\nurl: {$url}\n" );
echo "Disposable wp-config.php written (database {$db}, environment staging). Credentials not shown.\n";
