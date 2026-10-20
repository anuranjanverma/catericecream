<?php
/**
 * Location fallback schema constants: table names (configured prefix), allowed statuses, the
 * management capability, the fixture guard and the cache version used for invalidation.
 *
 * Provisional: the owner's city template and rewrite-rule code is still pending. These tables
 * satisfy the spec §9 fallback contract behind the LocationRepository adapter only.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class Tables {

	public const CAPABILITY = 'manage_locations';

	public const ZIP_STATUSES      = array( 'unreviewed', 'enabled', 'disabled', 'manual_review' );
	public const OVERRIDES         = array( 'inherit', 'enabled', 'disabled', 'manual_review' );
	public const PUBLISH_STATUSES  = array( 'draft', 'published' );
	public const INDEX_STATUSES    = array( 'noindex', 'index' );
	public const DATASETS          = array( 'fixture', 'production' );
	public const COVERAGE_STATUSES = array( 'covered', 'outside_area', 'manual_review', 'unknown', 'invalid' );

	/** Supported state hubs: URL slug => state code. */
	public const STATES = array( 'california' => 'CA' );

	/** Cache version option; bumped after every location, coverage, slug or content change. */
	public const CACHE_OPTION = 'cic_locations_cache_version';

	/**
	 * @return array{cities: string, zips: string, city_zips: string, city_content: string, city_slugs: string}
	 */
	public static function all(): array {
		global $wpdb;
		return array(
			'cities'       => $wpdb->prefix . 'cic_cities',
			'zips'         => $wpdb->prefix . 'cic_zips',
			'city_zips'    => $wpdb->prefix . 'cic_city_zips',
			'city_content' => $wpdb->prefix . 'cic_city_content',
			'city_slugs'   => $wpdb->prefix . 'cic_city_slugs',
		);
	}

	public static function name( string $table ): string {
		return self::all()[ $table ];
	}

	/** True when the v3 tables exist (migration ran). */
	public static function ready(): bool {
		return (int) get_option( \CIC\Config::OPTION_SCHEMA_VERSION, 0 ) >= 3;
	}

	/**
	 * Fixture rows are local test data, never approved coverage. They count only on local or
	 * development environments; on staging/production they are ignored (treated as absent).
	 */
	public static function fixtures_allowed(): bool {
		return (bool) apply_filters( 'cic_location_fixtures_allowed', in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) );
	}

	/** SQL fragment restricting rows to usable datasets (alias = table alias). */
	public static function dataset_sql( string $alias ): string {
		return self::fixtures_allowed() ? '1=1' : $alias . ".dataset = 'production'";
	}

	public static function state_code( string $slug ): string {
		return self::STATES[ $slug ] ?? '';
	}

	public static function state_slug( string $code ): string {
		$slug = array_search( $code, self::STATES, true );
		return false === $slug ? '' : $slug;
	}

	public static function state_name( string $code ): string {
		return 'CA' === $code ? __( 'California', 'cater-ice-cream-core' ) : $code;
	}

	/** Lowercase ASCII search key for prefix lookups ("Cañada Flintridge" → "canada flintridge"). */
	public static function search_key( string $name ): string {
		$ascii = remove_accents( $name );
		$ascii = strtolower( preg_replace( '/[^A-Za-z0-9]+/', ' ', $ascii ) );
		return trim( preg_replace( '/\s+/', ' ', $ascii ) );
	}

	public static function cache_version(): int {
		return (int) get_option( self::CACHE_OPTION, 1 );
	}

	/** Invalidate every cached location read (directories, map points, sitemap, lookups). */
	public static function bump_cache(): void {
		update_option( self::CACHE_OPTION, self::cache_version() + 1, true );
		do_action( 'cic_locations_changed' );
	}

	/**
	 * Cached read keyed by the current cache version (old versions simply expire).
	 *
	 * @param callable(): mixed $compute Producer.
	 * @return mixed
	 */
	public static function cached( string $key, callable $compute ) {
		$cache_key = 'cic_loc_' . self::cache_version() . '_' . md5( $key . '|' . ( self::fixtures_allowed() ? 'f' : 'p' ) );
		$value     = get_transient( $cache_key );
		if ( false === $value ) {
			$value = $compute();
			set_transient( $cache_key, $value, 6 * HOUR_IN_SECONDS );
		}
		return $value;
	}

	public static function grant_capabilities(): void {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( self::CAPABILITY );
		}
	}
}
