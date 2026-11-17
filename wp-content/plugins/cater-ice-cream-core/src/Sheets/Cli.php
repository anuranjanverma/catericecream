<?php
/**
 * WP-CLI: wp cic sheets <install|status|sync|test|reset|token>
 *
 * @package CIC
 */

namespace CIC\Sheets;

defined( 'ABSPATH' ) || exit;

final class Cli {

	/** Create the ledger table and run pending schema migrations. */
	public function install(): void {
		( new \CIC\Database\Migrations() )->maybe_migrate();
		Ledger::create();
		\WP_CLI::success( 'Sheets ledger ready.' );
	}

	/** Configuration, credentials and export state (no personal data). */
	public function status(): void {
		$s    = Settings::get();
		$cred = Credentials::load();
		\WP_CLI::log( 'Enabled: ' . ( $s['enabled'] ? 'yes' : 'no' ) );
		\WP_CLI::log( 'Spreadsheet: ' . ( $s['spreadsheet_id'] ?: '(not set)' ) . ' · tab: ' . $s['sheet_name'] );
		\WP_CLI::log( 'Credentials: ' . Credentials::path() . ( is_wp_error( $cred ) ? ' (unusable)' : ' (' . $cred['client_email'] . ')' ) );
		\WP_CLI::log( 'Exported: ' . Ledger::count() . ' · pending: ' . count( Ledger::pending_ids( 500 ) ) );
		$st = Settings::status();
		\WP_CLI::log( 'Last run: ' . ( $st['last_run'] ?: 'never' ) . ' · rows: ' . (int) $st['last_rows'] . ( $st['last_error'] ? ' · error: ' . $st['last_error'] : '' ) );
	}

	/**
	 * Run the export now.
	 *
	 * ## OPTIONS
	 * [--limit=<n>]
	 * : Maximum number of enquiries to export.
	 */
	public function sync( array $args, array $assoc ): void {
		$limit  = isset( $assoc['limit'] ) ? (int) $assoc['limit'] : 0;
		$result = Sync::run( $limit );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		\WP_CLI::success( sprintf( '%d pending, %d exported.', (int) $result['pending'], (int) $result['synced'] ) );
	}

	/** Verify credentials, spreadsheet access and the header row. */
	public function test(): void {
		$meta = SheetClient::meta();
		if ( is_wp_error( $meta ) ) {
			\WP_CLI::error( $meta->get_error_message() );
		}
		$title = (string) ( $meta['properties']['title'] ?? '' );
		$rows  = SheetClient::first_row();
		if ( is_wp_error( $rows ) ) {
			\WP_CLI::error( $rows->get_error_message() );
		}
		\WP_CLI::success( sprintf( 'Connected to "%s" as %s. Header row: %s.', $title, Credentials::email(), $rows ? 'present' : 'empty (will be written on first sync)' ) );
	}

	/**
	 * Forget every exported enquiry so the next run re-appends them.
	 *
	 * ## OPTIONS
	 * [--yes]
	 * : Skip the confirmation prompt.
	 */
	public function reset( array $args, array $assoc ): void {
		\WP_CLI::confirm( 'Clear the export ledger? The Sheet itself is not modified; rows will be appended again.', $assoc );
		Ledger::reset();
		\WP_CLI::success( 'Ledger cleared.' );
	}

	/** Print the token-protected endpoint URL. */
	public function token(): void {
		\WP_CLI::log( Endpoint::url() );
	}
}