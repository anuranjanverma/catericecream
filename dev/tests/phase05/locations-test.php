<?php
/**
 * Phase 05 server-side checks: schema/indexes, coverage rules, safe imports, fixture guard,
 * virtual routes (status codes, aliases, slug history), SEO tags, sitemap, cache invalidation,
 * prepared lookups, REST privacy, homepage connection, rewrite flushing and editorial privacy.
 * Everything changed is restored in `finally` (state.php snapshot).
 *
 * Usage: wp eval-file dev/tests/phase05/locations-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Locations\CityStore;
use CIC\Locations\Coverage;
use CIC\Locations\DatabaseRepository;
use CIC\Locations\Editorial;
use CIC\Locations\Importer;
use CIC\Locations\Routes;
use CIC\Locations\Service as Locations;
use CIC\Locations\Tables;
use CIC\Quote\Context;

global $cic5;
$cic5 = array( 'pass' => 0, 'fail' => 0 );

function cic5_check( string $label, bool $ok, $detail = '' ): void {
	global $cic5;
	$cic5[ $ok ? 'pass' : 'fail' ]++;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && '' !== $detail ? ' — ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' ) );
}

/** @return array{code: int, location: string, body: string, headers: mixed} */
function cic5_get( string $path ): array {
	$r = wp_remote_get( home_url( $path ), array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 30, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
	if ( is_wp_error( $r ) ) {
		return array( 'code' => 0, 'location' => '', 'body' => $r->get_error_message(), 'headers' => array() );
	}
	return array( 'code' => (int) wp_remote_retrieve_response_code( $r ), 'location' => (string) wp_remote_retrieve_header( $r, 'location' ), 'body' => (string) wp_remote_retrieve_body( $r ), 'headers' => wp_remote_retrieve_headers( $r ) );
}

function cic5_status( string $location, string $city = '' ): string {
	return Locations::repository()->evaluate_coverage( $location, $city )['status'];
}

$snapshot = wp_normalize_path( ABSPATH . 'dev/tests/output/phase05/state-server.json' );
wp_mkdir_p( dirname( $snapshot ) );
WP_CLI::runcommand( 'eval-file dev/tests/phase05/state.php snapshot ' . $snapshot, array( 'launch' => false, 'exit_error' => true ) );
$rules_before = md5( (string) wp_json_encode( get_option( 'rewrite_rules' ) ) );

try {
	global $wpdb;
	$repo = Locations::repository();
	$t    = Tables::all();

	/* ---------------- L1 schema ---------------- */
	$missing = array_filter( $t, static fn( $table ) => ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
	cic5_check( 'L1.1 five location tables exist with the configured prefix (' . $wpdb->prefix . ')', ! $missing && str_starts_with( $t['cities'], $wpdb->prefix ) && Tables::ready(), $missing );
	$idx = static function ( string $table ): array {
		global $wpdb;
		return array_unique( wp_list_pluck( $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A ), 'Key_name' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	};
	$ci = $idx( $t['cities'] );
	$zi = $idx( $t['city_zips'] );
	$si = $idx( $t['city_slugs'] );
	cic5_check( 'L1.2 indexes: unique country+state+slug, name prefix, state+county, reverse ZIP, unique slug history', ! array_diff( array( 'location_slug', 'name_prefix', 'state_county' ), $ci ) && in_array( 'zip', $zi, true ) && in_array( 'state_slug', $si, true ), compact( 'ci', 'zi', 'si' ) );
	$col = $wpdb->get_row( "SHOW COLUMNS FROM {$t['zips']} LIKE 'zip'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic5_check( 'L1.3 ZIP stored as CHAR(5) string', 'char(5)' === strtolower( (string) $col['Type'] ), $col );
	$explain = $wpdb->get_row( $wpdb->prepare( "EXPLAIN SELECT * FROM {$t['cities']} c WHERE c.state_code = %s AND c.name_search LIKE %s", 'CA', 'pas%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic5_check( 'L1.4 autocomplete prefix lookup uses the name_prefix index', 'name_prefix' === ( $explain['key'] ?? '' ), $explain );

	/* ---------------- L2 coverage rules ---------------- */
	$matrix = array(
		array( '91101', '', 'covered' ),
		array( '91101-1234', '', 'covered' ),
		array( '91208', '', 'unknown' ),
		array( '90220', '', 'manual_review' ),
		array( '90220', 'compton', 'outside_area' ),
		array( '90220', 'rancho-dominguez', 'covered' ),
		array( '90221', '', 'outside_area' ),
		array( '90221', 'east-rancho-dominguez', 'outside_area' ),
		array( '90222', '', 'outside_area' ),
		array( '90224', '', 'outside_area' ),
		array( '90021', '', 'manual_review' ),
		array( '93108', '', 'unknown' ),
		array( '94105', '', 'unknown' ),
		array( '1234', '', 'invalid' ),
		array( '123456', '', 'invalid' ),
		array( '9110a', '', 'invalid' ),
		array( 'pasadena', '', 'covered' ),
		array( 'Pasadena, CA', '', 'covered' ),
		array( 'pasadena-ca', '', 'covered' ),
		array( 'glendale', '', 'manual_review' ),
		array( 'Los Angeles', '', 'manual_review' ),
		array( 'compton', '', 'outside_area' ),
		array( 'ojai', '', 'unknown' ),
		array( 'atlantis', '', 'unknown' ),
		array( '<script>', '', 'invalid' ),
		array( '91101', 'glendale', 'manual_review' ),
	);
	$bad = array();
	foreach ( $matrix as $m ) {
		$got = cic5_status( $m[0], $m[1] );
		if ( $got !== $m[2] ) {
			$bad[] = "{$m[0]}|{$m[1]} → {$got} (expected {$m[2]})";
		}
	}
	cic5_check( 'L2.1 coverage matrix (' . count( $matrix ) . ' cases: exclusions win, shared ZIP needs venue city, partial city asks ZIP, unknown ≠ outside)', ! $bad, $bad );
	$out = $repo->evaluate_coverage( '90224' );
	cic5_check( 'L2.2 outside_area uses the exact required message and blocks the quote', 'Sorry, we do not currently serve this area. Please choose another event location.' === $out['message'] && 'block' === $out['action'] && Coverage::blocks_quote( 'outside_area' ), $out );
	$cov = $repo->evaluate_coverage( '91101' );
	cic5_check( 'L2.3 covered never claims a date (geography only), links the city page, flagged as fixture', ! preg_match( '/\b(available|reserved|booked)\b/i', $cov['message'] ) && str_contains( $cov['url'], '/service-areas/california/pasadena/' ) && true === $cov['fixture'], $cov );
	cic5_check( 'L2.4 clarify actions for unknown/manual_review; correct for invalid', 'clarify' === $repo->evaluate_coverage( '93108' )['action'] && 'clarify' === $repo->evaluate_coverage( '90220' )['action'] && 'correct' === $repo->evaluate_coverage( '1234' )['action'] );
	cic5_check( 'L2.5 Context keeps leading zeros and normalises ZIP+4 / "City, ST"', '02108' === Context::location( '02108' ) && '02108' === Context::location( '02108-1234' ) && 'pasadena-ca' === Context::location( 'Pasadena, CA' ) && '' === Context::location( '2108' ) );

	/* ---------------- L3 imports ---------------- */
	$src  = 'test:phase05';
	$zcsv = "zip,state_code,county_label,latitude,longitude\n02108,MA,Suffolk County,42.3570,-71.0640\n02109-1234,MA,Suffolk County,,\n2110,MA,Suffolk County,,\nabcde,MA,Suffolk County,,\n02108,MA,Suffolk County,,\n";
	$plan = ( new Importer( 'zips', 'fixture', $src ) )->run( $zcsv, true );
	$none = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['zips']} WHERE zip IN ('02108','02109')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic5_check( 'L3.1 dry run plans 2 creates, rejects 3 (lost zero, malformed, duplicate) and writes nothing', ! is_wp_error( $plan ) && 2 === $plan['counts']['created'] && 3 === $plan['counts']['rejected'] && 1 === $plan['counts']['duplicates'] && 0 === $none, is_wp_error( $plan ) ? $plan->get_error_message() : $plan['counts'] );
	$reasons = is_wp_error( $plan ) ? array() : wp_list_pluck( $plan['rejections'], 'reason' );
	cic5_check( 'L3.2 the 4-digit ZIP is rejected as a probable lost leading zero (not padded)', (bool) preg_grep( '/leading zero/i', $reasons ), $reasons );
	$apply = ( new Importer( 'zips', 'fixture', $src ) )->run( $zcsv, false );
	$z1    = $wpdb->get_row( "SELECT * FROM {$t['zips']} WHERE zip = '02108'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$z2    = $wpdb->get_var( "SELECT zip FROM {$t['zips']} WHERE zip = '02109'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic5_check( 'L3.3 apply keeps "02108" with its zero, shortens ZIP+4 to "02109", new geography is unreviewed', $z1 && '02108' === $z1['zip'] && '02109' === $z2 && 'unreviewed' === $z1['service_status'] && $src === $z1['source_reference'] && 'fixture' === $z1['dataset'], $z1 );
	$again = ( new Importer( 'zips', 'fixture', $src ) )->run( $zcsv, false );
	cic5_check( 'L3.4 repeat import is deterministic: 0 created, 0 updated, 2 unchanged', 0 === $again['counts']['created'] && 0 === $again['counts']['updated'] && 2 === $again['counts']['unchanged'], $again['counts'] );
	cic5_check( 'L3.5 unknown/geography ZIP is not confirmed coverage', 'unknown' === cic5_status( '02108' ) );
	$geo = ( new Importer( 'zips', 'fixture', 'fixture:local-dev-2026-10-20' ) )->run( "zip,state_code,county_label,service_status\n91101,CA,Los Angeles County,disabled\n", false );
	cic5_check( 'L3.6 a geography import (no --apply-status) never changes a reviewed service state', 'enabled' === $wpdb->get_var( "SELECT service_status FROM {$t['zips']} WHERE zip = '91101'" ) && 'covered' === cic5_status( '91101' ), $geo['counts'] ?? $geo ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->insert( $t['zips'], array( 'zip' => '02201', 'state_code' => 'MA', 'service_status' => 'enabled', 'dataset' => 'production', 'source_reference' => 'test:production', 'updated_at' => current_time( 'mysql', true ) ) );
	$clash = ( new Importer( 'zips', 'fixture', $src ) )->run( "zip,state_code,county_label\n02201,MA,Changed County\n", false );
	cic5_check( 'L3.7 fixture data cannot overwrite an approved production row', 1 === $clash['counts']['rejected'] && '' === (string) $wpdb->get_var( "SELECT county_label FROM {$t['zips']} WHERE zip = '02201'" ), $clash['counts'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$enc  = ( new Importer( 'zips', 'fixture', $src ) )->run( "zip,state_code\n\xff\xfe91101,CA\n", true );
	$cols = ( new Importer( 'zips', 'fixture', $src ) )->run( "postcode,state\n91101,CA\n", true );
	$nosrc = ( new Importer( 'zips', 'fixture', '' ) )->run( "zip,state_code\n91101,CA\n", true );
	cic5_check( 'L3.8 invalid UTF-8, missing required columns and a missing source reference are refused', is_wp_error( $enc ) && is_wp_error( $cols ) && is_wp_error( $nosrc ), array( is_wp_error( $enc ), is_wp_error( $cols ), is_wp_error( $nosrc ) ) );
	$bom = ( new Importer( 'cities', 'fixture', $src ) )->run( "\xEF\xBB\xBFstate_code,county_name,city_name\nCA,Test County,Cañada Testville\n", true );
	cic5_check( 'L3.9 UTF-8 BOM stripped; accented names keep their text and get an ASCII slug/search key', ! is_wp_error( $bom ) && 'canada-testville' === $bom['actions'][0]['data']['city_slug'] && 'Cañada Testville' === $bom['actions'][0]['data']['city_name'] && 'canada testville' === $bom['actions'][0]['data']['name_search'] && 'draft' === $bom['actions'][0]['data']['publish_status'], is_wp_error( $bom ) ? $bom->get_error_message() : $bom['actions'][0]['data'] ?? $bom );
	$map = ( new Importer( 'city_zips', 'fixture', $src ) )->run( "state_code,city_slug,zip\nCA,nowhere,91101\nCA,pasadena,99999\n", true );
	cic5_check( 'L3.10 mappings to unknown cities or ZIPs are rejected (logical referential checks)', 2 === $map['counts']['rejected'], $map['counts'] );

	/* ---------------- L4 fixture guard ---------------- */
	add_filter( 'cic_location_fixtures_allowed', '__return_false' );
	$guard_status = cic5_status( '91101' );
	$guard_import = ( new Importer( 'zips', 'fixture', $src ) )->run( "zip,state_code\n02110,MA\n", true );
	$guard_cities = $repo->list_cities( 'CA' )['total'];
	remove_filter( 'cic_location_fixtures_allowed', '__return_false' );
	cic5_check( 'L4.1 outside local/development, fixture rows are ignored (unknown, no cities) and fixture imports are refused', 'unknown' === $guard_status && is_wp_error( $guard_import ) && 0 === $guard_cities, compact( 'guard_status', 'guard_cities' ) );

	/* ---------------- L5 routes ---------------- */
	$codes = array(
		'/service-areas/'                              => 200,
		'/service-areas/california/'                   => 200,
		'/service-areas/california/pasadena/'          => 200,
		'/service-areas/california/compton/'           => 404,
		'/service-areas/california/atlantis/'          => 404,
		'/service-areas/texas/'                        => 404,
		'/service-areas/texas/austin/'                 => 404,
		'/service-areas/california/pasadena/birthday/' => 404,
		'/service-areas/california/pasadena/birthday/dippin-dots/' => 404,
		'/service-areas/california/pasadena/?location=91101' => 200,
	);
	$wrong = array();
	foreach ( $codes as $path => $code ) {
		$r = cic5_get( $path );
		if ( $r['code'] !== $code ) {
			$wrong[] = "{$path} → {$r['code']}";
		}
	}
	cic5_check( 'L5.1 canonical routes 200; draft/unknown city, unknown state and city×event×treat paths are real 404s', ! $wrong, $wrong );
	$r1 = cic5_get( '/service-areas/california/pasadena' );
	$r2 = cic5_get( '/service-areas/California/PASADENA/' );
	$canon = home_url( '/service-areas/california/pasadena/' );
	cic5_check( 'L5.2 missing slash and wrong case redirect once (301) to the canonical URL', 301 === $r1['code'] && $canon === $r1['location'] && 301 === $r2['code'] && $canon === $r2['location'], array( $r1['code'], $r1['location'], $r2['code'], $r2['location'] ) );
	$pid = (int) $wpdb->get_var( "SELECT id FROM {$t['cities']} WHERE city_slug = 'pasadena'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	CityStore::add_alias( $pid, 'pasadena-city' );
	$ra = cic5_get( '/service-areas/california/pasadena-city/' );
	cic5_check( 'L5.3 a recorded alias redirects once (301) to the canonical city', 301 === $ra['code'] && $canon === $ra['location'], array( $ra['code'], $ra['location'] ) );
	$changed = CityStore::update_city( $pid, array( 'city_slug' => 'pasadena-renamed' ) );
	$rold    = cic5_get( '/service-areas/california/pasadena/' );
	$rnew    = cic5_get( '/service-areas/california/pasadena-renamed/' );
	$ralias  = cic5_get( '/service-areas/california/pasadena-city/' );
	cic5_check( 'L5.4 after a slug change the old URL and the alias each redirect in ONE hop to the new canonical', true === $changed && 301 === $rold['code'] && home_url( '/service-areas/california/pasadena-renamed/' ) === $rold['location'] && 200 === $rnew['code'] && home_url( '/service-areas/california/pasadena-renamed/' ) === $ralias['location'], array( $rold['code'], $rold['location'], $rnew['code'], $ralias['location'] ) );
	CityStore::update_city( $pid, array( 'city_slug' => 'pasadena' ) );
	$rback = cic5_get( '/service-areas/california/pasadena/' );
	$hist  = wp_list_pluck( CityStore::redirects( $pid ), 'slug' );
	cic5_check( 'L5.5 restoring the slug serves it again (canonical slug removed from history; the renamed slug now redirects)', 200 === $rback['code'] && ! in_array( 'pasadena', $hist, true ) && in_array( 'pasadena-renamed', $hist, true ), $hist );
	CityStore::update_city( $pid, array( 'publish_status' => 'draft' ) );
	$rdraft = cic5_get( '/service-areas/california/pasadena/' );
	CityStore::update_city( $pid, array( 'publish_status' => 'published' ) );
	cic5_check( 'L5.6 an unpublished city returns 404 (no public preview without authentication)', 404 === $rdraft['code'], $rdraft['code'] );
	$rprev = cic5_get( '/service-areas/california/compton/?' . Editorial::PREVIEW . '=1&_wpnonce=abc' );
	cic5_check( 'L5.7 a forged preview request for a draft city is still a 404', 404 === $rprev['code'], $rprev['code'] );

	/* ---------------- L6 SEO ---------------- */
	update_option( 'blog_public', '1' );
	// Production view: non-production sites are always noindex (Seo\Service, 2026-10-26), so a
	// temporary MU plugin lifts that protection only for this test's own requests (random header).
	$cic5_token = wp_generate_password( 32, false );
	file_put_contents( WPMU_PLUGIN_DIR . '/zz-cic5-seo.php', "<?php\n// TEMPORARY (dev/tests/phase05); deleted by the test.\nif ( isset( \$_SERVER['HTTP_X_CIC5_SEO'] ) && hash_equals( '{$cic5_token}', (string) \$_SERVER['HTTP_X_CIC5_SEO'] ) ) {\n\tadd_filter( 'cic_seo_protect', '__return_false' );\n}\n" );
	$cic5_header = static function ( $a ) use ( $cic5_token ) {
		$a['headers']['X-CIC5-SEO'] = $cic5_token;
		return $a;
	};
	add_filter( 'http_request_args', $cic5_header );
	// With Yoast SEO the YoastAdapter feeds Yoast's output: Yoast prints no canonical on noindex pages
	// and serves its own sitemaps (cic-locations-sitemap.xml in sitemap_index.xml).
	$cic5_yoast = defined( 'WPSEO_VERSION' );
	$city = cic5_get( '/service-areas/california/pasadena/' )['body'];
	preg_match_all( '/<link rel="canonical" href="([^"]+)"/', $city, $canon_tags );
	preg_match( '/<title>([^<]+)<\/title>/', $city, $title );
	cic5_check( 'L6.1 one canonical tag pointing at the virtual city URL (Yoast: none while noindex); city title from the template', ( $cic5_yoast ? 0 === count( $canon_tags[1] ) : ( 1 === count( $canon_tags[1] ) && $canon === $canon_tags[1][0] ) ) && str_contains( html_entity_decode( $title[1] ?? '' ), 'Ice Cream Catering in Pasadena, CA' ), array( $canon_tags[1], $title[1] ?? '' ) );
	cic5_check( 'L6.2 unreviewed city is noindex even though its index status is "index"', (bool) preg_match( '/<meta name=[\'"]robots[\'"] content=[\'"][^\'"]*noindex/', $city ) );
	cic5_check( 'L6.3 BreadcrumbList + Service JSON-LD; no fabricated address, reviews or ratings', str_contains( $city, '"BreadcrumbList"' ) && str_contains( $city, '"areaServed"' ) && ! preg_match( '/"(address|aggregateRating|review)"/', $city ) );
	cic5_check( 'L6.4 Open Graph and meta description use the same canonical URL', str_contains( $city, '<meta property="og:url" content="' . $canon . '"' ) && str_contains( $city, '<meta name="description"' ) );
	$editorial = CityStore::ensure_editorial( $pid );
	wp_update_post( array( 'ID' => $editorial, 'post_status' => 'publish' ) );
	update_post_meta( $editorial, Editorial::REVIEWED, '1' );
	update_post_meta( $editorial, 'cic_city_subtitle', 'Reviewed local copy for the test.' );
	Tables::bump_cache();
	$city2 = cic5_get( '/service-areas/california/pasadena/' )['body'];
	cic5_check( 'L6.5 published + index + reviewed content → indexable (no noindex), one canonical to the city URL, and the override renders', ! preg_match( '/<meta name=[\'"]robots[\'"] content=[\'"][^\'"]*noindex/', $city2 ) && 1 === substr_count( $city2, 'rel="canonical"' ) && str_contains( $city2, '<link rel="canonical" href="' . $canon . '"' ) && str_contains( $city2, 'Reviewed local copy for the test.' ) );
	$q = cic5_get( '/service-areas/california/pasadena/?location=91101' )['body'];
	$h = cic5_get( '/service-areas/?location=pasadena' )['body'];
	$s = cic5_get( '/service-areas/california/?county=los-angeles-county' )['body'];
	$noindex = '/<meta name=[\'"]robots[\'"] content=[\'"][^\'"]*noindex/';
	cic5_check( 'L6.6 query-string variants (search, county filter) are noindex with the clean canonical (Yoast: no canonical on noindex)', preg_match( $noindex, $q ) && preg_match( $noindex, $h ) && preg_match( $noindex, $s ) && ( $cic5_yoast ? ! str_contains( $q, 'rel="canonical"' ) : str_contains( $q, '<link rel="canonical" href="' . $canon . '"' ) ) );

	/* ---------------- L7 sitemap ---------------- */
	$cic5_sm_path = $cic5_yoast ? '/cic-locations-sitemap.xml' : '/wp-sitemap-locations-1.xml';
	$index        = cic5_get( $cic5_yoast ? '/sitemap_index.xml' : '/wp-sitemap.xml' )['body'];
	$sm           = cic5_get( $cic5_sm_path );
	cic5_check( 'L7.1 sitemap index (core, or Yoast when active) lists the locations sitemap and not the private city content type', str_contains( $index, ltrim( $cic5_sm_path, '/' ) ) && ! str_contains( $index, Editorial::POST_TYPE ), $index );
	cic5_check( 'L7.2 locations sitemap: state hub + reviewed indexable Pasadena; no drafts, noindex cities, aliases or query URLs', 200 === $sm['code'] && str_contains( $sm['body'], home_url( '/service-areas/california/' ) ) && str_contains( $sm['body'], $canon ) && ! str_contains( $sm['body'], '/glendale/' ) && ! str_contains( $sm['body'], '/compton/' ) && ! str_contains( $sm['body'], 'pasadena-city' ) && ! preg_match( '/<loc>[^<]*\?/', $sm['body'] ), $sm['body'] );
	update_post_meta( $editorial, Editorial::REVIEWED, '0' );
	Tables::bump_cache();
	$sm2 = cic5_get( $cic5_sm_path )['body'];
	cic5_check( 'L7.3 un-reviewing the content removes Pasadena from the sitemap (cache invalidated)', ! str_contains( $sm2, $canon ), $sm2 );
	update_option( 'blog_public', '0' );
	remove_filter( 'http_request_args', $cic5_header );
	wp_delete_file( WPMU_PLUGIN_DIR . '/zz-cic5-seo.php' );

	/* ---------------- L8 cache invalidation ---------------- */
	$before = wp_list_pluck( $repo->list_cities( 'CA', 'los-angeles-county' )['items'], 'slug' );
	CityStore::update_city( $pid, array( 'publish_status' => 'draft' ) );
	$after = wp_list_pluck( $repo->list_cities( 'CA', 'los-angeles-county' )['items'], 'slug' );
	CityStore::update_city( $pid, array( 'publish_status' => 'published' ) );
	$again2 = wp_list_pluck( $repo->list_cities( 'CA', 'los-angeles-county' )['items'], 'slug' );
	cic5_check( 'L8.1 cached directories update immediately after a city change (version-keyed cache)', in_array( 'pasadena', $before, true ) && ! in_array( 'pasadena', $after, true ) && in_array( 'pasadena', $again2, true ) );
	$v1 = Tables::cache_version();
	CityStore::set_zip_status( '91107', 'manual_review' );
	$mid = cic5_status( 'pasadena' );
	CityStore::set_zip_status( '91107', 'enabled' );
	cic5_check( 'L8.2 coverage edits invalidate the cache and change results at once', Tables::cache_version() > $v1 && 'manual_review' === $mid && 'covered' === cic5_status( 'pasadena' ), $mid );

	/* ---------------- L9 prepared lookups ---------------- */
	$inj = array( "pas%' OR 1=1 -- ", '%', '_a%', "x' UNION SELECT user_pass FROM {$wpdb->users} -- " );
	$hits = array();
	foreach ( $inj as $q ) {
		$hits[ $q ] = count( $repo->search_locations( $q ) );
	}
	cic5_check( 'L9.1 injection-style and wildcard queries return nothing (prepared statements + esc_like)', 0 === array_sum( $hits ), $hits );
	cic5_check( 'L9.2 SQL-like coverage input is invalid, never executed', 'invalid' === cic5_status( "91101' OR '1'='1" ) && 'invalid' === cic5_status( '91101; DROP TABLE x' ) );
	$code_files = glob( WP_PLUGIN_DIR . '/cater-ice-cream-core/src/Locations/*.php' );
	$raw        = array();
	foreach ( $code_files as $file ) {
		foreach ( file( $file ) as $n => $line ) {
			if ( preg_match( '/\$wpdb->(get_\w+|query)\(\s*"[^"]*\{\$(?!t\b|t\[|table|s\b|z\b|cz\b|c\b|col\b|where|order)/', $line ) && ! str_contains( $line, 'prepare' ) ) {
				$raw[] = basename( $file ) . ':' . ( $n + 1 );
			}
		}
	}
	cic5_check( 'L9.3 location SQL interpolates only table names/static fragments; values go through prepare()', ! $raw, $raw );

	/* ---------------- L10 REST ---------------- */
	$req = new WP_REST_Request( 'GET', '/cic/v1/locations' );
	$req->set_param( 'q', 'pas' );
	$res   = rest_do_request( $req );
	$items = $res->get_data()['items'] ?? array();
	$json  = wp_json_encode( $res->get_data() );
	cic5_check( 'L10.1 GET /locations: limited candidates with label/key/url/status/message only (no notes, reasons or ids)', 200 === $res->get_status() && 'Pasadena, CA' === ( $items[0]['label'] ?? '' ) && ! str_contains( $json, 'review_note' ) && ! str_contains( $json, 'reason' ) && ! str_contains( $json, 'Fixture:' ) && ! isset( $items[0]['id'] ) && 'no-store' === ( $res->get_headers()['Cache-Control'] ?? '' ), $res->get_data() );
	$short = new WP_REST_Request( 'GET', '/cic/v1/locations' );
	$short->set_param( 'q', 'p' );
	cic5_check( 'L10.2 one-character queries are rejected (400)', 400 === rest_do_request( $short )->get_status() );
	$creq = new WP_REST_Request( 'POST', '/cic/v1/coverage' );
	$creq->set_param( 'location', '90220' );
	$creq->set_param( 'city', 'Compton' );
	$cres = rest_do_request( $creq )->get_data();
	cic5_check( 'L10.3 POST /coverage resolves a shared ZIP with the venue city (exclusion wins) without exposing notes', 'outside_area' === $cres['status'] && ! str_contains( wp_json_encode( $cres ), 'Fixture:' ) && ! isset( $cres['reason'] ), $cres );
	$zreq = new WP_REST_Request( 'GET', '/cic/v1/locations' );
	$zreq->set_param( 'q', '912' );
	$zitems = rest_do_request( $zreq )->get_data()['items'];
	cic5_check( 'L10.4 ZIP prefix autocomplete is limited (≤ 8) and each candidate carries its own coverage status', count( $zitems ) > 0 && count( $zitems ) <= 8 && ! array_diff( wp_list_pluck( $zitems, 'type' ), array( 'zip' ) ) && in_array( 'unknown', wp_list_pluck( $zitems, 'status' ), true ), $zitems );

	/* ---------------- L11 homepage + hub ---------------- */
	$home = cic5_get( '/' )['body'];
	cic5_check( 'L11.1 homepage county lists link published cities to their pages; drafts (Compton) never listed', str_contains( $home, 'href="' . $canon . '"' ) && ! preg_match( '/>\s*Compton\s*</', $home ) && str_contains( $home, 'data-cic-county' ) );
	$hub = cic5_get( '/service-areas/' )['body'];
	cic5_check( 'L11.2 hub: county cards, map points from data, steps, call panel with the global phone, FAQ, starter', str_contains( $hub, 'id="county-los-angeles-county"' ) && substr_count( $hub, 'cic-ca-map__point' ) >= 3 && str_contains( $hub, 'cic-loc-steps' ) && str_contains( $hub, 'href="' . CIC\Site\Contact::get()['phone_href'] . '"' ) && str_contains( $hub, 'data-cic-quote-form' ) );
	cic5_check( 'L11.3 no minimum-order or price copy on hub or city pages', ! preg_match( '/minimum|\$\s?\d/i', wp_strip_all_tags( $hub . $city ) ) );
	cic5_check( 'L11.4 no city-specific phone numbers or invented branches: the only tel link is the global contact value', ! array_diff( array_unique( preg_match_all( '/href="(tel:[^"]+)"/', $city, $tm ) ? $tm[1] : array() ), array( CIC\Site\Contact::get()['phone_href'] ) ) );

	/* ---------------- L12 rewrite flushing ---------------- */
	$rules = get_option( 'rewrite_rules' );
	cic5_check( 'L12.1 explicit city/state rules are stored and the route version is recorded', isset( $rules['^service-areas/([^/]+)/([^/]+)/?$'], $rules['^service-areas/([^/]+)/?$'] ) && Routes::ROUTE_VERSION === get_option( Routes::VERSION_OPTION ) );
	for ( $i = 0; $i < 3; $i++ ) {
		cic5_get( '/service-areas/california/pasadena/' );
	}
	cic5_check( 'L12.2 normal requests never flush rewrite rules (rules unchanged after page loads)', md5( (string) wp_json_encode( get_option( 'rewrite_rules' ) ) ) === $rules_before );

	/* ---------------- L13 editorial privacy ---------------- */
	$pt = get_post_type_object( Editorial::POST_TYPE );
	$rr = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . Editorial::POST_TYPE ) );
	$fq = cic5_get( '/?post_type=' . Editorial::POST_TYPE . '&p=' . $editorial );
	cic5_check( 'L13.1 city content records are private: not public/queryable, not in REST, not served on the frontend', ! $pt->public && ! $pt->publicly_queryable && $pt->exclude_from_search && 404 === $rr->get_status() && ! str_contains( $fq['body'], 'Reviewed local copy for the test.' ) );
	cic5_check( 'L13.2 city content records keep revisions and map every capability to manage_locations', post_type_supports( Editorial::POST_TYPE, 'revisions' ) && Tables::CAPABILITY === $pt->cap->edit_posts && Tables::CAPABILITY === $pt->cap->edit_post );
	cic5_check( 'L13.3 administrators have manage_locations; editors do not', get_role( 'administrator' )->has_cap( Tables::CAPABILITY ) && ! get_role( 'editor' )->has_cap( Tables::CAPABILITY ) );
} finally {
	update_option( 'blog_public', '0' );
	if ( file_exists( WPMU_PLUGIN_DIR . '/zz-cic5-seo.php' ) ) {
		wp_delete_file( WPMU_PLUGIN_DIR . '/zz-cic5-seo.php' );
	}
	WP_CLI::runcommand( 'eval-file dev/tests/phase05/state.php restore ' . $snapshot, array( 'launch' => false, 'exit_error' => false ) );
	$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cic_zips WHERE source_reference LIKE 'test:%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	cic5_check( 'L14.1 cleanup: test rows, aliases, editorial records and options restored', 0 === $left && '0' === (string) get_option( 'blog_public' ) && 'pasadena' === $wpdb->get_var( "SELECT city_slug FROM {$wpdb->prefix}cic_cities WHERE city_slug = 'pasadena'" ) && 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cic_city_slugs" ) && ! get_posts( array( 'post_type' => Editorial::POST_TYPE, 'post_status' => 'any', 'numberposts' => 1 ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

WP_CLI::log( sprintf( '== RESULT: %d passed, %d failed', $cic5['pass'], $cic5['fail'] ) );
