<?php
/**
 * WP-CLI commands for the design system and shared navigation.
 *
 * @package CIC
 */

namespace CIC\Design;

defined( 'ABSPATH' ) || exit;

/**
 * Cater Ice Cream site tools.
 */
final class Cli {

	/**
	 * Creates the approved initial menus for empty menu locations only. Assigned locations and
	 * existing menus are never changed, so editor work is not overwritten.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cic seed-navigation
	 *
	 * @subcommand seed-navigation
	 */
	public function seed_navigation(): void {
		$locations = get_nav_menu_locations();
		$plan      = self::navigation_plan();
		foreach ( $plan as $location => $menu ) {
			if ( ! empty( $locations[ $location ] ) && wp_get_nav_menu_object( $locations[ $location ] ) ) {
				\WP_CLI::log( sprintf( 'Skipped %s: a menu is already assigned.', $location ) );
				continue;
			}
			if ( wp_get_nav_menu_object( $menu['name'] ) ) {
				\WP_CLI::log( sprintf( 'Skipped %s: a menu named "%s" already exists (assign it in Appearance → Menus).', $location, $menu['name'] ) );
				continue;
			}
			$menu_id = wp_create_nav_menu( $menu['name'] );
			if ( is_wp_error( $menu_id ) ) {
				\WP_CLI::warning( $menu_id->get_error_message() );
				continue;
			}
			foreach ( $menu['items'] as $item ) {
				$parent = self::add_item( $menu_id, $item, 0 );
				foreach ( $item['children'] ?? array() as $child ) {
					self::add_item( $menu_id, $child, $parent );
				}
			}
			$locations[ $location ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
			\WP_CLI::success( sprintf( 'Created "%s" and assigned it to %s.', $menu['name'], $location ) );
		}
	}

	/**
	 * @param array<string, mixed> $item Item definition.
	 */
	private static function add_item( int $menu_id, array $item, int $parent ): int {
		$id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $item['title'],
				'menu-item-url'       => home_url( $item['path'] ),
				'menu-item-type'      => 'custom',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent,
			)
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Approved initial navigation (BUILD_SPECIFICATION §16). Custom links to the planned routes;
	 * later phases can swap them for page/term items without changing the structure.
	 *
	 * @return array<string, array{name: string, items: array<int, array<string, mixed>>}>
	 */
	public static function navigation_plan(): array {
		$treats = array(
			array( 'title' => "Dippin' Dots", 'path' => '/treats/dippin-dots-catering/' ),
			array( 'title' => "DipN' Bananas", 'path' => '/treats/dipn-bananas-catering/' ),
			array( 'title' => 'Classic Ice Cream', 'path' => '/treats/classic-ice-cream-catering/' ),
			array( 'title' => 'Frozen Yogurt', 'path' => '/treats/frozen-yogurt-catering/' ),
		);
		$events = array(
			array( 'title' => 'Birthday', 'path' => '/events/birthday-party-ice-cream-catering/' ),
			array( 'title' => 'Wedding', 'path' => '/events/wedding-ice-cream-catering/' ),
			array( 'title' => 'Anniversary', 'path' => '/events/anniversary-ice-cream-catering/' ),
			array( 'title' => 'Corporate Event', 'path' => '/events/corporate-event-ice-cream-catering/' ),
		);
		return array(
			'primary' => array(
				'name'  => 'Primary navigation',
				'items' => array(
					array( 'title' => 'Our Treats', 'path' => '/treats/', 'children' => $treats ),
					array( 'title' => 'Events', 'path' => '/events/', 'children' => $events ),
					array( 'title' => 'How It Works', 'path' => '/#how-it-works' ),
					// About left the header menu on 2026-10-24 (owner); it stays in the footer.
					array( 'title' => 'Service Areas', 'path' => '/service-areas/' ),
				),
			),
			'footer'  => array(
				'name'  => 'Footer navigation',
				'items' => array(
					array( 'title' => 'Our Treats', 'path' => '/treats/' ),
					array( 'title' => 'Events', 'path' => '/events/' ),
					array( 'title' => 'How It Works', 'path' => '/#how-it-works' ),
					array( 'title' => 'Service Areas', 'path' => '/service-areas/' ),
				),
			),
			'legal'   => array(
				'name'  => 'Footer legal links',
				'items' => array(
					array( 'title' => 'Privacy Policy', 'path' => '/privacy-policy/' ),
					array( 'title' => 'Website Terms', 'path' => '/website-terms/' ),
				),
			),
		);
	}
}
