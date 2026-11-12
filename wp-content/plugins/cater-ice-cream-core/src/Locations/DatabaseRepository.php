<?php
/**
 * Location repository over the spec §9 tables ({prefix}cic_cities, cic_zips, cic_city_zips,
 * cic_city_content, cic_city_slugs), the permanent implementation. All SQL is prepared; prefix
 * lookups use indexed columns with esc_like. Fixture rows count only where Tables::fixtures_allowed().
 *
 * @package CIC
 */

namespace CIC\Locations;

use CIC\Quote\Context;

defined( 'ABSPATH' ) || exit;

final class DatabaseRepository implements LocationRepository {

	/* ------------------------------------------------------------------ *
	 * Row mapping
	 * ------------------------------------------------------------------ */

	/**
	 * Public city array from a row.
	 *
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	public static function city( array $row ): array {
		$state_slug = Tables::state_slug( (string) $row['state_code'] );
		$public     = 'published' === $row['publish_status'] && '' !== $state_slug;
		return array(
			'id'             => (int) $row['id'],
			'name'           => (string) $row['city_name'],
			'slug'           => (string) $row['city_slug'],
			'state_code'     => (string) $row['state_code'],
			'state_slug'     => $state_slug,
			'county_name'    => (string) $row['county_name'],
			'county_slug'    => (string) $row['county_slug'],
			'key'            => self::key( (string) $row['city_slug'], (string) $row['state_code'] ),
			'label'          => $row['city_name'] . ', ' . $row['state_code'],
			'url'            => $public ? self::url( $state_slug, (string) $row['city_slug'] ) : '',
			'publish_status' => (string) $row['publish_status'],
			'index_status'   => (string) $row['index_status'],
			'latitude'       => null === $row['latitude'] ? null : (float) $row['latitude'],
			'longitude'      => null === $row['longitude'] ? null : (float) $row['longitude'],
			'display_order'  => (int) $row['display_order'],
			'fixture'        => 'fixture' === $row['dataset'],
		);
	}

	public static function key( string $slug, string $state_code ): string {
		return $slug . '-' . strtolower( $state_code );
	}

	/**
	 * Public city address: /service-areas/{state}/{city slug}-ice-cream-catering/ (SEO review
	 * 2026-11-12). The stored city slug never carries the suffix; Routes strips it, and the bare
	 * address 301s here.
	 */
	public static function url( string $state_slug, string $city_slug ): string {
		return home_url( user_trailingslashit( 'service-areas/' . $state_slug . '/' . $city_slug . self::URL_SUFFIX ) );
	}

	/** Keyword suffix of every public city address (not part of the stored slug or the city key). */
	public const URL_SUFFIX = '-ice-cream-catering';

	/* ------------------------------------------------------------------ *
	 * Cities
	 * ------------------------------------------------------------------ */

	/** @return array<string, mixed>|null */
	public function city_by_id( int $id ): ?array {
		global $wpdb;
		$t   = Tables::name( 'cities' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} c WHERE c.id = %d AND " . Tables::dataset_sql( 'c' ), $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and static fragment.
		return $row ? self::city( $row ) : null;
	}

	/** @return array<string, mixed>|null */
	private function city_by_slug( string $state_code, string $slug ): ?array {
		global $wpdb;
		$t   = Tables::name( 'cities' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} c WHERE c.country_code = 'US' AND c.state_code = %s AND c.city_slug = %s AND " . Tables::dataset_sql( 'c' ), $state_code, $slug ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::city( $row ) : null;
	}

	public function resolve_city( string $state_slug, string $city_slug, bool $include_unpublished = false ): ?array {
		global $wpdb;
		$state = Tables::state_code( $state_slug );
		if ( '' === $state || ! preg_match( '/^[a-z0-9-]{1,140}$/', $city_slug ) ) {
			return null;
		}
		$city = $this->city_by_slug( $state, $city_slug );
		if ( $city ) {
			return ( 'published' === $city['publish_status'] || $include_unpublished ) ? $city + array( 'redirect' => false ) : null;
		}
		// Recorded alias or previous slug → one hop to the current canonical URL.
		$s       = Tables::name( 'city_slugs' );
		$city_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT city_id FROM {$s} WHERE state_code = %s AND slug = %s", $state, $city_slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$city    = $city_id ? $this->city_by_id( $city_id ) : null;
		if ( $city && 'published' === $city['publish_status'] ) {
			return $city + array( 'redirect' => true );
		}
		return null;
	}

	/**
	 * City for a context key ("pasadena-ca"), slug or typed name; includes unpublished cities
	 * (coverage still applies to them, e.g. an excluded city) but never exposes their URL.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find_city( string $key ): ?array {
		global $wpdb;
		$key = sanitize_title( $key );
		if ( '' === $key ) {
			return null;
		}
		foreach ( Tables::STATES as $code ) {
			$suffix = '-' . strtolower( $code );
			if ( str_ends_with( $key, $suffix ) ) {
				$city = $this->city_by_slug( $code, substr( $key, 0, -strlen( $suffix ) ) );
				if ( $city ) {
					return $city;
				}
			}
			$city = $this->city_by_slug( $code, $key );
			if ( $city ) {
				return $city;
			}
		}
		// Typed name ("Pasadena") or a recorded alias.
		$t    = Tables::name( 'cities' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} c WHERE c.name_search = %s AND " . Tables::dataset_sql( 'c' ) . ' LIMIT 2', Tables::search_key( str_replace( '-', ' ', $key ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 === count( $rows ) ) {
			return self::city( $rows[0] );
		}
		$s  = Tables::name( 'city_slugs' );
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT city_id FROM {$s} WHERE slug = %s LIMIT 1", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $id ? $this->city_by_id( $id ) : null;
	}

	public function list_counties( string $state_code ): array {
		return Tables::cached(
			'counties:' . $state_code,
			static function () use ( $state_code ) {
				global $wpdb;
				$t    = Tables::name( 'cities' );
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.county_name, c.county_slug, COUNT(*) AS n, MIN(c.display_order) AS o FROM {$t} c WHERE c.state_code = %s AND c.publish_status = 'published' AND " . Tables::dataset_sql( 'c' ) . ' GROUP BY c.county_slug, c.county_name ORDER BY o, c.county_name', $state_code ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return array_map(
					static fn( $r ) => array(
						'name'  => (string) $r['county_name'],
						'slug'  => (string) $r['county_slug'],
						'count' => (int) $r['n'],
					),
					$rows
				);
			}
		);
	}

	public function list_cities( string $state_code, string $county_slug = '', int $page = 1, int $per_page = 24 ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		return Tables::cached(
			"cities:{$state_code}:{$county_slug}:{$page}:{$per_page}",
			static function () use ( $state_code, $county_slug, $page, $per_page ) {
				global $wpdb;
				$t      = Tables::name( 'cities' );
				$where  = "c.state_code = %s AND c.publish_status = 'published' AND " . Tables::dataset_sql( 'c' );
				$params = array( $state_code );
				if ( '' !== $county_slug ) {
					$where   .= ' AND c.county_slug = %s';
					$params[] = $county_slug;
				}
				$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} c WHERE {$where}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} c WHERE {$where} ORDER BY c.display_order, c.city_name LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return array(
					'items' => array_map( array( self::class, 'city' ), $rows ),
					'total' => $total,
					'page'  => $page,
					'pages' => (int) ceil( $total / $per_page ),
				);
			}
		);
	}

	/**
	 * Cities ranked by their number of DISTINCT effectively served ZIP codes (footer "Cities We
	 * Serve"). Eligible: published, index status "index", production dataset (city, link and ZIP),
	 * a supported state, and at least one served ZIP. A city-ZIP link counts only when the shared
	 * resolver's Coverage::effective() is "enabled", so globally disabled ZIPs (e.g. the Compton
	 * exclusions) and disabled links never count; a shared ZIP counts once for each city whose own
	 * link is served. One query for all rows; sorted by count (desc), then name. Cached under the
	 * location cache version, which every location write, import and `wp cic locations
	 * flush-cache` bumps. Counts are for ordering only and are never displayed.
	 *
	 * @return array<int, array<string, mixed>> Public city arrays plus 'served_zips'.
	 */
	public function served_city_ranking( int $limit = 20 ): array {
		$all = Tables::cached(
			'served-ranking',
			static function () {
				global $wpdb;
				$t    = Tables::all();
				$rows = $wpdb->get_results( "SELECT c.*, cz.zip AS link_zip, cz.service_override, z.service_status FROM {$t['cities']} c JOIN {$t['city_zips']} cz ON cz.city_id = c.id JOIN {$t['zips']} z ON z.zip = cz.zip WHERE c.publish_status = 'published' AND c.index_status = 'index' AND c.dataset = 'production' AND cz.dataset = 'production' AND z.dataset = 'production'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- internal table names, no input.
				$cities = array();
				$served = array();
				foreach ( $rows as $row ) {
					if ( '' === Tables::state_slug( (string) $row['state_code'] ) || 'enabled' !== Coverage::effective( (string) $row['service_override'], (string) $row['service_status'] ) ) {
						continue;
					}
					$id                                = (int) $row['id'];
					$cities[ $id ]                     = $cities[ $id ] ?? $row;
					$served[ $id ][ $row['link_zip'] ] = true;
				}
				$out = array();
				foreach ( $cities as $id => $row ) {
					$out[] = array_merge( self::city( $row ), array( 'served_zips' => count( $served[ $id ] ) ) );
				}
				usort( $out, static fn( $a, $b ) => ( $b['served_zips'] <=> $a['served_zips'] ) ?: strcasecmp( $a['name'], $b['name'] ) );
				return $out;
			}
		);
		return array_slice( $all, 0, max( 0, $limit ) );
	}

	/**
	 * Other published cities in the same county, nearest first when coordinates exist.
	 *
	 * @param array<string, mixed> $city City.
	 * @return array<int, array<string, mixed>>
	 */
	public function nearby_cities( array $city, int $limit = 3 ): array {
		return Tables::cached(
			'nearby:' . $city['id'] . ':' . $limit,
			static function () use ( $city, $limit ) {
				global $wpdb;
				$t     = Tables::name( 'cities' );
				$order = 'c.display_order, c.city_name';
				if ( null !== $city['latitude'] && null !== $city['longitude'] ) {
					$order = $wpdb->prepare( '(c.latitude IS NULL), POW(c.latitude - %f, 2) + POW(c.longitude - %f, 2), c.city_name', $city['latitude'], $city['longitude'] );
				}
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} c WHERE c.state_code = %s AND c.county_slug = %s AND c.id <> %d AND c.publish_status = 'published' AND " . Tables::dataset_sql( 'c' ) . " ORDER BY {$order} LIMIT %d", $city['state_code'], $city['county_slug'], $city['id'], $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order is prepared above.
				return array_map( array( self::class, 'city' ), $rows );
			}
		);
	}

	public function get_city_content( int $city_id ): array {
		global $wpdb;
		$t  = Tables::name( 'city_content' );
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT editorial_post_id FROM {$t} WHERE city_id = %d", $city_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id = ( $id && Editorial::POST_TYPE === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) ? $id : 0;
		return array(
			'editorial_id' => $id,
			'reviewed'     => $id && '1' === (string) get_post_meta( $id, Editorial::REVIEWED, true ) && 'publish' === get_post_status( $id ),
		);
	}

	/**
	 * Served = at least one production ZIP linked to the city is effectively "enabled" (the same
	 * rule as the Cities We Serve ranking, so exclusions and fixture data never count).
	 *
	 * @param array<string, mixed> $city City.
	 */
	public function is_served( array $city ): bool {
		foreach ( $this->served_city_ranking( PHP_INT_MAX ) as $row ) {
			if ( (int) $row['id'] === (int) $city['id'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Indexable = published + index status "index" + reviewed editorial content + approved coverage
	 * (is_served, 2026-10-28) + usable dataset.
	 *
	 * @param array<string, mixed> $city City.
	 */
	public function is_indexable( array $city ): bool {
		return 'published' === $city['publish_status'] && 'index' === $city['index_status'] && $this->get_city_content( (int) $city['id'] )['reviewed'] && $this->is_served( $city );
	}

	/**
	 * Canonical URLs of indexable cities (sitemap).
	 *
	 * @return array<int, string>
	 */
	public function indexable_urls( string $state_code ): array {
		return array_column( $this->indexable_entries( $state_code ), 'loc' );
	}

	/**
	 * Sitemap entries of indexable cities: canonical URL and lastmod (the later of the city record
	 * and its reviewed content, so it changes only when the page content does).
	 *
	 * @return array<int, array<string, string>>
	 */
	public function indexable_entries( string $state_code ): array {
		return Tables::cached(
			'indexable-entries:' . $state_code,
			function () use ( $state_code ) {
				global $wpdb;
				$t    = Tables::name( 'cities' );
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} c WHERE c.state_code = %s AND c.publish_status = 'published' AND c.index_status = 'index' AND " . Tables::dataset_sql( 'c' ) . ' ORDER BY c.display_order, c.city_name', $state_code ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$out  = array();
				foreach ( $rows as $row ) {
					$city = self::city( $row );
					if ( ! $this->is_indexable( $city ) ) {
						continue;
					}
					$times = array( (string) get_post_field( 'post_modified_gmt', $this->get_city_content( (int) $city['id'] )['editorial_id'] ) );
					if ( ! empty( $row['updated_at'] ) ) {
						$times[] = (string) $row['updated_at'];
					}
					$latest = max( array_map( 'strtotime', array_filter( $times ) ) ?: array( 0 ) );
					$out[]  = array_filter(
						array(
							'loc'     => $city['url'],
							'lastmod' => $latest ? gmdate( 'c', $latest ) : '',
						)
					);
				}
				return $out;
			}
		);
	}

	/* ------------------------------------------------------------------ *
	 * Search
	 * ------------------------------------------------------------------ */

	public function search_locations( string $query, int $limit = 8 ): array {
		global $wpdb;
		$query = trim( $query );
		$limit = max( 1, min( 10, $limit ) );
		$out   = array();

		if ( preg_match( '/^\d{3,5}(?:-\d{0,4})?$/', $query ) ) {
			$prefix = substr( $query, 0, 5 );
			$z      = Tables::name( 'zips' );
			$zips   = $wpdb->get_col( $wpdb->prepare( "SELECT z.zip FROM {$z} z WHERE z.zip LIKE %s AND " . Tables::dataset_sql( 'z' ) . ' ORDER BY z.zip LIMIT %d', $wpdb->esc_like( $prefix ) . '%', $limit ), 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $zips as $zip ) {
				$result         = $this->evaluate_coverage( $zip );
				$result['type'] = 'zip';
				$out[]          = $result;
			}
			return $out;
		}

		$needle = Tables::search_key( $query );
		if ( strlen( $needle ) < 2 || strlen( $needle ) > 60 ) {
			return array();
		}
		$t    = Tables::name( 'cities' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} c WHERE c.state_code IN ('" . implode( "','", array_map( 'esc_sql', Tables::STATES ) ) . "') AND c.name_search LIKE %s AND c.publish_status = 'published' AND " . Tables::dataset_sql( 'c' ) . ' ORDER BY c.name_search, c.display_order LIMIT %d', $wpdb->esc_like( $needle ) . '%', $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows as $row ) {
			$city           = self::city( $row );
			$result         = $this->city_coverage( $city );
			$result['type'] = 'city';
			$out[]          = $result;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Coverage
	 * ------------------------------------------------------------------ */

	public function evaluate_coverage( string $location, string $city = '' ): array {
		$raw = trim( $location );
		if ( Coverage::looks_like_zip( $raw ) ) {
			$zip = Coverage::normalize_zip( $raw );
			if ( '' === $zip ) {
				return Coverage::result( array( 'status' => 'invalid', 'reason' => 'malformed_zip' ) );
			}
			$venue = '' !== trim( $city ) ? $this->find_city( Context::location( $city ) ) : null;
			return $this->zip_coverage( $zip, $venue );
		}
		$key = Context::location( $raw );
		if ( '' === $key ) {
			return Coverage::result( array( 'status' => 'invalid', 'reason' => 'malformed' ) );
		}
		$found = $this->find_city( $key );
		if ( ! $found ) {
			return Coverage::result( array( 'status' => 'unknown', 'reason' => 'city_not_found' ), '', $key );
		}
		return $this->city_coverage( $found );
	}

	/**
	 * @param array<string, mixed>|null $venue Venue city for shared ZIP disambiguation.
	 * @return array<string, mixed>
	 */
	private function zip_coverage( string $zip, ?array $venue ): array {
		global $wpdb;
		$z   = Tables::name( 'zips' );
		$cz  = Tables::name( 'city_zips' );
		$c   = Tables::name( 'cities' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT z.* FROM {$z} z WHERE z.zip = %s AND " . Tables::dataset_sql( 'z' ), $zip ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$map = $wpdb->get_results( $wpdb->prepare( "SELECT cz.city_id, cz.service_override, cz.dataset, c.city_name, c.state_code FROM {$cz} cz JOIN {$c} c ON c.id = cz.city_id WHERE cz.zip = %s AND " . Tables::dataset_sql( 'cz' ) . ' AND ' . Tables::dataset_sql( 'c' ), $zip ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$verdict = Coverage::for_zip( $row, $map, $venue );
		$fixture = ( $row && 'fixture' === $row['dataset'] ) || in_array( 'fixture', wp_list_pluck( $map, 'dataset' ), true );
		$label   = $zip;
		$url     = '';
		if ( $venue ) {
			$label = $venue['name'] . ' ' . $zip;
			$url   = 'covered' === $verdict['status'] ? $venue['url'] : '';
		} elseif ( 1 === count( $map ) && 'covered' === $verdict['status'] ) {
			$only = $this->city_by_id( (int) $map[0]['city_id'] );
			if ( $only ) {
				$label = $only['name'] . ' ' . $zip;
				$url   = $only['url'];
			}
		}
		return Coverage::result( $verdict, $label, $zip, $url, $fixture );
	}

	/**
	 * @param array<string, mixed> $city City.
	 * @return array<string, mixed>
	 */
	public function city_coverage( array $city ): array {
		global $wpdb;
		$cz  = Tables::name( 'city_zips' );
		$z   = Tables::name( 'zips' );
		$map = $wpdb->get_results( $wpdb->prepare( "SELECT cz.zip, cz.service_override, z.service_status FROM {$cz} cz LEFT JOIN {$z} z ON z.zip = cz.zip AND " . Tables::dataset_sql( 'z' ) . ' WHERE cz.city_id = %d AND ' . Tables::dataset_sql( 'cz' ), $city['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return Coverage::result( Coverage::for_city( $map ), $city['label'], $city['key'], $city['url'], $city['fixture'] );
	}

	/* ------------------------------------------------------------------ *
	 * Map
	 * ------------------------------------------------------------------ */

	public function get_map_points( string $state_code ): array {
		return Tables::cached(
			'map:' . $state_code,
			static function () use ( $state_code ) {
				global $wpdb;
				$z    = Tables::name( 'zips' );
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT z.county_label, AVG(z.latitude) AS lat, AVG(z.longitude) AS lng, COUNT(*) AS n FROM {$z} z WHERE z.state_code = %s AND z.service_status = 'enabled' AND z.latitude IS NOT NULL AND z.longitude IS NOT NULL AND " . Tables::dataset_sql( 'z' ) . ' GROUP BY z.county_label ORDER BY z.county_label', $state_code ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return array_map(
					static fn( $r ) => array(
						'label'     => (string) $r['county_label'],
						'latitude'  => round( (float) $r['lat'], 3 ),
						'longitude' => round( (float) $r['lng'], 3 ),
						'count'     => (int) $r['n'],
					),
					$rows
				);
			}
		);
	}

	public function is_connected(): bool {
		return (bool) Tables::cached(
			'connected',
			static function () {
				global $wpdb;
				$t = Tables::name( 'cities' );
				return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} c WHERE " . Tables::dataset_sql( 'c' ) ) > 0 ? 1 : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
			}
		);
	}
}
