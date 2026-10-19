<?php
/**
 * Theme supports and menu locations.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'cater-ice-cream', get_template_directory() . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'custom-logo', array( 'flex-width' => true, 'flex-height' => true ) );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );

		// Header rows and footer menus are configured in Phase 04; locations are registered now
		// so menu assignments survive later template work.
		register_nav_menus(
			array(
				'primary'   => __( 'Primary navigation', 'cater-ice-cream' ),
				'secondary' => __( 'Header bottom row', 'cater-ice-cream' ),
				'footer'    => __( 'Footer navigation', 'cater-ice-cream' ),
				'legal'     => __( 'Footer legal links', 'cater-ice-cream' ),
			)
		);
	}
);
