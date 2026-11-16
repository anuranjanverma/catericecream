<?php
/**
 * H1 and eyebrow optimization (SEO specialist's sheet "CatereIceCream.com-Meta Tags & H1
 * Optimization.xlsx", 2026-11-17), refined by the owner's go-ahead: "accept the ones you think are
 * better than current, with corrections". Owner decision: the Home H1 (and its eyebrow) stay.
 *
 * Pattern: the hero H1 names the page topic (keyword), the former slogan moves to the eyebrow
 * (the small capitals line above it, uppercased by CSS, so values are stored in sentence case).
 * Also the Our Treats search title. Other titles/descriptions in the sheet were applied earlier
 * (meta-tags-20261112.php / -20261113.php) and are not changed here.
 *
 * Compare-and-set: a field is replaced only while it still holds the value it had on 2026-11-17
 * (or is empty); a value changed in wp-admin is reported "kept" and never overwritten.
 *   - Pages: cic_shop_* (Our Treats), cic_sa_* (Service Areas), cic_quote_* (Get a Quote) post meta.
 *   - Treats / events: cic_hero_headline / cic_hero_eyebrow post meta (found by cic_key).
 *   - Events overview: option cic_events_overview (empty option = code defaults).
 *
 * Usage: wp eval-file dev/tools/content/headings-20261117.php [dry-run] [report=/path/report.json]
 * Safe to re-run ("done"). WordPress APIs only. Afterwards purge page caches.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Content\Catalog;
use CIC\Content\EventsOverview;

$cic_args   = (array) ( $args ?? array() );
$cic_dry    = in_array( 'dry-run', $cic_args, true );
$cic_report = '';
foreach ( $cic_args as $cic_arg ) {
	if ( 0 === strpos( $cic_arg, 'report=' ) ) {
		$cic_report = substr( $cic_arg, 7 );
	}
}
if ( ! class_exists( Catalog::class ) || ! class_exists( EventsOverview::class ) ) {
	WP_CLI::error( 'The Cater Ice Cream core plugin is not active. Nothing changed.' );
}
WP_CLI::log( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' );

$cic_log  = array();
$cic_note = static function ( string $target, string $result, $before = null, $after = null ) use ( &$cic_log ): void {
	$cic_log[] = array_filter( array( 'target' => $target, 'result' => $result, 'before' => $before, 'after' => $after ), static fn( $v ) => null !== $v );
	$show      = static fn( $v ) => str_replace( "\n", ' / ', (string) $v );
	WP_CLI::log( sprintf( '%-9s %-46s %s', $result, $target, 'kept' === $result ? '(admin value: ' . $show( $before ) . ')' : ( null !== $after ? '→ ' . $show( $after ) : '' ) ) );
};
$cic_decide = static function ( string $target, string $now, array $old, string $new ) use ( $cic_note, $cic_dry ): bool {
	if ( $now === $new ) {
		$cic_note( $target, 'done' );
		return false;
	}
	if ( ! in_array( $now, $old, true ) ) {
		$cic_note( $target, 'kept', $now );
		return false;
	}
	$cic_note( $target, $cic_dry ? 'would set' : 'set', $now, $new );
	return ! $cic_dry;
};
$cic_meta = static function ( int $id, string $label, string $field, string $old, string $new ) use ( $cic_decide ): void {
	// Admin textareas save CRLF; the same text with other line endings is not an admin edit.
	$now = str_replace( "\r\n", "\n", (string) get_post_meta( $id, $field, true ) );
	if ( $cic_decide( "#$id $label $field", $now, array( $old, '' ), $new ) ) {
		update_post_meta( $id, $field, $new );
		update_post_meta( $id, '_' . $field, 'field_' . $field );
	}
};

/* ------------------------------------------------------------------ *
 * 1. Pages (found by path). field => [ old, new ].
 * ------------------------------------------------------------------ */
$cic_pages = array(
	'treats'        => array(
		'cic_shop_headline' => array( "Find your kind\nof happy.", "Frozen treat catering\n<em>for your next event</em>" ),
		'cic_shop_eyebrow'  => array( 'The catering collection', 'Find your kind of happy' ),
	),
	'service-areas' => array(
		'cic_sa_headline' => array( "A sweet celebration,\n<em>closer to home.</em>", "Ice cream catering areas\n<em>in Southern California</em>" ),
		'cic_sa_eyebrow'  => array( 'Southern California', 'Los Angeles · Ventura · Santa Barbara counties' ),
	),
	'get-a-quote'   => array(
		'cic_quote_headline' => array( "Your occasion. Your favorites.\nLet's bring them together.", "Get your ice cream\ncatering quote" ),
	),
);
foreach ( $cic_pages as $cic_path => $cic_fields ) {
	$cic_page = get_page_by_path( $cic_path );
	if ( ! $cic_page ) {
		$cic_note( "page:$cic_path", 'skipped', null, 'not found' );
		continue;
	}
	foreach ( $cic_fields as $cic_field => [ $cic_old, $cic_new ] ) {
		$cic_meta( (int) $cic_page->ID, $cic_path, $cic_field, $cic_old, $cic_new );
	}
}

/* Our Treats search title (Yoast box on the shop page). */
$cic_shop = (int) get_option( 'woocommerce_shop_page_id' );
if ( $cic_shop && class_exists( 'WPSEO_Meta' ) ) {
	$cic_now = trim( (string) WPSEO_Meta::get_value( 'title', $cic_shop ) );
	$cic_new = 'Frozen Treat Catering for Events %%sep%% %%sitename%%';
	if ( $cic_decide( "#$cic_shop treats Yoast title", $cic_now, array( 'Frozen Treats & Desserts for Events %%sep%% %%sitename%%', '' ), $cic_new ) ) {
		WPSEO_Meta::set_value( 'title', $cic_new, $cic_shop );
	}
}

/* ------------------------------------------------------------------ *
 * 2. Events overview (option; a missing key means the code default).
 * ------------------------------------------------------------------ */
$cic_ov_saved = (array) get_option( EventsOverview::OPTION, array() );
$cic_ov_defs  = EventsOverview::fields();
$cic_ov_new   = array(
	'headline' => "Ice cream catering\n<em>for events and parties</em>",
	'eyebrow'  => 'A sweet addition to every occasion',
);
$cic_ov_dirty = false;
foreach ( $cic_ov_new as $cic_key => $cic_new ) {
	$cic_now = array_key_exists( $cic_key, $cic_ov_saved ) ? (string) $cic_ov_saved[ $cic_key ] : (string) $cic_ov_defs[ $cic_key ][2];
	if ( $cic_decide( "events overview $cic_key", $cic_now, array( (string) $cic_ov_defs[ $cic_key ][2], '' ), $cic_new ) ) {
		$cic_ov_saved[ $cic_key ] = $cic_new;
		$cic_ov_dirty             = true;
	}
}
if ( $cic_ov_dirty ) {
	update_option( EventsOverview::OPTION, $cic_ov_saved );
}

/* ------------------------------------------------------------------ *
 * 3. Treats and events: hero H1 + eyebrow. type => cic_key => field => [ old, new ].
 * ------------------------------------------------------------------ */
$cic_heroes = array(
	'product'   => array(
		'dippin-dots'       => array(
			'cic_hero_headline' => array( "Little dots.\nBig <em>party energy.</em>", "Dippin’ Dots ice cream\n<em>catering for your event</em>" ),
			'cic_hero_eyebrow'  => array( "Dippin' Dots event catering", 'Little dots. Big party energy.' ),
		),
		'dipn-bananas'      => array(
			// Eyebrow stays "DipN' Bananas event catering": the H1 no longer names the brand.
			'cic_hero_headline' => array( "A little indulgence.\n<em>A delicious twist.</em>", "Chocolate-coated frozen bananas\n<em>for your event</em>" ),
		),
		'classic-ice-cream' => array(
			'cic_hero_headline' => array( "The familiar favorite\nthat <em>feels like\ncelebration.</em>", "Classic ice cream catering\n<em>for Southern California events</em>" ),
			'cic_hero_eyebrow'  => array( 'Classic ice cream catering', 'A timeless favorite, made for celebrations' ),
		),
		'frozen-yogurt'     => array(
			'cic_hero_headline' => array( "A bright little\npause <em>in your\ncelebration.</em>", "Frozen yogurt catering\n<em>in Southern California</em>" ),
			'cic_hero_eyebrow'  => array( 'Frozen yogurt event catering', 'Bring on the frozen fun' ),
		),
	),
	'cic_event' => array(
		'birthday'    => array(
			'cic_hero_headline' => array( "More candles.\n<em>More smiles.</em>\n<strong>More ice cream.</strong>", "Ice cream catering\n<em>for birthday parties</em>" ),
			'cic_hero_eyebrow'  => array( 'Birthday ice cream catering', 'More candles. More smiles. More ice cream.' ),
		),
		'wedding'     => array(
			'cic_hero_headline' => array( "A love story,\nwith a <em>sweeter</em>\nending.", "Ice cream catering for weddings\n<em>in Southern California</em>" ),
			'cic_hero_eyebrow'  => array( 'Wedding ice cream catering', 'A love story, with a sweeter ending' ),
		),
		'graduation'  => array(
			'cic_hero_headline' => array( "Caps off.\n<em>Scoops up.</em>", "Ice cream catering\n<em>for graduation parties</em>" ),
			'cic_hero_eyebrow'  => array( 'Graduation party ice cream catering', 'Caps off. Scoops up.' ),
		),
		'prom'        => array(
			'cic_hero_headline' => array( "Dressed up.\n<em>Served sweet.</em>", "Prom ice cream catering\n<em>in Southern California</em>" ),
			'cic_hero_eyebrow'  => array( 'Prom ice cream catering', 'Dressed up. Served sweet.' ),
		),
		'corporate'   => array(
			'cic_hero_headline' => array( "Good teams deserve\n<em>a sweet moment.</em>", "Corporate ice cream catering\n<em>in Southern California</em>" ),
			'cic_hero_eyebrow'  => array( 'Corporate ice cream catering', 'Good teams deserve a sweet moment.' ),
		),
		'anniversary' => array(
			'cic_hero_headline' => array( "Another year.\n<em>Another sweet\nmemory.</em>", "Anniversary\nice cream catering\n<em>in Southern California</em>" ),
			'cic_hero_eyebrow'  => array( 'Anniversary ice cream catering', 'Another year. Another sweet memory.' ),
		),
	),
);
foreach ( $cic_heroes as $cic_type => $cic_items ) {
	foreach ( $cic_items as $cic_key => $cic_fields ) {
		$cic_id = Catalog::find_by_key( $cic_type, $cic_key );
		if ( ! $cic_id ) {
			$cic_note( "$cic_type:$cic_key", 'skipped', null, 'not found' );
			continue;
		}
		foreach ( $cic_fields as $cic_field => [ $cic_old, $cic_new ] ) {
			$cic_meta( (int) $cic_id, $cic_key, $cic_field, $cic_old, $cic_new );
		}
	}
}

/* ------------------------------------------------------------------ */
$cic_counts = array_count_values( array_column( $cic_log, 'result' ) );
ksort( $cic_counts );
WP_CLI::log( 'Summary: ' . implode( ', ', array_map( static fn( $k, $v ) => "$k $v", array_keys( $cic_counts ), $cic_counts ) ) );
if ( '' !== $cic_report ) {
	$cic_json = wp_json_encode( array( 'run' => gmdate( 'c' ), 'site' => home_url( '/' ), 'dry_run' => $cic_dry, 'changes' => $cic_log ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === file_put_contents( $cic_report, $cic_json ) ) {
		WP_CLI::warning( 'Could not write the report to ' . $cic_report );
	} else {
		WP_CLI::log( 'Report: ' . $cic_report );
	}
}
WP_CLI::success( $cic_dry ? 'Dry run finished.' : 'Done. Purge page caches.' );
