<?php
/**
 * Google Analytics 4 integration checks (2026-10-25).
 *
 * 1. Rules (in process): status() for each environment/host/setting combination, settings
 *    sanitisation, and the printed snippet against the approved gtag.js snippet.
 * 2. This local site as it is (real HTTP requests): no Google tag on any page.
 * 3. Simulated production (real HTTP requests): a temporary MU plugin
 *    (wp-content/mu-plugins/zz-cic-analytics-test.php, git-ignored) filters
 *    cic_analytics_environment ONLY for requests carrying a random per-run header. Eligible pages
 *    contain exactly one installation inside <head>; staging, other hosts, logged-in
 *    administrators, the login page, REST and feeds contain none. No request is made to Google
 *    (the HTML is only fetched and inspected, never executed).
 *
 * Restores the cic_analytics option, removes the temporary MU plugin and destroys the temporary
 * administrator session in `finally`.
 *
 * Usage: wp eval-file dev/tests/analytics/analytics-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Analytics\Service;
use CIC\Analytics\Settings;

$cic_results = array();
$cic_check   = static function ( string $label, bool $ok, $detail = null ) use ( &$cic_results ): void {
	$cic_results[] = $ok;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && null !== $detail ? ' — ' . substr( wp_json_encode( $detail ), 0, 600 ) : '' ) );
};

$cic_id       = 'G-NXRRDRV5C2';
$cic_saved    = get_option( Settings::OPTION, '__absent__' );
$cic_mu       = WPMU_PLUGIN_DIR . '/zz-cic-analytics-test.php';
$cic_token    = wp_generate_password( 32, false );
$cic_admin    = (int) ( get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 1 ) )[0] ?? 0 );
$cic_session  = '';
$cic_env_hook = null;

$cic_get = static function ( string $path, array $headers = array() ): array {
	$r = wp_remote_get( home_url( $path ), array( 'sslverify' => false, 'timeout' => 30, 'redirection' => 0, 'headers' => array_merge( array( 'Cache-Control' => 'no-cache' ), $headers ) ) );
	return is_wp_error( $r ) ? array( 0, '' ) : array( (int) wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_body( $r ) );
};
// Every trace of a Google tag: the loader URL, the config call and the ID itself.
$cic_count = static function ( string $html ) use ( $cic_id ): array {
	return array(
		'loader' => substr_count( $html, 'googletagmanager.com/gtag/js' ),
		'config' => substr_count( $html, "gtag('config'" ),
		'id'     => substr_count( $html, $cic_id ),
		'gtm'    => preg_match_all( '/GTM-[A-Z0-9]+|googletagmanager\.com\/gtm\.js/', $html ),
	);
};
$cic_sim = static function ( string $type, string $home, string $request ) use ( &$cic_token ): array {
	return array( 'X-CIC-Analytics-Test' => $cic_token . '|' . $type . '|' . $home . '|' . $request );
};
$cic_force_env = static function ( string $type, string $home, string $request ) use ( &$cic_env_hook ): void {
	if ( $cic_env_hook ) {
		remove_filter( 'cic_analytics_environment', $cic_env_hook );
	}
	$cic_env_hook = static fn() => array( 'type' => $type, 'home_host' => $home, 'request_host' => $request );
	add_filter( 'cic_analytics_environment', $cic_env_hook );
};

try {
	update_option( Settings::OPTION, array( 'enabled' => 1, 'measurement_id' => $cic_id ) );

	/* 1. Rules. */
	$cic_check( 'R1 this local site is not eligible (environment ' . wp_get_environment_type() . ')', 'environment' === Service::status( false )['reason'], Service::status( false ) );
	$cic_matrix = array(
		array( 'production', 'catericecream.com', 'catericecream.com', 'active' ),
		array( 'production', 'www.catericecream.com', 'www.catericecream.com', 'active' ),
		array( 'production', 'catericecream.com', 'www.catericecream.com', 'active' ),
		array( 'staging', 'catericecream.com', 'catericecream.com', 'environment' ),
		array( 'development', 'catericecream.com', 'catericecream.com', 'environment' ),
		array( 'local', 'catericecream.com', 'catericecream.com', 'environment' ),
		array( 'production', 'catericecream.test', 'catericecream.test', 'host' ),
		array( 'production', 'localhost', 'localhost', 'host' ),
		array( 'production', 'staging.catericecream.com', 'staging.catericecream.com', 'host' ),
		array( 'production', 'catericecream.com', 'catericecream.test', 'host' ),
		array( 'production', 'catericecream.com', '127.0.0.1', 'host' ),
		array( 'production', 'catericecream.com.evil.test', 'catericecream.com.evil.test', 'host' ),
	);
	$cic_bad = array();
	foreach ( $cic_matrix as $cic_row ) {
		$cic_force_env( $cic_row[0], $cic_row[1], $cic_row[2] );
		$cic_got = Service::status( false )['reason'];
		if ( $cic_got !== $cic_row[3] ) {
			$cic_bad[] = array( $cic_row, $cic_got );
		}
	}
	$cic_check( 'R2 only production on catericecream.com / www.catericecream.com is eligible (' . count( $cic_matrix ) . ' combinations)', ! $cic_bad, $cic_bad );

	$cic_force_env( 'production', 'catericecream.com', 'catericecream.com' );
	$cic_check( 'R3 WP-CLI is never a front-end page (request check)', 'context' === Service::status( true )['reason'], Service::status( true ) );
	update_option( Settings::OPTION, array( 'enabled' => 0, 'measurement_id' => $cic_id ) );
	$cic_check( 'R4 switched off → not eligible even on production', 'disabled' === Service::status( false )['reason'] );
	update_option( Settings::OPTION, array( 'enabled' => 1, 'measurement_id' => 'UA-12345-1' ) );
	$cic_check( 'R5 invalid stored ID → not eligible and no snippet', 'no_id' === Service::status( false )['reason'] && '' === Service::snippet( 'UA-12345-1' ) );
	update_option( Settings::OPTION, array( 'enabled' => 1, 'measurement_id' => $cic_id ) );
	remove_filter( 'cic_analytics_environment', $cic_env_hook );
	$cic_env_hook = null;

	$cic_s1 = Settings::sanitize( array( 'enabled' => '1', 'measurement_id' => ' g-nxrrdrv5c2 ' ) );
	$cic_s2 = Settings::sanitize( array( 'enabled' => '1', 'measurement_id' => '<script>alert(1)</script>' ) );
	$cic_s3 = Settings::sanitize( array() );
	$cic_check( 'R6 sanitise: ID trimmed/uppercased, invalid ID keeps the previous value, unchecked = off', $cic_id === $cic_s1['measurement_id'] && 1 === $cic_s1['enabled'] && $cic_id === $cic_s2['measurement_id'] && 0 === $cic_s3['enabled'], array( $cic_s1, $cic_s2, $cic_s3 ) );

	$cic_approved = "<!-- Google tag (gtag.js) -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-NXRRDRV5C2\"></script>\n<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  gtag('config', 'G-NXRRDRV5C2');\n</script>\n";
	$cic_snip     = Service::snippet( $cic_id );
	$cic_lines    = explode( "\n", $cic_snip );
	$cic_redact   = array_values( array_filter( $cic_lines, static fn( $l ) => false !== strpos( $l, "gtag('set'" ) ) );
	$cic_without  = implode( "\n", array_filter( $cic_lines, static fn( $l ) => false === strpos( $l, "gtag('set'" ) ) );
	$cic_check( 'R7 snippet = the approved snippet plus one receipt-redaction line', $cic_approved === $cic_without && 1 === count( $cic_redact ), $cic_snip );
	$cic_check( 'R8 redaction line sits after gtag(\'js\') and before gtag(\'config\'), removes received/sent and sets no consent', array_search( $cic_redact[0] ?? '', $cic_lines, true ) === 6 && false !== strpos( $cic_redact[0] ?? '', "delete('received')" ) && false !== strpos( $cic_redact[0] ?? '', "delete('sent')" ) && false === stripos( $cic_snip, 'consent' ) );

	/* 2. This local site, unchanged: no tag anywhere. */
	$cic_pages = array( '/', '/treats/dippin-dots/', '/treats/dipn-bananas/', '/events/birthday/', '/events/wedding/', '/get-a-quote/', '/get-a-quote/?received=abc123', '/contact/', '/about/', '/service-areas/', '/service-areas/california/agoura-hills/', '/no-such-page-analytics/' );
	$cic_local = array();
	foreach ( $cic_pages as $cic_p ) {
		list( $cic_code, $cic_html ) = $cic_get( $cic_p );
		$cic_c = $cic_count( $cic_html );
		if ( '' === $cic_html || array_sum( $cic_c ) > 0 ) {
			$cic_local[ $cic_p ] = array( $cic_code, $cic_c );
		}
	}
	$cic_check( 'L1 catericecream.test (local): no Google tag, GTM or ID on ' . count( $cic_pages ) . ' pages', ! $cic_local, $cic_local );

	/* 3. Simulated production. */
	file_put_contents(
		$cic_mu,
		"<?php\n// TEMPORARY test file (dev/tests/analytics); deleted by the test. Simulates the Analytics environment only for requests with the per-run header.\n"
		. "if ( isset( \$_SERVER['HTTP_X_CIC_ANALYTICS_TEST'] ) ) {\n"
		. "\t\$cic_t = explode( '|', (string) \$_SERVER['HTTP_X_CIC_ANALYTICS_TEST'] );\n"
		. "\tif ( 4 === count( \$cic_t ) && hash_equals( '" . $cic_token . "', \$cic_t[0] ) ) {\n"
		. "\t\tadd_filter( 'cic_analytics_environment', static fn() => array( 'type' => \$cic_t[1], 'home_host' => \$cic_t[2], 'request_host' => \$cic_t[3] ) );\n"
		. "\t}\n}\n"
	);
	$cic_prod = $cic_sim( 'production', 'catericecream.com', 'catericecream.com' );
	$cic_one  = array();
	foreach ( $cic_pages as $cic_p ) {
		list( $cic_code, $cic_html ) = $cic_get( $cic_p, $cic_prod );
		$cic_c    = $cic_count( $cic_html );
		$cic_head = (string) strstr( $cic_html, '</head>', true );
		$cic_ok   = 1 === $cic_c['loader'] && 1 === $cic_c['config'] && 2 === $cic_c['id'] && 0 === $cic_c['gtm'] && 1 === substr_count( $cic_html, 'window.dataLayer = window.dataLayer || []' ) && false !== strpos( $cic_head, "gtag('config', 'G-NXRRDRV5C2');" ) && false !== strpos( $cic_head, '<!-- Google tag (gtag.js) -->' );
		if ( ! $cic_ok ) {
			$cic_one[ $cic_p ] = array( $cic_code, $cic_c );
		}
	}
	$cic_check( 'P1 simulated production: exactly one installation inside <head> on ' . count( $cic_pages ) . ' pages (incl. 404 and receipt URL)', ! $cic_one, $cic_one );

	list( , $cic_home ) = $cic_get( '/', $cic_prod );
	$cic_check( 'P2 the printed tag is exactly Service::snippet()', false !== strpos( $cic_home, $cic_snip ) );

	$cic_none = array();
	$cic_cases = array(
		'www host'             => array( $cic_sim( 'production', 'www.catericecream.com', 'www.catericecream.com' ), 1 ),
		'staging environment'  => array( $cic_sim( 'staging', 'catericecream.com', 'catericecream.com' ), 0 ),
		'development'          => array( $cic_sim( 'development', 'catericecream.com', 'catericecream.com' ), 0 ),
		'.test request host'   => array( $cic_sim( 'production', 'catericecream.com', 'catericecream.test' ), 0 ),
		'staging host'         => array( $cic_sim( 'production', 'staging.catericecream.com', 'staging.catericecream.com' ), 0 ),
		'localhost'            => array( $cic_sim( 'production', 'localhost', 'localhost' ), 0 ),
		'wrong token'          => array( array( 'X-CIC-Analytics-Test' => 'nope|production|catericecream.com|catericecream.com' ), 0 ),
	);
	foreach ( $cic_cases as $cic_label => $cic_case ) {
		foreach ( array( '/', '/get-a-quote/', '/treats/dippin-dots/' ) as $cic_p ) {
			list( , $cic_html ) = $cic_get( $cic_p, $cic_case[0] );
			$cic_c = $cic_count( $cic_html );
			if ( '' === $cic_html || $cic_c['loader'] !== $cic_case[1] || $cic_c['config'] !== $cic_case[1] ) {
				$cic_none[ $cic_label . ' ' . $cic_p ] = $cic_c;
			}
		}
	}
	$cic_check( 'P3 www host has one installation; staging, development, .test, staging host, localhost and an unknown header have none', ! $cic_none, $cic_none );

	$cic_other = array();
	foreach ( array( '/wp-login.php', '/wp-json/', '/feed/', '/wp-json/cic/v1/location?q=agoura' ) as $cic_p ) {
		list( $cic_code, $cic_html ) = $cic_get( $cic_p, $cic_prod );
		if ( array_sum( $cic_count( $cic_html ) ) > 0 ) {
			$cic_other[ $cic_p ] = $cic_code;
		}
	}
	$cic_check( 'P4 simulated production: login page, REST and feed contain no tag', ! $cic_other, $cic_other );

	if ( $cic_admin ) {
		$cic_exp     = time() + 600;
		$cic_session = WP_Session_Tokens::get_instance( $cic_admin )->create( $cic_exp );
		$cic_cookie  = LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $cic_admin, $cic_exp, 'logged_in', $cic_session ) );
		$cic_admin_r = array();
		foreach ( array( '/', '/get-a-quote/', '/treats/dippin-dots/' ) as $cic_p ) {
			list( , $cic_html ) = $cic_get( $cic_p, array_merge( $cic_prod, array( 'Cookie' => $cic_cookie ) ) );
			$cic_logged = false !== strpos( $cic_html, 'id="wpadminbar"' );
			$cic_c      = $cic_count( $cic_html );
			if ( ! $cic_logged || array_sum( $cic_c ) > 0 ) {
				$cic_admin_r[ $cic_p ] = array( 'admin_bar' => $cic_logged, 'tags' => $cic_c );
			}
		}
		$cic_check( 'P5 simulated production, logged-in administrator (admin bar present): no tag', ! $cic_admin_r, $cic_admin_r );

		// The settings screen (real local environment, no simulation header).
		$cic_admin_cookie = SECURE_AUTH_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $cic_admin, $cic_exp, 'secure_auth', $cic_session ) ) . '; ' . $cic_cookie;
		list( $cic_code, $cic_html ) = $cic_get( '/wp-admin/admin.php?page=' . Settings::PAGE, array( 'Cookie' => $cic_admin_cookie ) );
		$cic_check( 'A1 Cater Ice Cream → Analytics renders for an administrator with the saved ID, the on/off box and the local status', 200 === $cic_code && false !== strpos( $cic_html, 'value="' . $cic_id . '"' ) && false !== strpos( $cic_html, 'name="cic_analytics[enabled]"' ) && false !== strpos( $cic_html, 'Off on this site: the environment is' ) && false === strpos( $cic_html, 'googletagmanager.com/gtag/js' ), array( $cic_code, substr( wp_strip_all_tags( $cic_html ), 0, 300 ) ) );
	} else {
		$cic_check( 'P5 an administrator account exists for the exclusion check', false );
	}
} finally {
	if ( $cic_env_hook ) {
		remove_filter( 'cic_analytics_environment', $cic_env_hook );
	}
	if ( file_exists( $cic_mu ) ) {
		wp_delete_file( $cic_mu );
	}
	if ( $cic_session ) {
		WP_Session_Tokens::get_instance( $cic_admin )->destroy( $cic_session );
	}
	if ( '__absent__' === $cic_saved ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $cic_saved );
	}
}

$cic_pass = count( array_filter( $cic_results ) );
WP_CLI::log( sprintf( 'Analytics checks: %d/%d passed', $cic_pass, count( $cic_results ) ) );
WP_CLI::log( 'Temporary MU plugin removed: ' . ( file_exists( $cic_mu ) ? 'NO' : 'yes' ) . '; option restored: ' . wp_json_encode( get_option( Settings::OPTION, '(absent)' ) ) );
