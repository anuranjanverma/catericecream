<?php
/**
 * Versioned, non-destructive schema migrations.
 *
 * Runs on activation and on admin requests when the stored version is behind; never on public
 * page loads. Each step is idempotent and uses dbDelta with $wpdb->prefix when tables arrive.
 *
 * v1 baseline (Phase 01). v2 (Phase 03): product-event pairing table and event capabilities.
 * Location tables wait for the owner's city template and rewrite code (see BUILD_STATUS.md).
 *
 * @package CIC
 */

namespace CIC\Database;

use CIC\Config;

defined( 'ABSPATH' ) || exit;

final class Migrations {

	public const TARGET_VERSION = 2;

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
	 * Product-event pairings: the single store of pairing copy plus its unique, indexed relation.
	 * Event capabilities go to administrators and editors.
	 */
	private function migrate_to_2(): void {
		self::create_pairings_table();
		\CIC\Content\EventType::grant_capabilities();
	}

	public static function create_pairings_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'cic_product_events';
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL,
				event_id bigint(20) unsigned NOT NULL,
				headline varchar(200) NOT NULL DEFAULT '',
				description text NOT NULL,
				image_id bigint(20) unsigned NOT NULL DEFAULT 0,
				link_label varchar(120) NOT NULL DEFAULT '',
				event_intro text NOT NULL,
				product_sort int(11) NOT NULL DEFAULT 0,
				event_sort int(11) NOT NULL DEFAULT 0,
				updated_gmt datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY product_event (product_id,event_id),
				KEY event_lookup (event_id,event_sort)
			) {$collate};"
		);
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
