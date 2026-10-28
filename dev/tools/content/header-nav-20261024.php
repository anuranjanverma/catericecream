<?php
/**
 * Approved header changes of 2026-10-24 (menu and settings only; the code ships separately):
 *
 *   1. Removes "About" from the menu assigned to the header (theme location "primary"), desktop and
 *      mobile drawer alike. Only top-level items that point to the About page are removed; the page,
 *      its URL and every other menu (footer, legal) are untouched, and the remaining items keep their
 *      order. An About item with sub-items is reported and left alone.
 *   2. Turns on "Desktop: phone number beside the quote button" (Header settings, main.show_call)
 *      when it is saved as off. Nothing else in the header settings is written.
 *   3. Checks the central public phone (Business contact → business_phone). It is changed only when
 *      it still holds the previous number, (818) 726-9700, to the approved 424-235-4942. Any other
 *      saved number is reported and kept unless the argument update-phone is given.
 *
 * Usage: wp eval-file dev/tools/content/header-nav-20261024.php [dry-run] [update-phone]
 *
 * Safe to re-run: a second run reports "already done" for every step. Uses WordPress APIs only
 * (works with any table prefix).
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

$cic_args  = (array) ( $args ?? array() );
$cic_dry   = in_array( 'dry-run', $cic_args, true );
$cic_force = in_array( 'update-phone', $cic_args, true );
$cic_new   = '424-235-4942';
$cic_old   = array( '8187269700' );
$cic_digit = static fn( $v ) => (string) preg_replace( '/\D+/', '', (string) $v );

WP_CLI::log( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' );

/* 1. About out of the header menu. */
$cic_locations = get_nav_menu_locations();
$cic_menu_id   = (int) ( $cic_locations['primary'] ?? 0 );
$cic_about     = get_page_by_path( 'about' );
if ( ! $cic_menu_id ) {
	WP_CLI::warning( 'Menu: no menu is assigned to the header (primary) location; skipped.' );
} elseif ( ! $cic_about ) {
	WP_CLI::warning( 'Menu: no page with the slug "about" was found; skipped (nothing removed).' );
} else {
	$cic_about_path = trim( (string) wp_parse_url( (string) get_permalink( $cic_about ), PHP_URL_PATH ), '/' );
	$cic_items      = (array) wp_get_nav_menu_items( $cic_menu_id, array( 'post_status' => 'any' ) );
	$cic_parents    = array_map( static fn( $i ) => (int) $i->menu_item_parent, $cic_items );
	$cic_found      = array();
	foreach ( $cic_items as $cic_item ) {
		if ( 0 !== (int) $cic_item->menu_item_parent ) {
			continue;
		}
		$cic_is_page = 'post_type' === $cic_item->type && (int) $cic_item->object_id === (int) $cic_about->ID;
		$cic_is_link = 'custom' === $cic_item->type && trim( (string) wp_parse_url( (string) $cic_item->url, PHP_URL_PATH ), '/' ) === $cic_about_path && in_array( (string) wp_parse_url( (string) $cic_item->url, PHP_URL_HOST ), array( '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ), true );
		if ( $cic_is_page || $cic_is_link ) {
			$cic_found[] = $cic_item;
		}
	}
	$cic_menu = wp_get_nav_menu_object( $cic_menu_id );
	if ( ! $cic_found ) {
		WP_CLI::log( 'Menu: "About" is not in "' . $cic_menu->name . '" (already done).' );
	}
	foreach ( $cic_found as $cic_item ) {
		if ( in_array( (int) $cic_item->ID, $cic_parents, true ) ) {
			WP_CLI::warning( 'Menu: item #' . $cic_item->ID . ' "' . $cic_item->title . '" has sub-items; left alone. Remove it in Appearance → Menus after moving them.' );
			continue;
		}
		WP_CLI::log( 'Menu: remove item #' . $cic_item->ID . ' "' . $cic_item->title . '" (' . $cic_item->url . ') from "' . $cic_menu->name . '".' );
		if ( ! $cic_dry ) {
			wp_delete_post( (int) $cic_item->ID, true );
		}
	}
	$cic_left = array_filter( (array) wp_get_nav_menu_items( $cic_menu_id ), static fn( $i ) => 0 === (int) $i->menu_item_parent );
	WP_CLI::log( 'Menu: top-level items ' . ( $cic_dry ? 'currently' : 'after' ) . ': ' . implode( ' | ', array_map( static fn( $i ) => $i->title, $cic_left ) ) );
}

/* 2. Desktop phone beside the quote button. */
$cic_layout = get_option( \CIC\Site\Layout::OPTION, array() );
$cic_layout = is_array( $cic_layout ) ? $cic_layout : array();
if ( isset( $cic_layout['main']['show_call'] ) && ! $cic_layout['main']['show_call'] ) {
	WP_CLI::log( 'Header: "Desktop: phone number beside the quote button" is off; turn it on.' );
	if ( ! $cic_dry ) {
		$cic_layout['main']['show_call'] = true;
		update_option( \CIC\Site\Layout::OPTION, $cic_layout );
	}
} else {
	WP_CLI::log( 'Header: desktop phone is already on (saved or default).' );
}

/* 3. Central public phone. */
$cic_settings = get_option( \CIC\Config::OPTION_SETTINGS, array() );
$cic_settings = is_array( $cic_settings ) ? $cic_settings : array();
$cic_saved    = array_key_exists( 'business_phone', $cic_settings ) ? (string) $cic_settings['business_phone'] : null;
if ( null === $cic_saved ) {
	WP_CLI::log( 'Phone: not saved; the code default ' . $cic_new . ' applies (nothing to do).' );
} elseif ( $cic_digit( $cic_saved ) === $cic_digit( $cic_new ) ) {
	WP_CLI::log( 'Phone: already ' . $cic_saved . ' (already done).' );
} elseif ( in_array( $cic_digit( $cic_saved ), $cic_old, true ) || $cic_force ) {
	WP_CLI::log( 'Phone: change "' . $cic_saved . '" to "' . $cic_new . '" (tel:+14242354942).' );
	if ( ! $cic_dry ) {
		$cic_settings['business_phone'] = $cic_new;
		update_option( \CIC\Config::OPTION_SETTINGS, $cic_settings );
	}
} else {
	WP_CLI::warning( 'Phone: saved as "' . $cic_saved . '", which is neither the old nor the approved number; kept. Re-run with update-phone to replace it with ' . $cic_new . '.' );
}

WP_CLI::success( $cic_dry ? 'Dry run finished: nothing changed.' : 'Done.' );
