<?php
/**
 * Readable admin labels and explanations for the internal location statuses. Labels only: the
 * stored values and the rules in Coverage stay unchanged.
 *
 * Four separate things are labelled distinctly:
 * - ZIP coverage (cic_zips.service_status): one value per ZIP, shared by every linked city.
 * - City decision (cic_city_zips.service_override): this city-ZIP relationship only.
 * - Page publication (publish_status) and search-engine indexing (index_status).
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class Labels {

	/** @return array<string, array{0: string, 1: string}> status => [label, explanation] */
	public static function zip_statuses(): array {
		return array(
			'unreviewed'    => array( __( 'Not reviewed yet', 'cater-ice-cream-core' ), __( 'No decision yet. Visitors are never told this ZIP is served; they are asked for details.', 'cater-ice-cream-core' ) ),
			'enabled'       => array( __( 'Served', 'cater-ice-cream-core' ), __( 'We serve this ZIP, unless a city decision excludes it for that city.', 'cater-ice-cream-core' ) ),
			'disabled'      => array( __( 'Not served (exclusion)', 'cater-ice-cream-core' ), __( 'Always wins: no city decision can re-enable this ZIP.', 'cater-ice-cream-core' ) ),
			'manual_review' => array( __( 'Check each request', 'cater-ice-cream-core' ), __( 'Visitors are asked for the venue details and the team confirms service.', 'cater-ice-cream-core' ) ),
		);
	}

	/** @return array<string, array{0: string, 1: string}> */
	public static function overrides(): array {
		return array(
			'inherit'       => array( __( 'Use ZIP coverage', 'cater-ice-cream-core' ), __( 'This city follows the ZIP coverage above.', 'cater-ice-cream-core' ) ),
			'enabled'       => array( __( 'Served in this city', 'cater-ice-cream-core' ), __( 'Served for this city, unless the ZIP itself is excluded.', 'cater-ice-cream-core' ) ),
			'disabled'      => array( __( 'Not served in this city (exclusion)', 'cater-ice-cream-core' ), __( 'Excluded for this city; other cities sharing the ZIP keep their own decision.', 'cater-ice-cream-core' ) ),
			'manual_review' => array( __( 'Check each request in this city', 'cater-ice-cream-core' ), __( 'The team confirms service for this city.', 'cater-ice-cream-core' ) ),
		);
	}

	/** Effective per-city result (Coverage::effective) → label. */
	public static function effective( string $value ): string {
		$map = array(
			'enabled'       => __( 'Served', 'cater-ice-cream-core' ),
			'disabled'      => __( 'Not served', 'cater-ice-cream-core' ),
			'manual_review' => __( 'Needs checking', 'cater-ice-cream-core' ),
			'unreviewed'    => __( 'Not confirmed', 'cater-ice-cream-core' ),
		);
		return $map[ $value ] ?? $map['unreviewed'];
	}

	/** Public coverage result (Coverage::result status) → label. */
	public static function coverage( string $status ): string {
		$map = array(
			'covered'       => __( 'Covered', 'cater-ice-cream-core' ),
			'outside_area'  => __( 'Outside service area', 'cater-ice-cream-core' ),
			'manual_review' => __( 'Needs clarification', 'cater-ice-cream-core' ),
			'unknown'       => __( 'Not confirmed', 'cater-ice-cream-core' ),
			'invalid'       => __( 'Invalid', 'cater-ice-cream-core' ),
		);
		return $map[ $status ] ?? $map['unknown'];
	}

	public static function publish( string $status ): string {
		return 'published' === $status ? __( 'Published', 'cater-ice-cream-core' ) : __( 'Draft (not public)', 'cater-ice-cream-core' );
	}

	public static function index( bool $indexable, string $setting ): string {
		if ( $indexable ) {
			return __( 'Indexable', 'cater-ice-cream-core' );
		}
		return 'index' === $setting ? __( 'Noindex (requirements not met yet)', 'cater-ice-cream-core' ) : __( 'Noindex', 'cater-ice-cream-core' );
	}

	public static function dataset( string $dataset ): string {
		return 'fixture' === $dataset ? __( 'Fixture (local test data)', 'cater-ice-cream-core' ) : __( 'Production', 'cater-ice-cream-core' );
	}
}
