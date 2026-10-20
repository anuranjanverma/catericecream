<?php
/**
 * WooCommerce presentation support. Purchase prevention is NOT here: it lives in the
 * cic-catalog-guard must-use plugin so it survives theme changes.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		// Treat pages are custom compositions that render their own photos (product gallery images
		// stay editable in the product screen), so WooCommerce's gallery zoom/slider/lightbox scripts
		// are not enabled: they were loaded on every treat page without being used (Phase 09).
		add_theme_support( 'woocommerce' );
	}
);

// WooCommerce's single-product script drives its tabs, reviews and gallery, none of which the
// custom treat compositions use; it also pulled in jQuery on every treat page.
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_dequeue_script( 'wc-single-product' );
	},
	100
);

// The theme provides its own styles in later phases; skip WooCommerce's default stylesheets.
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
