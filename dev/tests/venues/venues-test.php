<?php
/**
 * "Popular Event Venues" checks (2026-11-21). Adds clearly named temporary venues ("CIC Test Venue …")
 * to two served cities and one unserved city, checks the store rules, the city/event lists and the
 * rendered sections, then deletes every test venue in `finally`, restores the audit option and bumps
 * the location cache (the cache version only goes up). Existing venues are never changed.
 *
 * Usage: wp eval-file dev/tests/venues/venues-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Content\Catalog;
use CIC\Locations\DatabaseRepository;
use CIC\Locations\Service;
use CIC\Locations\Tables;
use CIC\Locations\VenueStore;

$cic_results = array();
$cic_check   = static function ( string $label, bool $ok, $detail = null ) use ( &$cic_results ): void {
	$cic_results[] = $ok;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && null !== $detail ? ' — ' . substr( wp_json_encode( $detail ), 0, 600 ) : '' ) );
};
$cic_get = static function ( string $url ): string {
	$r = wp_remote_get( $url, array( 'sslverify' => false, 'timeout' => 30, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
	return is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
};
$cic_section = static function ( string $html ): string {
	return preg_match( '#<section class="[^"]*\bcic-venues\b[^"]*"[^>]*>.*?</section>#s', $html, $m ) ? $m[0] : '';
};

global $wpdb;
$repo     = Service::repository();
$audit    = get_option( 'cic_location_audit', '__absent__' );
$prefix   = 'CIC Test Venue';
$test_ids = array();
$add      = static function ( array $data ) use ( &$test_ids ) {
	$id = VenueStore::save( $data, 0, 'test' );
	if ( ! is_wp_error( $id ) ) {
		$test_ids[] = $id;
	}
	return $id;
};

try {
	$cic_check( 'V0 schema v7 and the venue table exist', (int) get_option( 'cic_core_schema_version' ) >= 7 && VenueStore::exists() );
	$served = $repo instanceof DatabaseRepository ? $repo->served_city_ranking( PHP_INT_MAX ) : array();
	$cic_check( 'V0 at least two served cities to test with', count( $served ) >= 2, count( $served ) );
	if ( count( $served ) < 2 ) {
		throw new \RuntimeException( 'not enough served cities' );
	}
	$city_a    = $served[0];
	$city_b    = $served[1];
	$served_id = array_map( static fn( $c ) => (int) $c['id'], $served );
	$cities_t  = Tables::name( 'cities' );
	$in        = implode( ',', $served_id );
	$unserved  = (int) $wpdb->get_var( "SELECT id FROM {$cities_t} WHERE id NOT IN ({$in}) ORDER BY id LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	/* 1. Validation. */
	$cic_check( 'S1 an empty name is rejected', is_wp_error( VenueStore::save( array( 'city_id' => $city_a['id'], 'name' => '  ' ) ) ) );
	$cic_check( 'S2 an unknown city is rejected', is_wp_error( VenueStore::save( array( 'city_id' => 999999999, 'name' => $prefix . ' X' ) ) ) );
	$cic_check( 'S3 an unknown status is rejected', is_wp_error( VenueStore::save( array( 'city_id' => $city_a['id'], 'name' => $prefix . ' X', 'status' => 'live' ) ) ) );
	$first = $add( array( 'city_id' => $city_a['id'], 'name' => $prefix . ' A01 <b>Hall</b> & Garden', 'occasions' => array( 'wedding', 'not-an-event' ), 'sort_order' => -9000, 'status' => 'approved' ) );
	$row   = is_wp_error( $first ) ? null : VenueStore::get( $first );
	$cic_check( 'S4 tags are stripped, unknown occasions dropped, approval is time-stamped', $row && $prefix . ' A01 Hall & Garden' === $row['name'] && array( 'wedding' ) === $row['occasions'] && null !== $row['reviewed_at'], $row );
	$cic_check( 'S5 a duplicate name in the same city is rejected (case/punctuation ignored)', is_wp_error( VenueStore::save( array( 'city_id' => $city_a['id'], 'name' => strtoupper( $prefix ) . ' a01 hall garden' ) ) ) );
	$before = Tables::cache_version();
	VenueStore::set_status( array( $first ), 'approved' );
	$cic_check( 'S6 every write raises the location cache version', Tables::cache_version() > $before );

	/* 2. City list: approved first by order, max 12; hidden never; drafts only when allowed. */
	for ( $i = 2; $i <= 14; $i++ ) {
		$add( array( 'city_id' => $city_a['id'], 'name' => sprintf( '%s A%02d', $prefix, $i ), 'occasions' => array( 'wedding' ), 'sort_order' => -9000 + $i, 'status' => 'approved' ) );
	}
	$hidden = $add( array( 'city_id' => $city_a['id'], 'name' => $prefix . ' A00 Hidden', 'occasions' => array( 'wedding' ), 'sort_order' => -9999, 'status' => 'hidden' ) );
	$draft  = $add( array( 'city_id' => $city_a['id'], 'name' => $prefix . ' A00 Draft', 'occasions' => array( 'wedding' ), 'sort_order' => -9998, 'status' => 'draft' ) );
	$list   = VenueStore::for_city( (int) $city_a['id'] );
	$cic_check( 'C1 a city lists at most 12 venues', 12 === count( $list ), count( $list ) );
	$cic_check( 'C2 hidden venues are never listed', ! in_array( $prefix . ' A00 Hidden', $list, true ) );
	add_filter( 'cic_venue_drafts_visible', '__return_false' );
	Tables::bump_cache();
	$live = VenueStore::for_city( (int) $city_a['id'] );
	$cic_check( 'C3 live rule: drafts are not listed, approved venues follow their order', ! in_array( $prefix . ' A00 Draft', $live, true ) && $prefix . ' A01 Hall & Garden' === $live[0] && sprintf( '%s A%02d', $prefix, 12 ) === $live[11], $live );
	remove_filter( 'cic_venue_drafts_visible', '__return_false' );
	add_filter( 'cic_venue_drafts_visible', '__return_true' );
	Tables::bump_cache();
	$cic_check( 'C4 local rule: drafts are listed for layout review', in_array( $prefix . ' A00 Draft', VenueStore::for_city( (int) $city_a['id'] ), true ) );
	remove_filter( 'cic_venue_drafts_visible', '__return_true' );

	/* 3. Event list: occasion only, served cities only, round-robin by city rank, max 32. */
	add_filter( 'cic_venue_drafts_visible', '__return_false' );
	$add( array( 'city_id' => $city_b['id'], 'name' => $prefix . ' B01', 'occasions' => array( 'wedding', 'corporate' ), 'sort_order' => -9000, 'status' => 'approved' ) );
	$add( array( 'city_id' => $city_b['id'], 'name' => $prefix . ' B02 Birthday only', 'occasions' => array( 'birthday' ), 'sort_order' => -8999, 'status' => 'approved' ) );
	if ( $unserved ) {
		$add( array( 'city_id' => $unserved, 'name' => $prefix . ' U01 Unserved', 'occasions' => array( 'wedding' ), 'sort_order' => -9999, 'status' => 'approved' ) );
	}
	Tables::bump_cache();
	$wed = VenueStore::for_event( 'wedding' );
	$cic_check( 'E1 an event lists at most 32 venues', count( $wed ) <= 32 && count( $wed ) >= 2, count( $wed ) );
	$cic_check( 'E2 round-robin: the top served city\'s first venue, then the next city\'s first venue', $prefix . ' A01 Hall & Garden' === $wed[0] && $prefix . ' B01' === $wed[1], array_slice( $wed, 0, 3 ) );
	$cic_check( 'E3 venues for other occasions are not listed', ! in_array( $prefix . ' B02 Birthday only', $wed, true ) );
	$cic_check( 'E4 venues in cities we do not serve are not listed', ! in_array( $prefix . ' U01 Unserved', $wed, true ) );
	$cic_check( 'E5 unknown occasion → empty list', array() === VenueStore::for_event( 'no-such-event' ) );
	remove_filter( 'cic_venue_drafts_visible', '__return_false' );

	/* 4. Rendered pages (web server; local environment). */
	Tables::bump_cache();
	$city_url  = DatabaseRepository::url( $city_a['state_slug'], $city_a['slug'] );
	$city_html = $cic_get( $city_url );
	$sec       = $cic_section( $city_html );
	$pos_ven   = strpos( $city_html, 'cic-venues' );
	$pos_near  = strpos( $city_html, 'cic-city-nearby' );
	$pos_faq   = strpos( $city_html, 'cic-city-faq' );
	$cic_check( 'P1 city page shows "Popular Event Venues" between the FAQ and "Explore nearby areas"', '' !== $sec && str_contains( $sec, '<h2' ) && str_contains( $sec, 'Popular Event Venues' ) && false !== $pos_faq && false !== $pos_near && $pos_faq < $pos_ven && $pos_ven < $pos_near, $city_url );
	$cic_check( 'P2 city section: 12 names, plain text (no links), escaped', 12 === substr_count( $sec, '<li class="cic-venues__item">' ) && ! str_contains( $sec, '<a ' ) && str_contains( $sec, 'A01 Hall &amp; Garden' ) && ! str_contains( $sec, '<b>' ) );
	$event_id  = Catalog::find_by_key( 'cic_event', 'wedding' );
	$ev_html   = $event_id ? $cic_get( (string) get_permalink( $event_id ) ) : '';
	$ev_sec    = $cic_section( $ev_html );
	$cic_check( 'P3 Wedding page shows the section after the approved sections, with the test venues', '' !== $ev_sec && str_contains( $ev_sec, 'Popular Event Venues' ) && str_contains( $ev_sec, 'B01' ) && strpos( $ev_html, 'cic-venues' ) > strpos( $ev_html, 'cic-qpanel' ) );
	$cic_check( 'P4 Wedding section lists at most 32 names, none from other occasions or unserved cities', substr_count( $ev_sec, '<li class="cic-venues__item">' ) <= 32 && ! str_contains( $ev_sec, 'Birthday only' ) && ! str_contains( $ev_sec, 'U01 Unserved' ) );
	$cic_check( 'P5 one h1 per page (section adds only an h2)', 1 === substr_count( $city_html, '<h1' ) && 1 === substr_count( $ev_html, '<h1' ) );
	$empty_id = (int) $wpdb->get_var( 'SELECT c.id FROM ' . $cities_t . ' c LEFT JOIN ' . VenueStore::table() . " v ON v.city_id = c.id AND v.status <> 'hidden' WHERE c.publish_status = 'published' AND c.dataset = 'production' AND v.id IS NULL ORDER BY c.id LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( $empty_id ) {
		$empty = $repo->city_by_id( $empty_id );
		$cic_check( 'P6 a city without venues has no venue section', $empty && '' === $cic_section( $cic_get( DatabaseRepository::url( $empty['state_slug'], $empty['slug'] ) ) ) );
	}
} catch ( \Throwable $e ) {
	$cic_check( 'run completed without an exception', false, $e->getMessage() );
} finally {
	remove_all_filters( 'cic_venue_drafts_visible' );
	if ( $test_ids ) {
		VenueStore::delete( $test_ids );
	}
	$left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . VenueStore::table() . ' WHERE name LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	'__absent__' === $audit ? delete_option( 'cic_location_audit' ) : update_option( 'cic_location_audit', $audit, false );
	Tables::bump_cache();
	$cic_check( 'Z1 test venues removed, audit restored', 0 === $left );
	WP_CLI::log( sprintf( 'Popular Event Venues checks: %d/%d passed', count( array_filter( $cic_results ) ), count( $cic_results ) ) );
}
