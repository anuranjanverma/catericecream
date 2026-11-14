<?php
/**
 * Meta tag optimization, part 2 (SEO manager's updated sheet "CatereIceCream.com-Meta Tags
 * Optimization.xlsx", 2026-11-13): search titles and descriptions for the four treat pages and the
 * six event pages. (Home, Our Treats, Events, Service Areas and Get a Quote: meta-tags-20261112.php.)
 *
 * Wording: the specialist's keywords, refined with the owner's standing go-ahead (2026-11-12):
 * site suffix "| CaterIceCream.com" on every page (not the supplier brand), coverage stays honest
 * (no "near you"/"across"), no "free quote" claim, grammar fixes, descriptions ≤ 160 characters.
 *
 * Compare-and-set: a field is replaced only while it still holds the value the sheet lists as
 * "existing" (or is empty); a value changed in wp-admin is reported "kept" and never overwritten.
 *   - Treats (WooCommerce products): Yoast per-product title / metadesc.
 *   - Events: the CIC "Search result" fields cic_seo_title / cic_seo_description (the site appends
 *     " | CaterIceCream.com" to the title). Yoast's own fields on an event are left alone.
 * Records are found by cic_key.
 *
 * Usage: wp eval-file dev/tools/content/meta-tags-20261113.php [dry-run] [report=/path/report.json]
 * Safe to re-run ("done"). WordPress and Yoast APIs only. Afterwards: `wp yoast index`.
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
if ( ! defined( 'WPSEO_VERSION' ) || ! class_exists( 'WPSEO_Meta' ) ) {
	WP_CLI::error( 'Yoast SEO is not active. Nothing changed.' );
}
if ( ! class_exists( Catalog::class ) ) {
	WP_CLI::error( 'The Cater Ice Cream core plugin is not active. Nothing changed.' );
}
WP_CLI::log( ( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' ) . ' Yoast SEO ' . WPSEO_VERSION . '.' );

$cic_log  = array();
$cic_note = static function ( string $target, string $result, $before = null, $after = null ) use ( &$cic_log ): void {
	$cic_log[] = array_filter( array( 'target' => $target, 'result' => $result, 'before' => $before, 'after' => $after ), static fn( $v ) => null !== $v );
	WP_CLI::log( sprintf( '%-9s %-44s %s', $result, $target, 'kept' === $result ? '(admin value: ' . $before . ')' : ( null !== $after ? '→ ' . $after : '' ) ) );
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

$cic_sep  = ' %%sep%% %%sitename%%';
$cic_area = 'selected areas of Los Angeles, Ventura and Santa Barbara counties';

/* ------------------------------------------------------------------ *
 * 1. Treats: Yoast title / metadesc. cic_key => [ field => [ old, new ] ].
 * ------------------------------------------------------------------ */
$cic_treats = array(
	'dippin-dots'       => array(
		'title'    => array( 'Dippin’ Dots Catering for Events' . $cic_sep, 'Dippin’ Dots Ice Cream Catering for Events' . $cic_sep ),
		'metadesc' => array(
			'Dippin’ Dots served by our team at birthdays, weddings, graduation parties, proms and corporate events in ' . $cic_area . '.',
			'Book Dippin’ Dots ice cream for your event in Los Angeles, Ventura & Santa Barbara. Fun flavors, staffed service. Get a quote today!',
		),
	),
	'dipn-bananas'      => array(
		'title'    => array( 'DipN’ Bananas Event Catering' . $cic_sep, 'Chocolate-Coated Frozen Banana Catering' . $cic_sep ),
		'metadesc' => array(
			'Bananas with a chocolate finish, served by our team: a playful change from cake for birthdays, weddings, graduation parties and more. Request a quote for your event.',
			'Chocolate-coated frozen bananas add a fun twist to birthdays, weddings and corporate events. DipN’ Bananas catering in Southern California. Request a quote.',
		),
	),
	'classic-ice-cream' => array(
		'title'    => array( 'Classic Ice Cream Catering' . $cic_sep, 'Classic Ice Cream Catering in Southern California' . $cic_sep ),
		'metadesc' => array(
			'Familiar, creamy scoops served by our team at birthdays, weddings, graduations and team gatherings. Flavors and serving options are discussed with your quote.',
			'Classic ice cream catering for birthdays, weddings, graduations and corporate events in Los Angeles, Ventura & Santa Barbara. Request your quote today.',
		),
	),
	'frozen-yogurt'     => array(
		'title'    => array( 'Frozen Yogurt Catering for Events' . $cic_sep, 'Frozen Yogurt Catering in Southern California' . $cic_sep ),
		'metadesc' => array(
			'Smooth frozen yogurt with a familiar tang, served by our team at birthdays, garden weddings, graduation parties and team gatherings. Request a quote for your event.',
			'Frozen yogurt catering for weddings, parties and corporate events in Southern California: smooth, tangy frozen yogurt served by our team. Get a quote.',
		),
	),
);
foreach ( $cic_treats as $cic_key => $cic_fields ) {
	$cic_id = Catalog::find_by_key( 'product', $cic_key );
	if ( ! $cic_id ) {
		$cic_note( "product:$cic_key", 'skipped', null, 'not found' );
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
 * 2. Events: CIC search result fields (title without the site suffix).
 * ------------------------------------------------------------------ */
$cic_events = array(
	'birthday'    => array(
		'cic_seo_title'       => array( 'Birthday Party Ice Cream Catering', 'Birthday Party Ice Cream Catering in Southern California' ),
		'cic_seo_description' => array(
			'Staffed ice cream catering for kids’ parties and milestone birthdays, with Dippin’ Dots, DipN’ Bananas, classic ice cream or frozen yogurt. Request a quote for your date.',
			'Ice cream catering for birthday parties in Southern California. Dippin’ Dots, DipN’ Bananas, ice cream & frozen yogurt, fully staffed. Get a quote today.',
		),
	),
	'wedding'     => array(
		'cic_seo_title'       => array( 'Wedding Ice Cream Catering', 'Ice Cream Catering for Weddings' ),
		'cic_seo_description' => array(
			'Staffed ice cream catering for wedding receptions, with Dippin’ Dots, DipN’ Bananas and other favorites, in ' . $cic_area . '.',
			'Ice cream catering for weddings in Los Angeles, Ventura & Santa Barbara. Staffed service with Dippin’ Dots & DipN’ Bananas. Request a quote today!',
		),
	),
	'graduation'  => array(
		'cic_seo_title'       => array( 'Graduation Party Ice Cream Catering', 'Ice Cream for Your Graduation Party' ),
		'cic_seo_description' => array(
			'Staffed ice cream catering for graduation parties, open houses and school celebrations in ' . $cic_area . '.',
			'Ice cream for your graduation party, served by our team. Dippin’ Dots, ice cream & frozen yogurt in LA, Ventura & Santa Barbara. Get a quote.',
		),
	),
	'prom'        => array(
		'cic_seo_title'       => array( 'Prom Ice Cream Catering', 'Prom Ice Cream Catering in Southern California' ),
		'cic_seo_description' => array(
			'A staffed ice cream dessert stop for proms and school dances in ' . $cic_area . '.',
			'Prom ice cream catering for school dances and prom night. Our team serves Dippin’ Dots, DipN’ Bananas & more in LA, Ventura & Santa Barbara. Get a quote.',
		),
	),
	'corporate'   => array(
		'cic_seo_title'       => array( 'Corporate Event Ice Cream Catering', 'Corporate Ice Cream Catering in Southern California' ),
		'cic_seo_description' => array(
			'Staffed ice cream catering for team appreciation, client gatherings and office celebrations in ' . $cic_area . '.',
			'Ice cream for corporate events, fully staffed: team appreciation, client gatherings & office parties in LA, Ventura & Santa Barbara. Get a quote.',
		),
	),
	'anniversary' => array(
		'cic_seo_title'       => array( 'Anniversary Party Ice Cream Catering', 'Anniversary Ice Cream Catering in Southern California' ),
		'cic_seo_description' => array(
			'Celebrate your anniversary with staffed ice cream catering and the frozen treats you both love, in ' . $cic_area . '.',
			'Book staffed ice cream catering for your anniversary party. Dippin’ Dots, DipN’ Bananas & more in LA, Ventura & Santa Barbara. Get a quote today.',
		),
	),
);
foreach ( $cic_events as $cic_key => $cic_fields ) {
	$cic_id = Catalog::find_by_key( 'cic_event', $cic_key );
	if ( ! $cic_id ) {
		$cic_note( "cic_event:$cic_key", 'skipped', null, 'not found' );
		continue;
	}
	foreach ( $cic_fields as $cic_field => [ $cic_old, $cic_new ] ) {
		$cic_now = trim( (string) get_post_meta( $cic_id, $cic_field, true ) );
		if ( $cic_decide( "#$cic_id $cic_key $cic_field", $cic_now, array( $cic_old, '' ), $cic_new ) ) {
			update_post_meta( $cic_id, $cic_field, $cic_new );
			update_post_meta( $cic_id, '_' . $cic_field, 'field_' . $cic_field );
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
WP_CLI::success( $cic_dry ? 'Dry run finished.' : 'Done. Next: wp yoast index' );
