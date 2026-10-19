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
		add_theme_support( 'woocommerce' );
		add_theme_support( 'wc-product-gallery-zoom' );
		add_theme_support( 'wc-product-gallery-lightbox' );
		add_theme_support( 'wc-product-gallery-slider' );
	}
);

// The theme provides its own styles in later phases; skip WooCommerce's default stylesheets.
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
