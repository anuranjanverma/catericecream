<?php
/**
 * Enquiry → Google Sheet export.
 *
 * Source of truth is the Flamingo message (the same record the Enquiries screen reads), so the
 * export never invents or duplicates data. Only submitted enquiries are exported; saved quote
 * progress ("incomplete") and spam are skipped. Each exported message is recorded in the ledger
 * by Flamingo id, so a cron run, a manual run and a retry overlap safely.
 *
 * Columns follow the CaterIceCream.com lead tracker ("Leads" tab) exactly: the team edits status,
 * assignment and payments there, so the connector only fills the fields the website knows and
 * leaves the workflow columns blank.
 *
 * @package CIC
 */

namespace CIC\Sheets;

use CIC\Enquiries\Workflow;

defined( 'ABSPATH' ) || exit;

final class Sync {

	public const HOOK = 'cic_sheets_sync';

	/** Rows per append request. */
	private const CHUNK = 100;

	/** @return array<int, string> */
	public static function header(): array {
		return array(
			'Lead Id',
			'Date Received',
			'Customer Name',
			'Phone',
			'Email',
			'Preferred Contact Method',
			'Event Type',
			'Event Date',
			'City',
			'ZIP Code',
			'Venue / Address',
			'Lead Source',
			'Website Form',
			'Guest Count',
			'Treats / Requirements',
			'Serving Start Time',
			'Serving Duration (Hours)',
			'Lead Status',
			'Assigned To',
			'Last Contact Date',
			'Next Follow-up Date',
			'Next Action',
			'Quote Amount (USD)',
			'Agreed Booking Amount (USD)',
			'Net Payments Received (USD)',
			'Payment Status',
			'Lost / Cancellation Reason',
			'Conversation Link',
			'Website Lead',
			'Notes',
		);
	}

	/**
	 * Export every pending enquiry.
	 *
	 * @param int $limit Optional override of the configured batch size.
	 * @return array{pending: int, synced: int}|\WP_Error
	 */
	public static function run( int $limit = 0 ) {
		if ( ! Settings::configured() ) {
			return new \WP_Error( 'cic_sheets_config', __( 'No Google Sheet is configured.', 'cater-ice-cream-core' ) );
		}
		if ( ! Ledger::exists() ) {
			Ledger::create();
		}

		$limit = $limit > 0 ? $limit : (int) Settings::value( 'sync_limit' );
		$ids   = Ledger::pending_ids( $limit );
		$total = count( $ids );

		if ( 0 === $total ) {
			self::record( 0, '' );
			return array(
				'pending' => 0,
				'synced'  => 0,
			);
		}

		$first_row = SheetClient::first_row();
		if ( is_wp_error( $first_row ) ) {
			self::record( 0, $first_row->get_error_message() );
			return $first_row;
		}
		if ( ! $first_row ) {
			$written = SheetClient::write_header( self::header() );
			if ( is_wp_error( $written ) ) {
				self::record( 0, $written->get_error_message() );
				return $written;
			}
		}

		// The "Lead Id" column is a plain 1..N series: header is row 1, so the serial of a row is
		// its row number minus one.
		$cursor = SheetClient::last_row();
		if ( is_wp_error( $cursor ) ) {
			self::record( 0, $cursor->get_error_message() );
			return $cursor;
		}

		$synced = 0;
		foreach ( array_chunk( $ids, self::CHUNK ) as $chunk ) {
			$rows = array();
			foreach ( array_values( $chunk ) as $i => $id ) {
				$rows[] = self::row( $id, (int) $cursor + $i );
			}
			$range = SheetClient::append( $rows );
			if ( is_wp_error( $range ) ) {
				self::record( $synced, $range->get_error_message() );
				return new \WP_Error( 'cic_sheets_partial', $range->get_error_message(), array( 'synced' => $synced ) );
			}
			Ledger::mark( $chunk, (string) $range, (string) Settings::value( 'spreadsheet_id' ) );
			$synced += count( $chunk );
			$cursor += count( $chunk );
		}

		self::record( $synced, '' );
		return array(
			'pending' => $total,
			'synced'  => $synced,
		);
	}

	/**
	 * One lead-tracker row for a Flamingo message.
	 *
	 * @param int $id     Flamingo message id.
	 * @param int $serial Row number in the sheet (1 for the first lead), used for the "Lead Id" column.
	 * @return array<int, string>
	 */
	public static function row( int $id, int $serial = 0 ): array {
		$ctx  = Workflow::context( $id );
		$meta = get_post_meta( $id, '_meta', true );
		$meta = is_array( $meta ) ? $meta : array();

		$field = static function ( string $name ) use ( $id ): string {
			$value = get_post_meta( $id, '_field_' . $name, true );
			return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
		};
		$pick  = static function ( array $ctx, string $key, string $fallback = '' ): string {
			$value = $ctx[ $key ] ?? '';
			return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
		};

		$type    = (string) get_post_meta( $id, '_cic_type', true );
		$treats  = ! empty( $ctx['treat_labels'] ) ? implode( ', ', (array) $ctx['treat_labels'] ) : $field( 'treats' );
		$date    = ! empty( $ctx['date_unknown'] ) ? '' : $pick( $ctx, 'date', $field( 'event_date' ) );
		$venue   = trim( $pick( $ctx, 'venue', $field( 'venue' ) ) );
		$detail  = trim( $pick( $ctx, 'location_detail', $field( 'location_detail' ) ) );
		$zip     = preg_match( '/^\d{5}(-\d{4})?$/', $detail ) ? $detail : '';
		// A bare ZIP is not an address: only keep it in the ZIP column.
		$address = '' !== $venue
			? $venue . ( '' !== $detail ? ', ' . $detail : '' )
			: ( '' === $zip ? $detail : '' );
		$pref    = ucfirst( strtolower( trim( $field( 'contact_pref' ) ) ) );

		$row = array(
			$serial > 0 ? (string) $serial : '',                      // Lead Id (row series).
			self::received( $id ),                                   // Date Received.
			$field( 'your_name' ),                                   // Customer Name.
			$field( 'phone' ),                                       // Phone.
			$field( 'your_email' ),                                  // Email.
			$pref,                                                   // Preferred Contact Method.
			$pick( $ctx, 'event_label', $field( 'event' ) ),         // Event Type.
			self::date_cell( $date ),                                // Event Date.
			$pick( $ctx, 'location_label', $field( 'location' ) ),   // City.
			$zip,                                                    // ZIP Code.
			$address,                                                // Venue / Address.
			'Website',                                               // Lead Source.
			'contact' === $type ? 'Contact Us' : 'Get a Quote',      // Website Form.
			$pick( $ctx, 'guests', $field( 'guests' ) ),             // Guest Count.
			$treats,                                                 // Treats / Requirements.
			$pick( $ctx, 'serve_time', $field( 'serve_time' ) ),     // Serving Start Time.
			$pick( $ctx, 'serve_duration', $field( 'serve_duration' ) ), // Serving Duration (Hours).
			self::lead_status( $id ),                                // Lead Status.
			'',                                                      // Assigned To (team).
			'',                                                      // Last Contact Date (team).
			'',                                                      // Next Follow-up Date (team).
			'',                                                      // Next Action (team).
			'',                                                      // Quote Amount (team).
			'',                                                      // Agreed Booking Amount (team).
			'',                                                      // Net Payments Received (team).
			'',                                                      // Payment Status (team).
			'',                                                      // Lost / Cancellation Reason (team).
			'',                                                      // Conversation Link (team).
			self::lead_id( $id ),                                    // Website Lead.
			self::notes( $id, $ctx, $meta, $field ),                 // Notes.
		);

		return array_map( array( self::class, 'cell' ), $row );
	}

	/** Spreadsheet formula protection, same rule as the CSV export. */
	public static function cell( $value ): string {
		$value = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
		$value = trim( $value );
		return preg_match( '/^[=+\-@\t]/', $value ) ? "'" . $value : $value;
	}

	private static function lead_id( int $id ): string {
		return sprintf( 'CIC-%06d', $id );
	}

	/**
	 * Date Received. Written as ISO (YYYY-MM-DD) because that is unambiguous for Google; the
	 * cell's own format decides how it is displayed (the tab is formatted MM/DD/YYYY, but the
	 * spreadsheet locale may be DD/MM, which would misread 11/05/2026).
	 */
	private static function received( int $id ): string {
		$ts = self::timestamp( $id );
		return $ts ? wp_date( 'Y-m-d', $ts, new \DateTimeZone( 'America/Los_Angeles' ) ) : '';
	}

	/** Event date (Y-m-d from the form) as ISO; empty when the visitor is still deciding. */
	private static function date_cell( string $value ): string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return $value;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
	}

	/** Workflow status mapped to the tracker's vocabulary. */
	private static function lead_status( int $id ): string {
		$map = array(
			'new'        => 'New',
			'contacted'  => 'Contacted',
			'quote_sent' => 'Quote Sent',
			'booked'     => 'Booked',
			'lost'       => 'Lost',
			'spam'       => 'Spam / Duplicate',
		);
		return $map[ Workflow::status( $id ) ] ?? 'New';
	}

	/**
	 * Notes column: the details the tracker has no dedicated column for.
	 *
	 * @param array<string,mixed> $ctx   Normalised context.
	 * @param array<string,mixed> $meta  Flamingo meta.
	 * @param callable            $field Field reader.
	 */
	private static function notes( int $id, array $ctx, array $meta, callable $field ): string {
		$parts = array();
		foreach ( array(
			'Event details' => $field( 'event_other' ),
			'Flavours'      => $field( 'flavours' ),
			'Party theme'   => $field( 'party_theme' ),
			'Dietary'       => $field( 'dietary' ),
			'Visitor notes' => $field( 'notes' ),
		) as $label => $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$parts[] = $label . ': ' . $value;
			}
		}
		$unsure = trim( (string) $field( 'treats_unsure' ) );
		if ( '' !== $unsure && '0' !== $unsure ) {
			$parts[] = 'Treats: not decided yet';
		}
		$url = (string) ( $meta['url'] ?? '' );
		if ( '' !== $url ) {
			$parts[] = 'Source: ' . $url;
		}
		$parts[] = 'Enquiry #' . $id;
		return implode( ' · ', $parts );
	}

	private static function timestamp( int $id ): int {
		$ts = (int) get_post_meta( $id, '_cic_submitted_at', true );
		if ( ! $ts ) {
			$ts = (int) get_post_meta( $id, '_cic_activity', true );
		}
		if ( ! $ts ) {
			$post = get_post( $id );
			$dt   = $post ? date_create_immutable( $post->post_date, wp_timezone() ) : false;
			$ts   = $dt ? $dt->getTimestamp() : 0;
		}
		return $ts;
	}

	private static function record( int $synced, string $error ): void {
		Settings::set_status(
			array(
				'last_run'   => gmdate( 'Y-m-d H:i:s' ),
				'last_rows'  => $synced,
				'last_error' => mb_substr( $error, 0, 300 ),
				'last_ok'    => '' === $error ? time() : (int) Settings::status()['last_ok'],
			)
		);
	}
}