<?php
/**
 * Placeholder repository used when no location data is connected (tables missing and no owner
 * adapter). It never claims coverage or exclusion: valid input is "unknown" (confirmed with the
 * quote), malformed input is "invalid". No cities, directories or map points.
 *
 * @package CIC
 */

namespace CIC\Locations;

use CIC\Quote\Context;

defined( 'ABSPATH' ) || exit;

final class PendingRepository implements LocationRepository {

	public function resolve_city( string $state_slug, string $city_slug, bool $include_unpublished = false ): ?array {
		return null;
	}

	public function search_locations( string $query, int $limit = 8 ): array {
		return array();
	}

	public function list_counties( string $state_code ): array {
		return array();
	}

	public function list_cities( string $state_code, string $county_slug = '', int $page = 1, int $per_page = 24 ): array {
		return array( 'items' => array(), 'total' => 0, 'page' => 1, 'pages' => 0 );
	}

	public function get_city_content( int $city_id ): array {
		return array( 'editorial_id' => 0, 'reviewed' => false );
	}

	public function evaluate_coverage( string $location, string $city = '' ): array {
		$location = Context::location( $location );
		if ( '' === $location ) {
			return Coverage::result( array( 'status' => 'invalid', 'reason' => 'malformed' ) );
		}
		return Coverage::result( array( 'status' => 'unknown', 'reason' => 'not_connected' ), '', $location );
	}

	public function get_map_points( string $state_code ): array {
		return array();
	}

	public function is_connected(): bool {
		return false;
	}
}
