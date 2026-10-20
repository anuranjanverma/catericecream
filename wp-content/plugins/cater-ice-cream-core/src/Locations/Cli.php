<?php
/**
 * WP-CLI: location imports and checks.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class Cli {

	/** Bundled local fixtures (cities, then ZIP geography + reviewed fixture states, then mappings). */
	public const FIXTURES = array(
		'cities'    => 'cities.fixture.csv',
		'zips'      => 'zips.fixture.csv',
		'city_zips' => 'city-zips.fixture.csv',
	);
	public const FIXTURE_SOURCE = 'fixture:local-dev-2026-10-20';

	/**
	 * Imports a location CSV. Dry run unless --apply is given.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : UTF-8 CSV file.
	 *
	 * --type=<type>
	 * : cities, zips or city_zips.
	 *
	 * --dataset=<dataset>
	 * : fixture or production.
	 *
	 * --source=<source>
	 * : Where the data came from (stored on every row).
	 *
	 * [--apply-status]
	 * : Reviewed coverage dataset: apply service_status / service_override columns.
	 *
	 * [--apply]
	 * : Write the changes (default is a dry run).
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			\WP_CLI::error( 'File not readable.' );
		}
		$importer = new Importer( (string) ( $assoc_args['type'] ?? '' ), (string) ( $assoc_args['dataset'] ?? '' ), (string) ( $assoc_args['source'] ?? '' ), isset( $assoc_args['apply-status'] ) );
		$this->report( $importer->run( (string) file_get_contents( $file ), ! isset( $assoc_args['apply'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Imports the bundled local fixtures (local/development sites only). Safe to repeat.
	 * Fixture rows are test data, not approved coverage, and are ignored on staging/production.
	 *
	 * [--dry-run]
	 * : Plan only.
	 *
	 * @subcommand import-fixtures
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function import_fixtures( array $args, array $assoc_args ): void {
		( new \CIC\Database\Migrations() )->maybe_migrate();
		foreach ( self::FIXTURES as $type => $file ) {
			\WP_CLI::log( "== {$type} ({$file})" );
			$importer = new Importer( $type, 'fixture', self::FIXTURE_SOURCE, true );
			$this->report( $importer->run( (string) file_get_contents( CIC_CORE_DIR . 'data/fixtures/' . $file ), isset( $assoc_args['dry-run'] ) ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
	}

	/**
	 * Location data summary by dataset and status.
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function status( array $args, array $assoc_args ): void {
		global $wpdb;
		$t = Tables::all();
		\WP_CLI::log( 'Environment: ' . wp_get_environment_type() . ' (fixtures ' . ( Tables::fixtures_allowed() ? 'counted' : 'ignored' ) . ')' );
		\WP_CLI::log( 'Repository: ' . get_class( Service::repository() ) );
		foreach ( array( 'cities' => 'publish_status', 'zips' => 'service_status', 'city_zips' => 'service_override' ) as $table => $col ) {
			$rows = $wpdb->get_results( "SELECT dataset, {$col} AS s, COUNT(*) AS n FROM {$t[ $table ]} GROUP BY dataset, {$col} ORDER BY dataset, {$col}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			\WP_CLI::log( $table . ': ' . ( $rows ? implode( ', ', array_map( static fn( $r ) => "{$r['dataset']}/{$r['s']}={$r['n']}", $rows ) ) : 'empty' ) );
		}
	}

	/**
	 * Coverage check, e.g. `wp cic locations check 91101` or `check 90220 --city=compton`.
	 *
	 * <location>
	 * : ZIP or city.
	 *
	 * [--city=<city>]
	 * : Venue city for a shared ZIP.
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function check( array $args, array $assoc_args ): void {
		$r = Service::repository()->evaluate_coverage( $args[0], (string) ( $assoc_args['city'] ?? '' ) );
		\WP_CLI::log( sprintf( '%s → %s (%s)%s: %s', $args[0], $r['status'], $r['reason'] ?? '', $r['fixture'] ? ' [fixture]' : '', $r['message'] ) );
	}

	/**
	 * @param array<string, mixed>|\WP_Error $report Report.
	 */
	private function report( $report, bool $verbose = true ): void {
		if ( is_wp_error( $report ) ) {
			\WP_CLI::error( $report->get_error_message() );
		}
		$c = $report['counts'];
		foreach ( $report['rejections'] as $r ) {
			\WP_CLI::warning( sprintf( 'line %d: %s', $r['line'], $r['reason'] ) );
		}
		if ( $verbose ) {
			foreach ( array_slice( $report['actions'], 0, 50 ) as $a ) {
				\WP_CLI::log( sprintf( '%-7s %s%s', $a['action'], $a['key'], isset( $a['diff'] ) ? ' (' . implode( ', ', array_keys( $a['diff'] ) ) . ')' : '' ) );
			}
		}
		if ( $report['ignored_columns'] ) {
			\WP_CLI::log( 'Ignored columns: ' . implode( ', ', $report['ignored_columns'] ) );
		}
		\WP_CLI::success( sprintf( '%s: %d created, %d updated, %d unchanged, %d rejected (%d duplicates).', $report['dry_run'] ? 'Dry run' : 'Applied', $c['created'], $c['updated'], $c['unchanged'], $c['rejected'], $c['duplicates'] ) );
	}
}
