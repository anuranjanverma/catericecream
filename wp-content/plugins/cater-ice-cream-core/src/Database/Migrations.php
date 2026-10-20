<?php
/**
 * Versioned, non-destructive schema migrations.
 *
 * Runs on activation and on admin requests when the stored version is behind; never on public
 * page loads. Each step is idempotent and uses dbDelta with $wpdb->prefix when tables arrive.
 *
 * v1 baseline (Phase 01). v2 (Phase 03): product-event pairing table and event capabilities.
 * v3 (Phase 05): provisional location tables (spec §9 fallback contract) behind the location
 * repository adapter, plus the manage_locations capability. The owner's city code is still
 * pending; these tables are a local fallback, not the final production schema.
 *
 * @package CIC
 */

namespace CIC\Database;

use CIC\Config;

defined( 'ABSPATH' ) || exit;

final class Migrations {

	public const TARGET_VERSION = 3;

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

	/**
	 * Location fallback tables and the location-management capability.
	 */
	private function migrate_to_3(): void {
		self::create_location_tables();
		\CIC\Locations\Tables::grant_capabilities();
	}

	/**
	 * Cities, ZIPs, city-ZIP mappings, editorial links and slug history (aliases + old slugs).
	 * ZIPs are CHAR(5) strings, so leading zeros survive. Prefix and reverse lookups are indexed.
	 */
	public static function create_location_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		$t       = \CIC\Locations\Tables::all();
		dbDelta(
			"CREATE TABLE {$t['cities']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				country_code char(2) NOT NULL DEFAULT 'US',
				state_code char(2) NOT NULL,
				county_name varchar(100) NOT NULL DEFAULT '',
				county_slug varchar(100) NOT NULL DEFAULT '',
				city_name varchar(120) NOT NULL,
				city_slug varchar(140) NOT NULL,
				name_search varchar(140) NOT NULL DEFAULT '',
				timezone varchar(64) NOT NULL DEFAULT 'America/Los_Angeles',
				latitude decimal(9,6) NULL DEFAULT NULL,
				longitude decimal(9,6) NULL DEFAULT NULL,
				publish_status varchar(20) NOT NULL DEFAULT 'draft',
				index_status varchar(20) NOT NULL DEFAULT 'noindex',
				display_order int(11) NOT NULL DEFAULT 0,
				dataset varchar(20) NOT NULL DEFAULT 'production',
				source_reference varchar(190) NOT NULL DEFAULT '',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY location_slug (country_code,state_code,city_slug),
				KEY state_county (state_code,county_slug,display_order),
				KEY name_prefix (state_code,name_search)
			) {$collate};"
		);
		dbDelta(
			"CREATE TABLE {$t['zips']} (
				zip char(5) NOT NULL,
				state_code char(2) NOT NULL DEFAULT '',
				county_label varchar(100) NOT NULL DEFAULT '',
				latitude decimal(9,6) NULL DEFAULT NULL,
				longitude decimal(9,6) NULL DEFAULT NULL,
				service_status varchar(20) NOT NULL DEFAULT 'unreviewed',
				dataset varchar(20) NOT NULL DEFAULT 'production',
				source_reference varchar(190) NOT NULL DEFAULT '',
				reviewed_at datetime NULL DEFAULT NULL,
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (zip),
				KEY service_status (service_status)
			) {$collate};"
		);
		dbDelta(
			"CREATE TABLE {$t['city_zips']} (
				city_id bigint(20) unsigned NOT NULL,
				zip char(5) NOT NULL,
				service_override varchar(20) NOT NULL DEFAULT 'inherit',
				review_note varchar(255) NOT NULL DEFAULT '',
				dataset varchar(20) NOT NULL DEFAULT 'production',
				source_reference varchar(190) NOT NULL DEFAULT '',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (city_id,zip),
				KEY zip (zip)
			) {$collate};"
		);
		dbDelta(
			"CREATE TABLE {$t['city_content']} (
				city_id bigint(20) unsigned NOT NULL,
				editorial_post_id bigint(20) unsigned NOT NULL,
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (city_id),
				UNIQUE KEY editorial_post_id (editorial_post_id)
			) {$collate};"
		);
		dbDelta(
			"CREATE TABLE {$t['city_slugs']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				state_code char(2) NOT NULL,
				slug varchar(140) NOT NULL,
				city_id bigint(20) unsigned NOT NULL,
				kind varchar(20) NOT NULL DEFAULT 'history',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY state_slug (state_code,slug),
				KEY city_id (city_id)
			) {$collate};"
		);
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
