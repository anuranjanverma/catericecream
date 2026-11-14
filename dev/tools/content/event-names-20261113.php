<?php
/**
 * Singular event names (owner, 2026-11-13): Birthday, Wedding, Graduation Party, Prom,
 * Corporate Event, Anniversary.
 *
 *   1. Event titles (cic_event post_title). They feed headings, breadcrumbs, event cards, the
 *      quote/contact occasion options (when no short label is set) and the Yoast fallback title.
 *   2. Short labels (cic_event_short_label: quote form tiles, inquiry summaries and emails):
 *      "Graduation" → "Graduation Party", "Corporate" → "Corporate Event".
 *   3. Menu items in every menu whose own title is a plural event name (custom links keep their
 *      own title; WordPress does not follow the event title).
 *   4. Service Areas occasions intro (cic_sa_occasions_intro).
 *
 * Compare-and-set: a value is changed only while it still holds the plural/old text (or, for the
 * intro, one of the known earlier texts); anything edited in wp-admin is reported "kept". Records
 * are found by cic_key. URLs are changed separately by url-slugs-20261112.php. Historical inquiries
 * are never touched (they store event keys).
 *
 * Usage: wp eval-file dev/tools/content/event-names-20261113.php [dry-run] [report=/path/report.json]
 * Safe to re-run ("done"). WordPress APIs only.
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
if ( ! class_exists( Catalog::class ) ) {
	WP_CLI::error( 'The Cater Ice Cream core plugin is not active. Nothing changed.' );
}
WP_CLI::log( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' );

$cic_log  = array();
$cic_note = static function ( string $target, string $result, $before = null, $after = null ) use ( &$cic_log ): void {
	$cic_log[] = array_filter( array( 'target' => $target, 'result' => $result, 'before' => $before, 'after' => $after ), static fn( $v ) => null !== $v );
	WP_CLI::log( sprintf( '%-9s %-40s %s', $result, $target, 'kept' === $result ? '(admin value: ' . $before . ')' : ( null !== $after ? $before . ' → ' . $after : '' ) ) );
};
/** Returns true when the value should be written. */
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

/** cic_key => [ plural title, singular title, [ old short label, new short label ] | null ]. */
$cic_events = array(
	'birthday'    => array( 'Birthdays', 'Birthday', null ),
	'wedding'     => array( 'Weddings', 'Wedding', null ),
	'graduation'  => array( 'Graduation Parties', 'Graduation Party', array( 'Graduation', 'Graduation Party' ) ),
	'prom'        => array( 'Proms', 'Prom', null ),
	'corporate'   => array( 'Corporate Events', 'Corporate Event', array( 'Corporate', 'Corporate Event' ) ),
	'anniversary' => array( 'Anniversaries', 'Anniversary', null ),
);

/* 1 + 2. Event titles and short labels. */
$cic_plural = array();
foreach ( $cic_events as $cic_key => [ $cic_old, $cic_new, $cic_short ] ) {
	$cic_plural[ $cic_old ] = $cic_new;
	$cic_id = Catalog::find_by_key( 'cic_event', $cic_key );
	if ( ! $cic_id ) {
		$cic_note( "cic_event:$cic_key", 'skipped', null, 'not found' );
		continue;
	}
	if ( $cic_decide( "#$cic_id $cic_key title", (string) get_post_field( 'post_title', $cic_id, 'raw' ), array( $cic_old ), $cic_new ) ) {
		wp_update_post( array( 'ID' => $cic_id, 'post_title' => $cic_new ) );
	}
	if ( $cic_short ) {
		$cic_now = trim( (string) get_post_meta( $cic_id, 'cic_event_short_label', true ) );
		if ( $cic_decide( "#$cic_id $cic_key short label", $cic_now, array( $cic_short[0] ), $cic_short[1] ) ) {
			update_post_meta( $cic_id, 'cic_event_short_label', $cic_short[1] );
			update_post_meta( $cic_id, '_cic_event_short_label', 'field_cic_event_short_label' );
		}
	}
}

/* 3. Menu items whose own title is a plural event name (all menus). */
$cic_menu_seen = 0;
foreach ( wp_get_nav_menus() as $cic_menu ) {
	foreach ( (array) wp_get_nav_menu_items( $cic_menu->term_id, array( 'post_status' => 'any' ) ) as $cic_item ) {
		$cic_own = (string) get_post_field( 'post_title', (int) $cic_item->ID, 'raw' );
		$cic_to  = $cic_plural[ $cic_own ] ?? ( in_array( $cic_own, $cic_plural, true ) ? $cic_own : null );
		if ( null === $cic_to ) {
			continue;
		}
		++$cic_menu_seen;
		if ( $cic_decide( "menu \"{$cic_menu->name}\" item #{$cic_item->ID}", $cic_own, array_keys( $cic_plural ), $cic_to ) ) {
			wp_update_post( array( 'ID' => (int) $cic_item->ID, 'post_title' => $cic_to ) );
		}
	}
}
if ( ! $cic_menu_seen ) {
	$cic_note( 'menus', 'skipped', null, 'no event-named menu items' );
}

/* 4. Service Areas occasions intro. */
$cic_sa = Catalog::find_by_key( 'page', 'service-areas' );
if ( $cic_sa ) {
	$cic_now = trim( (string) get_post_meta( $cic_sa, 'cic_sa_occasions_intro', true ) );
	$cic_new = 'Birthday, Wedding, Graduation Party, Prom, Corporate Event or Anniversary: pick your occasion to see how we can help.';
	$cic_old = array( 'Birthdays, weddings, graduation parties, proms, corporate events and anniversaries: pick your occasion to see how we can help.', '' );
	if ( $cic_decide( "#$cic_sa service-areas occasions intro", $cic_now, $cic_old, $cic_new ) ) {
		update_post_meta( $cic_sa, 'cic_sa_occasions_intro', $cic_new );
		update_post_meta( $cic_sa, '_cic_sa_occasions_intro', 'field_cic_sa_occasions_intro' );
	}
} else {
	$cic_note( 'page:service-areas', 'skipped', null, 'not found' );
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
WP_CLI::success( $cic_dry ? 'Dry run finished.' : 'Done.' );
