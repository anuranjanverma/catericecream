<?php
/**
 * Write operations on the provisional location tables, shared by the importer and the admin
 * screen. Every change is validated against the allowlists, audited and invalidates the location
 * cache. Slug changes keep the old slug in history (one-hop redirect). Deletion is restricted:
 * a city with an editorial record or ZIP mappings is unpublished rather than deleted.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class CityStore {

	public const AUDIT_OPTION = 'cic_location_audit';

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
			$slug = sanitize_title( (string) $data['city_slug'] );
			if ( '' === $slug || strlen( $slug ) > 140 ) {
				return new \WP_Error( 'cic_city_slug', __( 'Enter a URL slug.', 'cater-ice-cream-core' ) );
			}
			$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE country_code = %s AND state_code = %s AND city_slug = %s AND id <> %d", $row['country_code'], $row['state_code'], $slug, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $taken ) {
				return new \WP_Error( 'cic_city_slug_taken', __( 'Another city in this state already uses that slug.', 'cater-ice-cream-core' ) );
			}
			$s     = Tables::name( 'city_slugs' );
			$owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT city_id FROM {$s} WHERE state_code = %s AND slug = %s", $row['state_code'], $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $owner && $owner !== $id ) {
				return new \WP_Error( 'cic_city_slug_alias', __( 'That slug is recorded as an alias of another city.', 'cater-ice-cream-core' ) );
			}
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
		return $wpdb->get_results( $wpdb->prepare( "SELECT cz.zip, cz.service_override, cz.review_note, cz.dataset, z.service_status, z.county_label, (SELECT COUNT(*) FROM {$cz} o WHERE o.zip = cz.zip) AS shared FROM {$cz} cz LEFT JOIN {$z} z ON z.zip = cz.zip WHERE cz.city_id = %d ORDER BY cz.zip", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
