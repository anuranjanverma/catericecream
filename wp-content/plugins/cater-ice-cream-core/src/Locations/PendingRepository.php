<?php
/**
 * Placeholder repository used until the owner's city code and approved ZIP data are connected.
 * It never claims coverage or exclusion: valid input is "unknown" (confirmed with the quote),
 * malformed input is "invalid".
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class PendingRepository implements LocationRepository {

	public function coverage( string $location ): array {
		if ( '' === $location ) {
			return array(
				'status'  => 'invalid',
				'label'   => '',
				'url'     => '',
				'message' => __( 'Please enter a 5-digit ZIP code or a city name.', 'cater-ice-cream-core' ),
			);
		}
		return array(
			'status'  => 'unknown',
			'label'   => $location,
			'url'     => '',
			'message' => __( "We can't confirm this area online yet. Include your event ZIP code in your quote request and we'll confirm service for your location.", 'cater-ice-cream-core' ),
		);
	}

	public function is_connected(): bool {
		return false;
	}
}
