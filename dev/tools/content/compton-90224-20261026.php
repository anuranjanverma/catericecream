<?php
/**
 * Compton exclusion: ZIP 90224 (owner QA request, 2026-10-26).
 *
 * The client's approved Compton exclusion (2026-10-21) disabled 90059, 90220, 90221 and 90222.
 * 90224 (a Compton PO Box ZIP) was only present as local fixture data, which production sites ignore,
 * so live answered "unknown" (manual review) instead of "outside_area". This adds it to the approved
 * production data as excluded, through the location Importer (the only writer of ZIP records):
 *   - missing on this site → created as production, service_status disabled;
 *   - present as fixture data → becomes production, disabled;
 *   - present as production with another status → set to disabled, unless the status was decided by
 *     hand in Service Areas (manual_at): then it is reported as a conflict and kept.
 * Nothing else is written: no city, no city–ZIP link, no other ZIP. The location cache is bumped by
 * the Importer when something changes. An excluded ZIP is final in CIC\Locations\Coverage, with or
 * without any (published) city link.
 *
 * Usage: wp eval-file dev/tools/content/compton-90224-20261026.php [dry-run]
 *
 * Safe to re-run: a second run reports the row as unchanged.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Locations\Importer;
use CIC\Locations\Service;

$cic_args = (array) ( $args ?? array() );
$cic_dry  = in_array( 'dry-run', $cic_args, true );
$cic_zip  = '90224';
$cic_csv  = "zip,state_code,county_label,latitude,longitude,service_status\n{$cic_zip},CA,Los Angeles County,33.8960,-118.2250,disabled\n";
$cic_src  = 'Compton exclusion: USPS Compton PO Box ZIP 90224 (owner QA request 2026-10-26)';

global $wpdb;
$cic_row = static function () use ( $wpdb, $cic_zip ) {
	return $wpdb->get_row( $wpdb->prepare( "SELECT zip, service_status, dataset, source_reference, manual_at FROM {$wpdb->prefix}cic_zips WHERE zip = %s", $cic_zip ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};
$cic_verdict = static function () use ( $cic_zip ): string {
	$r = Service::repository()->evaluate_coverage( $cic_zip );
	return ( $r['status'] ?? '?' ) . ' (' . ( $r['reason'] ?? '' ) . ')';
};

WP_CLI::log( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' );
WP_CLI::log( 'Before: ' . wp_json_encode( $cic_row() ?: '(no record)' ) . '; coverage here: ' . $cic_verdict() );

$cic_report = ( new Importer( 'zips', 'production', $cic_src, true ) )->run( $cic_csv, $cic_dry );
if ( is_wp_error( $cic_report ) ) {
	WP_CLI::error( $cic_report->get_error_message() );
}
WP_CLI::log( 'Plan: ' . wp_json_encode( $cic_report['counts'] ) );
foreach ( (array) $cic_report['actions'] as $cic_action ) {
	WP_CLI::log( '  ' . $cic_action['action'] . ' ' . $cic_action['key'] . ( isset( $cic_action['diff'] ) ? ' ' . wp_json_encode( $cic_action['diff'] ) : '' ) );
}
foreach ( array_merge( (array) $cic_report['conflicts'], (array) $cic_report['rejections'] ) as $cic_issue ) {
	WP_CLI::warning( $cic_issue['reason'] );
}
if ( ! $cic_dry ) {
	WP_CLI::log( 'After: ' . wp_json_encode( $cic_row() ) . '; coverage here: ' . $cic_verdict() );
}
WP_CLI::success( $cic_dry ? 'Dry run finished.' : ( $cic_report['counts']['created'] || $cic_report['counts']['updated'] ? 'Done.' : 'Nothing to change (already done).' ) );
