<?php
/**
 * Safe CSV import for the location tables (BUILD_SPECIFICATION §9).
 *
 * - UTF-8 only (BOM stripped; invalid encoding rejects the file). Size and row limits.
 * - Dry run plans every row without writing; apply re-plans and writes in one transaction.
 * - Deterministic upsert by natural key (ZIP, state+slug, city+ZIP), processed in key order;
 *   counts created / updated / unchanged / rejected plus an in-file duplicate report.
 * - ZIPs stay strings: leading zeros survive, ZIP+4 is validated then shortened to 5 digits, and
 *   3–4 digit values (a lost leading zero) are rejected instead of guessed.
 * - Geography is not coverage: ZIP and mapping rows never change reviewed service states unless
 *   the import is explicitly a reviewed coverage dataset ($apply_status). New ZIPs start
 *   "unreviewed"; city publish/index states are set only when a city is created (draft/noindex
 *   by default) and never overwritten by a re-import.
 * - Source tracking: every row stores its dataset (fixture|production) and source reference.
 *   Fixture data cannot overwrite production rows, and fixture imports are refused where
 *   fixtures are not allowed (staging/production).
 * - Manual edits win: a coverage decision made in the Service Areas admin (manual_at) or a
 *   relationship removed there is reported as a conflict and left untouched, never overwritten.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class Importer {

	public const TYPES    = array( 'cities', 'zips', 'city_zips' );
	public const LOG      = 'cic_location_imports';
	public const MAX_SIZE = 5242880;
	public const MAX_ROWS = 20000;

	private const COLUMNS = array(
		'cities'    => array(
			'required' => array( 'state_code', 'county_name', 'city_name' ),
			'optional' => array( 'country_code', 'city_slug', 'timezone', 'latitude', 'longitude', 'display_order', 'publish_status', 'index_status' ),
		),
		'zips'      => array(
			'required' => array( 'zip', 'state_code' ),
			'optional' => array( 'county_label', 'latitude', 'longitude', 'service_status' ),
		),
		'city_zips' => array(
			'required' => array( 'state_code', 'city_slug', 'zip' ),
			'optional' => array( 'service_override', 'review_note' ),
		),
	);

	private string $type;
	private string $dataset;
	private string $source;
	private bool $apply_status;

	public function __construct( string $type, string $dataset, string $source, bool $apply_status = false ) {
		$this->type         = $type;
		$this->dataset      = $dataset;
		$this->source       = mb_substr( sanitize_text_field( $source ), 0, 190 );
		$this->apply_status = $apply_status;
	}

	/**
	 * Plan (dry run) or apply a CSV string.
	 *
	 * @return array<string, mixed>|\WP_Error Report.
	 */
	public function run( string $csv, bool $dry_run = true ) {
		if ( ! in_array( $this->type, self::TYPES, true ) ) {
			return new \WP_Error( 'cic_import_type', __( 'Unknown import type.', 'cater-ice-cream-core' ) );
		}
		if ( ! in_array( $this->dataset, Tables::DATASETS, true ) ) {
			return new \WP_Error( 'cic_import_dataset', __( 'Choose the dataset: fixture or production.', 'cater-ice-cream-core' ) );
		}
		if ( 'fixture' === $this->dataset && ! Tables::fixtures_allowed() ) {
			return new \WP_Error( 'cic_import_fixture', __( 'Fixture data can only be imported on a local or development site.', 'cater-ice-cream-core' ) );
		}
		if ( '' === $this->source ) {
			return new \WP_Error( 'cic_import_source', __( 'Enter a source reference (where this data came from).', 'cater-ice-cream-core' ) );
		}
		if ( ! Tables::ready() ) {
			return new \WP_Error( 'cic_import_schema', __( 'Location tables are not installed yet.', 'cater-ice-cream-core' ) );
		}
		$parsed = $this->parse( $csv );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		$plan = $this->plan( $parsed['rows'] );
		$plan['ignored_columns'] = $parsed['ignored'];
		$plan['dry_run']         = $dry_run;
		$plan['type']            = $this->type;
		$plan['dataset']         = $this->dataset;
		$plan['source']          = $this->source;
		$plan['apply_status']    = $this->apply_status;
		$plan['sha256']          = hash( 'sha256', $csv );

		if ( ! $dry_run ) {
			$this->apply( $plan['actions'] );
			if ( $plan['counts']['created'] || $plan['counts']['updated'] ) {
				Tables::bump_cache();
			}
			CityStore::audit( 'import', sprintf( '%s/%s %s: %d created, %d updated, %d unchanged, %d rejected', $this->type, $this->dataset, $this->source, $plan['counts']['created'], $plan['counts']['updated'], $plan['counts']['unchanged'], $plan['counts']['rejected'] ) );
		}
		$this->log( $plan );
		return $plan;
	}

	/**
	 * @return array{rows: array<int, array<string, string>>, ignored: string[]}|\WP_Error
	 */
	private function parse( string $csv ) {
		if ( strlen( $csv ) > self::MAX_SIZE ) {
			return new \WP_Error( 'cic_import_size', __( 'The file is larger than 5 MB.', 'cater-ice-cream-core' ) );
		}
		if ( str_starts_with( $csv, "\xEF\xBB\xBF" ) ) {
			$csv = substr( $csv, 3 );
		}
		if ( '' === trim( $csv ) ) {
			return new \WP_Error( 'cic_import_empty', __( 'The file is empty.', 'cater-ice-cream-core' ) );
		}
		if ( ! mb_check_encoding( $csv, 'UTF-8' ) ) {
			return new \WP_Error( 'cic_import_encoding', __( 'The file is not valid UTF-8. Save it as “CSV UTF-8” and try again.', 'cater-ice-cream-core' ) );
		}
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- in-memory stream.
		fwrite( $handle, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $handle );
		$header = fgetcsv( $handle, 0, ',', '"', '' );
		$header = array_map( static fn( $h ) => strtolower( trim( (string) $h ) ), (array) $header );
		$spec   = self::COLUMNS[ $this->type ];
		$miss   = array_diff( $spec['required'], $header );
		if ( $miss ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			/* translators: %s: column names. */
			return new \WP_Error( 'cic_import_columns', sprintf( __( 'Missing required column(s): %s.', 'cater-ice-cream-core' ), implode( ', ', $miss ) ) );
		}
		$known   = array_merge( $spec['required'], $spec['optional'] );
		$ignored = array_values( array_filter( array_diff( $header, $known ) ) );
		$rows    = array();
		$line    = 1;
		while ( false !== ( $cells = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			$line++;
			if ( array( null ) === $cells || ( 1 === count( $cells ) && '' === trim( (string) $cells[0] ) ) ) {
				continue;
			}
			if ( count( $rows ) >= self::MAX_ROWS ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				return new \WP_Error( 'cic_import_rows', __( 'Too many rows (limit 20,000). Split the file.', 'cater-ice-cream-core' ) );
			}
			$row = array( '_line' => (string) $line );
			foreach ( $header as $i => $col ) {
				if ( in_array( $col, $known, true ) ) {
					$row[ $col ] = trim( (string) ( $cells[ $i ] ?? '' ) );
				}
			}
			$rows[] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return array( 'rows' => $rows, 'ignored' => $ignored );
	}

	/**
	 * Validate and compare every row; nothing is written here.
	 *
	 * @param array<int, array<string, string>> $rows Parsed rows.
	 * @return array<string, mixed>
	 */
	private function plan( array $rows ): array {
		$counts     = array( 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'rejected' => 0, 'duplicates' => 0, 'conflicts' => 0 );
		$actions    = array();
		$rejections = array();
		$conflicts  = array();
		$unlinks    = 'city_zips' === $this->type ? (array) get_option( CityStore::UNLINK_OPTION, array() ) : array();
		$seen       = array();
		$valid      = array();

		foreach ( $rows as $row ) {
			$record = $this->validate( $row );
			if ( is_string( $record ) ) {
				$counts['rejected']++;
				$rejections[] = array( 'line' => (int) $row['_line'], 'reason' => $record );
				continue;
			}
			if ( isset( $seen[ $record['_key'] ] ) ) {
				$counts['rejected']++;
				$counts['duplicates']++;
				/* translators: 1: key, 2: line number. */
				$rejections[] = array( 'line' => (int) $row['_line'], 'reason' => sprintf( __( 'Duplicate of %1$s on line %2$d.', 'cater-ice-cream-core' ), $record['_key'], $seen[ $record['_key'] ] ) );
				continue;
			}
			$seen[ $record['_key'] ] = (int) $row['_line'];
			$valid[]                 = $record;
		}

		usort( $valid, static fn( $a, $b ) => strcmp( $a['_key'], $b['_key'] ) );
		foreach ( $valid as $record ) {
			$existing = $this->existing( $record );
			if ( null === $existing && isset( $unlinks[ $record['_key'] ] ) ) {
				$counts['conflicts']++;
				/* translators: 1: ZIP, 2: date. */
				$conflicts[] = array( 'line' => $record['_line'], 'key' => $record['_key'], 'reason' => sprintf( __( 'ZIP %1$s was removed from this city by hand on %2$s. Not re-linked; add it again in the city editor if intended.', 'cater-ice-cream-core' ), $record['zip'], $unlinks[ $record['_key'] ] ) );
				continue;
			}
			if ( null === $existing ) {
				$counts['created']++;
				$actions[] = array( 'action' => 'create', 'key' => $record['_key'], 'line' => $record['_line'], 'data' => $record );
				continue;
			}
			if ( $existing['dataset'] !== $this->dataset && 'production' === $existing['dataset'] ) {
				$counts['rejected']++;
				$rejections[] = array( 'line' => $record['_line'], 'reason' => __( 'Would overwrite an approved production row with fixture data.', 'cater-ice-cream-core' ) );
				continue;
			}
			$diff = array();
			foreach ( $this->managed( $record ) as $col => $value ) {
				if ( (string) $existing[ $col ] !== (string) $value && ! ( null === $value && null === $existing[ $col ] ) && ! ( is_numeric( $value ) && is_numeric( $existing[ $col ] ) && abs( (float) $value - (float) $existing[ $col ] ) < 0.0000005 ) ) {
					$diff[ $col ] = $value;
				}
			}
			// Manual coverage decisions win over the file: report them, never overwrite.
			$protected = array( 'zips' => array( 'service_status' ), 'city_zips' => array( 'service_override', 'review_note' ) )[ $this->type ] ?? array();
			$clash     = ! empty( $existing['manual_at'] ) ? array_intersect( array_keys( $diff ), $protected ) : array();
			if ( $clash ) {
				$counts['conflicts']++;
				$conflicts[] = array(
					'line'   => $record['_line'],
					'key'    => $record['_key'],
					/* translators: 1: fields, 2: date. */
					'reason' => sprintf( __( 'File changes %1$s, but this was decided by hand on %2$s. Kept the manual decision.', 'cater-ice-cream-core' ), implode( ', ', array_map( static fn( $c ) => $c . ' → ' . $diff[ $c ], $clash ) ), $existing['manual_at'] ),
				);
				$diff = array_diff_key( $diff, array_flip( $clash ) );
				if ( ! array_diff( array_keys( $diff ), array( 'source_reference', 'dataset' ) ) ) {
					$diff = array(); // Only provenance left: keep the row's own source.
				}
			}
			if ( $diff ) {
				$counts['updated']++;
				$actions[] = array( 'action' => 'update', 'key' => $record['_key'], 'line' => $record['_line'], 'data' => $record, 'diff' => $diff, 'existing' => $existing );
			} elseif ( ! $clash ) {
				$counts['unchanged']++;
			}
		}
		usort( $rejections, static fn( $a, $b ) => $a['line'] <=> $b['line'] );
		return array(
			'counts'     => $counts,
			'actions'    => $actions,
			'rejections' => $rejections,
			'conflicts'  => $conflicts,
		);
	}

	/**
	 * @param array<string, string> $row Raw row.
	 * @return array<string, mixed>|string Normalised record or a rejection reason.
	 */
	private function validate( array $row ) {
		$line  = (int) $row['_line'];
		$state = strtoupper( $row['state_code'] ?? '' );
		if ( ! preg_match( '/^[A-Z]{2}$/', $state ) ) {
			return __( 'State code must be two letters (e.g. CA).', 'cater-ice-cream-core' );
		}
		$lat = $this->coordinate( $row['latitude'] ?? '', 90 );
		$lng = $this->coordinate( $row['longitude'] ?? '', 180 );
		if ( false === $lat || false === $lng ) {
			return __( 'Latitude/longitude must be decimal degrees within range, or empty.', 'cater-ice-cream-core' );
		}

		if ( 'zips' === $this->type ) {
			$zip = $this->zip( $row['zip'] );
			if ( ! preg_match( '/^\d{5}$/', $zip ) ) {
				return $zip;
			}
			$record = array(
				'_key'         => $zip,
				'_line'        => $line,
				'zip'          => $zip,
				'state_code'   => $state,
				'county_label' => mb_substr( sanitize_text_field( $row['county_label'] ?? '' ), 0, 100 ),
				'latitude'     => $lat,
				'longitude'    => $lng,
			);
			if ( $this->apply_status ) {
				$status = strtolower( $row['service_status'] ?? '' );
				if ( ! in_array( $status, Tables::ZIP_STATUSES, true ) ) {
					return __( 'service_status must be unreviewed, enabled, disabled or manual_review for a reviewed coverage import.', 'cater-ice-cream-core' );
				}
				$record['service_status'] = $status;
			}
			return $record;
		}

		if ( 'cities' === $this->type ) {
			$name = sanitize_text_field( $row['city_name'] );
			if ( '' === $name || mb_strlen( $name ) > 120 ) {
				return __( 'City name is required (up to 120 characters).', 'cater-ice-cream-core' );
			}
			$county = sanitize_text_field( $row['county_name'] );
			if ( '' === $county ) {
				return __( 'County name is required.', 'cater-ice-cream-core' );
			}
			$country = strtoupper( '' !== ( $row['country_code'] ?? '' ) ? $row['country_code'] : 'US' );
			if ( 'US' !== $country ) {
				return __( 'Only US locations are supported.', 'cater-ice-cream-core' );
			}
			$slug = sanitize_title( '' !== ( $row['city_slug'] ?? '' ) ? $row['city_slug'] : $name );
			$tz   = '' !== ( $row['timezone'] ?? '' ) ? $row['timezone'] : 'America/Los_Angeles';
			if ( ! in_array( $tz, timezone_identifiers_list(), true ) ) {
				return __( 'Unknown timezone.', 'cater-ice-cream-core' );
			}
			$publish = strtolower( $row['publish_status'] ?? '' );
			$index   = strtolower( $row['index_status'] ?? '' );
			if ( ( '' !== $publish && ! in_array( $publish, Tables::PUBLISH_STATUSES, true ) ) || ( '' !== $index && ! in_array( $index, Tables::INDEX_STATUSES, true ) ) ) {
				return __( 'publish_status must be draft or published; index_status noindex or index.', 'cater-ice-cream-core' );
			}
			return array(
				'_key'           => $state . '/' . $slug,
				'_line'          => $line,
				'country_code'   => $country,
				'state_code'     => $state,
				'county_name'    => $county,
				'county_slug'    => sanitize_title( $county ),
				'city_name'      => $name,
				'city_slug'      => $slug,
				'name_search'    => Tables::search_key( $name ),
				'timezone'       => $tz,
				'latitude'       => $lat,
				'longitude'      => $lng,
				'display_order'  => (int) ( $row['display_order'] ?? 0 ),
				'publish_status' => '' !== $publish ? $publish : 'draft',
				'index_status'   => '' !== $index ? $index : 'noindex',
			);
		}

		// city_zips.
		$zip = $this->zip( $row['zip'] );
		if ( ! preg_match( '/^\d{5}$/', $zip ) ) {
			return $zip;
		}
		$slug    = sanitize_title( $row['city_slug'] );
		$city_id = $this->city_id( $state, $slug );
		if ( ! $city_id ) {
			/* translators: %s: city slug. */
			return sprintf( __( 'Unknown city “%s”: import cities first.', 'cater-ice-cream-core' ), $slug );
		}
		if ( ! $this->zip_exists( $zip ) ) {
			/* translators: %s: ZIP. */
			return sprintf( __( 'ZIP %s is not in the ZIP table: import ZIPs first.', 'cater-ice-cream-core' ), $zip );
		}
		$record = array(
			'_key'        => $city_id . '/' . $zip,
			'_line'       => $line,
			'city_id'     => $city_id,
			'zip'         => $zip,
			'review_note' => mb_substr( sanitize_text_field( $row['review_note'] ?? '' ), 0, 255 ),
		);
		if ( $this->apply_status ) {
			$override = strtolower( '' !== ( $row['service_override'] ?? '' ) ? $row['service_override'] : 'inherit' );
			if ( ! in_array( $override, Tables::OVERRIDES, true ) ) {
				return __( 'service_override must be inherit, enabled, disabled or manual_review.', 'cater-ice-cream-core' );
			}
			$record['service_override'] = $override;
		}
		return $record;
	}

	/** Normalised ZIP, or a rejection reason. */
	private function zip( string $raw ): string {
		$raw = trim( $raw );
		if ( preg_match( '/^(\d{5})(?:-(\d{4}))?$/', $raw, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/^\d{3,4}$/', $raw ) ) {
			return __( 'ZIP has fewer than 5 digits; a leading zero was probably lost (for example by a spreadsheet). Fix the source instead of guessing.', 'cater-ice-cream-core' );
		}
		return __( 'ZIP must be 5 digits or ZIP+4 (12345-6789).', 'cater-ice-cream-core' );
	}

	/** @return float|null|false */
	private function coordinate( string $raw, int $max ) {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		if ( ! is_numeric( $raw ) || abs( (float) $raw ) > $max ) {
			return false;
		}
		return round( (float) $raw, 6 );
	}

	private function city_id( string $state, string $slug ): int {
		global $wpdb;
		$t = Tables::name( 'cities' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE country_code = 'US' AND state_code = %s AND city_slug = %s", $state, $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function zip_exists( string $zip ): bool {
		global $wpdb;
		$t = Tables::name( 'zips' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT zip FROM {$t} WHERE zip = %s", $zip ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * @param array<string, mixed> $record Record.
	 * @return array<string, mixed>|null
	 */
	private function existing( array $record ): ?array {
		global $wpdb;
		switch ( $this->type ) {
			case 'zips':
				$t = Tables::name( 'zips' );
				return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE zip = %s", $record['zip'] ), ARRAY_A ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			case 'cities':
				$t = Tables::name( 'cities' );
				return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE country_code = %s AND state_code = %s AND city_slug = %s", $record['country_code'], $record['state_code'], $record['city_slug'] ), ARRAY_A ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			default:
				$t = Tables::name( 'city_zips' );
				return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE city_id = %d AND zip = %s", $record['city_id'], $record['zip'] ), ARRAY_A ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Columns an import may change on an existing row. Editorial states (publish/index) and, for
	 * geography imports, reviewed service states are never overwritten.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return array<string, mixed>
	 */
	private function managed( array $record ): array {
		$cols = array(
			'zips'      => array( 'state_code', 'county_label', 'latitude', 'longitude', 'service_status' ),
			'cities'    => array( 'county_name', 'county_slug', 'city_name', 'name_search', 'timezone', 'latitude', 'longitude', 'display_order' ),
			'city_zips' => array( 'review_note', 'service_override' ),
		)[ $this->type ];
		$out  = array();
		foreach ( $cols as $col ) {
			if ( array_key_exists( $col, $record ) ) {
				$out[ $col ] = $record[ $col ];
			}
		}
		$out['source_reference'] = $this->source;
		$out['dataset']          = $this->dataset;
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $actions Planned actions.
	 */
	private function apply( array $actions ): void {
		global $wpdb;
		$now   = current_time( 'mysql', true );
		$table = Tables::name( $this->type );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		try {
			foreach ( $actions as $a ) {
				$data = array_filter( $a['data'], static fn( $k ) => '_' !== $k[0], ARRAY_FILTER_USE_KEY );
				if ( 'create' === $a['action'] ) {
					$data['dataset']          = $this->dataset;
					$data['source_reference'] = $this->source;
					$data['updated_at']       = $now;
					if ( 'zips' === $this->type ) {
						$data['service_status'] = $data['service_status'] ?? 'unreviewed';
						$data['reviewed_at']    = 'unreviewed' === $data['service_status'] ? null : $now;
					}
					if ( 'city_zips' === $this->type ) {
						$data['service_override'] = $data['service_override'] ?? 'inherit';
					}
					$wpdb->insert( $table, $data );
				} else {
					$changes               = $a['diff'];
					$changes['updated_at'] = $now;
					if ( 'zips' === $this->type && isset( $changes['service_status'] ) ) {
						$changes['reviewed_at'] = 'unreviewed' === $changes['service_status'] ? null : $now;
					}
					$where = 'zips' === $this->type ? array( 'zip' => $a['data']['zip'] ) : ( 'cities' === $this->type ? array( 'id' => (int) $a['existing']['id'] ) : array( 'city_id' => $a['data']['city_id'], 'zip' => $a['data']['zip'] ) );
					$wpdb->update( $table, $changes, $where );
				}
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw $e;
		}
	}

	/**
	 * @param array<string, mixed> $plan Report.
	 */
	private function log( array $plan ): void {
		$log   = (array) get_option( self::LOG, array() );
		$log[] = array(
			'time'    => gmdate( 'Y-m-d H:i:s' ),
			'user'    => get_current_user_id(),
			'type'    => $plan['type'],
			'dataset' => $plan['dataset'],
			'source'  => $plan['source'],
			'sha256'  => $plan['sha256'],
			'dry_run' => $plan['dry_run'],
			'counts'  => $plan['counts'],
		);
		update_option( self::LOG, array_slice( $log, -30 ), false );
	}
}
