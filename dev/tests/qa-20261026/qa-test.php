<?php
/**
 * QA fixes of 2026-10-26: server-side and HTML checks (real local HTTP requests).
 *
 * Production is simulated with a temporary MU plugin (wp-content/mu-plugins/zz-cic-qa-test.php,
 * git-ignored) that acts ONLY on requests carrying a random per-run header:
 *   prod    → Reading "discourage search engines" off + environment protection off (= live, fixed)
 *   protect → Reading setting off but the environment protection left on (= a staging copy)
 *   privacy → a published privacy policy URL is simulated (the real policy stays a draft)
 * To check the indexable-city path, one city's content is marked reviewed for the run and restored.
 * The MU plugin is deleted, the city state restored and the location cache bumped (never lowered)
 * in `finally`. No other data is changed.
 *
 * Usage: wp eval-file dev/tests/qa-20261026/qa-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Locations\CityStore;
use CIC\Locations\Editorial;
use CIC\Locations\Service as Locations;
use CIC\Locations\Tables;

$cic_results = array();
$cic_check   = static function ( string $label, bool $ok, $detail = null ) use ( &$cic_results ): void {
	$cic_results[] = $ok;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && null !== $detail ? ' — ' . substr( wp_json_encode( $detail ), 0, 700 ) : '' ) );
};
$cic_token = wp_generate_password( 32, false );
$cic_mu    = WPMU_PLUGIN_DIR . '/zz-cic-qa-test.php';
$cic_get   = static function ( string $path, string $mode = '' ) use ( &$cic_token ): array {
	$headers = array( 'Cache-Control' => 'no-cache' );
	if ( '' !== $mode ) {
		$headers['X-CIC-QA'] = $cic_token . '|' . $mode;
	}
	$r = wp_remote_get( home_url( $path ), array( 'sslverify' => false, 'timeout' => 45, 'redirection' => 0, 'headers' => $headers ) );
	return is_wp_error( $r ) ? array( 0, '' ) : array( (int) wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_body( $r ) );
};
$cic_head = static fn( string $html ): string => (string) strstr( $html, '</head>', true );
$cic_meta = static function ( string $head ): array {
	preg_match_all( '#<title>([^<]*)</title>#', $head, $t );
	preg_match_all( '#<link rel="canonical" href="([^"]*)"#', $head, $c );
	preg_match_all( '#<meta name=[\'"]robots[\'"] content=[\'"]([^\'"]*)[\'"]#', $head, $r );
	preg_match_all( '#<meta name="description" content="([^"]*)"#', $head, $d );
	preg_match_all( '#<meta property="og:title" content="([^"]*)"#', $head, $o );
	preg_match_all( '#<meta property="og:site_name" content="([^"]*)"#', $head, $s );
	preg_match_all( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $head, $j );
	return array(
		'title'  => array_map( 'html_entity_decode', $t[1] ),
		'canon'  => $c[1],
		'robots' => $r[1],
		'desc'   => $d[1],
		'og'     => array_map( 'html_entity_decode', $o[1] ),
		'site'   => $s[1],
		'ld'     => $j[1],
	);
};
$cic_types = static function ( array $ld ): array {
	$types = array();
	foreach ( $ld as $json ) {
		foreach ( (array) ( json_decode( $json, true )['@graph'] ?? array() ) as $piece ) {
			foreach ( (array) ( $piece['@type'] ?? array() ) as $t ) {
				$types[] = $t;
			}
		}
	}
	return $types;
};

$cic_city_slug = 'camarillo';
$cic_city      = Locations::repository()->resolve_city( 'california', $cic_city_slug );
global $wpdb;
$cic_content_row = $cic_city ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Tables::name( 'city_content' ) . ' WHERE city_id = %d', $cic_city['id'] ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
$cic_editorial   = $cic_city ? CityStore::editorial_id( (int) $cic_city['id'] ) : 0;
$cic_editorial   = $cic_editorial && get_post( $cic_editorial ) ? $cic_editorial : 0;
$cic_restore   = array( 'created' => false, 'status' => $cic_editorial ? get_post_status( $cic_editorial ) : '', 'reviewed' => $cic_editorial ? get_post_meta( $cic_editorial, Editorial::REVIEWED, true ) : '' );

try {
	file_put_contents(
		$cic_mu,
		"<?php\n// TEMPORARY test file (dev/tests/qa-20261026); deleted by the test.\n"
		. "if ( isset( \$_SERVER['HTTP_X_CIC_QA'] ) ) {\n"
		. "\t\$cic_q = explode( '|', (string) \$_SERVER['HTTP_X_CIC_QA'] );\n"
		. "\tif ( 2 === count( \$cic_q ) && hash_equals( '" . $cic_token . "', \$cic_q[0] ) ) {\n"
		. "\t\tif ( in_array( \$cic_q[1], array( 'prod', 'protect' ), true ) ) {\n"
		. "\t\t\tadd_filter( 'pre_option_blog_public', static fn() => '1' );\n"
		. "\t\t}\n"
		. "\t\tif ( 'prod' === \$cic_q[1] ) {\n"
		. "\t\t\tadd_filter( 'cic_seo_protect', '__return_false' );\n"
		. "\t\t}\n"
		. "\t\tif ( 'privacy' === \$cic_q[1] ) {\n"
		. "\t\t\tadd_filter( 'privacy_policy_url', static fn() => home_url( '/privacy-policy/' ) );\n"
		. "\t\t}\n"
		. "\t}\n}\n"
	);

	/* ---------------- Item 4: indexing ---------------- */
	$cic_pages = array( '/', '/treats/dippin-dots-catering/', '/events/birthday-party-ice-cream-catering/', '/events/graduation-party-ice-cream-catering/', '/service-areas/', '/service-areas/california/', '/service-areas/california/los-angeles-ice-cream-catering/' );
	$cic_bad   = array();
	foreach ( array( '' => 'local', 'protect' => 'staging copy' ) as $cic_mode => $cic_label ) {
		foreach ( $cic_pages as $cic_p ) {
			$cic_m = $cic_meta( $cic_head( $cic_get( $cic_p, $cic_mode )[1] ) );
			$cic_r = implode( ',', $cic_m['robots'] );
			if ( 1 !== count( $cic_m['robots'] ) || false === strpos( $cic_r, 'noindex' ) || false === strpos( $cic_r, 'nofollow' ) ) {
				$cic_bad[ $cic_label . ' ' . $cic_p ] = $cic_m['robots'];
			}
		}
	}
	$cic_check( 'I1 local, and a staging copy whose Reading setting allows indexing: every sampled page is "noindex, nofollow" (one robots tag)', ! $cic_bad, $cic_bad );

	$cic_expect = array(
		'/'                          => home_url( '/' ),
		'/treats/dippin-dots-catering/'       => home_url( '/treats/dippin-dots-catering/' ),
		'/events/birthday-party-ice-cream-catering/'          => home_url( '/events/birthday-party-ice-cream-catering/' ),
		'/events/graduation-party-ice-cream-catering/' => home_url( '/events/graduation-party-ice-cream-catering/' ),
		'/service-areas/'            => home_url( '/service-areas/' ),
		'/service-areas/california/' => home_url( '/service-areas/california/' ),
	);
	$cic_bad = array();
	foreach ( $cic_expect as $cic_p => $cic_url ) {
		$cic_m = $cic_meta( $cic_head( $cic_get( $cic_p, 'prod' )[1] ) );
		$cic_r = implode( ',', $cic_m['robots'] );
		if ( 1 !== count( $cic_m['robots'] ) || false === strpos( $cic_r, 'index' ) || false !== strpos( $cic_r, 'noindex' ) || false !== strpos( $cic_r, 'nofollow' ) || array( $cic_url ) !== $cic_m['canon'] || 1 !== count( $cic_m['title'] ) || 1 !== count( $cic_m['ld'] ) || count( $cic_m['og'] ) > 1 ) {
			$cic_bad[ $cic_p ] = array( 'robots' => $cic_m['robots'], 'canon' => $cic_m['canon'], 'titles' => count( $cic_m['title'] ), 'ld' => count( $cic_m['ld'] ), 'og' => count( $cic_m['og'] ) );
		}
	}
	$cic_check( 'I2 production (fixed Reading setting): marketing pages are indexable with exactly one canonical (own URL), one title, one robots tag and one schema graph', ! $cic_bad, $cic_bad );

	$cic_m = $cic_meta( $cic_head( $cic_get( '/service-areas/california/?county=los-angeles-county', 'prod' )[1] ) );
	$cic_check( 'I3 production: a filtered state-directory variant (?county=) stays noindex', 1 === count( $cic_m['robots'] ) && false !== strpos( $cic_m['robots'][0], 'noindex' ), $cic_m['robots'] );
	$cic_qh = $cic_head( $cic_get( '/get-a-quote/?received=abc', 'prod' )[1] );
	$cic_m  = $cic_meta( $cic_qh );
	// Since 2026-10-28 receipt/prefill variants are noindex (dev/tests/seo-20261028 I2); Yoast leaves
	// out the canonical on noindex pages, so a canonical, if any, must still be the clean URL.
	$cic_check( 'I4 production: one Yoast SEO block per page; a receipt URL is noindex and never canonicalises to itself (no query string)', 1 === substr_count( $cic_qh, 'This site is optimized with the Yoast SEO plugin' ) && 1 === count( $cic_m['robots'] ) && false !== strpos( $cic_m['robots'][0], 'noindex' ) && in_array( $cic_m['canon'], array( array(), array( home_url( '/get-a-quote/' ) ) ), true ), array( $cic_m['canon'], $cic_m['robots'] ) );

	/* ---------------- Item 5: location metadata ---------------- */
	$cic_m = $cic_meta( $cic_head( $cic_get( '/service-areas/california/', 'prod' )[1] ) );
	$cic_check( 'S1 California directory: specific title and its own canonical', array( 'Ice Cream Catering Service Areas in California | CaterIceCream.com' ) === $cic_m['title'] && array( home_url( '/service-areas/california/' ) ) === $cic_m['canon'] && 1 === count( $cic_m['desc'] ), $cic_m );

	$cic_titles = array();
	$cic_bad    = array();
	foreach ( array( 'los-angeles' => 'Los Angeles', 'camarillo' => 'Camarillo', 'santa-barbara' => 'Santa Barbara', 'pasadena' => 'Pasadena' ) as $cic_slug => $cic_name ) {
		$cic_m = $cic_meta( $cic_head( $cic_get( "/service-areas/california/{$cic_slug}-ice-cream-catering/", 'prod' )[1] ) );
		$cic_titles[] = $cic_m['title'][0] ?? '';
		$cic_types_c  = $cic_types( $cic_m['ld'] );
		if ( array( "Ice Cream Catering in {$cic_name}, CA | CaterIceCream.com" ) !== $cic_m['title'] || 1 !== count( $cic_m['desc'] ) || false === strpos( $cic_m['desc'][0], "{$cic_name}, CA" ) || array( "Ice Cream Catering in {$cic_name}, CA | CaterIceCream.com" ) !== $cic_m['og'] || 1 !== count( $cic_m['ld'] ) || ! array_intersect( array( 'WebPage' ), $cic_types_c ) || ! in_array( 'BreadcrumbList', $cic_types_c, true ) || ! in_array( 'Service', $cic_types_c, true ) ) {
			$cic_bad[ $cic_slug ] = $cic_m + array( 'types' => $cic_types_c );
		}
	}
	$cic_check( 'S2 the four audited city pages: unique "Ice Cream Catering in {City}, CA | CaterIceCream.com" titles, city descriptions, og:title, and WebPage + BreadcrumbList + Service inside Yoast’s single graph', ! $cic_bad && 4 === count( array_unique( $cic_titles ) ), $cic_bad );

	$cic_m = $cic_meta( $cic_head( $cic_get( '/service-areas/california/los-angeles-ice-cream-catering/', 'prod' )[1] ) );
	$cic_check( 'S3 an unreviewed city keeps the existing rule: "noindex, follow", no canonical (Yoast omits it on noindex pages)', 1 === count( $cic_m['robots'] ) && false !== strpos( $cic_m['robots'][0], 'noindex' ) && false === strpos( $cic_m['robots'][0], 'nofollow' ) && ! $cic_m['canon'], $cic_m['robots'] );

	// Indexable path: mark one published + Index city's content as reviewed for the run.
	if ( $cic_city ) {
		if ( ! $cic_editorial ) {
			$cic_editorial          = CityStore::ensure_editorial( (int) $cic_city['id'] );
			$cic_restore['created'] = true;
		}
		update_post_meta( $cic_editorial, Editorial::REVIEWED, '1' );
		wp_update_post( array( 'ID' => $cic_editorial, 'post_status' => 'publish' ) );
		Tables::bump_cache();
		$cic_url = home_url( "/service-areas/california/{$cic_city_slug}-ice-cream-catering/" );
		$cic_m   = $cic_meta( $cic_head( $cic_get( "/service-areas/california/{$cic_city_slug}-ice-cream-catering/", 'prod' )[1] ) );
		$cic_sm  = $cic_get( '/cic-locations-sitemap.xml', 'prod' );
		$cic_idx = $cic_get( '/sitemap_index.xml', 'prod' )[1];
		$cic_check( 'S4 a reviewed (published + Index) city: indexable, canonical = its own URL, listed in cic-locations-sitemap.xml, which is in Yoast’s sitemap index', 1 === count( $cic_m['robots'] ) && false === strpos( $cic_m['robots'][0], 'noindex' ) && array( $cic_url ) === $cic_m['canon'] && 200 === $cic_sm[0] && 1 === substr_count( $cic_sm[1], '<loc>' . $cic_url . '</loc>' ) && false === strpos( $cic_sm[1], '/los-angeles/' ) && false !== strpos( $cic_idx, home_url( '/cic-locations-sitemap.xml' ) ), array( $cic_m['robots'], $cic_m['canon'], $cic_sm[0], substr( $cic_sm[1], 0, 600 ) ) );
	} else {
		$cic_check( 'S4 test city found', false );
	}

	/* ---------------- Item 1: brand ---------------- */
	$cic_bad = array();
	foreach ( array( '/', '/about/', '/contact/', '/get-a-quote/', '/events/birthday-party-ice-cream-catering/', '/treats/dippin-dots-catering/', '/service-areas/california/los-angeles-ice-cream-catering/' ) as $cic_p ) {
		$cic_html = $cic_get( $cic_p, 'prod' )[1];
		$cic_text = preg_replace( array( '#<style.*?</style>#s', '#https?://[^\s"\'<>]+#' ), '', $cic_html );
		$cic_m    = $cic_meta( $cic_head( $cic_html ) );
		if ( preg_match( '/cater[ \t\-]+ice[ \t\-]*cream/i', (string) $cic_text, $cic_hit ) && 'cater-ice-cream' !== $cic_hit[0] || array( 'CaterIceCream.com' ) !== $cic_m['site'] || false === strpos( (string) ( $cic_m['title'][0] ?? '' ), 'CaterIceCream.com' ) ) {
			$cic_bad[ $cic_p ] = array( 'hit' => $cic_hit[0] ?? '', 'site' => $cic_m['site'], 'title' => $cic_m['title'] );
		}
	}
	$cic_check( 'B1 public pages: no "Cater Ice Cream" text; titles and og:site_name use CaterIceCream.com', ! $cic_bad, $cic_bad );
	$cic_home = $cic_get( '/' )[1];
	$cic_check( 'B2 footer copyright keeps the legal name with the new brand; logo alt text is CaterIceCream.com', (bool) preg_match( '/© \d{4} CaterIceCream\.com · Frosty Yummy/', $cic_home ) && 2 <= substr_count( $cic_home, 'alt="CaterIceCream.com"' ) );

	/* ---------------- Item 7: privacy links ---------------- */
	$cic_count = static fn( string $html ): int => substr_count( $html, '>Privacy Policy</a>' );
	$cic_none  = array_sum( array_map( static fn( $p ) => $cic_count( $cic_get( $p )[1] ), array( '/', '/contact/', '/get-a-quote/' ) ) );
	$cic_check( 'P1 the policy is still a draft: no Privacy Policy link anywhere (nothing unapproved is linked)', 0 === $cic_none && 'draft' === get_post_status( (int) get_option( 'wp_page_for_privacy_policy' ) ), $cic_none );
	$cic_c = $cic_get( '/contact/', 'privacy' )[1];
	$cic_q = $cic_get( '/get-a-quote/', 'privacy' )[1];
	$cic_footer = static fn( string $h ): string => (string) strstr( $h, '<footer class="cic-footer"' );
	$cic_check( 'P2 once a policy is published: one link in the footer legal row, one beside the Contact form notice, and in both quote notices (Step 1 consent line, note beside "Request My Quote")', 1 === $cic_count( $cic_footer( $cic_c ) ) && 2 === $cic_count( $cic_c ) && 3 === $cic_count( $cic_q ) && false !== strpos( $cic_q, 'By continuing, you agree that we may contact you about your event.' ), array( $cic_count( $cic_footer( $cic_c ) ), $cic_count( $cic_c ), $cic_count( $cic_q ) ) );

	/* ---------------- Item 8: Contact card ---------------- */
	$cic_steps = preg_match( '#<ol class="cic-contact-steps">(.*?)</ol>#s', $cic_get( '/contact/' )[1], $cic_mm ) ? wp_strip_all_tags( $cic_mm[1] ) : '';
	$cic_check( 'C1 Contact "Planning an event?" card: 1 Your details & favorites, 2 Your event; no "Your treats" / "Review & send"', false !== strpos( $cic_steps, 'Your details &amp; favorites' ) && false !== strpos( $cic_steps, 'Your event' ) && false === strpos( $cic_steps, 'Review' ) && false === strpos( $cic_steps, 'Your treats' ), $cic_steps );

	/* ---------------- Item 6: ZIP 90224 ---------------- */
	$cic_cov = static fn( string $zip ): string => (string) ( Locations::repository()->evaluate_coverage( $zip )['status'] ?? '' );
	add_filter( 'cic_location_fixtures_allowed', '__return_false' );
	$cic_live = array();
	foreach ( array( '90224', '90059', '90220', '90221', '90222' ) as $cic_z ) {
		$cic_live[ $cic_z ] = $cic_cov( $cic_z );
	}
	$cic_served = $cic_cov( '91101' );
	remove_filter( 'cic_location_fixtures_allowed', '__return_false' );
	$cic_check( 'Z1 production data only (as on live): 90224 and the four approved Compton ZIPs are outside_area; a served ZIP (91101) is unchanged', array( 'outside_area' ) === array_values( array_unique( $cic_live ) ) && 'covered' === $cic_served, array( $cic_live, $cic_served ) );
	// POST only by design (ZIP codes stay out of URLs).
	$cic_post = wp_remote_post( rest_url( 'cic/v1/coverage' ), array( 'sslverify' => false, 'timeout' => 45, 'body' => array( 'location' => '90224' ) ) );
	$cic_rest = json_decode( (string) wp_remote_retrieve_body( $cic_post ), true );
	$cic_val  = \CIC\Enquiries\Validator::quote( array( 'location' => '90224', 'location_detail' => '' ) );
	$cic_check( 'Z2 entry points: coverage REST answers outside_area for 90224, and the quote validator blocks it', 'outside_area' === ( $cic_rest['status'] ?? '' ) && isset( $cic_val['errors']['location'] ), array( $cic_rest, $cic_val['errors']['location'] ?? null ) );
	$cic_row = $wpdb->get_row( "SELECT service_status, dataset FROM {$wpdb->prefix}cic_zips WHERE zip = '90224'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$cic_check( 'Z3 90224 is an approved production exclusion record', array( 'service_status' => 'disabled', 'dataset' => 'production' ) === $cic_row, $cic_row );

	/* ---------------- Item 10: alt text ---------------- */
	$cic_bad = array();
	foreach ( array( '/', '/treats/dippin-dots-catering/', '/treats/dipn-bananas-catering/', '/treats/frozen-yogurt-catering/', '/events/birthday-party-ice-cream-catering/', '/events/wedding-ice-cream-catering/', '/events/graduation-party-ice-cream-catering/', '/service-areas/', '/contact/', '/get-a-quote/' ) as $cic_p ) {
		preg_match_all( '#<img[^>]+alt="([^"]*)"#', $cic_get( $cic_p )[1], $cic_alts );
		foreach ( $cic_alts[1] as $cic_alt ) {
			$cic_alt = html_entity_decode( $cic_alt );
			if ( mb_strlen( $cic_alt ) > 150 || preg_match( '/\b(NOT|TINY|MANY)\b/', $cic_alt ) || preg_match( '/photorealistic|photograph|macro|still life|no logos/i', $cic_alt ) ) {
				$cic_bad[] = $cic_p . ': ' . mb_substr( $cic_alt, 0, 80 );
			}
		}
	}
	$cic_check( 'A1 ten public pages: no image alt text with generation prompts ("NOT …", "TINY", "photograph", …) or over 150 characters', ! $cic_bad, array_slice( $cic_bad, 0, 5 ) );
} finally {
	if ( file_exists( $cic_mu ) ) {
		wp_delete_file( $cic_mu );
	}
	if ( $cic_editorial ) {
		if ( $cic_restore['created'] ) {
			wp_delete_post( $cic_editorial, true );
			// ensure_editorial() also wrote the city_content link row: put back exactly what was there.
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
WP_CLI::log( sprintf( 'QA server checks: %d/%d passed; temporary MU plugin removed: %s; test city restored: %s', $cic_pass, count( $cic_results ), file_exists( $cic_mu ) ? 'NO' : 'yes', $cic_restore['created'] ? 'editorial post deleted' : 'meta/status restored' ) );
