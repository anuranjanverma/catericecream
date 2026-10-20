<?php
/**
 * Service Areas admin enhancement — server checks (local only). Uses temporary FIXTURE cities and
 * ZIPs; the location tables, options, content records and users are restored in `finally`.
 *
 *   wp eval-file dev/tests/service-areas/admin-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;
'local' === wp_get_environment_type() || WP_CLI::error( 'Local environment only.' );

use CIC\Locations\CityStore;
use CIC\Locations\Coverage;
use CIC\Locations\DatabaseRepository;
use CIC\Locations\Editorial;
use CIC\Locations\Importer;
use CIC\Locations\Tables;

$GLOBALS['cic_sa_results'] = array();
function cic_sa_check( string $label, bool $ok, $detail = null ): void {
	$GLOBALS['cic_sa_results'][] = $ok;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && null !== $detail ? ' — ' . wp_json_encode( $detail ) : '' ) );
}

global $wpdb;
$out      = ABSPATH . 'dev/tests/output/service-areas';
$snapshot = $out . '/state-server.json';
wp_mkdir_p( $out );
// Same-process include (nested WP-CLI launches hang on Windows pipes); returns the printed output.
$state = static function ( string $a, string $f = '' ): string {
	$args = array( $a, $f ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- read by state.php.
	ob_start();
	require __DIR__ . '/state.php';
	return (string) ob_get_clean();
};
$state( 'snapshot', $snapshot );
file_exists( $snapshot ) || WP_CLI::error( 'Snapshot failed.' );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID' ) );
wp_set_current_user( $admins[0]->ID );
$repo = new DatabaseRepository();
$t    = Tables::all();

try {
	// Fixture data to work with: a ZIP shared by 2+ cities, and a California ZIP nobody holds.
	$shared = (string) $wpdb->get_var( "SELECT zip FROM {$t['city_zips']} GROUP BY zip HAVING COUNT(*) > 1 ORDER BY zip LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$fresh  = '';
	for ( $n = 90001; $n < 96100 && '' === $fresh; $n += 7 ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT zip FROM {$t['zips']} WHERE zip = %s", (string) $n ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$fresh = (string) $n;
		}
	}
	$others_before = CityStore::zip_cities( $shared );
	$cov_before    = array();
	foreach ( $others_before as $o ) {
		$cov_before[ $o['city_id'] ] = $repo->city_coverage( $repo->city_by_id( $o['city_id'] ) )['status'];
	}
	$zip_before = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['zips']} WHERE zip = %s", $shared ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic_sa_check( 'S0 test data found (shared fixture ZIP + unused CA ZIP)', '' !== $shared && count( $others_before ) >= 2 && '' !== $fresh, compact( 'shared', 'fresh' ) );

	WP_CLI::log( '== A. Add City' );
	$v0 = Tables::cache_version();
	$id = CityStore::create_city( array( 'city_name' => 'Phase Test Vista', 'state_code' => 'CA', 'county_name' => 'Los Angeles County', 'city_slug' => '', 'latitude' => '', 'longitude' => '', 'dataset' => 'fixture' ) );
	$row = is_wp_error( $id ) ? null : CityStore::row( $id );
	cic_sa_check( 'A1 manual city is created Draft + Noindex, fixture, manual source, slug from name', $row && 'draft' === $row['publish_status'] && 'noindex' === $row['index_status'] && 'fixture' === $row['dataset'] && CityStore::MANUAL_SOURCE === $row['source_reference'] && 'phase-test-vista' === $row['city_slug'], $row ?? $id );
	$city = $repo->city_by_id( (int) $id );
	cic_sa_check( 'A2 creating geography approves nothing: no ZIPs, coverage unknown', 0 === count( CityStore::mappings( (int) $id ) ) && 'unknown' === $repo->city_coverage( $city )['status'] );
	cic_sa_check( 'A3 cache version bumped and audit recorded', Tables::cache_version() > $v0 && in_array( 'city_create', array_column( get_option( CityStore::AUDIT_OPTION ), 'action' ), true ) );
	$dup = CityStore::create_city( array( 'city_name' => 'phase test  VISTA', 'state_code' => 'CA', 'county_name' => 'Los Angeles County', 'city_slug' => 'other-vista', 'dataset' => 'fixture' ) );
	cic_sa_check( 'A4 duplicate city (same name + county) is refused', is_wp_error( $dup ) && 'cic_city_duplicate' === $dup->get_error_code(), $dup );
	$taken = CityStore::create_city( array( 'city_name' => 'Another', 'state_code' => 'CA', 'county_name' => 'Los Angeles County', 'city_slug' => 'pasadena', 'dataset' => 'fixture' ) );
	cic_sa_check( 'A5 slug used by another city is refused (names the city)', is_wp_error( $taken ) && 'cic_city_slug_taken' === $taken->get_error_code() && str_contains( $taken->get_error_message(), 'Pasadena' ), $taken );
	CityStore::add_alias( (int) $id, 'phase-test-alias' );
	$alias = CityStore::create_city( array( 'city_name' => 'Alias Clash', 'state_code' => 'CA', 'county_name' => 'Los Angeles County', 'city_slug' => 'phase-test-alias', 'dataset' => 'fixture' ) );
	cic_sa_check( 'A6 slug recorded as another city’s alias is refused', is_wp_error( $alias ) && 'cic_city_slug_alias' === $alias->get_error_code(), $alias );
	$bad = array(
		'name'    => array( 'city_name' => '', 'county_name' => 'X County' ),
		'county'  => array( 'city_name' => 'Y', 'county_name' => '' ),
		'state'   => array( 'city_name' => 'Y', 'county_name' => 'X County', 'state_code' => 'NV' ),
		'slug'    => array( 'city_name' => 'Y', 'county_name' => 'X County', 'city_slug' => '---' ),
		'lat'     => array( 'city_name' => 'Y', 'county_name' => 'X County', 'latitude' => '200', 'longitude' => '1' ),
		'one'     => array( 'city_name' => 'Y', 'county_name' => 'X County', 'latitude' => '34.1' ),
		'dataset' => array( 'city_name' => 'Y', 'county_name' => 'X County', 'dataset' => 'approved' ),
	);
	$codes = array();
	foreach ( $bad as $k => $data ) {
		$r           = CityStore::create_city( $data + array( 'state_code' => 'CA', 'dataset' => 'fixture' ) );
		$codes[ $k ] = is_wp_error( $r ) ? $r->get_error_code() : 'CREATED';
	}
	cic_sa_check( 'A7 invalid input is refused (name, county, state, slug, coordinates, dataset)', ! in_array( 'CREATED', $codes, true ), $codes );
	add_filter( 'cic_location_fixtures_allowed', '__return_false' );
	$fx = CityStore::create_city( array( 'city_name' => 'Staging Fixture', 'state_code' => 'CA', 'county_name' => 'X County', 'dataset' => 'fixture' ) );
	remove_filter( 'cic_location_fixtures_allowed', '__return_false' );
	cic_sa_check( 'A8 fixture cities cannot be created where fixtures are not allowed', is_wp_error( $fx ) );

	WP_CLI::log( '== B. Add and assign ZIPs' );
	$parsed = CityStore::parse_zips( "02134, {$fresh}\n{$fresh} 1234 9110x {$fresh}-1234" );
	cic_sa_check( 'B1 list parsing: commas/new lines, leading zero kept, repeats and invalid entries reported', array( '02134', $fresh ) === $parsed['zips'] && array( $fresh ) === $parsed['repeated'] && 2 === count( $parsed['invalid'] ) && str_contains( $parsed['invalid'][0]['reason'], 'leading zero' ), $parsed );
	$plan    = CityStore::plan_zip_links( (int) $id, "02134 {$fresh}, {$shared}" );
	$actions = array_column( $plan['items'], 'action', 'zip' );
	$sh_item = array_values( array_filter( $plan['items'], static fn( $i ) => $i['zip'] === $shared ) )[0];
	cic_sa_check( 'B2 check step: non-CA ZIP blocked, new ZIP "create", shared ZIP "link" listing its other cities', array( '02134' => 'blocked', $fresh => 'create', $shared => 'link' ) === $actions && count( $sh_item['others'] ) === count( $others_before ), array( $actions, $sh_item['others'] ) );
	$v1 = Tables::cache_version();
	CityStore::link_zips( (int) $id, "02134 {$fresh}, {$shared}" );
	$new_zip = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['zips']} WHERE zip = %s", $fresh ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$maps    = array_column( CityStore::mappings( (int) $id ), null, 'zip' );
	cic_sa_check( 'B3 new ZIP created "unreviewed" (never served), fixture, manual source; links inherit', $new_zip && 'unreviewed' === $new_zip['service_status'] && null === $new_zip['reviewed_at'] && 'fixture' === $new_zip['dataset'] && 2 === count( $maps ) && 'inherit' === $maps[ $fresh ]['service_override'] && 'inherit' === $maps[ $shared ]['service_override'], array( $new_zip, $maps ) );
	$zip_after = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['zips']} WHERE zip = %s", $shared ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic_sa_check( 'B4 linking an existing shared ZIP leaves the ZIP record and other cities unchanged', $zip_after === $zip_before && CityStore::zip_cities( $shared, (int) $id ) == $others_before, array( $zip_before, $zip_after ) ); // phpcs:ignore Universal.Operators.StrictComparisons
	$again = CityStore::plan_zip_links( (int) $id, "{$fresh} {$shared}" );
	CityStore::link_zips( (int) $id, "{$fresh} {$shared}" );
	cic_sa_check( 'B5 re-adding linked ZIPs is skipped: no duplicate relationships', array( 'already', 'already' ) === array_column( $again['items'], 'action' ) && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['city_zips']} WHERE city_id = %d AND zip = %s", $id, $shared ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic_sa_check( 'B6 linking bumps the cache and audits zip_link', Tables::cache_version() > $v1 && in_array( 'zip_link', array_column( get_option( CityStore::AUDIT_OPTION ), 'action' ), true ) );
	$prod = CityStore::create_city( array( 'city_name' => 'Phase Test Production', 'state_code' => 'CA', 'county_name' => 'Los Angeles County', 'dataset' => 'production' ) );
	$pp   = CityStore::plan_zip_links( (int) $prod, $shared );
	cic_sa_check( 'B7 a production city cannot be linked to fixture test ZIPs', 'blocked' === $pp['items'][0]['action'] && str_contains( $pp['items'][0]['reason'], 'fixture' ), $pp['items'] );

	WP_CLI::log( '== C. City-only versus ZIP-wide coverage' );
	CityStore::set_override( (int) $id, $shared, 'disabled', 'test exclusion' );
	$m_shared = array_column( CityStore::mappings( (int) $id ), null, 'zip' )[ $shared ];
	$cov_now  = array();
	foreach ( $others_before as $o ) {
		$cov_now[ $o['city_id'] ] = $repo->city_coverage( $repo->city_by_id( $o['city_id'] ) )['status'];
	}
	cic_sa_check( 'C1 a city decision affects only this relationship (other cities unchanged, manual_at stamped)', 'disabled' === Coverage::effective( $m_shared['service_override'], $m_shared['service_status'] ) && $cov_now === $cov_before && ! empty( $m_shared['manual_at'] ), array( $cov_before, $cov_now ) );
	$venue = $repo->evaluate_coverage( $shared, 'phase-test-vista-ca' );
	$plain = $repo->evaluate_coverage( $shared );
	cic_sa_check( 'C2 venue = this city → outside area; ZIP alone → shared-ZIP clarification (unless every city excludes it)', 'outside_area' === $venue['status'] && in_array( $plain['status'], array( 'manual_review', 'outside_area' ), true ) && ( 'outside_area' !== $plain['status'] || 'city_excluded' === $plain['reason'] ), array( $venue, $plain ) );
	CityStore::set_zip_status( $shared, 'disabled' );
	$all_out = true;
	foreach ( $others_before as $o ) {
		$all_out = $all_out && 'disabled' === Coverage::effective( 'enabled', 'disabled' ) && 'outside_area' === $repo->evaluate_coverage( $shared, (string) $repo->city_by_id( $o['city_id'] )['key'] )['status'];
	}
	cic_sa_check( 'C3 ZIP coverage change applies to every linked city; exclusion wins over any city decision', $all_out && 'outside_area' === $repo->evaluate_coverage( $shared )['status'] );
	CityStore::set_zip_status( $shared, (string) $zip_before['service_status'] );
	$wpdb->update( $t['zips'], array( 'manual_at' => $zip_before['manual_at'], 'reviewed_at' => $zip_before['reviewed_at'], 'updated_at' => $zip_before['updated_at'] ), array( 'zip' => $shared ) );
	CityStore::set_zip_status( $fresh, 'enabled' );
	$nz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['zips']} WHERE zip = %s", $fresh ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic_sa_check( 'C4 explicit review by an authorised user enables the new ZIP (reviewed_at + manual_at set)', 'enabled' === $nz['service_status'] && ! empty( $nz['reviewed_at'] ) && ! empty( $nz['manual_at'] ) );

	WP_CLI::log( '== D. Remove a relationship' );
	$left    = CityStore::unlink_zip( (int) $id, $shared );
	$zip_row = $wpdb->get_var( $wpdb->prepare( "SELECT zip FROM {$t['zips']} WHERE zip = %s", $shared ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic_sa_check( 'D1 removing keeps the ZIP record and every other city’s relationship', count( $others_before ) === $left && $shared === $zip_row && CityStore::zip_cities( $shared ) == $others_before && isset( get_option( CityStore::UNLINK_OPTION )[ $id . '/' . $shared ] ), array( $left, CityStore::zip_cities( $shared ) ) ); // phpcs:ignore Universal.Operators.StrictComparisons
	$cov_after = array();
	foreach ( $others_before as $o ) {
		$cov_after[ $o['city_id'] ] = $repo->city_coverage( $repo->city_by_id( $o['city_id'] ) )['status'];
	}
	cic_sa_check( 'D2 other cities’ coverage is unchanged after removal', $cov_after === $cov_before, array( $cov_before, $cov_after ) );
	$again = CityStore::unlink_zip( (int) $id, $shared );
	cic_sa_check( 'D3 removing a relationship that does not exist is refused', is_wp_error( $again ) );

	WP_CLI::log( '== E. Imports respect manual edits' );
	$csv = "zip,state_code,county_label,service_status\n{$fresh},CA,Changed County Label,disabled\n";
	$dry = ( new Importer( 'zips', 'fixture', 'phase-sa-test', true ) )->run( $csv, true );
	cic_sa_check( 'E1 dry run reports the manual ZIP decision as a conflict (geography change still planned)', 1 === $dry['counts']['conflicts'] && 1 === $dry['counts']['updated'] && str_contains( $dry['conflicts'][0]['reason'], 'Kept the manual decision' ), $dry['counts'] );
	( new Importer( 'zips', 'fixture', 'phase-sa-test', true ) )->run( $csv, false );
	$nz = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['zips']} WHERE zip = %s", $fresh ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic_sa_check( 'E2 applied import kept the manual status and applied the geography', 'enabled' === $nz['service_status'] && 'Changed County Label' === $nz['county_label'], $nz );
	$relink = ( new Importer( 'city_zips', 'fixture', 'phase-sa-test', false ) )->run( "state_code,city_slug,zip\nCA,phase-test-vista,{$shared}\n", false );
	cic_sa_check( 'E3 a relationship removed by hand is reported, not silently re-linked', 1 === $relink['counts']['conflicts'] && 0 === $relink['counts']['created'] && ! $wpdb->get_var( $wpdb->prepare( "SELECT zip FROM {$t['city_zips']} WHERE city_id = %d AND zip = %s", $id, $shared ) ), $relink['counts'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	CityStore::set_override( (int) $id, $fresh, 'manual_review', 'checked by hand' );
	$ov = ( new Importer( 'city_zips', 'fixture', 'phase-sa-test', true ) )->run( "state_code,city_slug,zip,service_override,review_note\nCA,phase-test-vista,{$fresh},enabled,from file\n", false );
	$mf = array_column( CityStore::mappings( (int) $id ), null, 'zip' )[ $fresh ];
	cic_sa_check( 'E4 a manual city decision and note are kept over a reviewed coverage import', 1 === $ov['counts']['conflicts'] && 'manual_review' === $mf['service_override'] && 'checked by hand' === $mf['review_note'], array( $ov['counts'], $mf ) );
	$prod_row = $wpdb->get_row( $wpdb->prepare( "SELECT dataset FROM {$t['zips']} WHERE zip = %s", $fresh ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic_sa_check( 'E5 fixture data stays fixture (never relabelled production by manual edits)', 'fixture' === $prod_row['dataset'] && 'fixture' === $mf['dataset'] );

	WP_CLI::log( '== F. Slugs and redirects' );
	CityStore::update_city( (int) $id, array( 'publish_status' => 'published' ) );
	$r1   = CityStore::update_city( (int) $id, array( 'city_slug' => 'phase-test-vista-renamed' ) );
	$hop  = $repo->resolve_city( 'california', 'phase-test-vista' );
	$hop2 = $repo->resolve_city( 'california', 'phase-test-alias' );
	cic_sa_check( 'F1 slug change keeps the old slug and the alias as one-hop redirects', true === $r1 && $hop && ! empty( $hop['redirect'] ) && 'phase-test-vista-renamed' === $hop['slug'] && $hop2 && ! empty( $hop2['redirect'] ), array( $r1, $hop, $hop2 ) );
	$clash = CityStore::update_city( (int) $id, array( 'city_slug' => 'pasadena' ) );
	cic_sa_check( 'F2 renaming onto another city’s slug is refused', is_wp_error( $clash ) && 'cic_city_slug_taken' === $clash->get_error_code() );
	$back = CityStore::update_city( (int) $id, array( 'city_slug' => 'phase-test-vista' ) );
	cic_sa_check( 'F3 a city may return to its own previous slug', true === $back && 'phase-test-vista' === CityStore::row( (int) $id )['city_slug'] );

	WP_CLI::log( '== G. Content inheritance and reset' );
	$hub      = \CIC\Content\Catalog::find_by_key( 'page', 'service-areas' );
	$template = trim( (string) get_post_meta( $hub, 'cic_citydef_eyebrow', true ) );
	$expected = strtr( $template, array( '{city}' => 'Phase Test Vista', '{state}' => 'California', '{state_code}' => 'CA', '{county}' => 'Los Angeles County' ) );
	cic_sa_check( 'G1 inherited value resolves the template with this city’s tokens', '' !== $template && Editorial::inherited( 'eyebrow', CityStore::row( (int) $id ) ) === $expected, array( $template, $expected ) );
	$post_id         = CityStore::ensure_editorial( (int) $id );
	$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$ed              = new Editorial();
	$empty_field     = $ed->show_inherited( array( 'name' => 'cic_city_eyebrow', '_name' => 'cic_city_eyebrow', 'value' => '', 'instructions' => '' ) );
	cic_sa_check( 'G2 empty override shows the inherited value beside it, value stays empty', '' === $empty_field['value'] && str_contains( $empty_field['instructions'], 'Inherited from the city template' ) && str_contains( $empty_field['instructions'], esc_html( wp_trim_words( $expected, 40 ) ) ), $empty_field['instructions'] );
	update_post_meta( $post_id, 'cic_city_eyebrow', 'Local override for {city}' );
	$full_field = $ed->show_inherited( array( 'name' => 'cic_city_eyebrow', '_name' => 'cic_city_eyebrow', 'value' => 'Local override for {city}', 'instructions' => '' ) );
	cic_sa_check( 'G3 an override shows the template value and a Reset to inherited control', str_contains( $full_field['instructions'], 'Overrides the city template' ) && str_contains( $full_field['instructions'], 'cic-reset-override' ) );
	$vm = \CIC\Content\ViewModel::city( $repo->city_by_id( (int) $id ), $post_id );
	cic_sa_check( 'G4 preview shows the override with tokens', 'Local override for Phase Test Vista' === $vm['hero']['eyebrow'], $vm['hero']['eyebrow'] );
	cic_sa_check( 'G5 override count reflects saved overrides only', 1 === Editorial::override_count( $post_id )['overrides'] );
	update_post_meta( $post_id, 'cic_city_eyebrow', '' ); // What "Reset to inherited" + Save stores.
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$vm2 = \CIC\Content\ViewModel::city( $repo->city_by_id( (int) $id ), 0 );
	cic_sa_check( 'G6 after reset the page inherits the template again (no copied value)', $expected === $vm2['hero']['eyebrow'] && 0 === Editorial::override_count( $post_id )['overrides'], $vm2['hero']['eyebrow'] );

	WP_CLI::log( '== H. Search, directory and cache' );
	$listed = wp_list_pluck( $repo->list_cities( 'CA', 'los-angeles-county', 1, 100 )['items'], 'slug' );
	$found  = wp_list_pluck( $repo->search_locations( 'Phase Test V' ), 'key' );
	cic_sa_check( 'H1 a published manual city appears in the county directory and search', in_array( 'phase-test-vista', $listed, true ) && in_array( 'phase-test-vista-ca', $found, true ), array( $found ) );
	CityStore::update_city( (int) $id, array( 'publish_status' => 'draft' ) );
	$listed = wp_list_pluck( $repo->list_cities( 'CA', 'los-angeles-county', 1, 100 )['items'], 'slug' );
	cic_sa_check( 'H2 unpublishing removes it from cached directory and search immediately', ! in_array( 'phase-test-vista', $listed, true ) && ! $repo->search_locations( 'Phase Test V' ) );
	cic_sa_check( 'H3 draft city stays out of the sitemap list', ! in_array( DatabaseRepository::url( 'california', 'phase-test-vista' ), $repo->indexable_urls( 'CA' ), true ) );

	WP_CLI::log( '== I. Permissions' );
	$editor = wp_insert_user( array( 'user_login' => 'cic_sa_cli_editor', 'user_pass' => wp_generate_password( 32 ), 'user_email' => 'cic-sa-cli@example.test', 'role' => 'editor' ) );
	update_user_meta( $editor, '_cic_sa_test', 1 );
	wp_set_current_user( $editor );
	cic_sa_check( 'I1 an editor without manage_locations cannot manage locations or city content', ! current_user_can( Tables::CAPABILITY ) && ! current_user_can( 'edit_post', $post_id ) );
	wp_set_current_user( $admins[0]->ID );
} catch ( \Throwable $e ) {
	cic_sa_check( 'Unexpected error: ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine(), false );
} finally {
	wp_set_current_user( $admins[0]->ID );
	$state( 'restore', $snapshot );
	$left  = (int) trim( $state( 'city-id', 'phase-test-vista' ) );
	$users = count( get_users( array( 'meta_key' => '_cic_sa_test', 'fields' => 'ID' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	cic_sa_check( 'Z1 state restored (temporary cities, ZIPs, links, records, users removed)', 0 === $left && 0 === $users, compact( 'left', 'users' ) );
	@unlink( $snapshot ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

$cic_sa_pass = count( array_filter( $GLOBALS['cic_sa_results'] ) );
WP_CLI::log( sprintf( 'Service Areas server checks: %d/%d passed', $cic_sa_pass, count( $GLOBALS['cic_sa_results'] ) ) );
