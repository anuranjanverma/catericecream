<?php
/**
 * Manual write operations on the location tables (the Service Areas admin; the importer has its
 * own planned upsert). Every change is validated against the allowlists, audited and invalidates
 * the location cache. Slug changes keep the old slug in history (one-hop redirect). Cities are
 * never deleted here: an unwanted city is left as a draft.
 *
 * Manual coverage decisions stamp manual_at, and removed city-ZIP relationships are remembered,
 * so a later CSV import reports a conflict instead of silently overwriting or re-linking.
 * Creating geography (a city, a ZIP, a link) never approves coverage: new ZIPs start
 * "unreviewed", new links "inherit" and new cities are Draft + Noindex.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class CityStore {

	public const AUDIT_OPTION  = 'cic_location_audit';
	public const UNLINK_OPTION = 'cic_location_unlinks';
	public const MANUAL_SOURCE = 'Manual entry (Service Areas admin)';
	public const MAX_ZIPS      = 200;

	/** First three ZIP digits allowed per supported state (typo guard). */
	public const ZIP_RANGES = array( 'CA' => array( 900, 961 ) );

	/** Append to the capped audit trail (no personal data; user id + action + detail). */
	public static function audit( string $action, string $detail ): void {
		$log   = (array) get_option( self::AUDIT_OPTION, array() );
		$log[] = array(
			'time'   => gmdate( 'Y-m-d H:i:s' ),
			'user'   => get_current_user_id(),
			'action' => $action,
			'detail' => mb_substr( $detail, 0, 300 ),
		);
		update_option( self::AUDIT_OPTION, array_slice( $log, -200 ), false );
	}

	/** @return array<string, mixed>|null Raw city row. */
	public static function row( int $id ): ?array {
		global $wpdb;
		$t   = Tables::name( 'cities' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ?: null;
	}

	/**
	 * Update editable city attributes. Slug changes record the previous slug in history.
	 *
	 * @param array<string, mixed> $data Raw values (city_name, city_slug, county_name, publish_status,
	 *                                   index_status, display_order, latitude, longitude).
	 * @return true|\WP_Error
	 */
	public static function update_city( int $id, array $data ) {
		global $wpdb;
		$row = self::row( $id );
		if ( ! $row ) {
			return new \WP_Error( 'cic_city_missing', __( 'City not found.', 'cater-ice-cream-core' ) );
		}
		$t       = Tables::name( 'cities' );
		$changes = array();
		if ( isset( $data['city_name'] ) ) {
			$name = sanitize_text_field( (string) $data['city_name'] );
			if ( '' === $name || mb_strlen( $name ) > 120 ) {
				return new \WP_Error( 'cic_city_name', __( 'Enter a city name (up to 120 characters).', 'cater-ice-cream-core' ) );
			}
			$changes['city_name']   = $name;
			$changes['name_search'] = Tables::search_key( $name );
		}
		if ( isset( $data['county_name'] ) ) {
			$county                 = sanitize_text_field( (string) $data['county_name'] );
			$changes['county_name'] = $county;
			$changes['county_slug'] = sanitize_title( $county );
		}
		foreach ( array( 'publish_status' => Tables::PUBLISH_STATUSES, 'index_status' => Tables::INDEX_STATUSES ) as $field => $allowed ) {
			if ( isset( $data[ $field ] ) ) {
				if ( ! in_array( $data[ $field ], $allowed, true ) ) {
					return new \WP_Error( 'cic_city_' . $field, __( 'Invalid status.', 'cater-ice-cream-core' ) );
				}
				$changes[ $field ] = $data[ $field ];
			}
		}
		if ( isset( $data['display_order'] ) ) {
			$changes['display_order'] = max( 0, min( 9999, (int) $data['display_order'] ) );
		}
		foreach ( array( 'latitude' => 90, 'longitude' => 180 ) as $field => $max ) {
			if ( array_key_exists( $field, $data ) ) {
				$value             = trim( (string) $data[ $field ] );
				$changes[ $field ] = ( '' === $value || ! is_numeric( $value ) || abs( (float) $value ) > $max ) ? null : round( (float) $value, 6 );
			}
		}
		if ( isset( $data['city_slug'] ) && $data['city_slug'] !== $row['city_slug'] ) {
			$slug     = sanitize_title( (string) $data['city_slug'] );
			$conflict = self::slug_conflict( (string) $row['state_code'], $slug, $id );
			if ( $conflict ) {
				return $conflict;
			}
			$s = Tables::name( 'city_slugs' );
			// The new slug is canonical now: drop it from history; keep the old one as a redirect.
			$wpdb->delete( $s, array( 'state_code' => $row['state_code'], 'slug' => $slug ) );
			$wpdb->replace(
				$s,
				array(
					'state_code' => $row['state_code'],
					'slug'       => $row['city_slug'],
					'city_id'    => $id,
					'kind'       => 'history',
					'created_at' => current_time( 'mysql', true ),
				)
			);
			$changes['city_slug'] = $slug;
		}
		if ( ! $changes ) {
			return true;
		}
		$changes['updated_at'] = current_time( 'mysql', true );
		$wpdb->update( $t, $changes, array( 'id' => $id ) );
		self::audit( 'city_update', sprintf( '#%d %s: %s', $id, $row['city_name'], implode( ', ', array_keys( $changes ) ) ) );
		Tables::bump_cache();
		return true;
	}

	/**
	 * Record an alias slug that redirects to the city.
	 *
	 * @return true|\WP_Error
	 */
	public static function add_alias( int $id, string $alias ) {
		global $wpdb;
		$row  = self::row( $id );
		$slug = sanitize_title( $alias );
		if ( ! $row || '' === $slug ) {
			return new \WP_Error( 'cic_alias', __( 'Invalid alias.', 'cater-ice-cream-core' ) );
		}
		$t = Tables::name( 'cities' );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE state_code = %s AND city_slug = %s", $row['state_code'], $slug ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return new \WP_Error( 'cic_alias_taken', __( 'A city already uses that slug.', 'cater-ice-cream-core' ) );
		}
		$s = Tables::name( 'city_slugs' );
		if ( false === $wpdb->insert( $s, array( 'state_code' => $row['state_code'], 'slug' => $slug, 'city_id' => $id, 'kind' => 'alias', 'created_at' => current_time( 'mysql', true ) ) ) ) {
			return new \WP_Error( 'cic_alias_taken', __( 'That alias is already recorded.', 'cater-ice-cream-core' ) );
		}
		self::audit( 'alias_add', sprintf( '#%d %s ← %s', $id, $row['city_slug'], $slug ) );
		Tables::bump_cache();
		return true;
	}

	/**
	 * Slugs that redirect to the city (aliases and history).
	 *
	 * @return array<int, array{slug: string, kind: string}>
	 */
	public static function redirects( int $id ): array {
		global $wpdb;
		$s = Tables::name( 'city_slugs' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT slug, kind FROM {$s} WHERE city_id = %d ORDER BY created_at", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function remove_redirect( int $id, string $slug ): void {
		global $wpdb;
		$wpdb->delete( Tables::name( 'city_slugs' ), array( 'city_id' => $id, 'slug' => sanitize_title( $slug ) ) );
		self::audit( 'redirect_remove', sprintf( '#%d %s', $id, $slug ) );
		Tables::bump_cache();
	}

	/**
	 * City's ZIP mappings with the ZIP's reviewed status.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function mappings( int $id ): array {
		global $wpdb;
		$cz = Tables::name( 'city_zips' );
		$z  = Tables::name( 'zips' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT cz.zip, cz.service_override, cz.review_note, cz.dataset, cz.manual_at, z.service_status, z.dataset AS zip_dataset, z.county_label, (SELECT COUNT(*) FROM {$cz} o WHERE o.zip = cz.zip) AS shared FROM {$cz} cz LEFT JOIN {$z} z ON z.zip = cz.zip WHERE cz.city_id = %d ORDER BY cz.zip", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Reviewed decision for one city-ZIP mapping (override + internal note).
	 *
	 * @return true|\WP_Error
	 */
	public static function set_override( int $city_id, string $zip, string $override, string $note ) {
		global $wpdb;
		if ( ! in_array( $override, Tables::OVERRIDES, true ) || ! preg_match( '/^\d{5}$/', $zip ) ) {
			return new \WP_Error( 'cic_override', __( 'Invalid coverage decision.', 'cater-ice-cream-core' ) );
		}
		$cz      = Tables::name( 'city_zips' );
		$updated = $wpdb->update(
			$cz,
			array(
				'service_override' => $override,
				'review_note'      => mb_substr( sanitize_text_field( $note ), 0, 255 ),
				'manual_at'        => current_time( 'mysql', true ),
				'updated_at'       => current_time( 'mysql', true ),
			),
			array( 'city_id' => $city_id, 'zip' => $zip )
		);
		if ( $updated ) {
			self::audit( 'override', sprintf( 'city #%d zip %s → %s', $city_id, $zip, $override ) );
			Tables::bump_cache();
		}
		return true;
	}

	/**
	 * Reviewed ZIP status (enabled / disabled / manual_review / unreviewed).
	 *
	 * @return true|\WP_Error
	 */
	public static function set_zip_status( string $zip, string $status ) {
		global $wpdb;
		if ( ! in_array( $status, Tables::ZIP_STATUSES, true ) || ! preg_match( '/^\d{5}$/', $zip ) ) {
			return new \WP_Error( 'cic_zip_status', __( 'Invalid ZIP status.', 'cater-ice-cream-core' ) );
		}
		$updated = $wpdb->update(
			Tables::name( 'zips' ),
			array(
				'service_status' => $status,
				'reviewed_at'    => 'unreviewed' === $status ? null : current_time( 'mysql', true ),
				'manual_at'      => current_time( 'mysql', true ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'zip' => $zip )
		);
		if ( $updated ) {
			self::audit( 'zip_status', sprintf( '%s → %s', $zip, $status ) );
			Tables::bump_cache();
		}
		return true;
	}

	/**
	 * Route identity check for a city slug in a state: the canonical slugs of other cities and
	 * every recorded alias / previous slug of other cities. The city's own history may be reused.
	 */
	public static function slug_conflict( string $state, string $slug, int $except_id = 0 ): ?\WP_Error {
		global $wpdb;
		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) || strlen( $slug ) > 140 ) {
			return new \WP_Error( 'cic_city_slug', __( 'Enter a URL slug using lowercase letters, numbers and hyphens.', 'cater-ice-cream-core' ) );
		}
		$t     = Tables::name( 'cities' );
		$taken = $wpdb->get_row( $wpdb->prepare( "SELECT id, city_name FROM {$t} WHERE country_code = 'US' AND state_code = %s AND city_slug = %s AND id <> %d", $state, $slug, $except_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $taken ) {
			/* translators: %s: city name. */
			return new \WP_Error( 'cic_city_slug_taken', sprintf( __( 'The URL slug is already used by %s. Choose a different slug.', 'cater-ice-cream-core' ), $taken['city_name'] ) );
		}
		$s     = Tables::name( 'city_slugs' );
		$owner = $wpdb->get_row( $wpdb->prepare( "SELECT s.kind, c.city_name FROM {$s} s LEFT JOIN {$t} c ON c.id = s.city_id WHERE s.state_code = %s AND s.slug = %s AND s.city_id <> %d", $state, $slug, $except_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $owner ) {
			/* translators: 1: alias or previous slug, 2: city name. */
			return new \WP_Error( 'cic_city_slug_alias', sprintf( __( 'That URL slug redirects to %2$s (recorded %1$s). Choose a different slug.', 'cater-ice-cream-core' ), 'alias' === $owner['kind'] ? __( 'alias', 'cater-ice-cream-core' ) : __( 'previous slug', 'cater-ice-cream-core' ), (string) $owner['city_name'] ) );
		}
		return null;
	}

	/** @return float|null|false Rounded coordinate, null when empty, false when invalid. */
	private static function coordinate( string $raw, int $max ) {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		return ( is_numeric( $raw ) && abs( (float) $raw ) <= $max ) ? round( (float) $raw, 6 ) : false;
	}

	/**
	 * Create a city by hand: always Draft + Noindex, no ZIPs, so its coverage stays unknown.
	 *
	 * @param array<string, mixed> $data city_name, state_code, county_name, city_slug, latitude, longitude, dataset.
	 * @return int|\WP_Error New city id.
	 */
	public static function create_city( array $data ) {
		global $wpdb;
		$state = strtoupper( (string) ( $data['state_code'] ?? '' ) );
		if ( '' === Tables::state_slug( $state ) ) {
			return new \WP_Error( 'cic_city_state', __( 'Choose a supported state.', 'cater-ice-cream-core' ) );
		}
		$name = sanitize_text_field( (string) ( $data['city_name'] ?? '' ) );
		if ( '' === $name || mb_strlen( $name ) > 120 ) {
			return new \WP_Error( 'cic_city_name', __( 'Enter a city name (up to 120 characters).', 'cater-ice-cream-core' ) );
		}
		$county = sanitize_text_field( (string) ( $data['county_name'] ?? '' ) );
		if ( '' === $county || mb_strlen( $county ) > 100 ) {
			return new \WP_Error( 'cic_city_county', __( 'Enter the county (up to 100 characters).', 'cater-ice-cream-core' ) );
		}
		$raw_slug = trim( (string) ( $data['city_slug'] ?? '' ) );
		$slug     = sanitize_title( '' !== $raw_slug ? $raw_slug : $name );
		$t   = Tables::name( 'cities' );
		$dup = $wpdb->get_row( $wpdb->prepare( "SELECT id, city_name, county_name FROM {$t} WHERE state_code = %s AND name_search = %s AND county_slug = %s", $state, Tables::search_key( $name ), sanitize_title( $county ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $dup ) {
			/* translators: 1: city, 2: county. */
			return new \WP_Error( 'cic_city_duplicate', sprintf( __( '%1$s already exists in %2$s. Edit the existing city instead.', 'cater-ice-cream-core' ), $dup['city_name'], $dup['county_name'] ) );
		}
		$conflict = self::slug_conflict( $state, $slug );
		if ( $conflict ) {
			return $conflict;
		}
		$lat = self::coordinate( (string) ( $data['latitude'] ?? '' ), 90 );
		$lng = self::coordinate( (string) ( $data['longitude'] ?? '' ), 180 );
		if ( false === $lat || false === $lng || ( null === $lat ) !== ( null === $lng ) ) {
			return new \WP_Error( 'cic_city_coordinates', __( 'Coordinates are optional; when used, enter both latitude (−90 to 90) and longitude (−180 to 180) as decimal degrees.', 'cater-ice-cream-core' ) );
		}
		$dataset = (string) ( $data['dataset'] ?? 'production' );
		if ( ! in_array( $dataset, Tables::DATASETS, true ) || ( 'fixture' === $dataset && ! Tables::fixtures_allowed() ) ) {
			return new \WP_Error( 'cic_city_dataset', __( 'Invalid dataset.', 'cater-ice-cream-core' ) );
		}
		$ok = $wpdb->insert(
			$t,
			array(
				'country_code'     => 'US',
				'state_code'       => $state,
				'county_name'      => $county,
				'county_slug'      => sanitize_title( $county ),
				'city_name'        => $name,
				'city_slug'        => $slug,
				'name_search'      => Tables::search_key( $name ),
				'timezone'         => 'America/Los_Angeles',
				'latitude'         => $lat,
				'longitude'        => $lng,
				'publish_status'   => 'draft',
				'index_status'     => 'noindex',
				'display_order'    => 0,
				'dataset'          => $dataset,
				'source_reference' => self::MANUAL_SOURCE,
				'updated_at'       => current_time( 'mysql', true ),
			)
		);
		if ( ! $ok ) {
			return new \WP_Error( 'cic_city_insert', __( 'The city could not be created (the slug may have just been taken). Try again.', 'cater-ice-cream-core' ) );
		}
		$id = (int) $wpdb->insert_id;
		self::audit( 'city_create', sprintf( '#%d %s, %s (%s, %s) draft/noindex', $id, $name, $state, $slug, $dataset ) );
		Tables::bump_cache();
		return $id;
	}

	/**
	 * Parse one ZIP or a pasted list (commas, spaces or new lines). ZIPs stay strings, so leading
	 * zeros survive; ZIP+4 is shortened to five digits.
	 *
	 * @return array{zips: string[], invalid: array<int, array{raw: string, reason: string}>, repeated: string[]}
	 */
	public static function parse_zips( string $raw ): array {
		$out    = array( 'zips' => array(), 'invalid' => array(), 'repeated' => array() );
		$tokens = array_values( array_filter( preg_split( '/[\s,;]+/', $raw ), static fn( $t ) => '' !== $t ) );
		foreach ( $tokens as $i => $token ) {
			$token = mb_substr( $token, 0, 20 );
			if ( $i >= self::MAX_ZIPS ) {
				/* translators: %d: limit. */
				$out['invalid'][] = array( 'raw' => $token, 'reason' => sprintf( __( 'More than %d ZIPs at once: add the rest separately.', 'cater-ice-cream-core' ), self::MAX_ZIPS ) );
				break;
			}
			if ( preg_match( '/^(\d{5})(?:-\d{4})?$/', $token, $m ) && '00000' !== $m[1] ) {
				if ( in_array( $m[1], $out['zips'], true ) ) {
					$out['repeated'][] = $m[1];
				} else {
					$out['zips'][] = $m[1];
				}
			} elseif ( preg_match( '/^\d{3,4}$/', $token ) ) {
				$out['invalid'][] = array( 'raw' => $token, 'reason' => __( 'Fewer than 5 digits: a leading zero was probably lost. Enter all five digits.', 'cater-ice-cream-core' ) );
			} else {
				$out['invalid'][] = array( 'raw' => $token, 'reason' => __( 'Not a 5-digit US ZIP code.', 'cater-ice-cream-core' ) );
			}
		}
		$out['repeated'] = array_values( array_unique( $out['repeated'] ) );
		return $out;
	}

	/**
	 * Cities linked to a ZIP, optionally excluding one.
	 *
	 * @return array<int, array{city_id: int, label: string, service_override: string, dataset: string}>
	 */
	public static function zip_cities( string $zip, int $except_city = 0 ): array {
		global $wpdb;
		$cz   = Tables::name( 'city_zips' );
		$c    = Tables::name( 'cities' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT cz.city_id, cz.service_override, cz.dataset, c.city_name, c.state_code FROM {$cz} cz JOIN {$c} c ON c.id = cz.city_id WHERE cz.zip = %s AND cz.city_id <> %d ORDER BY c.city_name", $zip, $except_city ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map(
			static fn( $r ) => array(
				'city_id'          => (int) $r['city_id'],
				'label'            => $r['city_name'] . ', ' . $r['state_code'],
				'service_override' => (string) $r['service_override'],
				'dataset'          => (string) $r['dataset'],
			),
			$rows
		);
	}

	/**
	 * Plan adding ZIPs to a city without writing: each ZIP is "create" (new record, unreviewed),
	 * "link" (existing record; lists the other cities it already belongs to), "already" (linked
	 * here) or "blocked" (with a reason).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function plan_zip_links( int $city_id, string $raw ) {
		global $wpdb;
		$city = self::row( $city_id );
		if ( ! $city ) {
			return new \WP_Error( 'cic_city_missing', __( 'City not found.', 'cater-ice-cream-core' ) );
		}
		$parsed = self::parse_zips( $raw );
		$z      = Tables::name( 'zips' );
		$cz     = Tables::name( 'city_zips' );
		$range  = self::ZIP_RANGES[ $city['state_code'] ] ?? null;
		$items  = array();
		foreach ( $parsed['zips'] as $zip ) {
			$item = array( 'zip' => $zip, 'action' => 'link', 'reason' => '', 'zip_status' => '', 'zip_dataset' => '', 'others' => array() );
			$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$z} WHERE zip = %s", $zip ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $range && ( (int) substr( $zip, 0, 3 ) < $range[0] || (int) substr( $zip, 0, 3 ) > $range[1] ) ) {
				$item['action'] = 'blocked';
				/* translators: 1: state code, 2: lowest prefix, 3: highest prefix. */
				$item['reason'] = sprintf( __( 'Not a %1$s ZIP code (%1$s ZIPs start %2$d–%3$d).', 'cater-ice-cream-core' ), $city['state_code'], $range[0], $range[1] );
			} elseif ( $row && $row['state_code'] !== $city['state_code'] ) {
				$item['action'] = 'blocked';
				/* translators: %s: state code. */
				$item['reason'] = sprintf( __( 'This ZIP is recorded in %s.', 'cater-ice-cream-core' ), $row['state_code'] );
			} elseif ( $row && 'fixture' === $row['dataset'] && 'production' === $city['dataset'] ) {
				$item['action'] = 'blocked';
				$item['reason'] = __( 'This ZIP is fixture test data and cannot be linked to a production city.', 'cater-ice-cream-core' );
			} elseif ( $wpdb->get_var( $wpdb->prepare( "SELECT zip FROM {$cz} WHERE city_id = %d AND zip = %s", $city_id, $zip ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$item['action'] = 'already';
			} elseif ( ! $row ) {
				$item['action'] = 'create';
			}
			if ( $row ) {
				$item['zip_status']  = (string) $row['service_status'];
				$item['zip_dataset'] = (string) $row['dataset'];
				$item['others']      = self::zip_cities( $zip, $city_id );
			}
			$items[] = $item;
		}
		return array(
			'city'     => $city,
			'items'    => $items,
			'invalid'  => $parsed['invalid'],
			'repeated' => $parsed['repeated'],
			'addable'  => count( array_filter( $items, static fn( $i ) => in_array( $i['action'], array( 'create', 'link' ), true ) ) ),
		);
	}

	/**
	 * Apply a ZIP plan (re-planned here, so a stale preview cannot add anything else). New ZIP
	 * records start unreviewed; new links inherit the ZIP coverage. Both take the city's dataset.
	 *
	 * @return array<string, mixed>|\WP_Error The plan that was applied.
	 */
	public static function link_zips( int $city_id, string $raw ) {
		global $wpdb;
		$plan = self::plan_zip_links( $city_id, $raw );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$city    = $plan['city'];
		$now     = current_time( 'mysql', true );
		$added   = array();
		$unlinks = (array) get_option( self::UNLINK_OPTION, array() );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $plan['items'] as $item ) {
			if ( 'create' === $item['action'] ) {
				$wpdb->insert(
					Tables::name( 'zips' ),
					array(
						'zip'              => $item['zip'],
						'state_code'       => $city['state_code'],
						'county_label'     => $city['county_name'],
						'service_status'   => 'unreviewed',
						'dataset'          => $city['dataset'],
						'source_reference' => self::MANUAL_SOURCE,
						'updated_at'       => $now,
					)
				);
			}
			if ( in_array( $item['action'], array( 'create', 'link' ), true ) ) {
				$wpdb->insert(
					Tables::name( 'city_zips' ),
					array(
						'city_id'          => $city_id,
						'zip'              => $item['zip'],
						'service_override' => 'inherit',
						'review_note'      => '',
						'dataset'          => $city['dataset'],
						'source_reference' => self::MANUAL_SOURCE,
						'updated_at'       => $now,
					)
				);
				unset( $unlinks[ $city_id . '/' . $item['zip'] ] );
				$added[] = $item['zip'] . ( 'create' === $item['action'] ? ' (new)' : '' );
			}
		}
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $added ) {
			update_option( self::UNLINK_OPTION, $unlinks, false );
			self::audit( 'zip_link', sprintf( '#%d %s ← %s', $city_id, $city['city_name'], implode( ', ', $added ) ) );
			Tables::bump_cache();
		}
		return $plan;
	}

	/**
	 * Remove one city-ZIP relationship. The ZIP record and its links to other cities stay.
	 *
	 * @return int|\WP_Error Number of cities still linked to the ZIP.
	 */
	public static function unlink_zip( int $city_id, string $zip ) {
		global $wpdb;
		$row = self::row( $city_id );
		if ( ! $row || ! preg_match( '/^\d{5}$/', $zip ) ) {
			return new \WP_Error( 'cic_unlink', __( 'Invalid ZIP or city.', 'cater-ice-cream-core' ) );
		}
		$deleted = $wpdb->delete( Tables::name( 'city_zips' ), array( 'city_id' => $city_id, 'zip' => $zip ) );
		if ( ! $deleted ) {
			return new \WP_Error( 'cic_unlink', __( 'That ZIP is not linked to this city.', 'cater-ice-cream-core' ) );
		}
		$unlinks                         = (array) get_option( self::UNLINK_OPTION, array() );
		$unlinks[ $city_id . '/' . $zip ] = gmdate( 'Y-m-d H:i:s' );
		update_option( self::UNLINK_OPTION, array_slice( $unlinks, -2000, null, true ), false );
		self::audit( 'zip_unlink', sprintf( '#%d %s ✕ %s', $city_id, $row['city_name'], $zip ) );
		Tables::bump_cache();
		return count( self::zip_cities( $zip ) );
	}

	/** Linked editorial record id (0 if none). */
	public static function editorial_id( int $city_id ): int {
		global $wpdb;
		$t = Tables::name( 'city_content' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT editorial_post_id FROM {$t} WHERE city_id = %d", $city_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** City linked to an editorial record (0 if none). */
	public static function city_for_editorial( int $post_id ): int {
		global $wpdb;
		$t = Tables::name( 'city_content' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT city_id FROM {$t} WHERE editorial_post_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Editorial record for a city, created as a private draft-like record on first use.
	 */
	public static function ensure_editorial( int $city_id ): int {
		global $wpdb;
		$existing = self::editorial_id( $city_id );
		if ( $existing && get_post( $existing ) ) {
			return $existing;
		}
		$row = self::row( $city_id );
		if ( ! $row ) {
			return 0;
		}
		$post_id = (int) wp_insert_post(
			array(
				'post_type'   => Editorial::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $row['city_name'] . ', ' . $row['state_code'],
			),
			true
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return 0;
		}
		$wpdb->replace(
			Tables::name( 'city_content' ),
			array(
				'city_id'           => $city_id,
				'editorial_post_id' => $post_id,
				'updated_at'        => current_time( 'mysql', true ),
			)
		);
		self::audit( 'editorial_create', sprintf( 'city #%d → post #%d', $city_id, $post_id ) );
		Tables::bump_cache();
		return $post_id;
	}
}
