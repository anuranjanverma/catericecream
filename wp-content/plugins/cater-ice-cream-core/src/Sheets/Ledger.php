<?php
/**
 * Export ledger ({prefix}cic_sheet_sync): one row per enquiry already appended to the Sheet.
 * The Flamingo id is the primary key, so a retried or overlapping run can never append the same
 * enquiry twice. No personal data is stored here - only the reference and the row Google returned.
 *
 * @package CIC
 */

namespace CIC\Sheets;

defined( 'ABSPATH' ) || exit;

final class Ledger {

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cic_sheet_sync';
	}

	public static function exists(): bool {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/** Create the ledger (idempotent; also called by the v6 migration and the CLI installer). */
	public static function create(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				flamingo_id bigint(20) unsigned NOT NULL,
				spreadsheet_id varchar(90) NOT NULL DEFAULT '',
				row_ref varchar(190) NOT NULL DEFAULT '',
				synced_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (flamingo_id),
				KEY synced_at (synced_at)
			) {$collate};"
		);
	}

	/**
	 * Submitted enquiries that have not been exported yet. Incomplete (saved draft) enquiries and
	 * spam are never exported.
	 *
	 * @return array<int, int>
	 */
	public static function pending_ids( int $limit ): array {
		global $wpdb;
		$ledger = self::table();
		$sql    = "SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_cic_type' AND t.meta_value <> ''
			LEFT JOIN {$ledger} l ON l.flamingo_id = p.ID
			WHERE p.post_type = 'flamingo_inbound'
				AND p.post_status = 'publish'
				AND l.flamingo_id IS NULL
				AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} s WHERE s.post_id = p.ID AND s.meta_key = '_cic_stage' AND s.meta_value = 'incomplete' )
				AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} sp WHERE sp.post_id = p.ID AND sp.meta_key = '_cic_status' AND sp.meta_value = 'spam' )
			ORDER BY p.ID ASC
			LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids    = $wpdb->get_col( $wpdb->prepare( $sql, max( 1, $limit ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Record exported enquiries.
	 *
	 * @param array<int, int> $ids       Flamingo ids.
	 * @param string          $row_ref   Updated range reported by Google.
	 * @param string          $sheet_id  Spreadsheet id.
	 */
	public static function mark( array $ids, string $row_ref, string $sheet_id ): void {
		global $wpdb;
		$now = current_time( 'mysql', true );
		foreach ( $ids as $id ) {
			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table(),
				array(
					'flamingo_id'    => (int) $id,
					'spreadsheet_id' => $sheet_id,
					'row_ref'        => mb_substr( $row_ref, 0, 190 ),
					'synced_at'      => $now,
				),
				array( '%d', '%s', '%s', '%s' )
			);
		}
	}

	public static function count(): int {
		global $wpdb;
		if ( ! self::exists() ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}

	public static function reset(): void {
		global $wpdb;
		if ( self::exists() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( 'TRUNCATE TABLE ' . self::table() );
		}
	}
}