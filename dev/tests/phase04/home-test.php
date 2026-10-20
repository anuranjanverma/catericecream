<?php
/**
 * Phase 04 server-side checks: quote context validation, coverage adapter, homepage data and
 * markup rules, seeding. Read-only apart from a seed re-run (which must change nothing).
 *
 * Usage: wp eval-file dev/tests/phase04/home-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Content\Seeder;
use CIC\Content\ViewModel;
use CIC\Locations\Service as Locations;
use CIC\Quote\Context;

global $cic4;
$cic4 = array( 'pass' => 0, 'fail' => 0 );

function cic4_check( string $label, bool $ok, $detail = '' ): void {
	global $cic4;
	$cic4[ $ok ? 'pass' : 'fail' ]++;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && '' !== $detail ? ' — ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' ) );
}

$tz       = new DateTimeZone( 'America/Los_Angeles' );
$today    = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
$tomorrow = ( new DateTimeImmutable( 'now', $tz ) )->modify( '+1 day' )->format( 'Y-m-d' );
$past     = ( new DateTimeImmutable( 'now', $tz ) )->modify( '-1 day' )->format( 'Y-m-d' );

WP_CLI::log( '== A. Quote context validation (non-personal, resolved against published records)' );
$c = Context::validate( array( 'event' => 'anniversary', 'treat' => 'dippin-dots,bogus,frozen-yogurt', 'location' => 'Pasadena', 'date' => $tomorrow, 'guests' => '80' ) );
cic4_check( 'A1 valid context kept; unknown treat key dropped; city name normalised to a key', array( 'event' => 'anniversary', 'treat' => 'dippin-dots,frozen-yogurt', 'location' => 'pasadena', 'date' => $tomorrow, 'guests' => 80 ) === $c, $c );
$c = Context::validate( array( 'event' => 'not-an-event', 'location' => '<script>', 'date' => $past, 'guests' => '0', 'name' => 'Jane', 'email' => 'a@b.test', 'phone' => '555' ) );
cic4_check( 'A2 invalid event/location/past date/zero guests rejected; personal fields never accepted', array() === $c, $c );
cic4_check( 'A3 today is valid (Los Angeles calendar date, no UTC shift); impossible dates rejected', $today === Context::date( $today ) && '' === Context::date( '2027-02-30' ) && '' === Context::date( '06/20/2027' ) );
cic4_check( 'A4 ZIP codes kept as 5 digits (leading zero preserved); "other" occasion allowed', '01234' === Context::location( '01234' ) && '' === Context::location( '1234' ) && 'other' === ( Context::validate( array( 'event' => 'other' ) )['event'] ?? '' ) );
wp_update_post( array( 'ID' => CIC\Content\Catalog::find_by_key( 'cic_event', 'wedding' ), 'post_status' => 'draft' ) );
$draft = Context::validate( array( 'event' => 'wedding' ) );
wp_update_post( array( 'ID' => CIC\Content\Catalog::find_by_key( 'cic_event', 'wedding' ), 'post_status' => 'publish' ) );
cic4_check( 'A5 a draft event key is not a valid selection', array() === $draft );
$url = Context::url( array( 'event' => 'birthday', 'date' => $tomorrow, 'name' => 'Jane' ) );
cic4_check( 'A6 quote URL carries only validated context', home_url( '/get-a-quote/?event=birthday&date=' . $tomorrow ) === $url, $url );

WP_CLI::log( '== B. Coverage adapter (owner city code pending; Phase 05 provisional repository)' );
$repo = Locations::repository();
cic4_check( 'B1 coverage goes through the LocationRepository adapter (provisional database repository once the Phase 05 tables exist)', $repo instanceof CIC\Locations\LocationRepository && ( CIC\Locations\Tables::ready() ? $repo instanceof CIC\Locations\DatabaseRepository : $repo instanceof CIC\Locations\PendingRepository ) );
$req = new WP_REST_Request( 'POST', '/cic/v1/coverage' );
$req->set_param( 'location', '94105' );
$res = rest_do_request( $req )->get_data();
cic4_check( 'B2 a valid ZIP without reviewed data → "unknown" with a confirm-with-quote message (never "covered")', 'unknown' === $res['status'] && str_contains( $res['message'], 'confirm' ) );
$req->set_param( 'location', '<b>x</b>' );
cic4_check( 'B3 malformed input → "invalid"', 'invalid' === rest_do_request( $req )->get_data()['status'] );
$req2 = new WP_REST_Request( 'GET', '/cic/v1/coverage' );
cic4_check( 'B4 coverage is POST-only', 404 === rest_do_request( $req2 )->get_status() );

WP_CLI::log( '== C. Homepage data' );
$front = (int) get_option( 'page_on_front' );
$vm    = ViewModel::home( $front );
cic4_check( 'C1 static front page is the seeded Home page', 'page' === get_option( 'show_on_front' ) && 'home' === get_post_meta( $front, 'cic_key', true ) );
cic4_check( 'C2 approved section order', array( 'hero', 'values', 'treats', 'events', 'process', 'faq', 'coverage', 'cta' ) === wp_list_pluck( $vm['sections'], 'key' ), wp_list_pluck( $vm['sections'], 'key' ) );
cic4_check( 'C3 events include Anniversary (correction) in menu order', array( 'birthday', 'wedding', 'corporate', 'anniversary' ) === wp_list_pluck( $vm['events']['items'], 'key' ) );
cic4_check( 'C4 hero search: Location, Event type, Date; event options are published events + Other', 'Location' === $vm['search']['location_label'] && 'Event type' === $vm['search']['event_label'] && 'Date' === $vm['search']['date_label'] && array( 'birthday', 'wedding', 'corporate', 'anniversary', 'other' ) === array_keys( $vm['search']['events'] ) );
cic4_check( 'C5 all homepage photos resolve to imported attachments', ! in_array( 0, $vm['hero']['images'], true ) && $vm['treats']['more_image'] && ! in_array( 0, wp_list_pluck( $vm['events']['items'], 'image' ), true ) && ! in_array( 0, wp_list_pluck( $vm['treats']['items'], 'image' ), true ) );
$cic4_cities = array_merge( ...wp_list_pluck( $vm['coverage']['counties'], 'cities' ) );
cic4_check( 'C6 counties in approved order; listed cities come only from published repository records and link to their city pages', array( 'Los Angeles County', 'Ventura County', 'Santa Barbara County' ) === wp_list_pluck( $vm['coverage']['counties'], 'name' ) && ! array_filter( $cic4_cities, static fn( $c ) => ! str_contains( (string) $c['url'], '/service-areas/' ) ) && ! in_array( 'Compton', wp_list_pluck( $cic4_cities, 'name' ), true ) );

WP_CLI::log( '== D. Rendered homepage rules' );
$r    = wp_remote_get( home_url( '/' ), array( 'sslverify' => false, 'timeout' => 30 ) );
$html = (string) wp_remote_retrieve_body( $r );
$text = wp_strip_all_tags( preg_replace( '#<(script|style)\b.*?</\1>#s', '', $html ) );
cic4_check( 'D1 no minimum-order or price copy anywhere on the homepage', ! preg_match( '/minimum|\$\s?\d/i', $text ) );
cic4_check( 'D2 exactly one h1; each section has an h2', 1 === substr_count( $html, '<h1' ) && substr_count( $html, '<h2' ) >= 6 );
preg_match_all( '/<img\b[^>]*>/', $html, $imgs );
$content_imgs = array_filter( $imgs[0], static fn( $i ) => str_contains( $i, 'cic-photo' ) );
$all_ok       = ! array_filter( $content_imgs, static fn( $i ) => ! preg_match( '/\swidth="\d+"/', $i ) || ! preg_match( '/\sheight="\d+"/', $i ) || ! str_contains( $i, 'srcset=' ) || ! str_contains( $i, 'sizes=' ) );
cic4_check( 'D3 every content image has width, height, srcset and sizes', count( $content_imgs ) >= 12 && $all_ok, count( $content_imgs ) );
$high = array_values( array_filter( $imgs[0], static fn( $i ) => str_contains( $i, 'fetchpriority="high"' ) ) );
cic4_check( 'D4 only the hero photo has high fetch priority; lower images lazy-load', 1 === count( $high ) && str_contains( $high[0], 'service-garden' ) && str_contains( $high[0], 'loading="eager"' ) && 9 <= count( array_filter( $content_imgs, static fn( $i ) => str_contains( $i, 'loading="lazy"' ) ) ) );
preg_match_all( '/href="(tel:[^"]+)"/', $html, $tels );
cic4_check( 'D5 every tel: link uses the global contact number', $tels[1] && ! array_diff( array_unique( $tels[1] ), array( CIC\Site\Contact::get()['phone_href'] ) ), array_unique( $tels[1] ) );
cic4_check( 'D6 How It Works anchor exists and is not marked as the current page', str_contains( $html, 'id="how-it-works"' ) && ! preg_match( '/current-menu-item[^>]*>\s*<a[^>]*#how-it-works/', $html ) );
cic4_check( 'D7 search form posts nowhere personal: GET to the quote route with location/event/date only', (bool) preg_match( '#<form class="cic-search" method="get" action="' . preg_quote( home_url( '/get-a-quote/' ), '#' ) . '"#', $html ) && 3 === preg_match_all( '/data-cic-context="(location|event|date)"/', substr( $html, strpos( $html, 'cic-search"' ), 6000 ) ) );

WP_CLI::log( '== E. Seeding' );
$result = ( new Seeder() )->run( false );
cic4_check( 'E1 seed re-run changes nothing', 0 === $result['report']['created'] && 0 === $result['report']['filled'] && 0 === $result['report']['pairs'], $result['report'] );
cic4_check( 'E2 57 masters imported in total (slice, homepage, county, Phase 07 detail pages and Phase 08 events hero), each once', 57 === count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_cic_asset_id', 'numberposts' => -1, 'fields' => 'ids' ) ) ) ); // phpcs:ignore

WP_CLI::log( sprintf( '== RESULT: %d passed, %d failed', $cic4['pass'], $cic4['fail'] ) );
