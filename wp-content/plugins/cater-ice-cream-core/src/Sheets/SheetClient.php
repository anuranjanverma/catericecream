<?php
/**
 * Minimal Google Sheets API v4 client (values.get / values.update / values.append).
 * Only the scopes the export needs; no external SDK.
 *
 * @package CIC
 */

namespace CIC\Sheets;

defined( 'ABSPATH' ) || exit;

final class SheetClient {

	private const BASE = 'https://sheets.googleapis.com/v4/spreadsheets/';

	/** A1 range on the configured tab. */
	public static function range( string $a1 = 'A1' ): string {
		$sheet = (string) Settings::value( 'sheet_name' );
		return "'" . str_replace( "'", "''", $sheet ) . "'!" . $a1;
	}

	/**
	 * Write rows (array of arrays) starting at the first row below the last Lead ID.
	 *
	 * The tab's dropdowns and date formatting extend to row 501, so Google's :append would place
	 * the first row at 502. Computing the next free row from column A keeps new leads at the top
	 * of the table, right under the header.
	 *
	 * @param array<int, array<int, string>> $rows Rows.
	 * @return string|\WP_Error Updated range reported by Google.
	 */
	public static function append( array $rows ) {
		if ( ! $rows ) {
			return '';
		}
		$last = self::last_row();
		if ( is_wp_error( $last ) ) {
			return $last;
		}
		$start = $last + 1;
		$end   = $start + count( $rows ) - 1;

		$width = 0;
		foreach ( $rows as $row ) {
			$width = max( $width, count( (array) $row ) );
		}
		$range = self::range( 'A' . $start . ':' . self::column_letter( $width ) . $end );

		$result = self::request(
			'PUT',
			self::path() . '/values/' . rawurlencode( $range ) . '?valueInputOption=USER_ENTERED',
			array(
				'majorDimension' => 'ROWS',
				'values'         => array_values( $rows ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return (string) ( $result['updatedRange'] ?? $range );
	}

	/**
	 * Number of rows in use in column A (0 for an empty tab).
	 *
	 * @return int|\WP_Error
	 */
	public static function last_row() {
		$result = self::request( 'GET', self::path() . '/values/' . rawurlencode( self::range( 'A1:A20000' ) ) . '?majorDimension=ROWS' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$values = isset( $result['values'] ) && is_array( $result['values'] ) ? $result['values'] : array();
		return count( $values );
	}

	/**
	 * First row values, or [] when the tab is empty.
	 *
	 * @return array<int, string>|\WP_Error
	 */
	public static function first_row() {
		$result = self::request( 'GET', self::path() . '/values/' . rawurlencode( self::range( 'A1:AZ1' ) ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$row = $result['values'][0] ?? array();
		return is_array( $row ) ? array_map( 'strval', $row ) : array();
	}

	/**
	 * Write the header row into A1.
	 *
	 * @param array<int, string> $header Header cells.
	 * @return true|\WP_Error
	 */
	public static function write_header( array $header ) {
		$result = self::request(
			'PUT',
			self::path() . '/values/' . rawurlencode( self::range( 'A1' ) ) . '?valueInputOption=RAW',
			array(
				'majorDimension' => 'ROWS',
				'values'         => array( array_values( $header ) ),
			)
		);
		return is_wp_error( $result ) ? $result : true;
	}

	/** Spreadsheet column letter for a 1-based index (1 → A, 27 → AA). */
	public static function column_letter( int $index ): string {
		$letters = '';
		while ( $index > 0 ) {
			$index--;
			$letters = chr( 65 + ( $index % 26 ) ) . $letters;
			$index   = intdiv( $index, 26 );
		}
		return '' === $letters ? 'A' : $letters;
	}

	/** Spreadsheet metadata (used by the connection test). */
	public static function meta() {
		return self::request( 'GET', self::path() . '?fields=spreadsheetId,properties.title,sheets.properties.title' );
	}

	private static function path(): string {
		return self::BASE . rawurlencode( (string) Settings::value( 'spreadsheet_id' ) );
	}

	/**
	 * @param string              $method HTTP method.
	 * @param string              $url    Full API URL (built from path()).
	 * @param array<string,mixed> $body   JSON body.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function request( string $method, string $url, array $body = array() ) {
		$token = TokenClient::access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);
		if ( $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}

		$response = self::send( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'cic_sheets_http', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';
			if ( 401 === $code ) {
				TokenClient::forget();
			}
			return new \WP_Error(
				'cic_sheets_api',
				sprintf(
					/* translators: 1: HTTP status, 2: Google error message. */
					__( 'Google Sheets API error (HTTP %1$s). %2$s', 'cater-ice-cream-core' ),
					$code,
					'' !== $message ? $message : __( 'Unexpected response from Google.', 'cater-ice-cream-core' )
				)
			);
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Google occasionally answers an otherwise valid request with an HTML error page; retry once
	 * before reporting it, so a transient frontend hiccup does not fail the export.
	 *
	 * @param string              $url  Request URL.
	 * @param array<string,mixed> $args Request args.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function send( string $url, array $args ) {
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		if ( $code >= 400 && null === json_decode( $raw, true ) ) {
			usleep( 300000 );
			$retry = wp_remote_request( $url, $args );
			if ( ! is_wp_error( $retry ) ) {
				return $retry;
			}
		}
		return $response;
	}
}