<?php
/**
 * Yoast SEO integration (2026-10-26): while Yoast SEO is active it owns the head (title, robots,
 * canonical, Open Graph, Twitter, schema graph, sitemaps), so the location and event context is fed
 * INTO Yoast through its filters instead of printing a second set of tags or a second JSON-LD graph.
 *
 * - Location routes (state hub, city): Yoast has no post for these virtual URLs, so it would print
 *   only the site name. Title, description, canonical, robots (noindex unless published + Index +
 *   reviewed; previews and query variants noindex), Open Graph/Twitter text, og:image, and a WebPage,
 *   BreadcrumbList and Service piece in Yoast's own graph come from the context.
 * - Events: Yoast handles the post; the CIC search title/description (cic_seo_*) fill in only where
 *   no Yoast per-post value is set, and the Service piece is added to the graph. Yoast's canonical,
 *   robots and breadcrumbs for the post are kept.
 * - Sitemap: eligible location URLs are served as cic-locations-sitemap.xml and listed in Yoast's
 *   sitemap index (core sitemaps are switched off by Yoast), with lastmod from the city content.
 * - Graph (2026-10-28): the Service piece points at Yoast's #organization when Site representation
 *   is set (else an inline provider) and at its WebPage; event WebPage name/description match the
 *   title and description supplied here.
 * Yoast leaves out the canonical on noindex pages by design.
 *
 * @package CIC
 */

namespace CIC\Seo;

defined( 'ABSPATH' ) || exit;

final class YoastAdapter implements Adapter {

	public const SITEMAP = 'cic-locations';

	/** @var array<string, mixed> */
	private array $context = array();

	private int $post_id = 0;

	public function name(): string {
		return 'Yoast SEO';
	}

	public function apply( array $context ): void {
		$this->context = $context;
		$this->post_id = is_singular() ? (int) get_queried_object_id() : 0;
		$c             = $context;

		if ( '' !== (string) ( $c['title'] ?? '' ) && ! $this->own( 'title' ) ) {
			foreach ( array( 'wpseo_title', 'wpseo_opengraph_title', 'wpseo_twitter_title' ) as $filter ) {
				add_filter( $filter, array( $this, 'title' ), 20 );
			}
		}
		if ( '' !== (string) ( $c['description'] ?? '' ) && ! $this->own( 'metadesc' ) ) {
			foreach ( array( 'wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description' ) as $filter ) {
				add_filter( $filter, array( $this, 'description' ), 20 );
			}
		}
		if ( ! $this->post_id ) {
			if ( '' !== (string) ( $c['canonical'] ?? '' ) ) {
				add_filter( 'wpseo_canonical', array( $this, 'canonical' ), 20 );
				add_filter( 'wpseo_opengraph_url', array( $this, 'canonical' ), 20 );
			}
			if ( ! empty( $c['image'] ) ) {
				add_action( 'wpseo_add_opengraph_images', array( $this, 'images' ) );
			}
		}
		if ( ! empty( $c['noindex'] ) ) {
			add_filter( 'wpseo_robots_array', array( $this, 'robots' ), 20 );
		}
		add_filter( 'wpseo_schema_graph', array( $this, 'graph' ), 20 );
	}

	/** A Yoast per-post value (admin override) is set for this field. */
	private function own( string $field ): bool {
		return $this->post_id && '' !== trim( (string) get_post_meta( $this->post_id, '_yoast_wpseo_' . $field, true ) );
	}

	public function title(): string {
		return (string) $this->context['title'];
	}

	public function description(): string {
		return (string) $this->context['description'];
	}

	public function canonical(): string {
		return (string) $this->context['canonical'];
	}

	/**
	 * @param array<string, string> $robots Yoast robots directives.
	 * @return array<string, string>
	 */
	public function robots( $robots ): array {
		$robots           = is_array( $robots ) ? $robots : array();
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
		return $robots;
	}

	/** @param object $container Yoast Open Graph image container. */
	public function images( $container ): void {
		if ( is_object( $container ) && method_exists( $container, 'add_image_by_id' ) ) {
			$container->add_image_by_id( (int) $this->context['image'] );
		}
	}

	/**
	 * Adds the page's pieces to Yoast's single graph (never a second JSON-LD block).
	 *
	 * @param array<int, array<string, mixed>> $graph Yoast graph pieces.
	 * @return array<int, array<string, mixed>>
	 */
	public function graph( $graph ): array {
		$graph     = is_array( $graph ) ? $graph : array();
		$c         = $this->context;
		$canonical = (string) ( $c['canonical'] ?? '' );
		if ( '' === $canonical ) {
			return $graph;
		}
		$types   = array();
		$ids     = array();
		$webpage = $this->post_id ? $canonical : $canonical . '#webpage';
		foreach ( $graph as $i => $piece ) {
			foreach ( (array) ( $piece['@type'] ?? array() ) as $type ) {
				$types[ $type ] = true;
			}
			$ids[ (string) ( $piece['@id'] ?? '' ) ] = true;
			// Event pages: Yoast names its WebPage from its own title template; keep the name and
			// description identical to the <title> and meta description this adapter supplied.
			if ( $this->post_id && $canonical === (string) ( $piece['@id'] ?? '' ) && in_array( 'WebPage', (array) ( $piece['@type'] ?? array() ), true ) ) {
				if ( '' !== (string) ( $c['title'] ?? '' ) && ! $this->own( 'title' ) ) {
					$graph[ $i ]['name'] = (string) $c['title'];
				}
				if ( '' !== (string) ( $c['description'] ?? '' ) && ! $this->own( 'metadesc' ) ) {
					$graph[ $i ]['description'] = (string) $c['description'];
				}
			}
		}
		if ( ! $this->post_id ) {
			if ( empty( $types['WebPage'] ) ) {
				$graph[] = array_filter(
					array(
						'@type'       => 'WebPage',
						'@id'         => $canonical . '#webpage',
						'url'         => $canonical,
						'name'        => (string) ( $c['title'] ?? '' ),
						'description' => (string) ( $c['description'] ?? '' ),
						'isPartOf'    => array( '@id' => home_url( '/' ) . '#website' ),
						'breadcrumb'  => ! empty( $c['breadcrumbs'] ) ? array( '@id' => $canonical . '#breadcrumb' ) : null,
						'inLanguage'  => get_bloginfo( 'language' ),
					)
				);
			}
			if ( ! empty( $c['breadcrumbs'] ) && empty( $types['BreadcrumbList'] ) ) {
				$items = array();
				foreach ( array_values( $c['breadcrumbs'] ) as $i => $crumb ) {
					$items[] = array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'name'     => (string) $crumb[0],
						'item'     => '' !== (string) $crumb[1] ? (string) $crumb[1] : $canonical,
					);
				}
				$graph[] = array(
					'@type'           => 'BreadcrumbList',
					'@id'             => $canonical . '#breadcrumb',
					'itemListElement' => $items,
				);
			}
		}
		if ( ! empty( $c['schema'] ) && empty( $types['Service'] ) ) {
			$service = array_merge( array( '@id' => $canonical . '#service' ), $c['schema'] );
			// One business identity: point at Yoast's Organization when it is in the graph
			// (Site representation set), otherwise keep the inline provider.
			$org = home_url( '/' ) . '#organization';
			if ( isset( $ids[ $org ] ) ) {
				$service['provider'] = array( '@id' => $org );
			}
			$service['mainEntityOfPage'] = array( '@id' => $webpage );
			$graph[]                     = $service;
		}
		return $graph;
	}

	/* ------------------------------------------------------------------ *
	 * Sitemap
	 * ------------------------------------------------------------------ */

	/** Registers cic-locations-sitemap.xml and its index entry (call on init). */
	public static function register_sitemap(): void {
		if ( empty( $GLOBALS['wpseo_sitemaps'] ) || ! is_object( $GLOBALS['wpseo_sitemaps'] ) ) {
			return;
		}
		$GLOBALS['wpseo_sitemaps']->register_sitemap( self::SITEMAP, array( self::class, 'build_sitemap' ) );
		add_filter( 'wpseo_sitemap_index', array( self::class, 'index_entry' ) );
	}

	public static function index_entry( $xml ): string {
		if ( ! LocationSitemap::urls() ) {
			return (string) $xml;
		}
		$latest = LocationSitemap::latest();
		return (string) $xml . "\t<sitemap>\n\t\t<loc>" . esc_url( home_url( '/' . self::SITEMAP . '-sitemap.xml' ) ) . "</loc>\n"
			. ( '' !== $latest ? "\t\t<lastmod>" . esc_html( $latest ) . "</lastmod>\n" : '' )
			. "\t</sitemap>\n";
	}

	public static function build_sitemap(): void {
		$sitemaps = $GLOBALS['wpseo_sitemaps'];
		// Yoast's renderer reads the modification date from "mod".
		$links = array_map(
			static fn( $u ) => array_filter( array( 'loc' => $u['loc'], 'mod' => $u['lastmod'] ?? '' ) ),
			LocationSitemap::urls()
		);
		if ( ! $links ) {
			$sitemaps->set_bad_sitemap( true );
			return;
		}
		$sitemaps->set_sitemap( $sitemaps->renderer->get_sitemap( $links, self::SITEMAP, 1 ) );
	}
}
