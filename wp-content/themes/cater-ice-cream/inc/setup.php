<?php
/**
 * Theme supports, menu locations and editor styles.
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
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );

		// Editor parity: the same token-driven component styles load inside the block editor canvas.
		add_editor_style( 'assets/css/main.css' );

		register_nav_menus(
			array(
				'primary'          => __( 'Primary navigation', 'cater-ice-cream' ),
				'secondary'        => __( 'Header bottom row', 'cater-ice-cream' ),
				'footer'           => __( 'Footer navigation', 'cater-ice-cream' ),
				'footer_secondary' => __( 'Footer second menu', 'cater-ice-cream' ),
				'legal'            => __( 'Footer legal links', 'cater-ice-cream' ),
			)
		);
	}
);

/**
 * The drawer prints a second copy of the primary menu; drop the duplicate element ids there.
 */
add_filter(
	'nav_menu_item_id',
	static function ( $id, $item, $args ) {
		return ( isset( $args->menu_id ) && str_starts_with( (string) $args->menu_id, 'cic-drawer' ) ) ? '' : $id;
	},
	10,
	3
);
