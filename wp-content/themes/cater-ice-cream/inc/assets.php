<?php
/**
 * Frontend assets. Versions use file modification time so edits bust caches locally.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cache-busting version for a theme asset.
 */
function cic_theme_asset_version( string $relative_path ): string {
	$file = get_template_directory() . '/' . ltrim( $relative_path, '/' );
	return is_readable( $file ) ? (string) filemtime( $file ) : CIC_THEME_VERSION;
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'cic-main',
			get_template_directory_uri() . '/assets/css/main.css',
			array(),
			cic_theme_asset_version( 'assets/css/main.css' )
		);
		wp_enqueue_script(
			'cic-main',
			get_template_directory_uri() . '/assets/js/main.js',
			array(),
			cic_theme_asset_version( 'assets/js/main.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}
);

/**
 * Mark JavaScript support before first paint so the mobile menu does not flash open.
 */
add_action(
	'wp_head',
	static function (): void {
		wp_print_inline_script_tag( 'document.documentElement.classList.add("cic-js");' );
	},
	1
);
