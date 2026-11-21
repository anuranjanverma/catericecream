<?php
/**
 * Popular Event Venues starter list (2026-11-21): imports dev/tools/content/data/venues-20261121.json
 * into {prefix}cic_venues as DRAFT (unverified; never shown on staging or production until the owner
 * approves each venue under Service Areas → Venues).
 *
 * Add-only:
 *   - Cities are found by city key ("pasadena-ca"), never by ID; unknown keys are reported.
 *   - A venue the city already has (same name) is left exactly as it is (admin edits win).
 *   - A venue this tool added once is never added again, even after the owner deleted it
 *     (option cic_venues_starter_20261121).
 *   - Unknown occasion keys are dropped and reported.
 *
 * Usage:
 *   wp eval-file dev/tools/content/venues-20261121.php dry-run
 *   wp eval-file dev/tools/content/venues-20261121.php
 *   wp eval-file dev/tools/content/venues-20261121.php rollback
 * Rollback deletes only rows this tool added that are still Draft (approved, hidden or edited-to-
 * approved venues stay) and forgets them, so a later run may add them again.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Locations\DatabaseRepository;
use CIC\Locations\Tables;
use CIC\Locations\VenueStore;

const CIC_VENUE_STARTER_OPTION = 'cic_venues_starter_20261121';

$cic_dry      = in_array( 'dry-run', (array) ( $args ?? array() ), true );
$cic_rollback = in_array( 'rollback', (array) ( $args ?? array() ), true );

if ( ! class_exists( VenueStore::class ) || ! Tables::ready() ) {
	WP_CLI::error( 'The Cater Ice Cream core plugin or the location tables are not available. Nothing changed.' );
}
if ( ! VenueStore::exists() ) {
	WP_CLI::error( 'The venue table does not exist yet. Open any wp-admin screen once (runs the v7 migration), then re-run. Nothing changed.' );
}

$cic_data = json_decode( (string) file_get_contents( __DIR__ . '/data/venues-20261121.json' ), true );
if ( ! is_array( $cic_data ) || empty( $cic_data['cities'] ) ) {
	WP_CLI::error( 'Data file missing or invalid. Nothing changed.' );
}
$cic_source = (string) $cic_data['source'];
$cic_added  = (array) get_option( CIC_VENUE_STARTER_OPTION, array() ); // "city-key|search key" => venue id.

if ( $cic_rollback ) {
	$cic_removed = 0;
	$cic_kept    = 0;
	foreach ( $cic_added as $cic_mark => $cic_id ) {
		$cic_v = VenueStore::get( (int) $cic_id );
		if ( $cic_v && ( 'draft' !== $cic_v['status'] || $cic_source !== $cic_v['source'] ) ) {
			++$cic_kept;
			WP_CLI::log( sprintf( 'kept       %s (%s)', $cic_v['name'], $cic_v['status'] ) );
			continue;
		}
		if ( $cic_v && ! $cic_dry ) {
			VenueStore::delete( array( (int) $cic_id ) );
		}
		unset( $cic_added[ $cic_mark ] );
		++$cic_removed;
	}
	if ( ! $cic_dry ) {
		update_option( CIC_VENUE_STARTER_OPTION, $cic_added, false );
	}
	WP_CLI::success( sprintf( '%sRollback: %d draft starter venues removed, %d kept (approved, hidden or not from this list).', $cic_dry ? '[dry run] ' : '', $cic_removed, $cic_kept ) );
	return;
}

$cic_repo   = new DatabaseRepository();
$cic_known  = array_keys( VenueStore::occasions() );
$cic_counts = array( 'added' => 0, 'exists' => 0, 'removed_before' => 0, 'unknown_city' => 0 );
$cic_drop   = array();

foreach ( $cic_data['cities'] as $cic_key => $cic_list ) {
	$cic_city = $cic_repo->find_city( (string) $cic_key );
	if ( ! $cic_city ) {
		++$cic_counts['unknown_city'];
		WP_CLI::warning( sprintf( 'city %s not found on this site; its %d venues were skipped', $cic_key, count( $cic_list ) ) );
		continue;
	}
	foreach ( array_values( $cic_list ) as $cic_i => $cic_item ) {
		[ $cic_name, $cic_occ ] = $cic_item;
		$cic_mark               = $cic_key . '|' . Tables::search_key( $cic_name );
		$cic_wanted             = array_filter( array_map( 'trim', explode( ',', $cic_occ ) ) );
		foreach ( array_diff( $cic_wanted, $cic_known ) as $cic_bad ) {
			$cic_drop[ $cic_bad ] = true;
		}
		$cic_exists = VenueStore::find_id( (int) $cic_city['id'], $cic_name );
		if ( $cic_exists ) {
			++$cic_counts['exists'];
			continue;
		}
		if ( isset( $cic_added[ $cic_mark ] ) ) {
			++$cic_counts['removed_before'];
			WP_CLI::log( sprintf( 'not re-added (removed by an admin): %s — %s', $cic_city['label'], $cic_name ) );
			continue;
		}
		if ( $cic_dry ) {
			++$cic_counts['added'];
			continue;
		}
		$cic_id = VenueStore::save(
			array(
				'city_id'    => $cic_city['id'],
				'name'       => $cic_name,
				'occasions'  => $cic_wanted,
				'sort_order' => ( $cic_i + 1 ) * 10,
				'status'     => 'draft',
			),
			0,
			$cic_source
		);
		if ( is_wp_error( $cic_id ) ) {
			WP_CLI::warning( sprintf( '%s — %s: %s', $cic_city['label'], $cic_name, $cic_id->get_error_message() ) );
			continue;
		}
		$cic_added[ $cic_mark ] = $cic_id;
		++$cic_counts['added'];
	}
}
if ( ! $cic_dry ) {
	update_option( CIC_VENUE_STARTER_OPTION, $cic_added, false );
}
if ( $cic_drop ) {
	WP_CLI::warning( 'Unknown occasion keys dropped: ' . implode( ', ', array_keys( $cic_drop ) ) );
}
WP_CLI::success(
	sprintf(
		'%s%d venues %s as Draft; %d already present (left unchanged); %d previously removed (not re-added); %d unknown cities.',
		$cic_dry ? '[dry run] ' : '',
		$cic_counts['added'],
		$cic_dry ? 'would be added' : 'added',
		$cic_counts['exists'],
		$cic_counts['removed_before'],
		$cic_counts['unknown_city']
	)
);
