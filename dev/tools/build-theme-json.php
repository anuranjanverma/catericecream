<?php
/**
 * Regenerates the token sections of the theme's theme.json from CIC\Design\Schema defaults,
 * keeping the hand-written parts (font faces, disabled controls). CLI only:
 *   wp eval-file dev/tools/build-theme-json.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

$file = get_template_directory() . '/theme.json';
$json = json_decode( file_get_contents( $file ), true );
$gen  = CIC\Design\ThemeJson::theme_json_settings( CIC\Design\Schema::defaults() );

$json['settings']['color']['palette']           = $gen['settings']['color']['palette'];
$json['settings']['typography']['fontSizes']    = $gen['settings']['typography']['fontSizes'];
$json['settings']['spacing']['spacingSizes']    = $gen['settings']['spacing']['spacingSizes'];
$json['settings']['layout']                     = $gen['settings']['layout'];
$json['settings']['custom']                     = $gen['settings']['custom'];

$out = json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
$out = preg_replace_callback( '/^( +)/m', static fn( $m ) => str_repeat( "\t", (int) ( strlen( $m[1] ) / 4 ) ), $out );
file_put_contents( $file, $out . "\n" );
WP_CLI::success( 'theme.json token sections regenerated from Schema defaults.' );
