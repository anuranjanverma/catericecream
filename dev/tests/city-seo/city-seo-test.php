<?php
/**
 * City landing pages: technical SEO checks for EVERY eligible city (2026-11-16).
 *
 * Production is simulated per request with a temporary MU plugin (deleted in finally), the same
 * approach as dev/tests/qa-20261026/qa-test.php: Reading allows indexing and the environment
 * protection is lifted only for requests carrying a one-time token header.
 *
 * Per eligible city (production, published, Index, ≥1 enabled ZIP): HTTP 200 without redirect;
 * one title = the city's search title + " | site"; one meta description = the stored one; robots
 * index (no noindex); one canonical = its own URL; og:url = its URL, og:title and og:description;
 * one JSON-LD block (Yoast's graph) with a Service piece for the city; exactly one H1 naming the
 * city; listed in cic-locations-sitemap.xml; linked from the state directory (county pages).
 * Site checks: robots.txt does not block /service-areas/; the sitemap index lists the location
 * sitemap; local (unsimulated) requests stay "noindex, nofollow"; a draft city (excluded) is 404
 * and not in the sitemap.
 *
 * Usage: wp eval-file dev/tests/city-seo/city-seo-test.php [report=/path/report.csv]
 * Read-only apart from the temporary MU plugin. Prints failures and totals.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Locations\DatabaseRepository;

$cic_report = '';
foreach ( (array) ( $args ?? array() ) as $cic_arg ) {
	if ( 0 === strpos( $cic_arg, 'report=' ) ) {
		$cic_report = substr( $cic_arg, 7 );
	}
}
$cic_token = wp_generate_password( 32, false );
$cic_mu    = WPMU_PLUGIN_DIR . '/zz-cic-city-seo-test.php';
$cic_get   = static function ( string $url, bool $prod = true ) use ( $cic_token ): array {
	$headers = array( 'Cache-Control' => 'no-cache' );
	if ( $prod ) {
		$headers['X-CIC-QA'] = $cic_token . '|prod';
	}
	$r = wp_remote_get( $url, array( 'sslverify' => false, 'timeout' => 60, 'redirection' => 0, 'headers' => $headers ) );
	return is_wp_error( $r ) ? array( 0, '' ) : array( (int) wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_body( $r ) );
};
$cic_attr = static function ( string $html, string $re ): array {
	preg_match_all( $re, $html, $m );
	return array_map( static fn( $v ) => html_entity_decode( $v, ENT_QUOTES | ENT_HTML5 ), $m[1] );
};

$cic_fail  = array();
$cic_rows  = array();
$cic_check = static function ( string $label, bool $ok, $detail = null ) use ( &$cic_fail ): void {
	if ( ! $ok ) {
		$cic_fail[] = $label . ( null !== $detail ? ' — ' . substr( wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 0, 400 ) : '' );
	}
};

try {
	file_put_contents(
		$cic_mu,
		"<?php\n// TEMPORARY test file (dev/tests/city-seo); deleted by the test.\n"
		. "if ( isset( \$_SERVER['HTTP_X_CIC_QA'] ) && hash_equals( '" . $cic_token . "|prod', (string) \$_SERVER['HTTP_X_CIC_QA'] ) ) {\n"
		. "\tadd_filter( 'pre_option_blog_public', static fn() => '1' );\n"
		. "\tadd_filter( 'cic_seo_protect', '__return_false' );\n"
		. "}\n"
	);

	$cic_repo     = new DatabaseRepository();
	$cic_eligible = $cic_repo->served_city_ranking( PHP_INT_MAX );
	usort( $cic_eligible, static fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

	// Site-level checks.
	[ , $cic_robots_txt ] = $cic_get( home_url( '/robots.txt' ) );
	$cic_blocked          = (bool) preg_match( '#^\s*Disallow:\s*/(service-areas)?/?\s*$#mi', $cic_robots_txt );
	$cic_check( 'robots.txt does not block /service-areas/', ! $cic_blocked, $cic_robots_txt );
	[ $cic_ic, $cic_index ] = $cic_get( home_url( '/sitemap_index.xml' ) );
	$cic_check( 'sitemap index lists cic-locations-sitemap.xml', 200 === $cic_ic && false !== strpos( $cic_index, 'cic-locations-sitemap.xml' ), $cic_ic );
	[ $cic_sc, $cic_sitemap ] = $cic_get( home_url( '/cic-locations-sitemap.xml' ) );
	$cic_check( 'location sitemap answers 200', 200 === $cic_sc, $cic_sc );
	$cic_locs = $cic_attr( $cic_sitemap, '#<loc>([^<]+)</loc>#' );

	// Directory discoverability: every county page of the state directory.
	$cic_linked = array();
	$cic_state  = home_url( '/service-areas/california/' );
	foreach ( array_unique( array_map( static fn( $c ) => sanitize_title( $c['county_name'] ?? '' ), $cic_eligible ) ) as $cic_county ) {
		for ( $cic_pg = 1; $cic_pg <= 30; $cic_pg++ ) {
			[ $cic_dc, $cic_dir ] = $cic_get( add_query_arg( array( 'county' => $cic_county, 'pg' => $cic_pg ), $cic_state ) );
			$cic_found            = $cic_attr( $cic_dir, '#href="(' . preg_quote( $cic_state, '#' ) . '[^"?]+-ice-cream-catering/)"#' );
			$cic_new              = array_diff( $cic_found, array_keys( $cic_linked ) );
			foreach ( $cic_found as $cic_u ) {
				$cic_linked[ $cic_u ] = true;
			}
			if ( 200 !== $cic_dc || ! $cic_new ) {
				break;
			}
		}
	}

	// Local (unsimulated) protection on a sample.
	$cic_sample = $cic_eligible[0]['url'] ?? '';
	[ , $cic_local ] = $cic_get( $cic_sample, false );
	$cic_lrobots     = $cic_attr( (string) strstr( $cic_local, '</head>', true ), '#<meta name=[\'"]robots[\'"] content=[\'"]([^\'"]*)#' );
	$cic_check( 'local environment stays "noindex, nofollow" (sample city)', 1 === count( $cic_lrobots ) && false !== strpos( $cic_lrobots[0], 'noindex' ) && false !== strpos( $cic_lrobots[0], 'nofollow' ), $cic_lrobots );

	// Excluded draft city (Compton) is a 404 and not in the sitemap.
	[ $cic_xc ] = $cic_get( home_url( '/service-areas/california/compton-ice-cream-catering/' ) );
	$cic_check( 'excluded draft city (Compton) answers 404 and is not in the sitemap', 404 === $cic_xc && ! preg_grep( '#/compton-#', $cic_locs ), $cic_xc );

	foreach ( $cic_eligible as $cic_c ) {
		$cic_city = $cic_repo->city_by_id( (int) $cic_c['id'] );
		$cic_url  = (string) $cic_city['url'];
		$cic_vm   = \CIC\Content\ViewModel::city( $cic_city );
		[ $cic_code, $cic_html ] = $cic_get( $cic_url );
		$cic_head = (string) strstr( $cic_html, '</head>', true );
		$cic_m    = array(
			'title'  => $cic_attr( $cic_head, '#<title>([^<]*)</title>#' ),
			'desc'   => $cic_attr( $cic_head, '#<meta name="description" content="([^"]*)"#' ),
			'robots' => $cic_attr( $cic_head, '#<meta name=[\'"]robots[\'"] content=[\'"]([^\'"]*)#' ),
			'canon'  => $cic_attr( $cic_head, '#<link rel="canonical" href="([^"]*)"#' ),
			'ogurl'  => $cic_attr( $cic_head, '#<meta property="og:url" content="([^"]*)"#' ),
			'ogt'    => $cic_attr( $cic_head, '#<meta property="og:title" content="([^"]*)"#' ),
			'ogd'    => $cic_attr( $cic_head, '#<meta property="og:description" content="([^"]*)"#' ),
			'ld'     => $cic_attr( $cic_html, '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s' ),
			'h1'     => $cic_attr( $cic_html, '#<h1[^>]*>(.*?)</h1>#s' ),
		);
		$cic_graph = json_decode( (string) ( $cic_m['ld'][0] ?? '' ), true );
		$cic_svc   = array_filter( (array) ( $cic_graph['@graph'] ?? array() ), static fn( $p ) => in_array( 'Service', (array) ( $p['@type'] ?? array() ), true ) );
		$cic_res   = array(
			'status'    => 200 === $cic_code,
			'title'     => array( $cic_vm['seo']['title'] ) === $cic_m['title'],
			'desc'      => array( $cic_vm['seo']['description'] ) === $cic_m['desc'],
			'indexable' => 1 === count( $cic_m['robots'] ) && false === strpos( $cic_m['robots'][0], 'noindex' ),
			'canonical' => array( $cic_url ) === $cic_m['canon'],
			'og'        => array( $cic_url ) === $cic_m['ogurl'] && 1 === count( $cic_m['ogt'] ) && 1 === count( $cic_m['ogd'] ),
			'schema'    => 1 === count( $cic_m['ld'] ) && 1 === count( $cic_svc ),
			'h1'        => 1 === count( $cic_m['h1'] ) && false !== stripos( wp_strip_all_tags( $cic_m['h1'][0] ?? '' ), $cic_city['name'] ),
			'sitemap'   => in_array( $cic_url, $cic_locs, true ),
			'directory' => isset( $cic_linked[ $cic_url ] ),
		);
		foreach ( $cic_res as $cic_k => $cic_ok ) {
			$cic_check( "{$cic_city['name']}: $cic_k", $cic_ok, 'status' === $cic_k ? $cic_code : ( $cic_m[ array( 'title' => 'title', 'desc' => 'desc', 'indexable' => 'robots', 'canonical' => 'canon', 'og' => 'ogurl', 'h1' => 'h1' )[ $cic_k ] ?? 'robots' ] ?? null ) );
		}
		$cic_rows[] = array_merge( array( 'id' => $cic_city['id'], 'city' => $cic_city['name'], 'url' => $cic_url ), array_map( static fn( $v ) => $v ? 'yes' : 'NO', $cic_res ), array( 'qa' => in_array( false, $cic_res, true ) ? 'FAIL' : 'PASS' ) );
	}
} finally {
	if ( file_exists( $cic_mu ) ) {
		wp_delete_file( $cic_mu );
	}
}

if ( '' !== $cic_report && $cic_rows ) {
	$cic_fh = fopen( $cic_report, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fputcsv( $cic_fh, array_keys( $cic_rows[0] ), ',', '"', '' );
	foreach ( $cic_rows as $cic_r ) {
		fputcsv( $cic_fh, $cic_r, ',', '"', '' );
	}
	fclose( $cic_fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
}
foreach ( array_slice( $cic_fail, 0, 60 ) as $cic_f ) {
	WP_CLI::log( 'FAIL ' . $cic_f );
}
$cic_pass = count( array_filter( $cic_rows, static fn( $r ) => 'PASS' === $r['qa'] ) );
WP_CLI::log( sprintf( 'Eligible cities: %d; all checks passed: %d; failures: %d (site + city checks); sitemap URLs: %d; directory-linked city URLs: %d; temporary MU plugin removed: %s', count( $cic_rows ), $cic_pass, count( $cic_fail ), count( $cic_locs ?? array() ), count( $cic_linked ?? array() ), file_exists( $cic_mu ) ? 'NO' : 'yes' ) );
