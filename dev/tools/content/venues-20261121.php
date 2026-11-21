<?php
/**
 * Popular Event Venues starter list (2026-11-21), CLI version of API/import-venues.php. Both call
 * CIC\Locations\VenueImporter (add-only; data: the core plugin's data/venues-starter-20261121.json,
 * keyed by city key). The list was drafted from general knowledge and is NOT verified.
 *
 * Usage:
 *   wp eval-file dev/tools/content/venues-20261121.php dry-run
 *   wp eval-file dev/tools/content/venues-20261121.php                  (new venues as Draft)
 *   wp eval-file dev/tools/content/venues-20261121.php status=approved  (owner decision only)
 *   wp eval-file dev/tools/content/venues-20261121.php rollback [dry-run]
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Locations\VenueImporter;

$cic_args   = (array) ( $args ?? array() );
$cic_dry    = in_array( 'dry-run', $cic_args, true );
$cic_status = in_array( 'status=approved', $cic_args, true ) ? 'approved' : 'draft';

if ( ! class_exists( VenueImporter::class ) ) {
	WP_CLI::error( 'The Cater Ice Cream core plugin is not active. Nothing changed.' );
}
if ( ! VenueImporter::ensure_table() ) {
	WP_CLI::error( 'The venue table could not be created. Nothing changed.' );
}

if ( in_array( 'rollback', $cic_args, true ) ) {
	$cic_r = VenueImporter::rollback( $cic_dry );
	WP_CLI::success( sprintf( '%sRollback: %d draft starter venues removed, %d kept (approved, hidden or edited).', $cic_dry ? '[dry run] ' : '', $cic_r['removed'], $cic_r['kept'] ) );
	return;
}

$cic_r = VenueImporter::run( $cic_dry, $cic_status );
foreach ( $cic_r['errors'] as $cic_e ) {
	WP_CLI::warning( $cic_e );
}
if ( $cic_r['unknown_cities'] ) {
	WP_CLI::warning( 'Cities not on this site (skipped): ' . implode( ', ', $cic_r['unknown_cities'] ) );
}
if ( $cic_r['unknown_occasions'] ) {
	WP_CLI::warning( 'Unknown occasion keys dropped: ' . implode( ', ', $cic_r['unknown_occasions'] ) );
}
WP_CLI::success( sprintf( '%s%d venues %s as %s; %d already present (unchanged); %d previously removed (not re-added).', $cic_dry ? '[dry run] ' : '', $cic_r['added'], $cic_dry ? 'would be added' : 'added', ucfirst( $cic_status ), $cic_r['exists'], $cic_r['removed_before'] ) );
