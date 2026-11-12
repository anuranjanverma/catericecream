<?php
/**
 * The single coverage rule set (BUILD_SPECIFICATION §10), shared by the frontend checks, the REST
 * API and (Phase 06) quote validation. Pure functions over rows the repository loaded, so a
 * legacy/owner repository can reuse the same rules.
 *
 * Precedence:
 * 1. Malformed input → invalid. Geography we do not hold → unknown (never outside_area).
 * 2. Explicit reviewed exclusions win: a disabled ZIP, or a disabled city-ZIP mapping, can never be
 *    re-enabled by another association.
 * 3. A ZIP shared by several communities whose reviewed states disagree (e.g. a Compton ZIP that
 *    also names a served community) → manual_review: ask for the venue city, never confirm.
 * 4. Approved city-ZIP mapping or an enabled, unambiguous ZIP → covered.
 * 5. A city with only partially served ZIPs → manual_review asking for the event ZIP.
 * Covered means service geography only; it never reserves or confirms a date.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class Coverage {

	/** What the quote flow does with each status (DATA_CONTRACTS coverage_acceptance). */
	public const ACTIONS = array(
		'covered'       => 'allow',
		'outside_area'  => 'block',
		'manual_review' => 'clarify',
		'unknown'       => 'clarify',
		'invalid'       => 'correct',
	);

	/**
	 * A 5-digit ZIP from "12345" or a structurally valid ZIP+4 "12345-6789"; '' otherwise.
	 * Leading zeros are kept (string, never cast to int).
	 */
	public static function normalize_zip( string $raw ): string {
		$raw = trim( $raw );
		return preg_match( '/^(\d{5})(?:-\d{4})?$/', $raw, $m ) ? $m[1] : '';
	}

	/** Looks like a ZIP attempt (digits/hyphen only), valid or not. */
	public static function looks_like_zip( string $raw ): bool {
		return (bool) preg_match( '/^[\d\s-]+$/', trim( $raw ) );
	}

	/**
	 * Effective status of one city-ZIP mapping. A disabled ZIP or a disabled mapping is final.
	 */
	public static function effective( string $override, string $zip_status ): string {
		if ( 'disabled' === $zip_status || 'disabled' === $override ) {
			return 'disabled';
		}
		if ( 'inherit' !== $override && in_array( $override, Tables::OVERRIDES, true ) ) {
			return $override;
		}
		return in_array( $zip_status, Tables::ZIP_STATUSES, true ) ? $zip_status : 'unreviewed';
	}

	/**
	 * Coverage for a ZIP.
	 *
	 * @param array<string, mixed>|null            $zip      ZIP row (service_status) or null if unknown.
	 * @param array<int, array<string, mixed>>     $mappings Rows: city_id, service_override.
	 * @param array<string, mixed>|null            $city     Venue city row when the visitor named one.
	 * @return array{status: string, reason: string}
	 */
	public static function for_zip( ?array $zip, array $mappings, ?array $city = null ): array {
		if ( null === $zip ) {
			return self::r( 'unknown', 'zip_not_found' );
		}
		$zip_status = (string) $zip['service_status'];
		if ( 'disabled' === $zip_status ) {
			return self::r( 'outside_area', 'zip_excluded' );
		}

		if ( null !== $city ) {
			foreach ( $mappings as $m ) {
				if ( (int) $m['city_id'] === (int) $city['id'] ) {
					return self::from_effective( self::effective( (string) $m['service_override'], $zip_status ), 'city_zip' );
				}
			}
			if ( $mappings ) {
				return self::r( 'manual_review', 'city_zip_mismatch' );
			}
			return self::from_effective( $zip_status, 'zip' );
		}

		if ( ! $mappings ) {
			return self::from_effective( $zip_status, 'zip' );
		}
		$effective = array_unique( array_map( static fn( $m ) => self::effective( (string) $m['service_override'], $zip_status ), $mappings ) );
		if ( array( 'disabled' ) === array_values( $effective ) ) {
			return self::r( 'outside_area', 'city_excluded' );
		}
		if ( count( $effective ) > 1 ) {
			return self::r( 'manual_review', 'shared_zip' );
		}
		return self::from_effective( (string) reset( $effective ), count( $mappings ) > 1 ? 'shared_zip_agreed' : 'city_zip' );
	}

	/**
	 * Coverage for a whole city from its mappings (rows: service_override, service_status).
	 *
	 * @param array<int, array<string, mixed>> $mappings City's ZIP mappings with ZIP status.
	 * @return array{status: string, reason: string}
	 */
	public static function for_city( array $mappings ): array {
		if ( ! $mappings ) {
			return self::r( 'unknown', 'city_unmapped' );
		}
		$effective = array_unique( array_map( static fn( $m ) => self::effective( (string) $m['service_override'], (string) ( $m['service_status'] ?? 'unreviewed' ) ), $mappings ) );
		$effective = array_values( $effective );
		if ( array( 'disabled' ) === $effective ) {
			return self::r( 'outside_area', 'city_excluded' );
		}
		if ( array( 'enabled' ) === $effective ) {
			return self::r( 'covered', 'city' );
		}
		if ( in_array( 'enabled', $effective, true ) ) {
			return self::r( 'manual_review', 'partial_city' );
		}
		if ( in_array( 'manual_review', $effective, true ) || in_array( 'disabled', $effective, true ) ) {
			return self::r( 'manual_review', 'city_review' );
		}
		return self::r( 'unknown', 'city_unreviewed' );
	}

	/** @return array{status: string, reason: string} */
	private static function from_effective( string $effective, string $reason ): array {
		switch ( $effective ) {
			case 'enabled':
				return self::r( 'covered', $reason );
			case 'disabled':
				return self::r( 'outside_area', $reason . '_excluded' );
			case 'manual_review':
				return self::r( 'manual_review', $reason . '_review' );
			default:
				return self::r( 'unknown', $reason . '_unreviewed' );
		}
	}

	/** @return array{status: string, reason: string} */
	private static function r( string $status, string $reason ): array {
		return array(
			'status' => $status,
			'reason' => $reason,
		);
	}

	/** Visitor-facing explanation. Never mentions dates as available or reserved. */
	public static function message( string $status, string $reason, string $label = '' ): string {
		switch ( $status ) {
			case 'covered':
				/* translators: %s: place name. */
				return sprintf( __( 'Good news: %s is within our service area. Your date and serving details are confirmed with your quote.', 'cater-ice-cream-core' ), '' !== $label ? $label : __( 'this location', 'cater-ice-cream-core' ) );
			case 'outside_area':
				return __( 'Sorry, we do not currently serve this area. Please choose another event location.', 'cater-ice-cream-core' );
			case 'manual_review':
				if ( 'shared_zip' === $reason || 'city_zip_mismatch' === $reason ) {
					return __( 'This ZIP code covers more than one community. Please tell us the venue’s city as well, and our team will confirm service for your location.', 'cater-ice-cream-core' );
				}
				if ( 'partial_city' === $reason ) {
					/* translators: %s: city name. */
					return sprintf( __( 'We serve parts of %s. Please enter your event’s 5-digit ZIP code so we can check the exact location.', 'cater-ice-cream-core' ), $label );
				}
				return __( 'We need to check this location. Please include your event ZIP code and the venue’s city, and our team will confirm service with your quote.', 'cater-ice-cream-core' );
			case 'invalid':
				return __( 'Please enter a 5-digit ZIP code or a city name.', 'cater-ice-cream-core' );
			default:
				return __( 'We can’t confirm this location online yet. Include your event ZIP code and the venue’s city in your quote request and our team will check it.', 'cater-ice-cream-core' );
		}
	}

	/**
	 * Full public result.
	 *
	 * @param array{status: string, reason: string} $verdict Rule result.
	 * @return array<string, mixed>
	 */
	public static function result( array $verdict, string $label = '', string $key = '', string $url = '', bool $fixture = false ): array {
		return array(
			'status'  => $verdict['status'],
			'reason'  => $verdict['reason'],
			'action'  => self::ACTIONS[ $verdict['status'] ] ?? 'clarify',
			'label'   => $label,
			'key'     => $key,
			'url'     => $url,
			'message' => self::message( $verdict['status'], $verdict['reason'], $label ),
			'fixture' => $fixture,
		);
	}

	/** Phase 06 hook point: may a quote proceed (only "covered"; clarify statuses need details). */
	public static function blocks_quote( string $status ): bool {
		return 'outside_area' === $status || 'invalid' === $status;
	}
}
