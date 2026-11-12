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

/**
 * In-page anchor links (e.g. "How It Works" → /#how-it-works) are not the current page, even
 * though WordPress ignores the fragment when it compares menu URLs.
 */
add_filter(
	'nav_menu_css_class',
	static function ( $classes, $item ) {
		if ( str_contains( (string) $item->url, '#' ) ) {
			$classes = array_diff( (array) $classes, array( 'current-menu-item', 'current_page_item' ) );
		}
		// Virtual state and city pages sit under the Service Areas hub.
		if ( class_exists( '\CIC\Locations\Routes' ) && null !== \CIC\Locations\Routes::current() && untrailingslashit( (string) $item->url ) === untrailingslashit( \CIC\Content\ViewModel::hub_url() ) ) {
			$classes[] = 'current-menu-ancestor';
		}
		return $classes;
	},
	10,
	2
);
/**
 * Menu links to Pages that are not published (e.g. unapproved policy drafts, or a page an editor
 * unpublished) are left out, so menus never point at a 404. They reappear once published.
 */
add_filter(
	'wp_nav_menu_objects',
	static function ( $items ) {
		$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return array_values(
			array_filter(
				(array) $items,
				static function ( $item ) use ( $home ) {
					if ( 'post_type' === $item->type ) {
						return 'publish' === get_post_status( (int) $item->object_id );
					}
					$url = (string) $item->url;
					if ( '' === $url || str_contains( $url, '#' ) || wp_parse_url( $url, PHP_URL_HOST ) !== $home ) {
						return true;
					}
					$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
					if ( '' === $path ) {
						return true;
					}
					$page = get_page_by_path( $path );
					return ! $page || 'publish' === $page->post_status;
				}
			)
		);
	},
	20
);
/**
 * Menu links to event pages follow the central event order (each event's Order field), so the
 * Events dropdown never needs reordering by hand. Links to unpublished events are left out.
 * Other menu items keep their place; event links keep their menu labels.
 */
add_filter(
	'wp_nav_menu_objects',
	static function ( $items ) {
		if ( ! class_exists( '\CIC\Content\Catalog' ) ) {
			return $items;
		}
		$by_path = array();
		foreach ( get_posts( array( 'post_type' => 'cic_event', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1, 'fields' => 'ids' ) ) as $event_id ) {
			$by_path[ trim( (string) wp_parse_url( (string) get_permalink( $event_id ), PHP_URL_PATH ), '/' ) ] = (int) $event_id;
		}
		$rank   = \CIC\Content\Catalog::event_rank();
		$events = array();
		foreach ( (array) $items as $index => $item ) {
			$id = 'post_type' === $item->type && 'cic_event' === $item->object ? (int) $item->object_id : ( $by_path[ trim( (string) wp_parse_url( (string) $item->url, PHP_URL_PATH ), '/' ) ] ?? 0 );
			if ( ! $id ) {
				continue;
			}
			if ( ! isset( $rank[ $id ] ) ) {
				unset( $items[ $index ] ); // Unpublished event.
				continue;
			}
			$events[ (int) $item->menu_item_parent ][ $index ] = $rank[ $id ];
		}
		// Within each parent, put the event links back into the slots they occupied, in central order.
		foreach ( $events as $group ) {
			$slots = array_keys( $group );
			asort( $group );
			$ordered = array();
			foreach ( array_keys( $group ) as $index ) {
				$ordered[] = $items[ $index ];
			}
			foreach ( $slots as $n => $slot ) {
				$items[ $slot ] = $ordered[ $n ];
			}
		}
		return array_values( $items );
	},
	21
);
add_filter(
	'nav_menu_link_attributes',
	static function ( $atts, $item ) {
		if ( str_contains( (string) $item->url, '#' ) ) {
			unset( $atts['aria-current'] );
		}
		return $atts;
	},
	10,
	2
);
