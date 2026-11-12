<?php
/**
 * SEO foundation of 2026-10-28: server-side and HTML checks (real local HTTP requests).
 *
 * Production is simulated with a temporary MU plugin (wp-content/mu-plugins/zz-cic-seo-test.php,
 * git-ignored) that acts ONLY on requests carrying a random per-run header ("prod": Reading setting
 * allows indexing + environment protection off). To check the indexable-city path, one city's
 * content is marked reviewed for the run and restored. The MU plugin is deleted, the city state
 * restored and the location cache bumped (never lowered) in `finally`. No other data is changed.
 * Expects the settings/metadata of dev/tools/content/seo-20261028.php to be applied.
 *
 * Usage: wp eval-file dev/tests/seo-20261028/seo-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Content\ViewModel;
use CIC\Locations\CityStore;
use CIC\Locations\DatabaseRepository;
use CIC\Locations\Editorial;
use CIC\Locations\Service as Locations;
use CIC\Locations\Tables;

$cic_results = array();
$cic_check   = static function ( string $label, bool $ok, $detail = null ) use ( &$cic_results ): void {
	$cic_results[] = $ok;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && null !== $detail ? ' — ' . substr( wp_json_encode( $detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 0, 900 ) : '' ) );
};
$cic_token = wp_generate_password( 32, false );
$cic_mu    = WPMU_PLUGIN_DIR . '/zz-cic-seo-test.php';
$cic_get   = static function ( string $path, bool $prod = true ) use ( &$cic_token ): array {
	$headers = array( 'Cache-Control' => 'no-cache' );
	if ( $prod ) {
		$headers['X-CIC-SEO'] = $cic_token;
	}
	$r = wp_remote_get( home_url( $path ), array( 'sslverify' => false, 'timeout' => 45, 'redirection' => 0, 'headers' => $headers ) );
	return is_wp_error( $r ) ? array( 0, '', '' ) : array( (int) wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_body( $r ), (string) wp_remote_retrieve_header( $r, 'location' ) );
};
$cic_meta = static function ( string $html ): array {
	$head = (string) strstr( $html, '</head>', true );
	preg_match_all( '#<title>([^<]*)</title>#', $head, $t );
	preg_match_all( '#<link rel="canonical" href="([^"]*)"#', $head, $c );
	preg_match_all( '#<meta name=[\'"]robots[\'"] content=[\'"]([^\'"]*)[\'"]#', $head, $r );
	preg_match_all( '#<meta name="description" content="([^"]*)"#', $head, $d );
	preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $j );
	$graph = array();
	foreach ( $j[1] as $json ) {
		$graph = array_merge( $graph, (array) ( json_decode( $json, true )['@graph'] ?? array() ) );
	}
	return array(
		'title'  => array_map( 'html_entity_decode', $t[1] ),
		'canon'  => $c[1],
		'robots' => $r[1],
		'desc'   => array_map( 'html_entity_decode', $d[1] ),
		'ld'     => count( $j[1] ),
		'graph'  => $graph,
	);
};
$cic_piece = static function ( array $graph, string $type ): array {
	foreach ( $graph as $piece ) {
		if ( in_array( $type, (array) ( $piece['@type'] ?? array() ), true ) ) {
			return $piece;
		}
	}
	return array();
};
$cic_locs = static function ( string $xml ): array {
	preg_match_all( '#<loc>([^<]*)</loc>#', $xml, $m );
	return array_map( 'html_entity_decode', $m[1] );
};

$cic_city_slug   = 'camarillo';
$cic_city        = Locations::repository()->resolve_city( 'california', $cic_city_slug );
global $wpdb;
$cic_content_row = $cic_city ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::name( 'city_content' ) . ' WHERE city_id = %d', $cic_city['id'] ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
$cic_editorial   = $cic_city ? CityStore::editorial_id( (int) $cic_city['id'] ) : 0;
$cic_editorial   = $cic_editorial && get_post( $cic_editorial ) ? $cic_editorial : 0;
$cic_restore     = array( 'created' => false, 'status' => $cic_editorial ? get_post_status( $cic_editorial ) : '', 'reviewed' => $cic_editorial ? get_post_meta( $cic_editorial, Editorial::REVIEWED, true ) : '' );
$cic_touched     = false;

try {
	file_put_contents(
		$cic_mu,
		"<?php\n// TEMPORARY test file (dev/tests/seo-20261028); deleted by the test.\n"
		. "if ( isset( \$_SERVER['HTTP_X_CIC_SEO'] ) && hash_equals( '" . $cic_token . "', (string) \$_SERVER['HTTP_X_CIC_SEO'] ) ) {\n"
		. "\tadd_filter( 'pre_option_blog_public', static fn() => '1' );\n"
		. "\tadd_filter( 'cic_seo_protect', '__return_false' );\n"
		. "}\n"
	);

	/* ---------------- M: metadata on every marketing page ---------------- */
	$cic_pages = array( '/', '/events/', '/events/birthday-party-ice-cream-catering/', '/events/weddings-ice-cream-catering/', '/events/graduation-party-ice-cream-catering/', '/events/proms-ice-cream-catering/', '/events/corporate-events-ice-cream-catering/', '/events/anniversary-ice-cream-catering/', '/treats/', '/treats/dippin-dots-catering/', '/treats/dipn-bananas-catering/', '/treats/classic-ice-cream-catering/', '/treats/frozen-yogurt-catering/', '/service-areas/', '/service-areas/california/', '/about/', '/contact/', '/get-a-quote/' );
	$cic_bad   = array();
	$cic_seen  = array( 'title' => array(), 'desc' => array() );
	$cic_html  = array();
	foreach ( $cic_pages as $cic_p ) {
		[ $cic_code, $cic_body ] = $cic_get( $cic_p );
		$cic_html[ $cic_p ]      = $cic_body;
		$cic_m                   = $cic_meta( $cic_body );
		$cic_title               = $cic_m['title'][0] ?? '';
		$cic_r                   = implode( ',', $cic_m['robots'] );
		$cic_problem             = array_filter(
			array(
				'status'    => 200 !== $cic_code ? $cic_code : null,
				'titles'    => 1 !== count( $cic_m['title'] ) ? count( $cic_m['title'] ) : null,
				'brand'     => 1 !== substr_count( $cic_title, 'CaterIceCream.com' ) || ! str_ends_with( $cic_title, ' | CaterIceCream.com' ) ? $cic_title : null,
				'generic'   => preg_match( '/^(Home|Our Treats|Service Areas|About|Get a Quote|Events Archive|Birthdays|Weddings|Anniversaries|Corporate Events) [|-]/', $cic_title ) ? $cic_title : null,
				'desc'      => 1 !== count( $cic_m['desc'] ) || strlen( $cic_m['desc'][0] ) < 70 ? $cic_m['desc'] : null,
				'robots'    => 1 !== count( $cic_m['robots'] ) || false !== strpos( $cic_r, 'noindex' ) || false !== strpos( $cic_r, 'nofollow' ) ? $cic_m['robots'] : null,
				'canonical' => array( home_url( $cic_p ) ) !== $cic_m['canon'] ? $cic_m['canon'] : null,
				'graphs'    => 1 !== $cic_m['ld'] ? $cic_m['ld'] : null,
				'british'   => preg_match( '/\b(flavour|colour|favourite|personalis|catalogue)/i', $cic_title . ' ' . implode( ' ', $cic_m['desc'] ) ) ? 'spelling' : null,
			)
		);
		if ( $cic_problem ) {
			$cic_bad[ $cic_p ] = $cic_problem;
		}
		$cic_seen['title'][ $cic_title ][]                    = $cic_p;
		$cic_seen['desc'][ (string) ( $cic_m['desc'][0] ?? '' ) ][] = $cic_p;
	}
	$cic_check( 'M1 production: 18 marketing pages: 200, one specific "… | CaterIceCream.com" title, one description, index/follow, self-canonical, one graph, American spelling', ! $cic_bad, $cic_bad );
	$cic_dupes = array_filter( array_merge( $cic_seen['title'], $cic_seen['desc'] ), static fn( $v ) => count( $v ) > 1 );
	$cic_check( 'M2 titles and descriptions are distinct across those pages', ! $cic_dupes, $cic_dupes );

	$cic_m = $cic_meta( $cic_get( '/events/birthday-party-ice-cream-catering/' )[1] );
	$cic_check( 'M3 event metadata comes from the CIC search fields: "Birthday Party Ice Cream Catering | CaterIceCream.com"', array( 'Birthday Party Ice Cream Catering | CaterIceCream.com' ) === $cic_m['title'], $cic_m['title'] );

	/* ---------------- I: indexing rules ---------------- */
	$cic_bad = array();
	foreach ( array( '/', '/events/birthday-party-ice-cream-catering/', '/treats/dippin-dots-catering/', '/service-areas/california/' ) as $cic_p ) {
		$cic_m = $cic_meta( $cic_get( $cic_p, false )[1] );
		if ( 1 !== count( $cic_m['robots'] ) || false === strpos( $cic_m['robots'][0], 'noindex' ) || false === strpos( $cic_m['robots'][0], 'nofollow' ) ) {
			$cic_bad[ $cic_p ] = $cic_m['robots'];
		}
	}
	$cic_check( 'I1 local (no simulation): sampled pages stay "noindex, nofollow"', ! $cic_bad, $cic_bad );

	$cic_bad = array();
	foreach ( array( '/get-a-quote/?event=birthday&treat=dippin-dots', '/get-a-quote/?location=los-angeles-ca', '/get-a-quote/?received=abc123', '/contact/?sent=abc123', '/service-areas/?county=ventura-county' ) as $cic_p ) {
		$cic_m = $cic_meta( $cic_get( $cic_p )[1] );
		if ( 1 !== count( $cic_m['robots'] ) || false === strpos( $cic_m['robots'][0], 'noindex' ) || false !== strpos( $cic_m['robots'][0], 'nofollow' ) ) {
			$cic_bad[ $cic_p ] = $cic_m['robots'];
		}
	}
	$cic_check( 'I2 production: quote prefills, receipts and filtered variants are "noindex, follow"', ! $cic_bad, $cic_bad );

	$cic_m = $cic_meta( $cic_get( '/events/birthday-party-ice-cream-catering/?utm_source=newsletter' )[1] );
	$cic_check( 'I3 production: a tracking-parameter URL keeps index and canonicalises to the clean URL', array( home_url( '/events/birthday-party-ice-cream-catering/' ) ) === $cic_m['canon'] && false === strpos( implode( ',', $cic_m['robots'] ), 'noindex' ), $cic_m );

	$cic_bad = array();
	foreach ( array( '/hello-world/' => 404, '/no-such-page/' => 404, '/service-areas/california/no-such-city/' => 404, '/service-areas/nevada/' => 404, '/service-areas/california/compton/' => 404 ) as $cic_p => $cic_want ) {
		$cic_code = $cic_get( $cic_p )[0];
		if ( $cic_want !== $cic_code ) {
			$cic_bad[ $cic_p ] = $cic_code;
		}
	}
	[ $cic_code, , $cic_loc ] = $cic_get( '/author/' . get_userdata( (int) ( get_users( array( 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 ) )->user_nicename . '/' );
	if ( 301 !== $cic_code ) {
		$cic_bad['author archive'] = array( $cic_code, $cic_loc );
	}
	$cic_check( 'I4 sample post, unknown page/city/state and the excluded Compton route return 404; the author archive redirects (301)', ! $cic_bad, $cic_bad );

	$cic_m = $cic_meta( $cic_get( '/category/uncategorized/' )[1] );
	$cic_check( 'I5 production: the empty category archive is noindex', 1 === count( $cic_m['robots'] ) && false !== strpos( $cic_m['robots'][0], 'noindex' ), $cic_m['robots'] );

	/* ---------------- G: schema graph ---------------- */
	$cic_home  = $cic_meta( $cic_html['/'] );
	$cic_org   = $cic_piece( $cic_home['graph'], 'Organization' );
	$cic_prov  = ViewModel::provider();
	$cic_check(
		'G1 one Organization (#organization) with name, legal name and phone from Header, Footer & Contact, a logo, and no address/rating',
		home_url( '/' ) . '#organization' === ( $cic_org['@id'] ?? '' ) && $cic_prov['name'] === ( $cic_org['name'] ?? '' ) && $cic_prov['legalName'] === ( $cic_org['legalName'] ?? '' ) && $cic_prov['telephone'] === ( $cic_org['telephone'] ?? '' ) && ! empty( $cic_org['logo'] ) && ! isset( $cic_org['address'] ) && ! isset( $cic_org['aggregateRating'] ),
		$cic_org
	);
	$cic_site = $cic_piece( $cic_home['graph'], 'WebSite' );
	$cic_check( 'G2 WebSite is published by the Organization and has no SearchAction', array( '@id' => home_url( '/' ) . '#organization' ) === ( $cic_site['publisher'] ?? null ) && empty( $cic_site['potentialAction'] ), $cic_site );

	$cic_bad = array();
	foreach ( array( '/events/birthday-party-ice-cream-catering/', '/events/proms-ice-cream-catering/' ) as $cic_p ) {
		$cic_m   = $cic_meta( $cic_html[ $cic_p ] );
		$cic_svc = $cic_piece( $cic_m['graph'], 'Service' );
		$cic_wp  = $cic_piece( $cic_m['graph'], 'WebPage' );
		if ( array( '@id' => home_url( '/' ) . '#organization' ) !== ( $cic_svc['provider'] ?? null ) || array( '@id' => home_url( $cic_p ) ) !== ( $cic_svc['mainEntityOfPage'] ?? null ) || ( $cic_wp['name'] ?? '' ) !== ( $cic_m['title'][0] ?? '-' ) || ( $cic_wp['description'] ?? '' ) !== ( $cic_m['desc'][0] ?? '-' ) ) {
			$cic_bad[ $cic_p ] = array( 'service' => $cic_svc, 'webpage' => array( $cic_wp['name'] ?? '', $cic_wp['description'] ?? '' ) );
		}
	}
	$cic_check( 'G3 event pages: Service → provider #organization + mainEntityOfPage; WebPage name/description equal the title/description', ! $cic_bad, $cic_bad );

	$cic_bad = array();
	foreach ( array( '/treats/dippin-dots-catering/', '/treats/frozen-yogurt-catering/', '/treats/' ) as $cic_p ) {
		foreach ( $cic_meta( $cic_html[ $cic_p ] )['graph'] as $cic_pc ) {
			if ( array_intersect( (array) ( $cic_pc['@type'] ?? array() ), array( 'Product', 'Offer', 'AggregateRating', 'Review' ) ) ) {
				$cic_bad[ $cic_p ] = $cic_pc['@type'];
			}
		}
		if ( preg_match( '#"@type"\s*:\s*"(Product|Offer)"#', $cic_html[ $cic_p ] ) ) {
			$cic_bad[ $cic_p . ' (page)' ] = 'Product/Offer JSON-LD outside the graph';
		}
	}
	$cic_check( 'G4 treat pages carry no Product/Offer/rating markup (quote-only catalogue)', ! $cic_bad, $cic_bad );

	/* ---------------- S: sitemaps ---------------- */
	[ $cic_code, $cic_index ] = $cic_get( '/sitemap_index.xml' );
	$cic_maps = $cic_locs( $cic_index );
	$cic_bad  = array();
	foreach ( array( 'post-sitemap', 'author-sitemap', 'category-sitemap', 'product_cat-sitemap', 'product_brand-sitemap', 'post_tag-sitemap' ) as $cic_no ) {
		foreach ( $cic_maps as $cic_u ) {
			if ( false !== strpos( $cic_u, '/' . $cic_no . '.xml' ) ) {
				$cic_bad[] = $cic_u;
			}
		}
	}
	$cic_check( 'S1 sitemap index (200, valid XML) lists no sample-post, author, category, tag or product taxonomy sitemaps', 200 === $cic_code && false !== simplexml_load_string( $cic_index ) && ! $cic_bad, array( $cic_code, $cic_bad ) );

	$cic_all = array();
	foreach ( $cic_maps as $cic_u ) {
		$cic_xml = $cic_get( wp_parse_url( $cic_u, PHP_URL_PATH ) )[1];
		foreach ( $cic_locs( $cic_xml ) as $cic_l ) {
			$cic_all[] = $cic_l;
		}
	}
	$cic_dupes = array_keys( array_filter( array_count_values( $cic_all ), static fn( $n ) => $n > 1 ) );
	$cic_woo   = array_filter( $cic_all, static fn( $u ) => (bool) preg_match( '#/(cart|checkout|my-account)/#', $u ) );
	$cic_check( 'S2 sitemaps: no duplicate URL, no cart/checkout/account pages', ! $cic_dupes && ! $cic_woo, array( $cic_dupes, $cic_woo ) );

	$cic_bad = array();
	foreach ( $cic_all as $cic_l ) {
		$cic_p                   = (string) wp_parse_url( $cic_l, PHP_URL_PATH );
		[ $cic_code, $cic_body ] = $cic_get( $cic_p );
		$cic_m                   = $cic_meta( $cic_body );
		if ( 200 !== $cic_code || array( $cic_l ) !== $cic_m['canon'] || false !== strpos( implode( ',', $cic_m['robots'] ), 'noindex' ) ) {
			$cic_bad[ $cic_p ] = array( $cic_code, $cic_m['canon'], $cic_m['robots'] );
		}
	}
	$cic_check( 'S3 production: every sitemap URL (' . count( $cic_all ) . ') answers 200, is indexable and canonicalises to itself', ! $cic_bad, $cic_bad );

	/* ---------------- L: locations ---------------- */
	$cic_repo = Locations::repository();
	$cic_check( 'L1 a city without an enabled production ZIP is never served or indexable', $cic_repo instanceof DatabaseRepository && ! $cic_repo->is_served( array( 'id' => 0 ) ) && ! $cic_repo->is_indexable( array( 'id' => 0, 'publish_status' => 'published', 'index_status' => 'index' ) ) );

	if ( $cic_city ) {
		if ( ! $cic_editorial ) {
			$cic_editorial          = CityStore::ensure_editorial( (int) $cic_city['id'] );
			$cic_restore['created'] = true;
		}
		$cic_touched = true;
		update_post_meta( $cic_editorial, Editorial::REVIEWED, '1' );
		wp_update_post( array( 'ID' => $cic_editorial, 'post_status' => 'publish' ) );
		Tables::bump_cache();
		$cic_url = DatabaseRepository::url( 'california', $cic_city_slug );
		$cic_xml = $cic_get( '/cic-locations-sitemap.xml' )[1];
		$cic_idx = $cic_get( '/sitemap_index.xml' )[1];
		$cic_m   = $cic_meta( $cic_get( '/service-areas/california/' . $cic_city_slug . '-ice-cream-catering/' )[1] );
		$cic_ok  = $cic_repo->is_served( $cic_city ) && false !== strpos( $cic_xml, '<loc>' . $cic_url . '</loc>' ) && (bool) preg_match( '#<loc>' . preg_quote( $cic_url, '#' ) . '</loc>\s*<lastmod>\d{4}-\d\d-\d\dT#', $cic_xml )
			&& (bool) preg_match( '#cic-locations-sitemap\.xml</loc>\s*<lastmod>\d{4}-#', $cic_idx )
			&& array( $cic_url ) === $cic_m['canon'] && false === strpos( implode( ',', $cic_m['robots'] ), 'noindex' );
		$cic_check( 'L2 reviewed + served city: indexable, self-canonical, in cic-locations-sitemap.xml with lastmod; index entry has lastmod', $cic_ok, array( 'canon' => $cic_m['canon'], 'robots' => $cic_m['robots'], 'xml' => substr( $cic_xml, 0, 600 ) ) );
		$cic_svc = $cic_piece( $cic_m['graph'], 'Service' );
		$cic_check( 'L3 city Service: provider #organization, areaServed is the city, mainEntityOfPage is its WebPage', array( '@id' => home_url( '/' ) . '#organization' ) === ( $cic_svc['provider'] ?? null ) && 'City' === ( $cic_svc['areaServed']['@type'] ?? '' ) && array( '@id' => $cic_url . '#webpage' ) === ( $cic_svc['mainEntityOfPage'] ?? null ), $cic_svc );
	} else {
		$cic_check( 'L2 test city ' . $cic_city_slug . ' exists', false );
	}

	// Two different city routes in a row keep their own metadata (no cross-route leakage).
	$cic_a = $cic_meta( $cic_get( '/service-areas/california/los-angeles-ice-cream-catering/' )[1] );
	$cic_b = $cic_meta( $cic_get( '/service-areas/california/pasadena-ice-cream-catering/' )[1] );
	$cic_check(
		'L4 city metadata is per route: Los Angeles and Pasadena titles/descriptions name their own city',
		false !== strpos( $cic_a['title'][0] ?? '', 'Los Angeles, CA' ) && false !== strpos( $cic_a['desc'][0] ?? '', 'Los Angeles' ) && false === strpos( implode( ' ', $cic_a['desc'] ), 'Pasadena' )
		&& false !== strpos( $cic_b['title'][0] ?? '', 'Pasadena, CA' ) && false !== strpos( $cic_b['desc'][0] ?? '', 'Pasadena' ) && false === strpos( implode( ' ', $cic_b['desc'] ), 'Los Angeles' ),
		array( $cic_a['title'], $cic_b['title'] )
	);
} finally {
	if ( file_exists( $cic_mu ) ) {
		wp_delete_file( $cic_mu );
	}
	if ( $cic_editorial && $cic_touched ) {
		if ( $cic_restore['created'] ) {
			wp_delete_post( $cic_editorial, true );
			if ( $cic_content_row ) {
				$wpdb->replace( Tables::name( 'city_content' ), $cic_content_row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} else {
				$wpdb->delete( Tables::name( 'city_content' ), array( 'city_id' => (int) $cic_city['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		} else {
			if ( '' === (string) $cic_restore['reviewed'] ) {
				delete_post_meta( $cic_editorial, Editorial::REVIEWED );
			} else {
				update_post_meta( $cic_editorial, Editorial::REVIEWED, $cic_restore['reviewed'] );
			}
			wp_update_post( array( 'ID' => $cic_editorial, 'post_status' => $cic_restore['status'] ) );
		}
		Tables::bump_cache();
	}
}
$cic_pass = count( array_filter( $cic_results ) );
WP_CLI::log( sprintf( 'SEO checks: %d/%d passed; temporary MU plugin removed: %s; test city restored: %s', $cic_pass, count( $cic_results ), file_exists( $cic_mu ) ? 'NO' : 'yes', $cic_touched ? ( $cic_restore['created'] ? 'editorial post deleted' : 'meta/status restored' ) : 'not changed' ) );
