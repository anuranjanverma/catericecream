<?php
/**
 * Event venues ({prefix}cic_venues, schema v7, 2026-11-21): names only, shown in the "Popular Event
 * Venues" section of city pages (that city's venues, max 12) and event pages (venues suited to that
 * occasion across the served cities, max 32). All venue SQL lives here (prepared, configured prefix).
 *
 * Statuses:
 * - draft:    unverified (e.g. the 2026-11-21 starter list). Visible only on local/development
 *             environments so the layout can be reviewed; never on staging or production.
 * - approved: checked by the owner; shown everywhere.
 * - hidden:   kept for reference, never shown.
 * Only a name is stored or shown: no address, rating, partnership or availability claim.
 * Every write bumps the location cache version.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class VenueStore {

	public const STATUSES    = array( 'draft', 'approved', 'hidden' );
	public const CITY_LIMIT  = 12;
	public const EVENT_LIMIT = 32;
	public const NAME_MAX    = 120;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cic_venues';
	}

	public static function exists(): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/** Create the table (idempotent; called by the v7 migration). */
	public static function create(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				city_id bigint(20) unsigned NOT NULL,
				name varchar(120) NOT NULL,
				name_search varchar(140) NOT NULL DEFAULT '',
				occasions varchar(255) NOT NULL DEFAULT '',
				sort_order int(11) NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'draft',
				source_reference varchar(190) NOT NULL DEFAULT '',
				reviewed_at datetime NULL DEFAULT NULL,
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY city_name (city_id,name_search),
				KEY city_status (city_id,status,sort_order),
				KEY status (status)
			) {$collate};"
		);
	}

	/** Draft (unverified) venues are visible only on local/development (filter for tests). */
	public static function drafts_visible(): bool {
		return (bool) apply_filters( 'cic_venue_drafts_visible', in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) );
	}

	/** @return string[] Statuses shown on public pages. */
	public static function visible_statuses(): array {
		return self::drafts_visible() ? array( 'approved', 'draft' ) : array( 'approved' );
	}

	/** @return array<string, string> Occasion key => label, from the published events (central order). */
	public static function occasions(): array {
		$out = array();
		foreach ( \CIC\Content\Catalog::selectable_events() as $event ) {
			if ( '' !== $event['key'] ) {
				$out[ $event['key'] ] = $event['label'];
			}
		}
		return $out;
	}

	/**
	 * @param string|string[] $raw Comma list or array of occasion keys.
	 * @return string[] Known keys only, unique, in central event order.
	 */
	public static function clean_occasions( $raw ): array {
		$keys = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$keys = array_map( static fn( $k ) => sanitize_key( trim( (string) $k ) ), $keys );
		return array_values( array_intersect( array_keys( self::occasions() ), $keys ) );
	}

	/** Stored form: ",wedding,prom," (so one occasion is matched with LIKE '%,key,%'). */
	private static function pack_occasions( array $keys ): string {
		return $keys ? ',' . implode( ',', $keys ) . ',' : '';
	}

	/** @return array<string, mixed> */
	private static function row( array $row ): array {
		return array(
			'id'          => (int) $row['id'],
			'city_id'     => (int) $row['city_id'],
			'name'        => (string) $row['name'],
			'occasions'   => array_values( array_filter( explode( ',', (string) $row['occasions'] ) ) ),
			'sort_order'  => (int) $row['sort_order'],
			'status'      => (string) $row['status'],
			'source'      => (string) $row['source_reference'],
			'reviewed_at' => $row['reviewed_at'],
			'updated_at'  => (string) $row['updated_at'],
		);
	}

	/** @return array<string, mixed>|null */
	public static function get( int $id ): ?array {
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::row( $row ) : null;
	}

	/**
	 * Validate and normalise editable fields.
	 *
	 * @param array<string, mixed> $data city_id, name, occasions, sort_order, status.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function clean( array $data ) {
		$name = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $data['name'] ?? '' ) ) ) );
		if ( '' === $name ) {
			return new \WP_Error( 'cic_venue_name', __( 'Enter the venue name.', 'cater-ice-cream-core' ) );
		}
		if ( mb_strlen( $name ) > self::NAME_MAX ) {
			return new \WP_Error( 'cic_venue_name_long', sprintf( /* translators: %d: characters. */ __( 'Venue names can have at most %d characters.', 'cater-ice-cream-core' ), self::NAME_MAX ) );
		}
		$city_id = (int) ( $data['city_id'] ?? 0 );
		if ( ! $city_id || ! CityStore::row( $city_id ) ) {
			return new \WP_Error( 'cic_venue_city', __( 'Choose the city the venue is in.', 'cater-ice-cream-core' ) );
		}
		$status = (string) ( $data['status'] ?? 'draft' );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new \WP_Error( 'cic_venue_status', __( 'Unknown venue status.', 'cater-ice-cream-core' ) );
		}
		return array(
			'city_id'     => $city_id,
			'name'        => $name,
			'name_search' => Tables::search_key( $name ),
			'occasions'   => self::pack_occasions( self::clean_occasions( $data['occasions'] ?? array() ) ),
			'sort_order'  => max( -9999, min( 9999, (int) ( $data['sort_order'] ?? 0 ) ) ),
			'status'      => $status,
		);
	}

	private static function duplicate( int $city_id, string $name_search, int $except = 0 ): bool {
		global $wpdb;
		$t = self::table();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE city_id = %d AND name_search = %s AND id <> %d", $city_id, $name_search, $except ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Id of the city's venue with this name (case/accents/punctuation ignored), or 0. */
	public static function find_id( int $city_id, string $name ): int {
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE city_id = %d AND name_search = %s", $city_id, Tables::search_key( $name ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Create (id 0) or update a venue.
	 *
	 * @param array<string, mixed> $data Fields.
	 * @return int|\WP_Error Venue id.
	 */
	public static function save( array $data, int $id = 0, string $source = '' ) {
		global $wpdb;
		$clean = self::clean( $data );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		$old = $id ? self::get( $id ) : null;
		if ( $id && ! $old ) {
			return new \WP_Error( 'cic_venue_missing', __( 'That venue no longer exists.', 'cater-ice-cream-core' ) );
		}
		if ( self::duplicate( $clean['city_id'], $clean['name_search'], $id ) ) {
			return new \WP_Error( 'cic_venue_duplicate', __( 'This city already has a venue with that name.', 'cater-ice-cream-core' ) );
		}
		$now                 = current_time( 'mysql', true );
		$clean['updated_at'] = $now;
		if ( 'approved' === $clean['status'] && ( ! $old || 'approved' !== $old['status'] ) ) {
			$clean['reviewed_at'] = $now;
		}
		if ( $id ) {
			$ok = $wpdb->update( self::table(), $clean, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$clean['source_reference'] = substr( '' !== $source ? $source : 'admin', 0, 190 );
			$ok                        = $wpdb->insert( self::table(), $clean ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id                        = (int) $wpdb->insert_id;
		}
		if ( false === $ok ) {
			return new \WP_Error( 'cic_venue_db', __( 'The venue could not be saved.', 'cater-ice-cream-core' ) );
		}
		Tables::bump_cache();
		return $id;
	}

	/**
	 * Set the status of several venues.
	 *
	 * @param int[] $ids Venue ids.
	 * @return int Rows changed.
	 */
	public static function set_status( array $ids, string $status ): int {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids || ! in_array( $status, self::STATUSES, true ) ) {
			return 0;
		}
		$t      = self::table();
		$now    = current_time( 'mysql', true );
		$in     = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$review = 'approved' === $status ? ", reviewed_at = IF(status = 'approved', reviewed_at, %s)" : '';
		$params = array_merge( array( $status ), $review ? array( $now ) : array(), array( $now ), $ids );
		$n      = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = %s{$review}, updated_at = %s WHERE id IN ({$in})", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		Tables::bump_cache();
		return $n;
	}

	/**
	 * @param int[] $ids Venue ids.
	 * @return int Rows deleted.
	 */
	public static function delete( array $ids ): int {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$t  = self::table();
		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$n  = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE id IN ({$in})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		Tables::bump_cache();
		return $n;
	}

	/**
	 * Admin list with filters.
	 *
	 * @param array<string, mixed> $filter city_id, status, occasion, s.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function search( array $filter, int $page = 1, int $per = 50 ): array {
		global $wpdb;
		$t      = self::table();
		$c      = Tables::name( 'cities' );
		$where  = '1=1';
		$params = array();
		if ( ! empty( $filter['city_id'] ) ) {
			$where   .= ' AND v.city_id = %d';
			$params[] = (int) $filter['city_id'];
		}
		if ( ! empty( $filter['status'] ) && in_array( $filter['status'], self::STATUSES, true ) ) {
			$where   .= ' AND v.status = %s';
			$params[] = $filter['status'];
		}
		if ( ! empty( $filter['occasion'] ) ) {
			$where   .= ' AND v.occasions LIKE %s';
			$params[] = '%,' . $wpdb->esc_like( sanitize_key( $filter['occasion'] ) ) . ',%';
		}
		if ( ! empty( $filter['s'] ) ) {
			$where   .= ' AND v.name_search LIKE %s';
			$params[] = '%' . $wpdb->esc_like( Tables::search_key( (string) $filter['s'] ) ) . '%';
		}
		$from  = "FROM {$t} v JOIN {$c} c ON c.id = v.city_id WHERE {$where}";
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from}", $params ) ) : $wpdb->get_var( "SELECT COUNT(*) {$from}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT v.*, c.city_name, c.state_code, c.publish_status {$from} ORDER BY c.city_name, v.sort_order, v.name LIMIT %d OFFSET %d", array_merge( $params, array( $per, ( max( 1, $page ) - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$out   = array();
		foreach ( $rows as $row ) {
			$out[] = self::row( $row ) + array( 'city_label' => $row['city_name'] . ', ' . $row['state_code'], 'city_published' => 'published' === $row['publish_status'] );
		}
		return array( 'rows' => $out, 'total' => $total );
	}

	/** @return array<int, string> City id => "City, ST" for the admin city picker (all cities, by name). */
	public static function city_options(): array {
		global $wpdb;
		$c   = Tables::name( 'cities' );
		$out = array();
		foreach ( $wpdb->get_results( "SELECT id, city_name, state_code, dataset FROM {$c} ORDER BY city_name, state_code", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out[ (int) $row['id'] ] = $row['city_name'] . ', ' . $row['state_code'] . ( 'fixture' === $row['dataset'] ? ' (fixture)' : '' );
		}
		return $out;
	}

	/** @return array<string, int> Status => count. */
	public static function counts(): array {
		global $wpdb;
		$t   = self::table();
		$out = array_fill_keys( self::STATUSES, 0 );
		foreach ( $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$t} GROUP BY status", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out[ $row['status'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Venue names for a city page (visible statuses, venue order, then name).
	 *
	 * @return string[]
	 */
	public static function for_city( int $city_id, int $limit = self::CITY_LIMIT ): array {
		if ( ! Tables::ready() || ! self::exists() ) {
			return array();
		}
		$statuses = self::visible_statuses();
		return Tables::cached(
			'venues-city:' . $city_id . ':' . $limit . ':' . implode( ',', $statuses ),
			static function () use ( $city_id, $limit, $statuses ) {
				global $wpdb;
				$t  = self::table();
				$in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
				return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT name FROM {$t} WHERE city_id = %d AND status IN ({$in}) ORDER BY sort_order, name LIMIT %d", array_merge( array( $city_id ), $statuses, array( $limit ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			}
		);
	}

	/**
	 * Venue names for an event page: venues suited to the occasion in the served cities (Cities We
	 * Serve ranking), taken round-robin (each city's first venue, then each city's second …) so the
	 * list spans the area instead of filling up from one city.
	 *
	 * @return string[]
	 */
	public static function for_event( string $occasion, int $limit = self::EVENT_LIMIT ): array {
		$occasion = sanitize_key( $occasion );
		if ( '' === $occasion || ! Tables::ready() || ! self::exists() ) {
			return array();
		}
		$repo = Service::repository();
		if ( ! $repo instanceof DatabaseRepository ) {
			return array();
		}
		$statuses = self::visible_statuses();
		return Tables::cached(
			'venues-event:' . $occasion . ':' . $limit . ':' . implode( ',', $statuses ),
			static function () use ( $occasion, $limit, $statuses, $repo ) {
				global $wpdb;
				$rank = array();
				foreach ( $repo->served_city_ranking( PHP_INT_MAX ) as $i => $city ) {
					$rank[ (int) $city['id'] ] = $i;
				}
				if ( ! $rank ) {
					return array();
				}
				$t    = self::table();
				$in   = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT city_id, name FROM {$t} WHERE occasions LIKE %s AND status IN ({$in}) ORDER BY sort_order, name", array_merge( array( '%,' . $wpdb->esc_like( $occasion ) . ',%' ), $statuses ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
				$by   = array();
				foreach ( $rows as $row ) {
					$city = (int) $row['city_id'];
					if ( isset( $rank[ $city ] ) ) {
						$by[ $rank[ $city ] ][] = (string) $row['name'];
					}
				}
				ksort( $by );
				$out  = array();
				$seen = array();
				for ( $round = 0; count( $out ) < $limit && $by; $round++ ) {
					foreach ( $by as $r => $names ) {
						if ( ! isset( $names[ $round ] ) ) {
							unset( $by[ $r ] );
							continue;
						}
						$key = Tables::search_key( $names[ $round ] );
						if ( ! isset( $seen[ $key ] ) ) {
							$seen[ $key ] = true;
							$out[]        = $names[ $round ];
							if ( count( $out ) >= $limit ) {
								break;
							}
						}
					}
				}
				return $out;
			}
		);
	}
}
