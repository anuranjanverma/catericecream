<?php
/**
 * "Cities We Serve" prefooter checks (2026-10-22). Uses clearly named temporary test cities
 * (slugs cic-test-*), linked to existing ZIP records by direct inserts; everything is removed in
 * `finally`, the layout and audit options are restored and the location cache is bumped (the
 * cache version only goes up). ZIP records themselves are never changed.
 *
 * Usage: wp eval-file dev/tests/cities/cities-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Locations\CityStore;
use CIC\Locations\DatabaseRepository;
use CIC\Locations\Service;
use CIC\Locations\Tables;
use CIC\Site\Layout;

$cic_results = array();
$cic_check   = static function ( string $label, bool $ok, $detail = null ) use ( &$cic_results ): void {
	$cic_results[] = $ok;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && null !== $detail ? ' — ' . substr( wp_json_encode( $detail ), 0, 600 ) : '' ) );
};
$cic_get = static function ( string $path ): string {
	$r = wp_remote_get( home_url( $path ), array( 'sslverify' => false, 'timeout' => 30, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
	return is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
};
$cic_section = static function ( string $html ): string {
	return preg_match( '#<section class="cic-prefooter".*?</section>#s', $html, $m ) ? $m[0] : '';
};

global $wpdb;
$t        = Tables::all();
$repo     = Service::repository();
$layout   = get_option( Layout::OPTION, '__absent__' );
$audit    = get_option( 'cic_location_audit', '__absent__' );
$test_ids = array();

// Independent reference: distinct effectively served ZIPs per eligible city, straight from SQL.
$cic_reference = static function () use ( $wpdb, $t ): array {
	return $wpdb->get_results(
		"SELECT c.id, c.city_name, COUNT(DISTINCT cz.zip) AS n FROM {$t['cities']} c JOIN {$t['city_zips']} cz ON cz.city_id = c.id JOIN {$t['zips']} z ON z.zip = cz.zip
		WHERE c.publish_status = 'published' AND c.index_status = 'index' AND c.dataset = 'production' AND cz.dataset = 'production' AND z.dataset = 'production' AND c.state_code = 'CA'
		AND z.service_status <> 'disabled' AND cz.service_override <> 'disabled'
		AND ( cz.service_override = 'enabled' OR ( cz.service_override = 'inherit' AND z.service_status = 'enabled' ) )
		GROUP BY c.id, c.city_name ORDER BY n DESC, c.city_name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
};
$cic_ranking = static fn() => array_map( static fn( $c ) => array( 'id' => (string) $c['id'], 'city_name' => $c['name'], 'n' => (string) $c['served_zips'] ), $repo->served_city_ranking( 1000 ) );

try {
	$cic_check( 'S0 the repository offers the ranking (DatabaseRepository)', $repo instanceof DatabaseRepository );

	/* 1. Ranking equals the independent distinct count, order included. */
	Tables::bump_cache();
	$ref  = $cic_reference();
	$rank = $cic_ranking();
	$cic_check( 'R1 ranking = DISTINCT effectively served ZIPs per city, sorted by count then name (' . count( $ref ) . ' cities)', $ref === $rank, array( 'ref' => array_slice( $ref, 0, 5 ), 'rank' => array_slice( $rank, 0, 5 ) ) );
	$excluded = $wpdb->get_col( "SELECT zip FROM {$t['zips']} WHERE service_status = 'disabled' AND dataset = 'production'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$enabled  = $wpdb->get_col( "SELECT zip FROM {$t['zips']} WHERE service_status = 'enabled' AND dataset = 'production' ORDER BY zip LIMIT 160" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$cic_check( 'S1 data available for the test (excluded and served ZIP records)', count( $excluded ) >= 1 && count( $enabled ) >= 150, array( count( $excluded ), count( $enabled ) ) );
	$top_before = $rank ? (int) $rank[0]['n'] : 0;

	/* 2. Temporary test cities. */
	$now  = current_time( 'mysql', true );
	$make = static function ( string $slug, string $name, string $publish, string $index, string $dataset ) use ( $wpdb, $t, $now, &$test_ids ): int {
		$wpdb->insert( $t['cities'], array( 'state_code' => 'CA', 'county_name' => 'Test County', 'county_slug' => 'test-county', 'city_name' => $name, 'city_slug' => $slug, 'name_search' => Tables::search_key( $name ), 'publish_status' => $publish, 'index_status' => $index, 'dataset' => $dataset, 'updated_at' => $now ) );
		$id         = (int) $wpdb->insert_id;
		$test_ids[] = $id;
		return $id;
	};
	$link = static function ( int $city, array $zips, string $override = 'inherit', string $dataset = 'production' ) use ( $wpdb, $t, $now ): void {
		foreach ( $zips as $zip ) {
			$wpdb->insert( $t['city_zips'], array( 'city_id' => $city, 'zip' => $zip, 'service_override' => $override, 'review_note' => 'cities-test', 'dataset' => $dataset, 'source_reference' => 'cities-test', 'updated_at' => $now ) );
		}
	};
	$top_n = $top_before + 5; // More served ZIPs than any real city, so it must rank first.
	$top   = $make( 'cic-test-top', 'Cictest Topcity', 'published', 'index', 'production' );
	$link( $top, array_slice( $enabled, 0, $top_n ) );
	$link( $top, $excluded ); // Globally disabled (Compton exclusions): must not count.
	$link( $top, array_slice( $enabled, $top_n, 2 ), 'disabled' ); // Disabled relationships: must not count.
	$link( $top, array_slice( $enabled, $top_n + 2, 2 ), 'manual_review' ); // Not served: must not count.
	$draft   = $make( 'cic-test-draft', 'Cictest Draft', 'draft', 'index', 'production' );
	$noindex = $make( 'cic-test-noindex', 'Cictest Noindex', 'published', 'noindex', 'production' );
	$fixture = $make( 'cic-test-fixture', 'Cictest Fixture', 'published', 'index', 'fixture' );
	$unserv  = $make( 'cic-test-unserved', 'Cictest Unserved', 'published', 'index', 'production' );
	foreach ( array( $draft, $noindex ) as $id ) {
		$link( $id, array_slice( $enabled, 0, $top_n + 10 ) );
	}
	$link( $fixture, array_slice( $enabled, 0, $top_n + 10 ), 'inherit', 'fixture' );
	$link( $unserv, $excluded );
	$link( $unserv, array_slice( $enabled, 0, 3 ), 'disabled' );

	/* 3. Cache: stale until invalidated; `wp cic locations flush-cache` refreshes it. */
	$stale = $cic_ranking();
	$cic_check( 'C1 results are cached: direct database inserts are not visible before a cache flush', ! in_array( (string) $top, array_column( $stale, 'id' ), true ) );
	( new \CIC\Locations\Cli() )->flush_cache( array(), array() );
	$rank = $cic_ranking();
	$ids  = array_column( $rank, 'id' );
	$cic_check( 'C2 after `flush-cache` the new city appears, first, with exactly its served ZIPs (' . $top_n . ')', ! empty( $rank ) && (string) $top === $rank[0]['id'] && (string) $top_n === $rank[0]['n'], $rank[0] ?? null );
	$cic_check( 'E1 excluded (Compton) ZIPs, disabled links and manual-review links did not inflate the count', (string) $top_n === ( $rank[0]['n'] ?? '' ) );
	$cic_check( 'E2 draft, noindex, fixture and unserved cities are excluded', ! array_intersect( array_map( 'strval', array( $draft, $noindex, $fixture, $unserv ) ), $ids ), array_intersect( array_map( 'strval', array( $draft, $noindex, $fixture, $unserv ) ), $ids ) );
	$cic_check( 'R2 ranking with test cities still equals the independent count', $cic_reference() === $rank );

	/* 4. An admin coverage change (existing write path) refreshes the results by itself. */
	CityStore::set_override( $top, $enabled[0], 'disabled', 'cities-test' );
	$after = $cic_ranking();
	$cic_check( 'C3 an admin coverage change (CityStore::set_override) refreshes the ranking automatically', (string) ( $top_n - 1 ) === ( $after[0]['n'] ?? '' ) && (string) $top === ( $after[0]['id'] ?? '' ), $after[0] ?? null );

	/* 5. Labels, canonical links and rendering. */
	$first = $repo->served_city_ranking( 1 )[0];
	$cic_check( 'L1 label "Cictest Topcity, CA" and canonical URL from the route helper', 'Cictest Topcity, CA' === $first['label'] && DatabaseRepository::url( 'california', 'cic-test-top' ) === $first['url'], array( $first['label'], $first['url'] ) );
	$labels_ok = true;
	foreach ( $repo->served_city_ranking( 20 ) as $c ) {
		$labels_ok = $labels_ok && preg_match( '/^[^,]+, CA$/', $c['label'] ) && ! preg_match( '/^[a-z0-9-]+, CA$/', $c['label'] ) && DatabaseRepository::url( $c['state_slug'], $c['slug'] ) === $c['url'] && null !== $repo->resolve_city( $c['state_slug'], $c['slug'] );
	}
	$cic_check( 'L2 every listed city: "Name, CA" label (never a slug), canonical URL that resolves to the city route', (bool) $labels_ok );

	$home = $cic_section( $cic_get( '/' ) );
	preg_match_all( '#<li><a href="([^"]+)">([^<]+)</a></li>#', $home, $m );
	$cic_check( 'H1 homepage: section above the footer, 20 links, first is the top-ranked test city', 20 === count( $m[0] ) && str_contains( $m[2][0] ?? '', 'Cictest Topcity, CA' ) && DatabaseRepository::url( 'california', 'cic-test-top' ) === html_entity_decode( $m[1][0] ?? '' ), array( count( $m[0] ), $m[2][0] ?? '' ) );
	$cic_check( 'H2 heading, semantic list, "View all service areas" → /service-areas/, no numbers shown', str_contains( $home, '<h2 class="cic-prefooter__title" id="cic-prefooter-title">Cities We Serve</h2>' ) && str_contains( $home, '<ul class="cic-prefooter__list">' ) && str_contains( $home, '/service-areas/">View all service areas' ) && ! preg_match( '/\d/', wp_strip_all_tags( preg_replace( '#href="[^"]*"#', '', $home ) ) ) );
	$cic_check( 'H3 the section sits immediately before the footer', (bool) preg_match( '#</section>\s*<footer class="cic-footer"#', $cic_get( '/about/' ) ) );
	$cic_check( 'H4 omitted on the reduced Get a Quote page; shown on an event, a treat, Service Areas and a city page', '' === $cic_section( $cic_get( '/get-a-quote/' ) ) && '' !== $cic_section( $cic_get( '/events/prom-ice-cream-catering/' ) ) && '' !== $cic_section( $cic_get( '/treats/dippin-dots-catering/' ) ) && '' !== $cic_section( $cic_get( '/service-areas/' ) ) && '' !== $cic_section( $cic_get( '/service-areas/california/pasadena-ice-cream-catering/' ) ) );

	/* 6. Admin settings. */
	$opt           = get_option( Layout::OPTION, array() );
	$opt           = is_array( $opt ) ? $opt : array();
	$opt['cities'] = array( 'enabled' => true, 'heading' => 'Where We Cater', 'count' => 5, 'link_label' => 'See every area' );
	update_option( Layout::OPTION, $opt );
	$five = $cic_section( $cic_get( '/' ) );
	$cic_check( 'A1 settings: heading, number of cities (5) and link label are applied', 5 === substr_count( $five, '<li>' ) && str_contains( $five, 'Where We Cater' ) && str_contains( $five, 'See every area' ) );
	$opt['cities']['enabled'] = false;
	update_option( Layout::OPTION, $opt );
	$cic_check( 'A2 settings: disabling removes the section', '' === $cic_section( $cic_get( '/' ) ) );
} catch ( \Throwable $e ) {
	$cic_check( 'run completed without an exception', false, $e->getMessage() );
} finally {
	if ( $test_ids ) {
		$in = implode( ',', array_map( 'intval', $test_ids ) );
		$wpdb->query( "DELETE FROM {$t['city_zips']} WHERE city_id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$t['cities']} WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	'__absent__' === $layout ? delete_option( Layout::OPTION ) : update_option( Layout::OPTION, $layout );
	'__absent__' === $audit ? delete_option( 'cic_location_audit' ) : update_option( 'cic_location_audit', $audit, false );
	Tables::bump_cache();
	$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['cities']} WHERE city_slug LIKE 'cic-test-%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$cic_check( 'Z1 test cities and links removed, settings restored', 0 === $left && get_option( Layout::OPTION, '__absent__' ) == $layout ); // phpcs:ignore Universal.Operators.StrictComparisonOperator -- array compare.
	WP_CLI::log( sprintf( 'Cities We Serve checks: %d/%d passed', count( array_filter( $cic_results ) ), count( $cic_results ) ) );
}
