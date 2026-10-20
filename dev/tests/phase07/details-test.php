<?php
/**
 * Phase 07 server-side checks for the eight detail pages: composition section order, distinct
 * composition markup, markup rules, internal links, contextual quote actions (no false success),
 * copy rules, shared pairings, catalogue protection on treat pages and idempotent seeding.
 * Read-only apart from a seeder re-run that must change nothing.
 *
 * Usage: wp eval-file dev/tests/phase07/details-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Content\Catalog;
use CIC\Content\Fields;
use CIC\Content\Pairings;
use CIC\Content\ViewModel;

global $cic7;
$cic7 = array( 'pass' => 0, 'fail' => 0 );

function cic7_check( string $label, bool $ok, $detail = '' ): void {
	global $cic7;
	$cic7[ $ok ? 'pass' : 'fail' ]++;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && '' !== $detail ? ' — ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' ) );
}

/** @return array{code: int, body: string} */
function cic7_get( string $url ): array {
	$r = wp_remote_get( $url, array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 30, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
	return is_wp_error( $r ) ? array( 'code' => 0, 'body' => $r->get_error_message() ) : array( 'code' => (int) wp_remote_retrieve_response_code( $r ), 'body' => (string) wp_remote_retrieve_body( $r ) );
}

/** The page's <main> content only (header/footer excluded). */
function cic7_main( string $html ): string {
	return preg_match( '#<main\b.*?</main>#s', $html, $m ) ? $m[0] : $html;
}

$events = array( 'birthday', 'wedding', 'anniversary', 'corporate' );
$treats = array( 'dippin-dots', 'dipn-bananas', 'classic-ice-cream', 'frozen-yogurt' );
$pages  = array();
foreach ( $events as $key ) {
	$pages[ $key ] = array( 'type' => 'cic_event', 'id' => Catalog::find_by_key( 'cic_event', $key ), 'path' => '/events/' . $key . '/' );
}
foreach ( $treats as $key ) {
	$pages[ $key ] = array( 'type' => 'product', 'id' => Catalog::find_by_key( 'product', $key ), 'path' => '/treats/' . $key . '/' );
}
$html = array();
foreach ( $pages as $key => $p ) {
	$r            = cic7_get( home_url( $p['path'] ) );
	$html[ $key ] = $r['body'];
	$pages[ $key ]['code'] = $r['code'];
}

/* ---------------- D1 composition order ---------------- */
cic7_check( 'D1.1 all eight detail pages return 200', ! array_filter( $pages, static fn( $p ) => 200 !== $p['code'] ), wp_list_pluck( $pages, 'code' ) );
$expected = array(
	'birthday'          => 'hero,starter,treats,stories,planning,quote',
	'wedding'           => 'hero,ribbon,feature,treats,gallery,planning,quote',
	'anniversary'       => 'hero,values,treats,feature,planning,quote',
	'corporate'         => 'hero,values,treats,feature,planning,quote',
	'dippin-dots'       => 'hero,feelings,occasions,themes,detail,quote',
	'dipn-bananas'      => 'hero,feelings,occasions,themes,detail,faq,quote',
	'classic-ice-cream' => 'hero,feelings,occasions,themes,detail,quote',
	'frozen-yogurt'     => 'hero,feelings,themes,occasions,detail,quote',
);
$got = array();
foreach ( $pages as $key => $p ) {
	$vm          = 'cic_event' === $p['type'] ? ViewModel::event( $p['id'] ) : ViewModel::treat( $p['id'] );
	$got[ $key ] = implode( ',', wp_list_pluck( $vm['sections'], 'key' ) );
}
cic7_check( 'D1.2 every page renders its own approved section set and order (05–07, 09–12)', $expected === $got, array_diff_assoc( $got, $expected ) );
$rendered = array();
foreach ( $pages as $key => $p ) {
	preg_match_all( '/<section class="cic-comp cic-(?:event|treat)__([a-z]+)/', $html[ $key ], $m );
	$rendered[ $key ] = implode( ',', $m[1] );
}
cic7_check( 'D1.3 the rendered HTML follows the same order', $expected === $rendered, array_diff_assoc( $rendered, $expected ) );

/* ---------------- D2 distinct compositions ---------------- */
$markers = array(
	'birthday'          => array( 'cic-collage--3', 'cic-confetti' ),
	'wedding'           => array( 'cic-wed-hero', 'cic-motif--rings', 'cic-ribbon__list', 'cic-feature--wedding', 'cic-gallery__photo', 'cic-qpanel--wedding' ),
	'anniversary'       => array( 'cic-split-hero', 'cic-motif--hearts', 'cic-ev-values__list', 'cic-feature--split', 'cic-qpanel--anniversary' ),
	'corporate'         => array( 'cic-split-hero', 'cic-motif--strokes', 'cic-ev-values__list', 'cic-motif--people-line', 'cic-qpanel--corporate' ),
	'dippin-dots'       => array( 'cic-dots-decor', 'cic-pairing-grid', 'cic-swatches' ),
	'dipn-bananas'      => array( 'cic-pairing-grid', 'cic-swatches', 'cic-faq-split__mascot' ),
	'classic-ice-cream' => array( 'cic-classic-hero__card', 'cic-occ-rows', 'cic-treat-close--classic' ),
	'frozen-yogurt'     => array( 'cic-yogurt-hero__product', 'cic-occ-cards', 'cic-theme-card__icon', 'cic-treat-close--trio' ),
);
$missing = array();
foreach ( $markers as $key => $list ) {
	foreach ( $list as $marker ) {
		if ( ! str_contains( $html[ $key ], $marker ) ) {
			$missing[] = "{$key}: {$marker}";
		}
	}
}
cic7_check( 'D2.1 each page carries its own composition markup (not one reordered template)', ! $missing, $missing );
$confetti = array_keys( array_filter( $html, static fn( $h ) => str_contains( cic7_main( $h ), 'cic-confetti' ) ) );
$gold     = array_keys( array_filter( $html, static fn( $h ) => str_contains( cic7_main( $h ), 'cic-motif--rings' ) ) );
cic7_check( 'D2.2 confetti only on Birthday; ring motif only on Wedding', array( 'birthday' ) === $confetti && array( 'wedding' ) === $gold, compact( 'confetti', 'gold' ) );
$presets = array();
foreach ( array( 'wedding' => 'wedding', 'anniversary' => 'anniversary', 'corporate' => 'corporate', 'dipn-bananas' => 'banana', 'classic-ice-cream' => 'classic', 'frozen-yogurt' => 'yogurt' ) as $key => $ctx ) {
	if ( ! str_contains( $html[ $key ], 'cic-context-' . $ctx ) ) {
		$presets[] = $key;
	}
}
cic7_check( 'D2.3 each page uses its occasion/treat preset from the Design System', ! $presets, $presets );

/* ---------------- D3 markup rules ---------------- */
$bad = array();
foreach ( $html as $key => $h ) {
	$main = cic7_main( $h );
	if ( 1 !== preg_match_all( '/<h1\b/', $h ) ) {
		$bad[] = "{$key}: h1 count";
	}
	preg_match_all( '/<img\b[^>]*>/', $main, $imgs );
	foreach ( $imgs[0] as $img ) {
		if ( ! preg_match( '/\bwidth="\d+"/', $img ) || ! preg_match( '/\bheight="\d+"/', $img ) || ! preg_match( '/\balt="/', $img ) ) {
			$bad[] = "{$key}: img attrs " . substr( $img, 0, 80 );
		}
	}
	if ( 1 < preg_match_all( '/fetchpriority="high"/', $h ) ) {
		$bad[] = "{$key}: more than one high-priority image";
	}
	if ( preg_match( '/<img[^>]+(screenshot|designs\/|\d\d_[a-z_]+\.png)/i', $main ) ) {
		$bad[] = "{$key}: mockup image embedded";
	}
}
cic7_check( 'D3.1 one h1, images with width/height/alt, at most one high-priority image, no embedded mockups', ! $bad, array_slice( $bad, 0, 8 ) );
$svg = array_keys( array_filter( $html, static fn( $h ) => preg_match( '/class="cic-motif[^"]*"[^>]*aria-hidden="true"/', $h ) || ! str_contains( $h, 'cic-motif' ) ) );
cic7_check( 'D3.2 decorative motifs are inline SVG hidden from assistive technology', 8 === count( $svg ), $svg );

/* ---------------- D4 internal links ---------------- */
$links = array();
foreach ( $html as $key => $h ) {
	preg_match_all( '/<a\b[^>]*href="([^"]+)"/', cic7_main( $h ), $m );
	foreach ( $m[1] as $href ) {
		$href = html_entity_decode( $href );
		if ( str_starts_with( $href, '#' ) || str_starts_with( $href, 'tel:' ) || str_starts_with( $href, 'mailto:' ) ) {
			continue;
		}
		$links[ $href ][] = $key;
	}
}
$external = array_filter( array_keys( $links ), static fn( $u ) => wp_parse_url( $u, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) );
$broken   = array();
foreach ( array_keys( $links ) as $url ) {
	if ( in_array( $url, $external, true ) ) {
		continue;
	}
	$code = cic7_get( $url )['code'];
	if ( 200 !== $code ) {
		$broken[] = $url . ' → ' . $code;
	}
}
cic7_check( 'D4.1 related product/event links in the page content are internal', ! $external, array_values( $external ) );
cic7_check( 'D4.2 every internal content link resolves (200) (' . count( $links ) . ' unique)', ! $broken, $broken );
$cross = array();
foreach ( $treats as $t ) {
	foreach ( $events as $e ) {
		if ( ! str_contains( cic7_main( $html[ $t ] ), 'href="' . home_url( '/events/' . $e . '/' ) . '"' ) ) {
			$cross[] = "{$t}→{$e}";
		}
	}
}
foreach ( array( 'wedding', 'anniversary', 'corporate' ) as $e ) {
	foreach ( $treats as $t ) {
		if ( ! str_contains( cic7_main( $html[ $e ] ), 'href="' . home_url( '/treats/' . $t . '/' ) . '"' ) ) {
			$cross[] = "{$e}→{$t}";
		}
	}
}
cic7_check( 'D4.3 every treat links all four occasions; every new event links all four treats', ! $cross, $cross );

/* ---------------- D5 quote actions ---------------- */
$qa = array();
foreach ( $pages as $key => $p ) {
	$param = 'cic_event' === $p['type'] ? 'event' : 'treat';
	if ( ! preg_match( '/href="[^"]*\/get-a-quote\/\?' . $param . '=' . preg_quote( $key, '/' ) . '"/', $html[ $key ] ) ) {
		$qa[] = "{$key}: hero quote link";
	}
	if ( ! preg_match( '/<form[^>]+action="[^"]*\/get-a-quote\/"[^>]*data-cic-quote-form/', $html[ $key ] ) ) {
		$qa[] = "{$key}: starter form";
	}
	if ( ! preg_match( '/<input type="hidden" name="' . $param . '" value="' . preg_quote( $key, '/' ) . '" data-cic-context="' . $param . '" data-cic-fixed>/', $html[ $key ] ) ) {
		$qa[] = "{$key}: fixed {$param} in starter";
	}
	if ( preg_match( '/<form[^>]+data-cic-quote-form[^>]*>.*?<\/form>/s', $html[ $key ], $form ) && preg_match( '/name="(name|email|phone|message|notes|address)"/', $form[0] ) ) {
		$qa[] = "{$key}: personal field in starter";
	}
}
cic7_check( 'D5.1 every page: Plan/quote link carries its event/treat; starter forms go to the quote route with it fixed; no personal fields', ! $qa, $qa );
$route = cic7_get( home_url( '/get-a-quote/?event=wedding&location=91101&guests=40' ) )['body'];
$route2 = cic7_get( home_url( '/get-a-quote/?treat=frozen-yogurt&event=corporate' ) )['body'];
cic7_check( 'D5.2 quote route shows the carried selections and claims no submission (Phase 06 pending)', str_contains( $route, 'Weddings' ) && str_contains( $route, '91101' ) && str_contains( $route2, 'Frozen Yogurt' ) && str_contains( $route2, 'Corporate Events' ) && ! preg_match( '/thank you|received|submitted|booked|confirmed your/i', wp_strip_all_tags( cic7_main( $route . $route2 ) ) ) );
cic7_check( 'D5.3 no second form system: no CF7/Flamingo or other form plugin output on detail pages', ! array_filter( $html, static fn( $h ) => preg_match( '/wpcf7|class="gform|wpforms|nf-form/', $h ) ) );

/* ---------------- D6 copy rules ---------------- */
$copy = array();
foreach ( $html as $key => $h ) {
	$text = wp_strip_all_tags( cic7_main( $h ) );
	if ( preg_match( '/minimum|\$\s?\d|®|(?<!cannot )guarantee/i', $text, $m ) ) {
		$copy[] = "{$key}: {$m[0]}";
	}
}
$health = preg_match( '/healthy|low[- ]fat|nutritio|guilt[- ]free|probiotic|low[- ]calorie/i', wp_strip_all_tags( cic7_main( $html['frozen-yogurt'] ) ), $hm );
cic7_check( 'D6.1 no minimum-order, price, ® or guarantee copy on any detail page', ! $copy, $copy );
cic7_check( 'D6.2 Frozen Yogurt makes no health or nutrition claims', ! $health, $hm[0] ?? '' );
$notes = array();
foreach ( $treats as $t ) {
	if ( ! preg_match( '/(inspiration|Theme ideas)/i', wp_strip_all_tags( cic7_main( $html[ $t ] ) ) ) ) {
		$notes[] = $t;
	}
}
cic7_check( 'D6.3 every theme board is labelled as inspiration, not a decor/packaging promise', ! $notes, $notes );

/* ---------------- D7 pairings ---------------- */
$counts = array();
foreach ( $treats as $t ) {
	$counts[ $t ] = count( Pairings::for_product( $pages[ $t ]['id'], true ) );
}
cic7_check( 'D7.1 every treat has four occasion pairings (16 total)', array( 4, 4, 4, 4 ) === array_values( $counts ), $counts );
$shared = array();
// Event pages carry the pairing copy of their two featured (large) treats.
foreach ( array( array( 'dipn-bananas', 'wedding' ), array( 'dippin-dots', 'anniversary' ), array( 'dipn-bananas', 'corporate' ) ) as $pair ) {
	foreach ( Pairings::for_product( $pages[ $pair[0] ]['id'], true ) as $row ) {
		if ( $pages[ $pair[1] ]['id'] === (int) $row['event_id'] ) {
			$headline = esc_html( $row['headline'] );
			if ( ! str_contains( $html[ $pair[0] ], $headline ) || ! str_contains( $html[ $pair[1] ], $headline ) ) {
				$shared[] = implode( '×', $pair );
			}
		}
	}
}
cic7_check( 'D7.2 pairing copy comes from one source and appears on both the treat and the event page', ! $shared, $shared );

/* ---------------- D8 catalogue protection ---------------- */
$shop = array();
foreach ( $treats as $t ) {
	$h = $html[ $t ];
	if ( preg_match( '/add-to-cart|single_add_to_cart_button|class="price"|woocommerce-Price-amount|"@type":"Offer"|"priceCurrency"/', $h, $m ) ) {
		$shop[] = "{$t}: {$m[0]}";
	}
}
cic7_check( 'D8.1 treat pages show no add-to-cart, price or Offer schema', ! $shop, $shop );
$purch = array();
foreach ( $treats as $t ) {
	$product = wc_get_product( $pages[ $t ]['id'] );
	if ( $product->is_purchasable() || '' !== (string) $product->get_price() ) {
		$purch[] = $t;
	}
}
cic7_check( 'D8.2 the four products stay non-purchasable with no price', ! $purch, $purch );

/* ---------------- D9 seeding ---------------- */
$report = ( new CIC\Content\Seeder() )->run( false )['report'];
cic7_check( 'D9.1 re-running the seeder changes nothing (non-destructive, idempotent)', 0 === $report['created'] && 0 === $report['filled'] && 0 === $report['pairs'], $report );
$layouts = array();
foreach ( array( 'wedding', 'anniversary', 'corporate' ) as $e ) {
	$layouts[ $e ] = get_post_meta( $pages[ $e ]['id'], 'cic_layout', true );
}
foreach ( $treats as $t ) {
	$layouts[ $t ] = get_post_meta( $pages[ $t ]['id'], 'cic_treat_layout', true );
}
cic7_check( 'D9.2 compositions are editable record settings (event layout / treat composition fields)', array( 'wedding' => 'wedding', 'anniversary' => 'anniversary', 'corporate' => 'corporate', 'dippin-dots' => 'split', 'dipn-bananas' => 'split', 'classic-ice-cream' => 'classic', 'frozen-yogurt' => 'yogurt' ) === $layouts && isset( Fields::TREAT_LAYOUTS['yogurt'], Fields::EVENT_LAYOUT_SECTIONS['wedding'] ), $layouts );

WP_CLI::log( sprintf( '== RESULT: %d passed, %d failed', $cic7['pass'], $cic7['fail'] ) );
