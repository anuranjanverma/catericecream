<?php
/**
 * Chooses the single SEO adapter and feeds it the location context:
 * - city:  title/description from city content (inheriting the city template), canonical virtual
 *          URL, breadcrumbs, Service schema; noindex unless published + Index + reviewed content;
 *          previews and any query-string variant are noindex with the clean canonical.
 * - state: state hub, indexable; query-string variants (county filter, pagination) are noindex.
 * - hub:   /service-areas/ Page keeps core canonical; query-string variants are noindex.
 * Location sitemaps are registered through core (wp-sitemap-locations-1.xml).
 *
 * If a known SEO plugin is active, the core adapter stands down (no competing tags) and Site
 * Health reports that a plugin adapter is required (see BUILD_STATUS.md).
 *
 * @package CIC
 */

namespace CIC\Seo;

use CIC\Content\Catalog;
use CIC\Content\ViewModel;
use CIC\Locations\Routes;
use CIC\Locations\Service as Locations;

defined( 'ABSPATH' ) || exit;

final class Service {

	public function register(): void {
		add_action( 'init', array( $this, 'sitemaps' ) );
		add_action( 'template_redirect', array( $this, 'apply' ), 5 );
		add_filter( 'site_status_tests', array( $this, 'health_test' ) );
	}

	/** Detected SEO plugin name, or ''. */
	public static function seo_plugin(): string {
		$known = array(
			'WPSEO_VERSION'      => 'Yoast SEO',
			'RANK_MATH_VERSION'  => 'Rank Math',
			'SEOPRESS_VERSION'   => 'SEOPress',
			'AIOSEO_VERSION'     => 'All in One SEO',
			'THE_SEO_FRAMEWORK_VERSION' => 'The SEO Framework',
		);
		foreach ( $known as $constant => $name ) {
			if ( defined( $constant ) ) {
				return $name;
			}
		}
		return '';
	}

	public static function adapter(): ?Adapter {
		$adapter = apply_filters( 'cic_seo_adapter', null );
		if ( $adapter instanceof Adapter ) {
			return $adapter;
		}
		return '' === self::seo_plugin() ? new CoreAdapter() : null;
	}

	public function sitemaps(): void {
		if ( function_exists( 'wp_register_sitemap_provider' ) && null !== self::adapter() && ( Locations::repository()->is_connected() ) ) {
			wp_register_sitemap_provider( 'locations', new LocationSitemap() );
		}
	}

	private static function has_query(): bool {
		return ! empty( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only.
	}

	public function apply(): void {
		$adapter = self::adapter();
		if ( null === $adapter ) {
			return;
		}
		$context = self::context();
		if ( $context ) {
			$adapter->apply( $context );
		}
	}

	/**
	 * Context for the current request, or [] when this is not a location page.
	 *
	 * @return array<string, mixed>
	 */
	public static function context(): array {
		$current = Routes::current();
		$hub_id  = Catalog::find_by_key( 'page', 'service-areas' );
		if ( null === $current ) {
			if ( $hub_id && is_page( $hub_id ) && self::has_query() ) {
				return array( 'noindex' => true );
			}
			return array();
		}
		if ( 'city' === $current['type'] ) {
			$vm   = ViewModel::city( $current['city'], (int) $current['preview'] );
			$seo  = $vm['seo'];
			$repo = Locations::repository();
			$seo['noindex'] = $current['preview'] || self::has_query() || ! ( $repo instanceof \CIC\Locations\DatabaseRepository && $repo->is_indexable( $current['city'] ) );
			return $seo;
		}
		$vm             = ViewModel::hub( $hub_id, $current['state'] );
		$seo            = $vm['seo'];
		$seo['noindex'] = self::has_query();
		return $seo;
	}

	/**
	 * @param array<string, array<string, mixed>> $tests Tests.
	 * @return array<string, array<string, mixed>>
	 */
	public function health_test( array $tests ): array {
		$tests['direct']['cic_seo_adapter'] = array(
			'label' => __( 'Cater Ice Cream SEO adapter', 'cater-ice-cream-core' ),
			'test'  => static function () {
				$plugin = self::seo_plugin();
				$ok     = '' === $plugin || ( apply_filters( 'cic_seo_adapter', null ) instanceof Adapter );
				return array(
					'label'       => $ok ? __( 'One SEO adapter owns location metadata', 'cater-ice-cream-core' ) : __( 'An SEO plugin is active without a Cater Ice Cream adapter', 'cater-ice-cream-core' ),
					'status'      => $ok ? 'good' : 'recommended',
					'badge'       => array( 'label' => __( 'SEO', 'cater-ice-cream-core' ), 'color' => $ok ? 'blue' : 'orange' ),
					/* translators: %s: plugin name. */
					'description' => '<p>' . esc_html( $ok ? __( 'City and service-area pages get their title, canonical, robots, breadcrumbs and sitemap entries from a single adapter.', 'cater-ice-cream-core' ) : sprintf( __( '%s is active, so the core adapter stopped printing location metadata to avoid duplicate tags. Add an adapter for it (filter cic_seo_adapter).', 'cater-ice-cream-core' ), $plugin ) ) . '</p>',
					'test'        => 'cic_seo_adapter',
				);
			},
		);
		return $tests;
	}
}
