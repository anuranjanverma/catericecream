<?php
/**
 * Core sitemap provider for location routes (wp-sitemap-locations-1.xml): the state hub plus only
 * approved, indexable canonical city URLs. Drafts, noindex cities, aliases, previews and
 * search-parameter URLs never appear.
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
		if ( 1 !== (int) $page_num ) {
			return array();
		}
		$repo = Locations::repository();
		$urls = array();
		foreach ( Tables::STATES as $slug => $code ) {
			if ( ! $repo->list_counties( $code ) ) {
				continue;
			}
			$urls[] = array( 'loc' => home_url( user_trailingslashit( 'service-areas/' . $slug ) ) );
			if ( $repo instanceof DatabaseRepository ) {
				foreach ( $repo->indexable_urls( $code ) as $url ) {
					$urls[] = array( 'loc' => $url );
				}
			}
		}
		return $urls;
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
