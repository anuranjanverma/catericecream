<?php
/**
 * Minimal PSR-4 autoloader (no Composer dependency at runtime).
 *
 * @package CIC
 */

namespace CIC;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	public static function register( string $prefix, string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class ) use ( $prefix, $base_dir ): void {
				if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
					return;
				}
				$relative = substr( $class, strlen( $prefix ) );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
