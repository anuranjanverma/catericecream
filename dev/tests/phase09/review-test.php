<?php
/**
 * Phase 09 server checks (local only): regressions for the defects fixed in the visual,
 * accessibility and performance review, plus the "do not change" constraints. Read-only.
 *
 *   wp eval-file dev/tests/phase09/review-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;
'local' === wp_get_environment_type() || WP_CLI::error( 'Local environment only.' );

$GLOBALS['p9'] = array();
function p9_check( string $label, bool $ok, $detail = null ): void {
	$GLOBALS['p9'][] = $ok;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && null !== $detail ? ' — ' . wp_json_encode( $detail ) : '' ) );
}
function p9_get( string $url ): array {
	$r = wp_remote_get( $url, array( 'sslverify' => false, 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'User-Agent' => 'Mozilla/5.0 CIC-Phase09' ) ) );
	return is_wp_error( $r ) ? array( 'code' => 0, 'body' => '', 'cookies' => array() ) : array( 'code' => wp_remote_retrieve_response_code( $r ), 'body' => wp_remote_retrieve_body( $r ), 'cookies' => (array) wp_remote_retrieve_header( $r, 'set-cookie' ) );
}
function p9_lum( string $hex ): float {
	$c = array_map( static fn( $x ) => hexdec( $x ) / 255, str_split( ltrim( $hex, '#' ), 2 ) );
	$c = array_map( static fn( $v ) => $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4, $c );
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function p9_ratio( string $a, string $b ): float {
	$x = p9_lum( $a );
	$y = p9_lum( $b );
	return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
}

// R1 field keys are unique per type (a shared key with different types breaks admin saves).
$types = array();
foreach ( array( 'home_group', 'hub_group', 'shop_group', 'about_group', 'contact_group', 'quote_group', 'event_group', 'treat_group', 'city_group', 'policy_group' ) as $g ) {
	foreach ( call_user_func( array( 'CIC\\Content\\Fields', $g ) )['fields'] as $f ) {
		if ( ! in_array( $f['type'], array( 'tab', 'message' ), true ) ) {
			$types[ $f['key'] ][ $f['type'] ] = true;
		}
	}
}
$conflicts = array_keys( array_filter( $types, static fn( $t ) => count( $t ) > 1 ) );
p9_check( 'R1 no ACF field key is defined with different field types (the FAQ heading conflict is gone)', ! $conflicts, $conflicts );
// Browsers submit textarea line breaks as CRLF, so an admin save stores "\r\n"; both are valid.
p9_check( 'R2 homepage FAQ heading keeps its line break (no glued "youmight")', (bool) preg_match( "/you\r?\nmight/", (string) get_post_meta( (int) get_option( 'page_on_front' ), 'cic_faq_heading', true ) ) );

// R3 link colour contrast on every surface token.
$colors  = CIC\Design\Schema::colors();
$roles   = CIC\Design\Schema::brand_roles();
$link    = $colors[ $roles['link'] ]['default'];
$surface = array( 'surface_base', 'surface_soft', 'birthday_surface', 'wedding_ivory', 'wedding_blush', 'treat_blush', 'treat_mint', 'treat_sky' );
$low     = array();
foreach ( $surface as $s ) {
	$r = p9_ratio( $link, $colors[ $s ]['default'] );
	if ( $r < 4.5 ) {
		$low[ $s ] = round( $r, 2 );
	}
}
p9_check( 'R3 the default link colour (' . $link . ') reaches 4.5:1 on every surface token; buttons keep the primary action pink', ! $low && '#D92373' === $colors[ $roles['action'] ]['default'], $low );

// R4–R6 public pages: no unused commerce scripts, lightbox markup or tracking cookies.
$pages  = array( '/', '/events/', '/events/wedding/', '/treats/', '/treats/dippin-dots/', '/service-areas/california/pasadena/', '/about/', '/contact/', '/get-a-quote/' );
$found  = array();
$pswp   = array();
$cookie = array();
foreach ( $pages as $p ) {
	$r = p9_get( home_url( $p ) );
	if ( preg_match_all( '#<script[^>]+src=["\'][^"\']*(woocommerce\.min|jquery\.blockUI|js\.cookie|sourcebuster|order-attribution|flexslider|photoswipe|zoom/jquery\.zoom|single-product)[^"\']*["\']#i', $r['body'], $m ) ) {
		$found[ $p ] = array_unique( $m[1] );
	}
	if ( str_contains( $r['body'], 'class="pswp' ) ) {
		$pswp[] = $p;
	}
	if ( array_filter( $r['cookies'] ) ) {
		$cookie[ $p ] = count( $r['cookies'] );
	}
}
p9_check( 'R4 no WooCommerce front-end, order-attribution or gallery scripts on public pages (catalogue mode)', ! $found, $found );
p9_check( 'R5 no unused lightbox markup on treat pages', ! $pswp, $pswp );
p9_check( 'R6 anonymous public pages set no cookies', ! $cookie, $cookie );

// R7 mascot uses small resampled copies.
$about = p9_get( home_url( '/about/' ) )['body'];
p9_check( 'R7 decorative mascot images offer 160/320/640w sources with a size hint', (bool) preg_match( '#mascot-original-160\.webp 160w.*mascot-original\.webp 640w" sizes="\d+px"#', $about ) && file_exists( get_template_directory() . '/assets/brand/mascot-original-160.webp' ) );

// R8 constraints that the review must not change.
$policies = array_map( static fn( $k ) => get_post_status( CIC\Content\Catalog::find_by_key( 'page', $k ) ), array( 'privacy-policy', 'website-terms' ) );
global $wpdb;
$prod = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . CIC\Locations\Tables::name( 'zips' ) . " WHERE dataset = 'production'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
p9_check( 'R8 unchanged: policies stay drafts, reminders stay off, coverage data is still fixtures only, header logo 190/136 px', array( 'draft', 'draft' ) === $policies && ! CIC\Enquiries\Settings::reminders_ready() && 0 === $prod && 190 === (int) CIC\Site\Layout::get()['main']['logo_width'] && 136 === (int) CIC\Site\Layout::get()['main']['logo_width_mobile'], array( $policies, $prod ) );

WP_CLI::log( sprintf( 'Phase 09 server checks: %d/%d passed', count( array_filter( $GLOBALS['p9'] ) ), count( $GLOBALS['p9'] ) ) );
