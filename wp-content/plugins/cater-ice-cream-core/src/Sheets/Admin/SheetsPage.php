<?php
/**
 * Enquiries → Google Sheet settings screen (administrators only). The service-account key itself
 * is never displayed or stored here: only the path to the file outside the web root.
 *
 * @package CIC
 */

namespace CIC\Sheets\Admin;

use CIC\Sheets\Credentials;
use CIC\Sheets\Endpoint;
use CIC\Sheets\Ledger;
use CIC\Sheets\Settings;
use CIC\Sheets\SheetClient;
use CIC\Sheets\Sync;

defined( 'ABSPATH' ) || exit;

final class SheetsPage {

	public function register(): void {
		add_action( 'admin_post_cic_sheets_sync_now', array( $this, 'sync_now' ) );
	}

	public function sync_now(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'cic_sheets_sync_now' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run the export.', 'cater-ice-cream-core' ), '', array( 'response' => 403 ) );
		}
		$result = Sync::run();
		$msg    = is_wp_error( $result )
			? $result->get_error_message()
			: sprintf(
				/* translators: 1: pending count, 2: exported count. */
				__( '%1$d pending, %2$d exported.', 'cater-ice-cream-core' ),
				(int) $result['pending'],
				(int) $result['synced']
			);
		wp_safe_redirect( add_query_arg( array( 'page' => Settings::PAGE, is_wp_error( $result ) ? 'cic_err' : 'cic_msg' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change these settings.', 'cater-ice-cream-core' ), '', array( 'response' => 403 ) );
		}
		$s     = Settings::get();
		$cred  = Credentials::load();
		$st    = Settings::status();
		$name  = static fn( string $k ): string => Settings::OPTION . '[' . $k . ']';
		$pend  = count( Ledger::pending_ids( 500 ) );

		echo '<div class="wrap"><h1>' . esc_html__( 'Google Sheet export', 'cater-ice-cream-core' ) . '</h1>';

		if ( isset( $_GET['cic_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( (string) $_GET['cic_msg'] ) ) ) . '</p></div>';
		}
		if ( isset( $_GET['cic_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( (string) $_GET['cic_err'] ) ) ) . '</p></div>';
		}

		echo '<p class="description">' . esc_html__( 'Every submitted quote request and contact message is appended to the Sheet with one row per enquiry. Saved quote progress and spam are never exported. Each enquiry is exported once; re-runs only add new rows.', 'cater-ice-cream-core' ) . '</p>';

		echo '<form method="post" action="options.php">';
		settings_fields( Settings::PAGE );
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row">' . esc_html__( 'Export', 'cater-ice-cream-core' ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name( 'enabled' ) ) . '" value="1"' . checked( ! empty( $s['enabled'] ), true, false ) . '> ' . esc_html__( 'Export enquiries to Google Sheets', 'cater-ice-cream-core' ) . '</label></td></tr>';

		printf(
			'<tr><th scope="row"><label for="cic-sheets-id">%1$s</label></th><td><input class="regular-text" id="cic-sheets-id" name="%2$s" value="%3$s" placeholder="1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789abcd"><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Spreadsheet ID', 'cater-ice-cream-core' ),
			esc_attr( $name( 'spreadsheet_id' ) ),
			esc_attr( (string) $s['spreadsheet_id'] ),
			esc_html__( 'The long value between /d/ and /edit in the Sheet URL. Share the Sheet with the service-account email below as an Editor.', 'cater-ice-cream-core' )
		);

		printf(
			'<tr><th scope="row"><label for="cic-sheets-tab">%1$s</label></th><td><input class="regular-text" id="cic-sheets-tab" name="%2$s" value="%3$s"><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Tab name', 'cater-ice-cream-core' ),
			esc_attr( $name( 'sheet_name' ) ),
			esc_attr( (string) $s['sheet_name'] ),
			esc_html__( 'The tab (worksheet) name at the bottom of the Sheet. The header row is written automatically when the tab is empty.', 'cater-ice-cream-core' )
		);

		printf(
			'<tr><th scope="row"><label for="cic-sheets-cred">%1$s</label></th><td><input class="regular-text" id="cic-sheets-cred" name="%2$s" value="%3$s"><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Credentials file', 'cater-ice-cream-core' ),
			esc_attr( $name( 'credentials_path' ) ),
			esc_attr( (string) $s['credentials_path'] ),
			esc_html__( 'Absolute path to the service-account JSON key, outside the web root. Leave empty to use the default secure path.', 'cater-ice-cream-core' )
		);

		printf(
			'<tr><th scope="row"><label for="cic-sheets-limit">%1$s</label></th><td><input type="number" min="1" max="500" class="small-text" id="cic-sheets-limit" name="%2$s" value="%3$d"><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Rows per run', 'cater-ice-cream-core' ),
			esc_attr( $name( 'sync_limit' ) ),
			(int) $s['sync_limit'],
			esc_html__( 'Maximum number of enquiries exported in a single run. The next run continues where this one stopped.', 'cater-ice-cream-core' )
		);

		echo '</table>';
		submit_button();
		echo '</form>';

		echo '<h2>' . esc_html__( 'Status', 'cater-ice-cream-core' ) . '</h2><table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Service account', 'cater-ice-cream-core' ) . '</th><td>' . ( is_wp_error( $cred ) ? '<span class="cic-pill cic-pill--bad">' . esc_html( $cred->get_error_message() ) . '</span>' : '<code>' . esc_html( $cred['client_email'] ) . '</code>' ) . '</td></tr>';
		printf( '<tr><th scope="row">%1$s</th><td>%2$d</td></tr>', esc_html__( 'Enquiries exported', 'cater-ice-cream-core' ), (int) Ledger::count() );
		printf( '<tr><th scope="row">%1$s</th><td>%2$d</td></tr>', esc_html__( 'Waiting to export', 'cater-ice-cream-core' ), (int) $pend );
		printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html__( 'Last run', 'cater-ice-cream-core' ), esc_html( $st['last_run'] ? $st['last_run'] . ' UTC · ' . $st['last_rows'] . ' rows' : __( 'never', 'cater-ice-cream-core' ) ) );
		if ( $st['last_error'] ) {
			printf( '<tr><th scope="row">%1$s</th><td><span class="cic-pill cic-pill--bad">%2$s</span></td></tr>', esc_html__( 'Last error', 'cater-ice-cream-core' ), esc_html( $st['last_error'] ) );
		}
		echo '</table>';

		echo '<h2>' . esc_html__( 'Automatic runs', 'cater-ice-cream-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'A WP-Cron event exports every 5 minutes while the site receives traffic, and each new enquiry queues an immediate run. For a guaranteed schedule add a SiteGround cron job that calls the endpoint below every 5 minutes.', 'cater-ice-cream-core' ) . '</p>';
		echo '<p><code>' . esc_html( Endpoint::url() ) . '</code></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'cic_sheets_sync_now' );
		echo '<input type="hidden" name="action" value="cic_sheets_sync_now">';
		submit_button( __( 'Export now', 'cater-ice-cream-core' ), 'secondary', 'submit', false );
		echo '</form>';

		if ( ! is_wp_error( $cred ) && Settings::configured() ) {
			$meta = SheetClient::meta();
			echo '<p class="description">';
			if ( is_wp_error( $meta ) ) {
				echo '<span class="cic-pill cic-pill--bad">' . esc_html( $meta->get_error_message() ) . '</span>';
			} else {
				printf(
					/* translators: %s: spreadsheet title. */
					esc_html__( 'Connected to "%s".', 'cater-ice-cream-core' ),
					esc_html( (string) ( $meta['properties']['title'] ?? '' ) )
				);
			}
			echo '</p>';
		}

		echo '</div>';
	}
}