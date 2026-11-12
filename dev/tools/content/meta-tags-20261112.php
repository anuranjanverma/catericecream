<?php
/**
 * Meta tag optimization of 2026-11-12 (SEO specialist sheet
 * "CatereIceCream.com-Meta Tags Optimization.xlsx"): new search titles and descriptions for
 * Home, Our Treats, the Events archive, Service Areas and Get a Quote.
 *
 * Wording: the specialist's keywords, adjusted with the owner's go-ahead (2026-11-12) to keep
 * titles distinct, coverage honest ("selected" areas, no "any event"/"across") and titles short.
 *
 * Compare-and-set, unlike the add-only seo-20261028.php: a field is replaced ONLY while it still
 * holds the value seo-20261028.php wrote (or is empty). A value an admin changed in wp-admin is
 * reported as "kept" and never overwritten. Already-new values report "done".
 *
 *   - Pages (Home, Our Treats = shop page, Service Areas, Get a Quote): Yoast per-page fields
 *     (WPSEO_Meta title / metadesc). Pages are found by their cic_key, never by ID or slug.
 *   - Events archive: Yoast settings title/metadesc-ptarchive-cic_event and the matching social
 *     title/description (they were set together on 2026-10-28). %%page%% is kept in the title.
 *
 * Usage: wp eval-file dev/tools/content/meta-tags-20261112.php [dry-run] [report=/path/report.json]
 * Safe to re-run. WordPress and Yoast APIs only; never Yoast's indexable tables. Afterwards run
 * `wp yoast index` so Yoast's indexables reflect the changes.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Content\Catalog;

$cic_args   = (array) ( $args ?? array() );
$cic_dry    = in_array( 'dry-run', $cic_args, true );
$cic_report = '';
foreach ( $cic_args as $cic_arg ) {
	if ( 0 === strpos( $cic_arg, 'report=' ) ) {
		$cic_report = substr( $cic_arg, 7 );
	}
}

if ( ! defined( 'WPSEO_VERSION' ) || ! class_exists( 'WPSEO_Options' ) || ! class_exists( 'WPSEO_Meta' ) ) {
	WP_CLI::error( 'Yoast SEO is not active. Nothing changed.' );
}
if ( ! class_exists( Catalog::class ) ) {
	WP_CLI::error( 'The Cater Ice Cream core plugin is not active. Nothing changed.' );
}

WP_CLI::log( ( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' ) . ' Yoast SEO ' . WPSEO_VERSION . '.' );

$cic_log  = array();
$cic_note = static function ( string $target, string $result, $before = null, $after = null ) use ( &$cic_log ): void {
	$cic_log[] = array_filter(
		array(
			'target' => $target,
			'result' => $result,
			'before' => $before,
			'after'  => $after,
		),
		static fn( $v ) => null !== $v
	);
	WP_CLI::log( sprintf( '%-9s %-44s %s', $result, $target, 'kept' === $result ? '(admin value: ' . $before . ')' : ( null !== $after ? '→ ' . $after : '' ) ) );
};

/**
 * Decides one field. Returns true when the new value should be written.
 *
 * @param string   $target Report label.
 * @param string   $now    Current value.
 * @param string[] $old    Values that may be replaced (2026-10-28 value; '' = empty).
 * @param string   $new    New value.
 */
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

$cic_sep  = ' %%sep%% %%sitename%%';
$cic_area = 'selected areas of Los Angeles, Ventura and Santa Barbara counties';

/* ------------------------------------------------------------------ *
 * 1. Pages: Yoast per-page title and meta description.
 *    cic_key => [ title: [old, new], metadesc: [old, new] ].
 * ------------------------------------------------------------------ */
$cic_pages = array(
	'home'          => array(
		'title'    => array( 'Ice Cream Catering in Southern California' . $cic_sep, 'Ice Cream Catering Services in Southern California' . $cic_sep ),
		'metadesc' => array(
			'Staffed ice cream catering with Dippin’ Dots, DipN’ Bananas, classic ice cream and frozen yogurt for events in ' . $cic_area . '.',
			'Staffed ice cream catering in selected areas of LA, Ventura & Santa Barbara counties. Dippin’ Dots, DipN’ Bananas & more for birthdays, weddings & parties.',
		),
	),
	'shop'          => array(
		'title'    => array( 'Catering Treats: Dippin’ Dots, DipN’ Bananas & More' . $cic_sep, 'Frozen Treats & Desserts for Events' . $cic_sep ),
		'metadesc' => array(
			'Compare the treats we serve at events: Dippin’ Dots, DipN’ Bananas, classic ice cream and frozen yogurt. Choose one or more for your quote, or pick “Not sure yet.”',
			'Frozen treats and desserts for your event: Dippin’ Dots, DipN’ Bananas, classic ice cream and frozen yogurt, served by our team. Request a quote today.',
		),
	),
	'service-areas' => array(
		'title'    => array( 'Where We Cater: LA, Ventura & Santa Barbara Counties' . $cic_sep, 'Ice Cream Catering Near You: Check Your ZIP' . $cic_sep ),
		'metadesc' => array(
			'We cater events in selected ZIP codes across Los Angeles, Ventura and Santa Barbara counties. Enter your venue city or ZIP code to check your location.',
			'We cater events in selected ZIP codes across Los Angeles, Ventura and Santa Barbara counties. Enter your city or ZIP code, then get a quote for your event.',
		),
	),
	'get-a-quote'   => array(
		'title'    => array( 'Request an Ice Cream Catering Quote' . $cic_sep, 'Get an Ice Cream Catering Quote' . $cic_sep ),
		'metadesc' => array(
			'Tell us about your occasion, date, guest count, location and favorite treats. We’ll confirm availability for your area and follow up with your quote.',
			'Request an ice cream catering quote for your Southern California event. Share your date, guest count and location, and we’ll confirm availability.',
		),
	),
);
foreach ( $cic_pages as $cic_key => $cic_fields ) {
	$cic_id = Catalog::find_by_key( 'page', $cic_key );
	if ( ! $cic_id ) {
		$cic_note( "page:$cic_key", 'skipped', null, 'not found' );
		continue;
	}
	foreach ( $cic_fields as $cic_field => [ $cic_old, $cic_new ] ) {
		$cic_now = trim( (string) WPSEO_Meta::get_value( $cic_field, $cic_id ) );
		if ( $cic_decide( "#$cic_id $cic_key Yoast $cic_field", $cic_now, array( $cic_old, '' ), $cic_new ) ) {
			WPSEO_Meta::set_value( $cic_field, $cic_new, $cic_id );
		}
	}
}

/* ------------------------------------------------------------------ *
 * 2. Events archive: Yoast settings (search + social, set together on 2026-10-28).
 * ------------------------------------------------------------------ */
$cic_ev_old_t = 'Ice Cream Catering by Occasion';
$cic_ev_new_t = 'Ice Cream Catering for Events & Parties';
$cic_ev_old_d = 'Staffed ice cream catering for birthdays, weddings, graduation parties, proms, corporate events and anniversaries. See what suits your occasion and request a quote.';
$cic_ev_new_d = 'Staffed ice cream catering for birthdays, weddings, graduation parties, proms, corporate events and anniversaries in Southern California. Get a quote today.';
$cic_settings = array(
	// key => [ replaceable values, new value ].
	'title-ptarchive-cic_event'              => array( array( $cic_ev_old_t . ' %%page%%' . $cic_sep, '%%pt_plural%% Archive %%page%%' . $cic_sep ), $cic_ev_new_t . ' %%page%%' . $cic_sep ),
	'metadesc-ptarchive-cic_event'           => array( array( $cic_ev_old_d, '' ), $cic_ev_new_d ),
	'social-title-ptarchive-cic_event'       => array( array( $cic_ev_old_t, '%%pt_plural%% Archive', '' ), $cic_ev_new_t ),
	'social-description-ptarchive-cic_event' => array( array( $cic_ev_old_d, '' ), $cic_ev_new_d ),
);
foreach ( $cic_settings as $cic_key => [ $cic_old, $cic_new ] ) {
	$cic_now = trim( (string) WPSEO_Options::get( $cic_key ) );
	if ( $cic_decide( "yoast $cic_key", $cic_now, $cic_old, $cic_new ) ) {
		WPSEO_Options::set( $cic_key, $cic_new );
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
WP_CLI::success( $cic_dry ? 'Dry run finished.' : 'Done. Next: wp yoast index' );
