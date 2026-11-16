<?php
/**
 * City landing-page inventory (2026-11-16): one row per production city with coverage, content and
 * SEO state, read from the real location tables through the project's repository classes.
 *
 * Columns: id, city, state, county, url, publish, index, zips_linked, zips_enabled, zips_disabled,
 * zips_other (unreviewed/outside/partial), zips_shared (linked to more than one city), coverage
 * (city-level status), served, editorial_id, editorial_status, reviewed, overrides, title,
 * description, canonical, robots_production (what production would print), sitemap_eligible.
 *
 * Usage: wp eval-file dev/tools/content/city-inventory.php [csv=/path/inventory.csv]
 * Read-only. Prints a summary; writes the CSV when csv= is given. No personal data.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Locations\CityStore;
use CIC\Locations\Coverage;
use CIC\Locations\DatabaseRepository;
use CIC\Locations\Editorial;
use CIC\Locations\Tables;

$cic_csv = '';
foreach ( (array) ( $args ?? array() ) as $cic_arg ) {
	if ( 0 === strpos( $cic_arg, 'csv=' ) ) {
		$cic_csv = substr( $cic_arg, 4 );
	}
}

global $wpdb;
$cic_t    = Tables::all();
$cic_repo = new DatabaseRepository();

// ZIP links with their effective status, and how many production cities share each ZIP.
$cic_links  = $wpdb->get_results( "SELECT cz.city_id, cz.zip, cz.service_override, z.service_status FROM {$cic_t['city_zips']} cz LEFT JOIN {$cic_t['zips']} z ON z.zip = cz.zip AND z.dataset = 'production' WHERE cz.dataset = 'production'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$cic_shared = array_count_values( array_column( $cic_links, 'zip' ) );
$cic_by     = array();
foreach ( $cic_links as $cic_l ) {
	$cic_by[ (int) $cic_l['city_id'] ][] = $cic_l;
}
$cic_served = array_column( $cic_repo->served_city_ranking( PHP_INT_MAX ), 'served_zips', 'id' );

$cic_rows = array();
foreach ( $wpdb->get_results( "SELECT * FROM {$cic_t['cities']} WHERE dataset = 'production' ORDER BY county_name, city_name", ARRAY_A ) as $cic_row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$cic_city = DatabaseRepository::city( $cic_row );
	$cic_id   = (int) $cic_city['id'];
	$cic_n    = array( 'enabled' => 0, 'disabled' => 0, 'other' => 0, 'shared' => 0 );
	foreach ( $cic_by[ $cic_id ] ?? array() as $cic_l ) {
		$cic_eff = Coverage::effective( (string) $cic_l['service_override'], (string) $cic_l['service_status'] );
		$cic_key = isset( $cic_n[ $cic_eff ] ) ? $cic_eff : 'other';
		++$cic_n[ $cic_key ];
		if ( ( $cic_shared[ $cic_l['zip'] ] ?? 0 ) > 1 ) {
			++$cic_n['shared'];
		}
	}
	$cic_ed      = CityStore::editorial_id( $cic_id );
	$cic_ed      = $cic_ed && get_post( $cic_ed ) ? $cic_ed : 0;
	$cic_content = $cic_repo->get_city_content( $cic_id );
	$cic_vm      = \CIC\Content\ViewModel::city( $cic_city, $cic_ed && 'publish' !== get_post_status( $cic_ed ) ? $cic_ed : 0 );
	$cic_index   = $cic_repo->is_indexable( $cic_city );
	$cic_rows[]  = array(
		'id'                => $cic_id,
		'city'              => $cic_city['name'],
		'state'             => $cic_row['state_code'],
		'county'            => $cic_row['county_name'],
		'url'               => $cic_city['url'],
		'publish'           => $cic_city['publish_status'],
		'index'             => $cic_city['index_status'],
		'zips_linked'       => count( $cic_by[ $cic_id ] ?? array() ),
		'zips_enabled'      => $cic_n['enabled'],
		'zips_disabled'     => $cic_n['disabled'],
		'zips_other'        => $cic_n['other'],
		'zips_shared'       => $cic_n['shared'],
		'coverage'          => $cic_repo->city_coverage( $cic_city )['status'],
		'served'            => isset( $cic_served[ $cic_id ] ) ? 'yes' : 'no',
		'editorial_id'      => $cic_ed,
		'editorial_status'  => $cic_ed ? get_post_status( $cic_ed ) : '',
		'reviewed'          => $cic_content['reviewed'] ? 'yes' : 'no',
		'overrides'         => $cic_ed ? Editorial::override_count( $cic_ed )['overrides'] : 0,
		'title'             => (string) ( $cic_vm['seo']['title'] ?? '' ),
		'description'       => (string) ( $cic_vm['seo']['description'] ?? '' ),
		'canonical'         => $cic_index ? (string) ( $cic_vm['seo']['canonical'] ?? '' ) : '(none: noindex)',
		'robots_production' => $cic_index ? 'index, follow' : 'noindex, follow',
		'sitemap_eligible'  => $cic_index ? 'yes' : 'no',
	);
}

$cic_count = static fn( string $k, string $v ) => count( array_filter( $cic_rows, static fn( $r ) => (string) $r[ $k ] === $v ) );
WP_CLI::log( sprintf( 'Production cities: %d', count( $cic_rows ) ) );
WP_CLI::log( sprintf( '  published: %d, index setting: %d, served (>=1 enabled ZIP): %d', $cic_count( 'publish', 'published' ), $cic_count( 'index', 'index' ), $cic_count( 'served', 'yes' ) ) );
WP_CLI::log( sprintf( '  approved + published + served (eligible inventory): %d', count( array_filter( $cic_rows, static fn( $r ) => 'published' === $r['publish'] && 'index' === $r['index'] && 'yes' === $r['served'] ) ) ) );
WP_CLI::log( sprintf( '  with a content record: %d, reviewed: %d, indexable now: %d', count( array_filter( $cic_rows, static fn( $r ) => $r['editorial_id'] ) ), $cic_count( 'reviewed', 'yes' ), $cic_count( 'sitemap_eligible', 'yes' ) ) );
WP_CLI::log( sprintf( '  with disabled (excluded) ZIPs: %d, with non-enabled ZIPs: %d, with shared ZIPs: %d', count( array_filter( $cic_rows, static fn( $r ) => $r['zips_disabled'] > 0 ) ), count( array_filter( $cic_rows, static fn( $r ) => $r['zips_disabled'] + $r['zips_other'] > 0 ) ), count( array_filter( $cic_rows, static fn( $r ) => $r['zips_shared'] > 0 ) ) ) );
foreach ( array_count_values( array_column( $cic_rows, 'county' ) ) as $cic_c => $cic_v ) {
	WP_CLI::log( "  $cic_c: $cic_v" );
}
foreach ( array_count_values( array_column( $cic_rows, 'coverage' ) ) as $cic_c => $cic_v ) {
	WP_CLI::log( "  coverage $cic_c: $cic_v" );
}

if ( '' !== $cic_csv ) {
	$cic_fh = fopen( $cic_csv, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fputcsv( $cic_fh, array_keys( $cic_rows[0] ), ',', '"', '' );
	foreach ( $cic_rows as $cic_r ) {
		fputcsv( $cic_fh, $cic_r, ',', '"', '' );
	}
	fclose( $cic_fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	WP_CLI::log( 'CSV: ' . $cic_csv );
}
WP_CLI::success( 'Inventory done (read-only).' );
