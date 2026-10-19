<?php
/**
 * Versioned, non-destructive schema migrations.
 *
 * Runs on activation and on admin requests when the stored version is behind; never on public
 * page loads. Each step is idempotent and uses dbDelta with $wpdb->prefix when tables arrive.
 *
 * Phase 01 ships only the baseline. Location tables wait for the owner's city template and
 * rewrite code (see BUILD_STATUS.md); the product-event index arrives with Phase 03.
 *
 * @package CIC
 */

namespace CIC\Database;

use CIC\Config;

defined( 'ABSPATH' ) || exit;

final class Migrations {

	public const TARGET_VERSION = 1;

	private const LOCK_OPTION = 'cic_core_migration_lock';
	private const LOCK_TTL    = 300;

	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_migrate' ) );
	}

	public function current_version(): int {
		return (int) get_option( Config::OPTION_SCHEMA_VERSION, 0 );
	}

	public function maybe_migrate(): void {
		if ( $this->current_version() >= self::TARGET_VERSION || ! $this->acquire_lock() ) {
			return;
		}
		try {
			for ( $version = $this->current_version() + 1; $version <= self::TARGET_VERSION; $version++ ) {
				$method = 'migrate_to_' . $version;
				$this->$method();
				update_option( Config::OPTION_SCHEMA_VERSION, $version, false );
			}
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * Baseline: records the schema version only. No tables are created yet.
	 */
	private function migrate_to_1(): void {
	}

	/**
	 * add_option is atomic (fails if the row exists), so concurrent admin requests cannot run
	 * the same migration twice. A stale lock expires after LOCK_TTL seconds.
	 */
	private function acquire_lock(): bool {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}
		$locked_at = (int) get_option( self::LOCK_OPTION, 0 );
		if ( $locked_at && ( time() - $locked_at ) > self::LOCK_TTL ) {
			delete_option( self::LOCK_OPTION );
			return add_option( self::LOCK_OPTION, time(), '', false );
		}
		return false;
	}
}
