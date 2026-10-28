<?php
/**
 * Core sitemap provider for location routes (wp-sitemap-locations-1.xml; with Yoast SEO the same
 * list is served as cic-locations-sitemap.xml by YoastAdapter): the state hub plus only
 * approved, indexable canonical city URLs. Drafts, noindex cities, cities without an enabled
 * production ZIP, aliases, previews and search-parameter URLs never appear. lastmod comes from the
 * city record and its reviewed content (2026-10-28); the state hub carries the latest city date.
 *
 * @package CIC
 */

namespace CIC\Seo;

use CIC\Locations\DatabaseRepository;
use CIC\Locations\Service as Locations;
use CIC\Locations\Tables;

defined( 'ABSPATH' ) || exit;

final class LocationSitemap extends \WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'locations';
		$this->object_type = 'location';
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		return 1 === (int) $page_num ? self::urls() : array();
	}

	/**
	 * Eligible location URLs (state hubs with listed counties + indexable cities), shared by the
	 * core sitemap and the Yoast SEO sitemap (YoastAdapter). Entries: loc, optional lastmod (W3C).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function urls(): array {
		$repo = Locations::repository();
		$urls = array();
		foreach ( Tables::STATES as $slug => $code ) {
			if ( ! $repo->list_counties( $code ) ) {
				continue;
			}
			$cities = $repo instanceof DatabaseRepository ? $repo->indexable_entries( $code ) : array();
			$latest = $cities ? max( array_map( static fn( $c ) => (string) ( $c['lastmod'] ?? '' ), $cities ) ) : '';
			$urls[] = array_filter(
				array(
					'loc'     => home_url( user_trailingslashit( 'service-areas/' . $slug ) ),
					'lastmod' => $latest,
				)
			);
			foreach ( $cities as $city ) {
				$urls[] = $city;
			}
		}
		return $urls;
	}

	/** Latest lastmod of all entries ('' when none has one). */
	public static function latest(): string {
		$dates = array_filter( array_map( static fn( $u ) => (string) ( $u['lastmod'] ?? '' ), self::urls() ) );
		return $dates ? max( $dates ) : '';
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
