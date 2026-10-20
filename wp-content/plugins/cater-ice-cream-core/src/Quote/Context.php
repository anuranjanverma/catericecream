<?php
/**
 * Central quote context (BUILD_SPECIFICATION §12). Validates the non-personal selections that may
 * travel in a URL or sessionStorage: event, treat(s), location (ZIP or city key), date, guests.
 * Keys are resolved against published records; invalid values are dropped, never trusted.
 * Names, emails, phones, addresses, dietary notes and free text are never part of the context.
 *
 * @package CIC
 */

namespace CIC\Quote;

use CIC\Content\Catalog;
use CIC\Site\Contact;

defined( 'ABSPATH' ) || exit;

final class Context {

	/** Allowed context fields and their URL parameter names. */
	public const FIELDS = array( 'event', 'treat', 'location', 'date', 'guests' );

	public const TIMEZONE = 'America/Los_Angeles';

	/**
	 * Validate raw values (query args, form fields or stored context).
	 *
	 * @param array<string, mixed> $raw Raw values.
	 * @return array<string, mixed> Only valid fields.
	 */
	public static function validate( array $raw ): array {
		$clean = array();

		$event = isset( $raw['event'] ) ? sanitize_key( (string) $raw['event'] ) : '';
		if ( 'other' === $event ) {
			$clean['event'] = 'other';
		} elseif ( '' !== $event && in_array( $event, wp_list_pluck( Catalog::selectable_events(), 'key' ), true ) ) {
			$clean['event'] = $event;
		}

		if ( isset( $raw['treat'] ) ) {
			$allowed = wp_list_pluck( Catalog::selectable_products(), 'key' );
			$treats  = array_filter( array_map( 'sanitize_key', explode( ',', (string) $raw['treat'] ) ) );
			$treats  = array_values( array_unique( array_intersect( $treats, $allowed ) ) );
			if ( $treats ) {
				$clean['treat'] = implode( ',', $treats );
			}
		}

		$location = isset( $raw['location'] ) ? self::location( (string) $raw['location'] ) : '';
		if ( '' !== $location ) {
			$clean['location'] = $location;
		}

		$date = isset( $raw['date'] ) ? self::date( (string) $raw['date'] ) : '';
		if ( '' !== $date ) {
			$clean['date'] = $date;
		}

		if ( isset( $raw['guests'] ) && preg_match( '/^\d{1,5}$/', (string) $raw['guests'] ) ) {
			$guests = (int) $raw['guests'];
			if ( $guests >= 1 && $guests <= 10000 ) {
				$clean['guests'] = $guests;
			}
		}
		return $clean;
	}

	/**
	 * A 5-digit ZIP, or a city key (lowercase letters, digits, hyphens; e.g. "pasadena-ca").
	 * A typed city name is normalised to such a key; anything else is rejected.
	 */
	public static function location( string $raw ): string {
		$raw = trim( $raw );
		if ( preg_match( '/^\d{5}$/', $raw ) ) {
			return $raw;
		}
		if ( strlen( $raw ) > 60 || ! preg_match( "/^[A-Za-z][A-Za-z .'\-]{1,58}[A-Za-z.]$/", $raw ) ) {
			return '';
		}
		return sanitize_title( $raw );
	}

	/** Y-m-d calendar date, today or later in America/Los_Angeles; no UTC conversion. */
	public static function date( string $raw ): string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		$today = ( new \DateTimeImmutable( 'now', new \DateTimeZone( self::TIMEZONE ) ) )->format( 'Y-m-d' );
		$limit = ( new \DateTimeImmutable( 'now', new \DateTimeZone( self::TIMEZONE ) ) )->modify( '+3 years' )->format( 'Y-m-d' );
		return ( $raw >= $today && $raw <= $limit ) ? $raw : '';
	}

	/**
	 * Valid context from the current request's query string.
	 *
	 * @return array<string, mixed>
	 */
	public static function from_request(): array {
		$raw = array();
		foreach ( self::FIELDS as $field ) {
			if ( isset( $_GET[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, validated context.
				$raw[ $field ] = wp_unslash( $_GET[ $field ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended -- validated below.
			}
		}
		if ( isset( $_POST['event'] ) || isset( $_POST['location'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- non-personal starter values, validated.
			foreach ( array( 'event' => 'event', 'location' => 'location', 'event_date' => 'date', 'guests' => 'guests' ) as $post_key => $field ) {
				if ( isset( $_POST[ $post_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$raw[ $field ] = wp_unslash( $_POST[ $post_key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
				}
			}
		}
		return self::validate( $raw );
	}

	/**
	 * Quote route URL carrying a validated context.
	 *
	 * @param array<string, mixed> $context Context (validated here again).
	 */
	public static function url( array $context = array() ): string {
		return add_query_arg( array_map( 'rawurlencode', array_map( 'strval', self::validate( $context ) ) ), Contact::get()['quote_url'] );
	}

	/**
	 * Data for the browser helper (assets/js/context.js).
	 *
	 * @return array<string, mixed>
	 */
	public static function client_config(): array {
		return array(
			'quoteUrl' => Contact::get()['quote_url'],
			'events'   => array_merge( wp_list_pluck( Catalog::selectable_events(), 'key' ), array( 'other' ) ),
			'treats'   => wp_list_pluck( Catalog::selectable_products(), 'key' ),
			'today'    => ( new \DateTimeImmutable( 'now', new \DateTimeZone( self::TIMEZONE ) ) )->format( 'Y-m-d' ),
			'maxDate'  => ( new \DateTimeImmutable( 'now', new \DateTimeZone( self::TIMEZONE ) ) )->modify( '+3 years' )->format( 'Y-m-d' ),
		);
	}
}
