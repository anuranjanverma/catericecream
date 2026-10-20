<?php
/**
 * Location repository adapter boundary (BUILD_SPECIFICATION §9–10).
 *
 * The owner's city template and rewrite-rule code is pending. Every coverage or city lookup goes
 * through this interface so the owner's implementation can be plugged in later (filter
 * cic_location_repository) without touching templates or the quote flow. No competing city schema
 * is implemented here.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

interface LocationRepository {

	/**
	 * Coverage of a normalised location (5-digit ZIP or city key).
	 *
	 * @return array{status: string, label: string, url: string, message: string}
	 *         status: covered | outside_area | manual_review | unknown | invalid
	 */
	public function coverage( string $location ): array;

	/** True once real, approved location data is connected. */
	public function is_connected(): bool;
}
