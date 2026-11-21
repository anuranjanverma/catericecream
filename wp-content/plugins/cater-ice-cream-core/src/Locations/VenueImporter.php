<?php
/**
 * Venue starter-list importer (2026-11-21), shared by the browser page (API/import-venues.php) and
 * the CLI tool (dev/tools/content/venues-20261121.php). Data: the plugin's
 * data/venues-starter-20261121.json, keyed by city key (never database IDs, so it works on any
 * copy of the site) with occasion keys per venue.
 *
 * Add-only:
 *   - A venue the city already has (same name) is left exactly as it is (admin edits win).
 *   - A venue this importer added once is never added again after an admin deleted it
 *     (option cic_venues_starter_20261121).
 *   - Unknown city keys and occasion keys are reported, never created.
 * Status of new rows: draft (default; never shown on staging/production) or approved (owner choice).
 * Rollback deletes only rows this importer added that are still Draft and unedited.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class VenueImporter {

	public const OPTION = 'cic_venues_starter_20261121';

	public static function data_file(): string {
		return CIC_CORE_DIR . 'data/venues-starter-20261121.json';
	}

	/** @return array{source: string, cities: array<string, array<int, array{0: string, 1: string}>>}|null */
	public static function data(): ?array {
		$raw  = is_readable( self::data_file() ) ? file_get_contents( self::data_file() ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		$data = $raw ? json_decode( $raw, true ) : null;
		return is_array( $data ) && ! empty( $data['cities'] ) && ! empty( $data['source'] ) ? $data : null;
	}

	/** Create the venue table if the v7 migration has not run yet (idempotent). */
	public static function ensure_table(): bool {
		if ( ! VenueStore::exists() ) {
			( new \CIC\Database\Migrations() )->maybe_migrate();
		}
		return VenueStore::exists();
	}

	/**
	 * Import (or preview with $dry) the starter list.
	 *
	 * @return array{added: int, exists: int, removed_before: int, unknown_cities: string[], unknown_occasions: string[], errors: string[], by_city: array<string, int>}
	 */
	public static function run( bool $dry = true, string $status = 'draft' ): array {
		$report = array( 'added' => 0, 'exists' => 0, 'removed_before' => 0, 'unknown_cities' => array(), 'unknown_occasions' => array(), 'errors' => array(), 'by_city' => array() );
		$data   = self::data();
		if ( ! $data ) {
			$report['errors'][] = 'Data file missing or invalid: ' . basename( self::data_file() );
			return $report;
		}
		if ( ! Tables::ready() || ! VenueStore::exists() ) {
			$report['errors'][] = 'The location or venue tables are not installed.';
			return $report;
		}
		$status = in_array( $status, array( 'draft', 'approved' ), true ) ? $status : 'draft';
		$repo   = new DatabaseRepository();
		$known  = array_keys( VenueStore::occasions() );
		$added  = (array) get_option( self::OPTION, array() ); // "city-key|search key" => venue id.
		$bad    = array();

		foreach ( $data['cities'] as $key => $list ) {
			$city = $repo->find_city( (string) $key );
			if ( ! $city ) {
				$report['unknown_cities'][] = (string) $key;
				continue;
			}
			foreach ( array_values( (array) $list ) as $i => $item ) {
				$name   = (string) ( $item[0] ?? '' );
				$wanted = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $item[1] ?? '' ) ) ) ) );
				$mark   = $key . '|' . Tables::search_key( $name );
				foreach ( array_diff( $wanted, $known ) as $b ) {
					$bad[ $b ] = true;
				}
				if ( VenueStore::find_id( (int) $city['id'], $name ) ) {
					++$report['exists'];
					continue;
				}
				if ( isset( $added[ $mark ] ) ) {
					++$report['removed_before'];
					continue;
				}
				if ( ! $dry ) {
					$id = VenueStore::save(
						array(
							'city_id'    => $city['id'],
							'name'       => $name,
							'occasions'  => $wanted,
							'sort_order' => ( $i + 1 ) * 10,
							'status'     => $status,
						),
						0,
						(string) $data['source']
					);
					if ( is_wp_error( $id ) ) {
						$report['errors'][] = $city['label'] . ' — ' . $name . ': ' . $id->get_error_message();
						continue;
					}
					$added[ $mark ] = $id;
				}
				++$report['added'];
				$report['by_city'][ $city['label'] ] = ( $report['by_city'][ $city['label'] ] ?? 0 ) + 1;
			}
		}
		if ( ! $dry ) {
			update_option( self::OPTION, $added, false );
			CityStore::audit( 'venue_import', sprintf( '%s: %d added as %s', $data['source'], $report['added'], $status ) );
		}
		$report['unknown_occasions'] = array_keys( $bad );
		return $report;
	}

	/**
	 * Remove venues this importer added that are still Draft (approved, hidden or other venues stay).
	 *
	 * @return array{removed: int, kept: int}
	 */
	public static function rollback( bool $dry = true ): array {
		$data   = self::data();
		$source = $data ? (string) $data['source'] : '';
		$added  = (array) get_option( self::OPTION, array() );
		$out    = array( 'removed' => 0, 'kept' => 0 );
		foreach ( $added as $mark => $id ) {
			$v = VenueStore::get( (int) $id );
			if ( $v && ( 'draft' !== $v['status'] || $source !== $v['source'] ) ) {
				++$out['kept'];
				continue;
			}
			if ( ! $dry ) {
				if ( $v ) {
					VenueStore::delete( array( (int) $id ) );
				}
				unset( $added[ $mark ] );
			}
			++$out['removed'];
		}
		if ( ! $dry ) {
			update_option( self::OPTION, $added, false );
		}
		return $out;
	}

	/** Purge page caches after an import (SiteGround Speed Optimizer when present). */
	public static function purge_page_cache(): bool {
		if ( function_exists( 'sg_cachepress_purge_everything' ) ) {
			sg_cachepress_purge_everything();
			return true;
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			return true;
		}
		return false;
	}
}
