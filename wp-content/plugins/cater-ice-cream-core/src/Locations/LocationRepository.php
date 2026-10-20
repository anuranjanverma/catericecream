<?php
/**
 * Location repository adapter boundary (BUILD_SPECIFICATION §9–10).
 *
 * Every city, directory, map and coverage read goes through this contract, so the renderer, the
 * search, the REST API and the quote flow never carry their own SQL or coverage rules.
 * DatabaseRepository over the spec §9 tables is the permanent implementation (owner decision,
 * 2026-10-20); the cic_location_repository filter remains only as an extension point.
 *
 * City arrays: id, name, slug, state_code, county_name, county_slug, key ("pasadena-ca"), url
 * (canonical, '' when not public), publish_status, index_status, latitude, longitude, fixture.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

interface LocationRepository {

	/**
	 * Public city for a state slug + city slug. A recorded alias/old slug returns the city with
	 * 'redirect' => true (one hop to the canonical URL). Unknown or unpublished → null, unless
	 * $include_unpublished (authenticated preview).
	 *
	 * @return array<string, mixed>|null
	 */
	public function resolve_city( string $state_slug, string $city_slug, bool $include_unpublished = false ): ?array;

	/**
	 * Limited autocomplete candidates (published cities by name prefix, known ZIPs by prefix), each
	 * with label, key, url, status, action and message. Never internal notes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function search_locations( string $query, int $limit = 8 ): array;

	/**
	 * Counties with published cities: name, slug, count.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function list_counties( string $state_code ): array;

	/**
	 * Published cities, paginated.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, pages: int}
	 */
	public function list_cities( string $state_code, string $county_slug = '', int $page = 1, int $per_page = 24 ): array;

	/**
	 * Editorial link for a city: editorial_id (private revisioned record, 0 if none) and reviewed.
	 *
	 * @return array{editorial_id: int, reviewed: bool}
	 */
	public function get_city_content( int $city_id ): array;

	/**
	 * Coverage for a ZIP or city key/name, optionally with the venue city for a shared ZIP.
	 *
	 * @return array<string, mixed> status covered|outside_area|manual_review|unknown|invalid, action,
	 *                              label, key, url, message, reason, fixture
	 */
	public function evaluate_coverage( string $location, string $city = '' ): array;

	/**
	 * Approved ZIP centroids grouped by county (approximate points, not boundaries).
	 *
	 * @return array<int, array{label: string, latitude: float, longitude: float, count: int}>
	 */
	public function get_map_points( string $state_code ): array;

	/** True once usable location data is connected. */
	public function is_connected(): bool;
}
