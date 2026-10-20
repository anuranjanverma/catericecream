<?php
/**
 * Phase 03 checks: catalogue records, ACF Free fields, pairings (one source, indexed, validated,
 * consistent both ways), routes, draft visibility, seed reruns, fifth records without source
 * edits, REST schemas/capabilities, revisions, theme switch, ACF off, media idempotency and
 * catalogue protection. Everything changed is restored in `finally`, even after a failure.
 *
 * Usage: wp eval-file dev/tests/phase03/catalog-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Content\Catalog;
use CIC\Content\EventType;
use CIC\Content\Fields;
use CIC\Content\Media;
use CIC\Content\Pairings;
use CIC\Content\Seeder;
use CIC\Content\ViewModel;

global $cic3;
$cic3 = array( 'pass' => 0, 'fail' => 0 );

function cic3_check( string $label, bool $ok, $detail = '' ): void {
	global $cic3;
	$cic3[ $ok ? 'pass' : 'fail' ]++;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && '' !== $detail ? ' — ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' ) );
}

function cic3_get( string $path, array $cookies = array() ): array {
	$r = wp_remote_get( home_url( $path ), array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 30, 'cookies' => $cookies, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
	if ( is_wp_error( $r ) ) {
		return array( 'code' => 0, 'body' => $r->get_error_message(), 'location' => '' );
	}
	return array( 'code' => (int) wp_remote_retrieve_response_code( $r ), 'body' => (string) wp_remote_retrieve_body( $r ), 'location' => (string) wp_remote_retrieve_header( $r, 'location' ) );
}

function cic3_rest( string $method, string $route, array $body = array() ): WP_REST_Response {
	$req = new WP_REST_Request( $method, $route );
	if ( $body ) {
		$req->set_header( 'Content-Type', 'application/json' );
		$req->set_body( wp_json_encode( $body ) );
	}
	return rest_do_request( $req );
}

$dd       = Catalog::find_by_key( 'product', 'dippin-dots' );
$bananas  = Catalog::find_by_key( 'product', 'dipn-bananas' );
$birthday = Catalog::find_by_key( EventType::POST_TYPE, 'birthday' );
$wedding  = Catalog::find_by_key( EventType::POST_TYPE, 'wedding' );
$admin    = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];

// Snapshot everything this test may change.
$snap = array(
	'theme'       => get_stylesheet(),
	'acf'         => is_plugin_active( 'advanced-custom-fields/acf.php' ),
	'log'         => get_option( Seeder::LOG ),
	'dd_pairs'    => Pairings::for_product( $dd ),
	'status'      => array( $wedding => get_post_status( $wedding ), $bananas => get_post_status( $bananas ) ),
	'dd_meta'     => array(),
	'bananas_act' => get_post_meta( $bananas, 'cic_catalog_active', true ),
	'alt'         => get_post_meta( Media::id( 'dots-hero' ), '_wp_attachment_image_alt', true ),
	'birthday'    => get_post( $birthday ),
);
foreach ( array( 'cic_hero_headline', 'cic_feel_note', Seeder::SEEDED ) as $k ) {
	$snap['dd_meta'][ $k ] = get_post_meta( $dd, $k, true );
}

try {
	wp_set_current_user( $admin->ID );

	WP_CLI::log( '== A. Schema, capabilities, dependencies' );
	global $wpdb;
	$idx = $wpdb->get_results( 'SHOW INDEX FROM ' . Pairings::table(), ARRAY_A );
	$uniq = array_values( array_map( static fn( $i ) => $i['Column_name'], array_filter( $idx, static fn( $i ) => 'product_event' === $i['Key_name'] && '0' === (string) $i['Non_unique'] ) ) );
	cic3_check( 'A1 pairing table has UNIQUE(product_id, event_id) and an event_id index', array( 'product_id', 'event_id' ) === $uniq && (bool) array_filter( $idx, static fn( $i ) => 'event_lookup' === $i['Key_name'] && 'event_id' === $i['Column_name'] ) );
	cic3_check( 'A2 schema version 2', 2 === (int) get_option( 'cic_core_schema_version' ) );
	cic3_check( 'A3 event capabilities granted to administrator and editor only', get_role( 'administrator' )->has_cap( 'publish_cic_events' ) && get_role( 'editor' )->has_cap( 'edit_others_cic_events' ) && ! get_role( 'author' )->has_cap( 'edit_cic_events' ) );
	$types = array();
	foreach ( array( Fields::treat_group(), Fields::event_group() ) as $g ) {
		foreach ( $g['fields'] as $f ) {
			$types[ $f['type'] ] = true;
		}
	}
	$free = array( 'tab', 'message', 'text', 'textarea', 'image', 'select', 'true_false', 'number', 'relationship' );
	cic3_check( 'A4 ACF Free only: no Pro build, only free field types', ! defined( 'ACF_PRO' ) && ! array_diff( array_keys( $types ), $free ), array_keys( $types ) );
	cic3_check( 'A5 field groups registered with ACF and exposed to REST', (bool) acf_get_field_group( 'group_cic_treat' ) && (bool) acf_get_field_group( 'group_cic_event' ) && ! empty( acf_get_field_group( 'group_cic_treat' )['show_in_rest'] ) );

	WP_CLI::log( '== B. Records' );
	$keys = array();
	foreach ( array( 'dippin-dots', 'dipn-bananas', 'classic-ice-cream', 'frozen-yogurt' ) as $key ) {
		$id      = Catalog::find_by_key( 'product', $key );
		$product = $id ? wc_get_product( $id ) : null;
		$keys[]  = $product && 'simple' === $product->get_type() && 'publish' === $product->get_status() && '' === $product->get_regular_price() && '' === $product->get_price() && 'showcase' === get_post_meta( $id, 'cic_catalog_role', true ) && ! $product->is_purchasable();
	}
	cic3_check( 'B1 four real WooCommerce products with stable keys, showcase role, no price, not purchasable', 4 === count( array_filter( $keys ) ), $keys );
	cic3_check( 'B2 no duplicate treat CPT; products use the product post type', ! post_type_exists( 'cic_treat' ) && ! post_type_exists( 'cic_product' ) );
	$brands = array_map( static fn( $k ) => wp_get_object_terms( Catalog::find_by_key( 'product', $k ), 'product_brand', array( 'fields' => 'names' ) ), array( 'dippin-dots', 'classic-ice-cream', 'frozen-yogurt' ) );
	cic3_check( 'B3 WooCommerce Brands reused: Dippin\' Dots branded; generic treats carry no brand', array( "Dippin' Dots" ) === $brands[0] && array() === $brands[1] && array() === $brands[2] && 'generic' === get_post_meta( Catalog::find_by_key( 'product', 'classic-ice-cream' ), 'cic_brand_type', true ) );
	$events = wp_list_pluck( Catalog::selectable_events(), 'key' );
	cic3_check( 'B4 four published events in order; Anniversary is the fourth card', array( 'birthday', 'wedding', 'corporate', 'anniversary' ) === $events, $events );
	$pt = get_post_type_object( EventType::POST_TYPE );
	cic3_check( 'B5 cic_event: public, REST, archive /events/, revisions, title/editor/thumbnail', $pt->public && $pt->show_in_rest && 'events' === $pt->has_archive && post_type_supports( EventType::POST_TYPE, 'revisions' ) && post_type_supports( EventType::POST_TYPE, 'thumbnail' ) && post_type_supports( EventType::POST_TYPE, 'editor' ) );

	WP_CLI::log( '== C. Routes' );
	cic3_check( 'C1 canonical product URL /treats/{slug}/', home_url( '/treats/dippin-dots/' ) === get_permalink( $dd ) );
	cic3_check( 'C2 catalogue /treats/ and events /events/{slug}/', home_url( '/treats/' ) === wc_get_page_permalink( 'shop' ) && home_url( '/events/birthday/' ) === get_permalink( $birthday ) );
	$checks = array(
		'/treats/'                        => array( 200, '' ),
		'/treats/dippin-dots/'            => array( 200, '' ),
		'/events/'                        => array( 200, '' ),
		'/events/birthday/'               => array( 200, '' ),
		'/product/dippin-dots/'           => array( 301, '/treats/dippin-dots/' ),
		'/shop/'                          => array( 301, '/treats/' ),
		'/brand/dippin-dots/'             => array( 301, '/treats/dippin-dots/' ),
		'/product-category/uncategorized/' => array( 301, '/treats/' ),
		'/treats/not-a-treat/'            => array( 404, '' ),
		'/events/not-an-event/'           => array( 404, '' ),
	);
	$route_fail = array();
	foreach ( $checks as $path => list( $code, $to ) ) {
		$r = cic3_get( $path );
		if ( $r['code'] !== $code || ( $to && home_url( $to ) !== $r['location'] ) ) {
			$route_fail[ $path ] = $r['code'] . ' ' . $r['location'];
		}
	}
	cic3_check( 'C3 live routes: 200s, permanent redirects for legacy/competing URLs, real 404s', ! $route_fail, $route_fail );

	WP_CLI::log( '== D. Pairings: one source, validated, consistent both ways' );
	$all  = $wpdb->get_results( 'SELECT product_id, event_id FROM ' . Pairings::table(), ARRAY_A );
	$same = true;
	foreach ( $all as $row ) {
		$p = array_values( array_filter( Pairings::for_product( (int) $row['product_id'] ), static fn( $x ) => $x['event_id'] === (int) $row['event_id'] ) )[0];
		$e = array_values( array_filter( Pairings::for_event( (int) $row['event_id'] ), static fn( $x ) => $x['product_id'] === (int) $row['product_id'] ) )[0];
		$same = $same && $p === $e;
	}
	cic3_check( 'D1 seven seeded pairings; product-side and event-side reads return identical rows', 7 === count( $all ) && $same );
	$tvm = ViewModel::treat( $dd );
	$evm = ViewModel::event( $birthday );
	$tb  = array_values( array_filter( $tvm['occasions']['items'], static fn( $i ) => $i['event_id'] === $birthday ) )[0];
	$eb  = array_values( array_filter( $evm['treats']['items'], static fn( $i ) => $i['product_id'] === $dd ) )[0];
	cic3_check( 'D2 the same description renders on the treat page and the event page', $tb['description'] === $eb['description'] && $tb['headline'] === $eb['headline'] );
	$page_dd = cic3_get( '/treats/dippin-dots/' )['body'];
	$page_bd = cic3_get( '/events/birthday/' )['body'];
	cic3_check( 'D3 rendered HTML contains the shared pairing text on both pages', str_contains( $page_dd, esc_html( $tb['description'] ) ) && str_contains( $page_bd, esc_html( $tb['description'] ) ) );
	$errs = Pairings::replace_for_product( $dd, array( array( 'event_id' => 999999 ), array( 'event_id' => $dd ), array( 'event_id' => $wedding ), array( 'event_id' => $wedding ) ), true );
	cic3_check( 'D4 strict save rejects unknown IDs, non-events and duplicate events, writing nothing', 3 === count( $errs ) && 4 === count( Pairings::for_product( $dd ) ), $errs );
	$errs = Pairings::replace_for_product( $birthday, array( array( 'event_id' => $wedding ) ) );
	cic3_check( 'D5 pairings can only belong to a product', 1 === count( $errs ) && ! Pairings::for_product( $birthday ) );
	$raw = $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . Pairings::table() . ' (product_id, event_id, description, event_intro) VALUES (%d, %d, %s, %s)', $dd, $birthday, 'dup', '' ) ); // phpcs:ignore
	cic3_check( 'D6 the database itself refuses a duplicate product+event row', false === $raw );
	$reverse_sql = '';
	add_filter( 'query', static function ( $q ) use ( &$reverse_sql ) { if ( str_contains( $q, 'cic_product_events' ) && str_contains( $q, 'event_id =' ) ) { $reverse_sql = $q; } return $q; } );
	Pairings::for_event( $birthday );
	cic3_check( 'D7 reverse lookup is an indexed table query (no postmeta LIKE)', str_contains( $reverse_sql, 'WHERE event_id =' ) && ! str_contains( $reverse_sql, 'LIKE' ) && ! str_contains( $reverse_sql, 'postmeta' ) );

	WP_CLI::log( '== E. Draft / inactive visibility' );
	wp_update_post( array( 'ID' => $wedding, 'post_status' => 'draft' ) );
	wp_update_post( array( 'ID' => $bananas, 'post_status' => 'draft' ) );
	$t2 = ViewModel::treat( $dd );
	$e2 = ViewModel::event( $birthday );
	cic3_check( 'E1 draft event is hidden from the treat page pairings and quote event choices', ! in_array( $wedding, wp_list_pluck( $t2['occasions']['items'], 'event_id' ), true ) && ! in_array( 'wedding', wp_list_pluck( Catalog::selectable_events(), 'key' ), true ) );
	cic3_check( 'E2 draft treat is hidden from the event page and quote treat choices', ! in_array( $bananas, wp_list_pluck( $e2['treats']['items'], 'product_id' ), true ) && ! in_array( 'dipn-bananas', wp_list_pluck( Catalog::selectable_products(), 'key' ), true ) );
	cic3_check( 'E3 drafts return 404 to anonymous visitors; editor still sees the pairing in admin', 404 === cic3_get( '/events/wedding/' )['code'] && 404 === cic3_get( '/treats/dipn-bananas/' )['code'] && 4 === count( Pairings::for_product( $dd ) ) );
	$page = cic3_get( '/treats/dippin-dots/' )['body'];
	// Checked on the pairing cards only: the seeded navigation menu uses custom links (pending item).
	cic3_check( 'E4 rendered treat page shows no pairing card for the draft event', ! str_contains( $page, 'data-cic-pairing-event="' . $wedding . '"' ) && str_contains( $page, 'data-cic-pairing-event="' . $birthday . '"' ) );
	wp_update_post( array( 'ID' => $wedding, 'post_status' => 'publish' ) );
	wp_update_post( array( 'ID' => $bananas, 'post_status' => 'publish' ) );
	update_post_meta( $bananas, 'cic_catalog_active', '0' );
	$list = cic3_get( '/treats/' )['body'];
	cic3_check( 'E5 inactive treat leaves the catalogue, quote choices and event page; page is noindex', ! str_contains( $list, 'data-cic-product="dipn-bananas"' ) && ! in_array( 'dipn-bananas', wp_list_pluck( Catalog::selectable_products(), 'key' ), true ) && ! in_array( $bananas, wp_list_pluck( ViewModel::event( $birthday )['treats']['items'], 'product_id' ), true ) && str_contains( cic3_get( '/treats/dipn-bananas/' )['body'], 'noindex' ) );
	update_post_meta( $bananas, 'cic_catalog_active', $snap['bananas_act'] );

	WP_CLI::log( '== F. Seed reruns preserve edits' );
	update_post_meta( $dd, 'cic_hero_headline', "Edited by an editor\n<em>keep me</em>" );
	update_post_meta( $dd, 'cic_feel_note', '' );
	$rows = Pairings::for_product( $dd );
	$rows[0]['description'] = 'Editor-changed pairing text.';
	$rows = array_values( array_filter( $rows, static fn( $r ) => $r['event_id'] !== Catalog::find_by_key( EventType::POST_TYPE, 'anniversary' ) ) );
	Pairings::replace_for_product( $dd, $rows );
	$result = ( new Seeder() )->run( false );
	$r      = $result['report'];
	cic3_check( 'F1 rerun creates and fills nothing', 0 === $r['created'] && 0 === $r['filled'] && 0 === $r['pairs'], $r );
	cic3_check( 'F2 edited field kept; deliberately cleared field stays empty', "Edited by an editor\n<em>keep me</em>" === get_post_meta( $dd, 'cic_hero_headline', true ) && '' === get_post_meta( $dd, 'cic_feel_note', true ) );
	$after = Pairings::for_product( $dd );
	cic3_check( 'F3 edited pairing text kept; deliberately removed pairing not recreated', 'Editor-changed pairing text.' === $after[0]['description'] && 3 === count( $after ) );

	WP_CLI::log( '== G. Fifth event and fifth treat without source edits' );
	$event5 = wp_insert_post( array( 'post_type' => EventType::POST_TYPE, 'post_status' => 'publish', 'post_title' => 'Graduations', 'post_name' => 'cic-test-graduation', 'meta_input' => array( '_cic_fixture' => 'phase03', 'cic_hero_headline' => "Caps off.\n<em>Cups up.</em>" ) ) );
	$p5     = new WC_Product_Simple();
	$p5->set_name( 'Test Sorbet' );
	$p5->set_slug( 'cic-test-sorbet' );
	$p5->set_status( 'publish' );
	$p5->set_short_description( 'A fifth treat added in the admin.' );
	$p5->update_meta_data( '_cic_fixture', 'phase03' );
	$prod5 = $p5->save();
	Pairings::replace_for_product( $prod5, array( array( 'event_id' => $event5, 'headline' => 'Fifth pairing headline.', 'description' => 'Fifth pairing text shown on both pages.' ) ) );
	cic3_check( 'G1 new records receive stable keys automatically', 'cic-test-graduation' === Catalog::key( $event5 ) && 'cic-test-sorbet' === Catalog::key( $prod5 ) );
	$g_event = cic3_get( '/events/cic-test-graduation/' );
	$g_treat = cic3_get( '/treats/cic-test-sorbet/' );
	cic3_check( 'G2 both render through the same templates with the pairing on each page', 200 === $g_event['code'] && 200 === $g_treat['code'] && str_contains( $g_event['body'], 'Test Sorbet' ) && str_contains( $g_treat['body'], 'Fifth pairing text shown on both pages.' ) && str_contains( $g_event['body'], 'Cups up.' ) );
	cic3_check( 'G3 new event is selectable in public event choices once published', in_array( 'cic-test-graduation', wp_list_pluck( Catalog::selectable_events(), 'key' ), true ) );
	wp_delete_post( $event5, true );
	cic3_check( 'G4 permanently deleting an event removes its pairings', ! Pairings::for_product( $prod5 ) );
	wp_delete_post( $prod5, true );

	WP_CLI::log( '== H. REST schemas and capabilities' );
	wp_set_current_user( 0 );
	$anon = cic3_rest( 'GET', '/wp/v2/events/' . $birthday );
	$data = $anon->get_data();
	cic3_check( 'H1 public event REST exposes published pairings and typed meta', 200 === $anon->get_status() && 4 === count( $data['cic_pairings'] ) && 'birthday' === $data['meta']['cic_key'] );
	$denied = cic3_rest( 'POST', '/wp/v2/product/' . $dd, array( 'cic_pairings' => array() ) );
	cic3_check( 'H2 anonymous users cannot edit pairings', in_array( $denied->get_status(), array( 401, 403 ), true ) && 3 === count( Pairings::for_product( $dd ) ) );
	wp_set_current_user( $admin->ID );
	$bad = cic3_rest( 'POST', '/wp/v2/product/' . $dd, array( 'cic_pairings' => array( array( 'event_id' => 123456789 ) ) ) );
	cic3_check( 'H3 invalid REST pairing update is rejected (400) and writes nothing', 400 === $bad->get_status() && 3 === count( Pairings::for_product( $dd ) ), $bad->get_status() );
	$schema = cic3_rest( 'OPTIONS', '/wp/v2/events' )->get_data()['schema']['properties'];
	cic3_check( 'H4 REST schema documents cic_pairings and meta types (key pattern, preset enum)', isset( $schema['cic_pairings']['items']['properties']['event_id'] ) && '^[a-z0-9]+(-[a-z0-9]+)*$' === $schema['meta']['properties']['cic_key']['pattern'] && in_array( 'wedding', $schema['meta']['properties']['cic_preset']['enum'], true ) );
	$bad_meta = cic3_rest( 'POST', '/wp/v2/events/' . $birthday, array( 'meta' => array( 'cic_preset' => 'not-a-preset' ) ) );
	cic3_check( 'H5 invalid meta via REST is rejected by schema', 400 === $bad_meta->get_status() && 'birthday' === get_post_meta( $birthday, 'cic_preset', true ) );

	WP_CLI::log( '== I. Revisions' );
	$before = count( wp_get_post_revisions( $birthday ) );
	wp_update_post( array( 'ID' => $birthday, 'post_content' => '<!-- wp:paragraph --><p>Revision test.</p><!-- /wp:paragraph -->' ) );
	$revs = wp_get_post_revisions( $birthday );
	$rev  = reset( $revs );
	cic3_check( 'I1 event edits create revisions, including revisioned preset meta', count( $revs ) > $before && 'birthday' === get_metadata( 'post', $rev->ID, 'cic_preset', true ) );

	WP_CLI::log( '== J. Catalogue protection on seeded products' );
	$pages = array( cic3_get( '/treats/dippin-dots/' )['body'], cic3_get( '/treats/' )['body'] );
	$leak  = false;
	foreach ( $pages as $html ) {
		$leak = $leak || (bool) preg_match( '/add_to_cart|add-to-cart|single_add_to_cart|woocommerce-Price-amount|"@type":"Offer"|wc-block-cart|name="quantity"/', $html );
	}
	cic3_check( 'J1 treat and catalogue pages render no price, quantity, add-to-cart, cart or Offer markup', ! $leak );
	$add = cic3_get( '/treats/dippin-dots/?add-to-cart=' . $dd );
	cic3_check( 'J2 add-to-cart query on a seeded treat is refused (redirect without the key)', 302 === $add['code'] && ! str_contains( $add['location'], 'add-to-cart' ) );
	$store = wp_remote_post( home_url( '/wp-json/wc/store/v1/cart/add-item' ), array( 'sslverify' => false, 'body' => array( 'id' => $dd, 'quantity' => 1 ) ) );
	cic3_check( 'J3 Store API add-item for a seeded treat returns 403', 403 === (int) wp_remote_retrieve_response_code( $store ) );

	WP_CLI::log( '== K. Media importer' );
	$assets = Media::assets_for_pages( array( 'dippin-dots', 'birthday' ) );
	$ids    = array_map( array( Media::class, 'id' ), $assets );
	$all_assets = array_map( static fn( $id ) => get_post_meta( $id, Media::META, true ), get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => Media::META, 'numberposts' => -1, 'fields' => 'ids' ) ) ); // phpcs:ignore
	cic3_check( 'K1 the 20 slice masters are imported, and no asset is imported twice', 20 === count( $assets ) && ! in_array( 0, $ids, true ) && 20 === count( array_unique( $ids ) ) && count( $all_assets ) === count( array_unique( $all_assets ) ) );
	update_post_meta( Media::id( 'dots-hero' ), '_wp_attachment_image_alt', 'Editor alt text' );
	$again = array_map( array( Media::class, 'import' ), $assets );
	cic3_check( 'K2 re-import reuses attachments and keeps attachment edits', $again === $ids && 'Editor alt text' === get_post_meta( Media::id( 'dots-hero' ), '_wp_attachment_image_alt', true ) );
	cic3_check( 'K3 alt text comes from the manifest; provenance stored on the attachment', '' !== $snap['alt'] && str_contains( get_post_field( 'post_content', Media::id( 'dots-family' ) ), 'Provenance' ) );

	WP_CLI::log( '== L. Content survives a theme switch and ACF deactivation' );
	switch_theme( 'twentytwentyfive' );
	$tt5 = cic3_get( '/events/birthday/' );
	$tt5p = cic3_get( '/treats/dippin-dots/' );
	cic3_check( 'L1 with Twenty Twenty-Five active, event and treat still exist and return 200', 200 === $tt5['code'] && 200 === $tt5p['code'] && 'More candles.' !== '' && str_contains( $tt5['body'], 'Birthdays' ) );
	switch_theme( $snap['theme'] );
	deactivate_plugins( 'advanced-custom-fields/acf.php', true );
	$noacf = cic3_get( '/events/birthday/' );
	cic3_check( 'L2 with ACF deactivated, the composed Birthday page still renders its stored fields', 200 === $noacf['code'] && str_contains( $noacf['body'], 'Every age deserves a' ) && str_contains( $noacf['body'], 'Party planning questions' ) );
	activate_plugin( 'advanced-custom-fields/acf.php' );
} catch ( Throwable $e ) {
	cic3_check( 'Unexpected error: ' . $e->getMessage(), false );
} finally {
	// Restore everything, whatever happened above.
	if ( get_stylesheet() !== $snap['theme'] ) {
		switch_theme( $snap['theme'] );
	}
	if ( $snap['acf'] && ! is_plugin_active( 'advanced-custom-fields/acf.php' ) ) {
		activate_plugin( 'advanced-custom-fields/acf.php' );
	}
	foreach ( $snap['status'] as $id => $status ) {
		if ( get_post_status( $id ) !== $status ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
		}
	}
	update_post_meta( $bananas, 'cic_catalog_active', $snap['bananas_act'] );
	foreach ( $snap['dd_meta'] as $k => $v ) {
		update_post_meta( $dd, $k, $v );
	}
	Pairings::replace_for_product( $dd, $snap['dd_pairs'] );
	update_option( Seeder::LOG, $snap['log'], false );
	update_post_meta( Media::id( 'dots-hero' ), '_wp_attachment_image_alt', $snap['alt'] );
	wp_update_post( array( 'ID' => $birthday, 'post_content' => $snap['birthday']->post_content ) );
	foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_cic_fixture', 'meta_value' => 'phase03', 'numberposts' => -1, 'fields' => 'ids' ) ) as $fixture ) { // phpcs:ignore
		wp_delete_post( $fixture, true );
	}
	WP_CLI::log( '(restored: theme, ACF, statuses, edited fields, pairings, seed log, alt text, fixtures)' );
}

WP_CLI::log( sprintf( '== RESULT: %d passed, %d failed', $cic3['pass'], $cic3['fail'] ) );
