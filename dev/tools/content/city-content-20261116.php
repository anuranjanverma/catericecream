<?php
/**
 * City landing pages (2026-11-16): applies the generated city content
 * (dev/tools/content/data/city-content-20261116.json, from city-content-20261116-generate.php) and
 * makes every approved, published, served city indexable by publishing its content record and
 * marking it Reviewed (the existing rule: published + Index + reviewed content + served).
 *
 * Per city (eligible = production, published, Index, ≥1 enabled ZIP):
 * The data file is keyed by city key ("pasadena-ca"), never by database ID, and holds every
 * eligible city: generated copy plus the copy of records that already existed locally ("existing").
 *   - Data-file cities: create the content record if missing (CityStore::ensure_editorial, same as
 *     the admin button), then compare-and-set each field: empty → set; same → done; different →
 *     "kept" (an admin value is never overwritten) and the proposed text is written to the report.
 *   - Eligible cities missing from the data file but with a record: content untouched.
 *   - Data-file keys that are not eligible on this site are listed, never created.
 *   - Then, when the record holds the required fields (headline, intro, FAQ 1, search title and
 *     description), it is published and marked Reviewed. Records missing them are reported.
 * Eligibility records (cities, ZIPs, links, statuses) are never changed.
 *
 * Usage:
 *   wp eval-file dev/tools/content/city-content-20261116.php dry-run [report=…]
 *   wp eval-file dev/tools/content/city-content-20261116.php backup=/dir [keys=pasadena-ca,…|ids=1,2] [limit=N offset=N] [no-publish] [report=…]
 *   wp eval-file dev/tools/content/city-content-20261116.php rollback=/dir/city-content-backup-….jsonl
 * Applying needs backup= (a folder outside the web root): every changed city's previous values,
 * status and Reviewed flag are appended there BEFORE it changes, so an interrupted run can be
 * resumed (re-run: finished fields report "done") and rolled back (only the recorded cities and
 * fields). Safe to re-run; never creates a second record for a city. WordPress APIs only.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Locations\CityStore;
use CIC\Locations\DatabaseRepository;
use CIC\Locations\Editorial;
use CIC\Locations\Tables;

$cic_opt = array( 'dry' => false, 'report' => '', 'backup' => '', 'rollback' => '', 'ids' => array(), 'keys' => array(), 'limit' => 0, 'offset' => 0, 'publish' => true, 'data' => dirname( __FILE__ ) . '/data/city-content-20261116.json' );
foreach ( (array) ( $args ?? array() ) as $cic_arg ) {
	if ( 'dry-run' === $cic_arg ) {
		$cic_opt['dry'] = true;
	} elseif ( 'no-publish' === $cic_arg ) {
		$cic_opt['publish'] = false;
	} elseif ( preg_match( '/^(report|backup|rollback|data|limit|offset|ids|keys)=(.*)$/', $cic_arg, $cic_m ) ) {
		$cic_opt[ $cic_m[1] ] = 'keys' === $cic_m[1] ? array_filter( array_map( 'sanitize_key', explode( ',', $cic_m[2] ) ) ) : ( 'ids' === $cic_m[1] ? array_filter( array_map( 'intval', explode( ',', $cic_m[2] ) ) ) : ( in_array( $cic_m[1], array( 'limit', 'offset' ), true ) ? (int) $cic_m[2] : $cic_m[2] ) );
	}
}
if ( ! class_exists( CityStore::class ) ) {
	WP_CLI::error( 'The Cater Ice Cream core plugin is not active. Nothing changed.' );
}
$cic_fields_all = array_keys( \CIC\Content\Fields::CITY_FIELDS );
$cic_required   = array( 'title', 'intro', 'faq_1_q', 'faq_1_a', 'seo_title', 'seo_description' );

/* ------------------------------------------------------------------ *
 * Rollback: restore exactly what the backup recorded, newest first.
 * ------------------------------------------------------------------ */
if ( '' !== $cic_opt['rollback'] ) {
	$cic_lines = is_readable( $cic_opt['rollback'] ) ? file( $cic_opt['rollback'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : false;
	if ( ! $cic_lines ) {
		WP_CLI::error( 'Backup file not found or empty: ' . $cic_opt['rollback'] );
	}
	$cic_done = 0;
	foreach ( array_reverse( $cic_lines ) as $cic_line ) {
		$cic_e = json_decode( $cic_line, true );
		if ( ! is_array( $cic_e ) || empty( $cic_e['city_id'] ) ) {
			continue;
		}
		$cic_cid = (int) $cic_e['city_id'];
		$cic_pid = (int) $cic_e['post_id'];
		if ( ! empty( $cic_e['created'] ) ) {
			$cic_ok = CityStore::remove_editorial( $cic_cid, $cic_pid );
			WP_CLI::log( sprintf( '%-10s %s (record #%d deleted)', $cic_ok ? 'restored' : 'skipped', $cic_e['city'], $cic_pid ) );
		} else {
			foreach ( (array) $cic_e['before'] as $cic_field => $cic_value ) {
				if ( null === $cic_value || '' === $cic_value ) {
					delete_post_meta( $cic_pid, 'cic_city_' . $cic_field );
					delete_post_meta( $cic_pid, '_cic_city_' . $cic_field );
				} else {
					update_post_meta( $cic_pid, 'cic_city_' . $cic_field, wp_slash( $cic_value ) );
				}
			}
			if ( array_key_exists( 'reviewed', $cic_e ) ) {
				'' === (string) $cic_e['reviewed'] ? delete_post_meta( $cic_pid, Editorial::REVIEWED ) : update_post_meta( $cic_pid, Editorial::REVIEWED, $cic_e['reviewed'] );
			}
			if ( ! empty( $cic_e['status'] ) && get_post_status( $cic_pid ) !== $cic_e['status'] ) {
				wp_update_post( array( 'ID' => $cic_pid, 'post_status' => $cic_e['status'] ) );
			}
			WP_CLI::log( sprintf( 'restored   %s (record #%d: %d fields, status %s)', $cic_e['city'], $cic_pid, count( (array) $cic_e['before'] ), $cic_e['status'] ) );
		}
		++$cic_done;
	}
	Tables::bump_cache();
	WP_CLI::success( "Rollback finished: $cic_done cities." );
	return;
}

/* ------------------------------------------------------------------ *
 * Apply / dry run.
 * ------------------------------------------------------------------ */
$cic_data = json_decode( (string) file_get_contents( $cic_opt['data'] ), true );
if ( ! is_array( $cic_data ) || empty( $cic_data['cities'] ) ) {
	WP_CLI::error( 'Data file missing or invalid: ' . $cic_opt['data'] );
}
$cic_backup_file = '';
if ( ! $cic_opt['dry'] ) {
	if ( '' === $cic_opt['backup'] || ! is_dir( $cic_opt['backup'] ) || ! wp_is_writable( $cic_opt['backup'] ) ) {
		WP_CLI::error( 'Applying needs backup=<existing writable folder outside the web root>. Nothing changed.' );
	}
	if ( 0 === strpos( wp_normalize_path( realpath( $cic_opt['backup'] ) ), wp_normalize_path( ABSPATH ) ) ) {
		WP_CLI::error( 'The backup folder must be outside the web root. Nothing changed.' );
	}
	$cic_backup_file = trailingslashit( $cic_opt['backup'] ) . 'city-content-backup-' . gmdate( 'Ymd-His' ) . '.jsonl';
}
WP_CLI::log( $cic_opt['dry'] ? 'DRY RUN: nothing will be changed.' : 'Applying. Backup: ' . $cic_backup_file );

$cic_repo     = new DatabaseRepository();
$cic_eligible = $cic_repo->served_city_ranking( PHP_INT_MAX ); // production + published + Index + served.
usort( $cic_eligible, static fn( $a, $b ) => (int) $a['id'] <=> (int) $b['id'] );
if ( $cic_opt['ids'] ) {
	$cic_eligible = array_values( array_filter( $cic_eligible, static fn( $c ) => in_array( (int) $c['id'], $cic_opt['ids'], true ) ) );
}
if ( $cic_opt['keys'] ) {
	$cic_eligible = array_values( array_filter( $cic_eligible, static fn( $c ) => in_array( (string) $c['key'], $cic_opt['keys'], true ) ) );
}
// Data-file cities that are not eligible on this site (unknown key, draft, noindex, not served).
$cic_eligible_keys = array_column( $cic_eligible, 'key' );
$cic_not_eligible  = array_values( array_diff( array_keys( $cic_data['cities'] ), array_column( $cic_repo->served_city_ranking( PHP_INT_MAX ), 'key' ) ) );
if ( $cic_opt['limit'] > 0 || $cic_opt['offset'] > 0 ) {
	$cic_eligible = array_slice( $cic_eligible, $cic_opt['offset'], $cic_opt['limit'] > 0 ? $cic_opt['limit'] : null );
}

$cic_report = array();
$cic_totals = array();
foreach ( $cic_eligible as $cic_c ) {
	$cic_cid  = (int) $cic_c['id'];
	// Data is keyed by city key ("pasadena-ca"), never by database ID, so it applies on any site.
	$cic_entry = $cic_data['cities'][ (string) $cic_c['key'] ] ?? null;
	$cic_new   = $cic_entry['fields'] ?? null;
	$cic_pid   = CityStore::editorial_id( $cic_cid );
	$cic_pid   = $cic_pid && get_post( $cic_pid ) ? $cic_pid : 0;
	$cic_row   = array( 'city_id' => $cic_cid, 'key' => $cic_c['key'], 'city' => $cic_c['name'], 'source' => $cic_new ? (string) ( $cic_entry['source'] ?? 'data' ) : ( $cic_pid ? 'record only' : 'none' ), 'set' => 0, 'done' => 0, 'kept' => array(), 'proposals' => array() );
	$cic_back = array( 'city_id' => $cic_cid, 'city' => $cic_c['name'], 'post_id' => $cic_pid, 'created' => false, 'before' => array() );

	if ( ! $cic_new && ! $cic_pid ) {
		$cic_row['result'] = 'missing content (not in data file, no record)';
		$cic_report[]      = $cic_row;
		continue;
	}
	// Plan field changes.
	$cic_writes = array();
	foreach ( (array) $cic_new as $cic_field => $cic_value ) {
		if ( ! in_array( $cic_field, $cic_fields_all, true ) ) {
			continue;
		}
		$cic_now = $cic_pid ? trim( (string) get_post_meta( $cic_pid, 'cic_city_' . $cic_field, true ) ) : '';
		if ( $cic_now === $cic_value ) {
			++$cic_row['done'];
		} elseif ( '' === $cic_now ) {
			$cic_writes[ $cic_field ] = $cic_value;
		} else {
			$cic_row['kept'][]                  = $cic_field;
			$cic_row['proposals'][ $cic_field ] = $cic_value;
		}
	}
	$cic_row['set'] = count( $cic_writes );
	// Would the record be complete (for Reviewed)?
	$cic_have = static function ( string $f ) use ( $cic_pid, $cic_writes ) {
		return isset( $cic_writes[ $f ] ) || ( $cic_pid && '' !== trim( (string) get_post_meta( $cic_pid, 'cic_city_' . $f, true ) ) );
	};
	$cic_missing  = array_values( array_filter( $cic_required, static fn( $f ) => ! $cic_have( $f ) ) );
	$cic_status   = $cic_pid ? get_post_status( $cic_pid ) : '';
	$cic_reviewed = $cic_pid ? (string) get_post_meta( $cic_pid, Editorial::REVIEWED, true ) : '';
	$cic_publish  = $cic_opt['publish'] && ! $cic_missing && ( 'publish' !== $cic_status || '1' !== $cic_reviewed );

	if ( ! $cic_opt['dry'] && ( $cic_writes || $cic_publish ) ) {
		if ( ! $cic_pid ) {
			$cic_pid             = CityStore::ensure_editorial( $cic_cid );
			$cic_back['post_id'] = $cic_pid;
			$cic_back['created'] = true;
			if ( ! $cic_pid ) {
				$cic_row['result'] = 'error: could not create the content record';
				$cic_report[]      = $cic_row;
				continue;
			}
		} else {
			foreach ( array_keys( $cic_writes ) as $cic_field ) {
				$cic_back['before'][ $cic_field ] = (string) get_post_meta( $cic_pid, 'cic_city_' . $cic_field, true );
			}
			$cic_back['status']   = $cic_status;
			$cic_back['reviewed'] = $cic_reviewed;
		}
		// Backup first (append), then change.
		file_put_contents( $cic_backup_file, wp_json_encode( $cic_back, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n", FILE_APPEND | LOCK_EX );
		foreach ( $cic_writes as $cic_field => $cic_value ) {
			update_post_meta( $cic_pid, 'cic_city_' . $cic_field, wp_slash( $cic_value ) );
			update_post_meta( $cic_pid, '_cic_city_' . $cic_field, 'field_cic_city_' . $cic_field );
		}
		if ( $cic_publish ) {
			update_post_meta( $cic_pid, Editorial::REVIEWED, '1' );
			if ( 'publish' !== get_post_status( $cic_pid ) ) {
				wp_update_post( array( 'ID' => $cic_pid, 'post_status' => 'publish' ) );
			}
		}
	}
	$cic_row['post_id'] = $cic_pid;
	if ( $cic_missing ) {
		$cic_row['result'] = 'not reviewed: missing ' . implode( ', ', $cic_missing );
	} elseif ( ! $cic_opt['publish'] ) {
		$cic_row['result'] = 'content only (no-publish)';
	} elseif ( $cic_publish ) {
		$cic_row['result'] = $cic_opt['dry'] ? 'would publish + review' : 'published + reviewed';
	} else {
		$cic_row['result'] = 'already published + reviewed';
	}
	$cic_totals[ $cic_row['result'] ] = ( $cic_totals[ $cic_row['result'] ] ?? 0 ) + 1;
	WP_CLI::log( sprintf( '%-22s %-9s set %2d done %2d kept %d  %s', $cic_c['name'], $cic_row['source'], $cic_row['set'], $cic_row['done'], count( $cic_row['kept'] ), $cic_row['result'] ) );
	$cic_report[] = $cic_row;
}
if ( ! $cic_opt['dry'] ) {
	Tables::bump_cache();
}

$cic_indexable = 0;
foreach ( $cic_eligible as $cic_c ) {
	$cic_city = $cic_repo->city_by_id( (int) $cic_c['id'] );
	$cic_indexable += ( $cic_city && $cic_repo->is_indexable( $cic_city ) ) ? 1 : 0;
}
if ( $cic_not_eligible ) {
	WP_CLI::warning( count( $cic_not_eligible ) . ' data-file cities are not eligible on this site (skipped): ' . implode( ', ', array_slice( $cic_not_eligible, 0, 20 ) ) );
}
WP_CLI::log( 'Summary: ' . wp_json_encode( $cic_totals ) . sprintf( '; cities processed %d; indexable now %d of %d', count( $cic_report ), $cic_indexable, count( $cic_eligible ) ) );
if ( '' !== $cic_opt['report'] ) {
	file_put_contents( $cic_opt['report'], wp_json_encode( array( 'run' => gmdate( 'c' ), 'site' => home_url( '/' ), 'dry_run' => $cic_opt['dry'], 'backup' => $cic_backup_file, 'totals' => $cic_totals, 'not_eligible' => $cic_not_eligible, 'cities' => $cic_report ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	WP_CLI::log( 'Report: ' . $cic_opt['report'] );
}
WP_CLI::success( $cic_opt['dry'] ? 'Dry run finished.' : 'Done.' );
