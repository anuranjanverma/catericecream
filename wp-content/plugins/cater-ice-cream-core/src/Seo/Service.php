<?php
/**
 * Chooses the single SEO adapter and feeds it the location context:
 * - city:  title/description from city content (inheriting the city template), canonical virtual
 *          URL, breadcrumbs, Service schema; noindex unless published + Index + reviewed content;
 *          previews and any query-string variant are noindex with the clean canonical.
 * - state: state hub, indexable; query-string variants (county filter, pagination) are noindex.
 * - hub:   /service-areas/ Page keeps core canonical; query-string variants are noindex.
 * - forms: Get a Quote / Contact with any query string (prefills, receipts) and the WooCommerce
 *          cart/checkout/account pages are noindex; those Woo pages and the shop page (listed as the
 *          product archive) stay out of Yoast's page sitemap (2026-10-28).
 * - event: event pages (2026-10-22): search title/description fields (else the excerpt), canonical,
 *          breadcrumbs and a Service schema with the central business details. Robots follow core.
 * Location sitemaps are registered through core (wp-sitemap-locations-1.xml).
 *
 * If a known SEO plugin is active, the core adapter stands down (no competing tags). Yoast SEO has a
 * built-in adapter (YoastAdapter, 2026-10-26) that feeds the same context into Yoast's own output;
 * any other SEO plugin needs one (filter cic_seo_adapter), which Site Health reports.
 *
 * Environment protection: every non-production site (local, development, staging) is noindex,
 * nofollow whatever the Reading setting says (core wp_robots and Yoast robots), so a copied
 * production database cannot make a staging site indexable. Production follows the Reading setting
 * ("Discourage search engines" off) and the per-page rules above.
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
		add_filter( 'wp_robots', array( $this, 'protect_core' ), 99 );
		add_filter( 'wpseo_robots_array', array( $this, 'protect_yoast' ), 99 );
		add_action( 'template_redirect', array( $this, 'apply' ), 5 );
		add_filter( 'site_status_tests', array( $this, 'health_test' ) );
		// Yoast SEO (2026-10-28): utility pages out of the sitemaps, one business identity, and no
		// SearchAction (the site has no search feature; search results are noindex).
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( $this, 'sitemap_exclusions' ) );
		add_filter( 'wpseo_schema_organization', array( $this, 'organization' ) );
		add_filter( 'disable_wpseo_json_ld_search', '__return_true' );
	}

	/**
	 * WooCommerce utility pages (cart, checkout, account; redirected in catalogue mode) and the shop
	 * page (already listed as the product archive) never go into the page sitemap.
	 *
	 * @return array<int, int>
	 */
	public static function utility_page_ids(): array {
		$ids = array();
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
				$ids[] = (int) wc_get_page_id( $page );
			}
		}
		return array_values( array_filter( $ids, static fn( $id ) => $id > 0 ) );
	}

	/**
	 * @param array<int, int>|mixed $ids Post IDs Yoast leaves out of its sitemaps.
	 * @return array<int, int>
	 */
	public function sitemap_exclusions( $ids ): array {
		$ids   = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		$ids   = array_merge( $ids, self::utility_page_ids() );
		$ids[] = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		return array_values( array_unique( array_filter( $ids, static fn( $id ) => $id > 0 ) ) );
	}

	/**
	 * Yoast's Organization piece takes its name, legal name and phone from Header, Footer & Contact,
	 * so the schema never drifts from the visible business details (no address or rating is added).
	 *
	 * @param array<string, mixed>|mixed $data Organization piece.
	 * @return array<string, mixed>
	 */
	public function organization( $data ): array {
		$data     = is_array( $data ) ? $data : array();
		$provider = ViewModel::provider();
		foreach ( array( 'name', 'legalName', 'telephone' ) as $key ) {
			if ( ! empty( $provider[ $key ] ) ) {
				$data[ $key ] = $provider[ $key ];
			}
		}
		return $data;
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
		$plugin = self::seo_plugin();
		if ( '' === $plugin ) {
			return new CoreAdapter();
		}
		return 'Yoast SEO' === $plugin ? new YoastAdapter() : null;
	}

	public function sitemaps(): void {
		$adapter = self::adapter();
		if ( null === $adapter || ! Locations::repository()->is_connected() ) {
			return;
		}
		if ( $adapter instanceof YoastAdapter ) {
			YoastAdapter::register_sitemap();
		} elseif ( function_exists( 'wp_register_sitemap_provider' ) ) {
			wp_register_sitemap_provider( 'locations', new LocationSitemap() );
		}
	}

	/** Non-production sites are never indexable (tests may filter cic_seo_protect to simulate production). */
	public static function protected_environment(): bool {
		return (bool) apply_filters( 'cic_seo_protect', 'production' !== wp_get_environment_type() );
	}

	/**
	 * @param array<string, bool|string> $robots Core robots directives.
	 * @return array<string, bool|string>
	 */
	public function protect_core( $robots ): array {
		$robots = is_array( $robots ) ? $robots : array();
		if ( self::protected_environment() ) {
			unset( $robots['index'], $robots['follow'], $robots['max-image-preview'] );
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * @param array<string, string> $robots Yoast robots directives.
	 * @return array<string, string>
	 */
	public function protect_yoast( $robots ): array {
		$robots = is_array( $robots ) ? $robots : array();
		if ( self::protected_environment() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'nofollow';
		}
		return $robots;
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
		if ( null === $current && is_singular( \CIC\Content\EventType::POST_TYPE ) ) {
			return ViewModel::event( (int) get_queried_object_id() )['seo'];
		}
		if ( null === $current ) {
			if ( $hub_id && is_page( $hub_id ) && self::has_query() ) {
				return array( 'noindex' => true );
			}
			// Quote/contact variants (prefilled selections, receipts) and Woo utility pages are never
			// indexable; the clean Get a Quote and Contact pages follow core/Yoast (2026-10-28).
			$forms = array_filter( array( Catalog::find_by_key( 'page', 'get-a-quote' ), Catalog::find_by_key( 'page', 'contact' ) ) );
			if ( ( $forms && is_page( $forms ) && self::has_query() ) || ( self::utility_page_ids() && is_page( self::utility_page_ids() ) ) ) {
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
		// State hub: its own title and canonical (/service-areas/{state}/); county filter and
		// pagination variants are noindex below. (Before 2026-10-26 the hub page's values were used.)
		$vm             = ViewModel::hub( $hub_id, $current['state'], 'state' );
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
				$ok     = null !== self::adapter();
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
