<?php
/**
 * Core WordPress SEO integration for location routes: document title, meta description,
 * canonical, robots (via wp_robots, so the site-wide "discourage search engines" setting still
 * applies), Open Graph and JSON-LD (BreadcrumbList + the business's Service with its listed area).
 * A listed service area is not an office address: no local branch, phone or review is invented.
 *
 * @package CIC
 */

namespace CIC\Seo;

defined( 'ABSPATH' ) || exit;

final class CoreAdapter implements Adapter {

	/** @var array<string, mixed> */
	private array $context = array();

	public function name(): string {
		return 'WordPress core';
	}

	public function apply( array $context ): void {
		$this->context = $context;
		if ( '' !== ( $context['title'] ?? '' ) ) {
			add_filter( 'pre_get_document_title', array( $this, 'title' ), 20 );
		}
		add_filter( 'wp_robots', array( $this, 'robots' ), 20 );
		add_action( 'wp_head', array( $this, 'head' ), 2 );
		if ( '' !== ( $context['canonical'] ?? '' ) ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}
	}

	public function title(): string {
		return (string) $this->context['title'];
	}

	/**
	 * @param array<string, bool|string> $robots Directives.
	 * @return array<string, bool|string>
	 */
	public function robots( array $robots ): array {
		if ( ! empty( $this->context['noindex'] ) ) {
			unset( $robots['max-image-preview'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	public function head(): void {
		$c = $this->context;
		if ( '' !== ( $c['description'] ?? '' ) ) {
			echo '<meta name="description" content="' . esc_attr( $c['description'] ) . '">' . "\n";
		}
		if ( '' !== ( $c['canonical'] ?? '' ) ) {
			echo '<link rel="canonical" href="' . esc_url( $c['canonical'] ) . '">' . "\n";
		}
		if ( '' !== ( $c['title'] ?? '' ) ) {
			$og = array(
				'og:type'        => 'website',
				'og:site_name'   => get_bloginfo( 'name' ),
				'og:title'       => $c['title'],
				'og:description' => $c['description'] ?? '',
				'og:url'         => $c['canonical'] ?? '',
			);
			if ( ! empty( $c['image'] ) ) {
				$src = wp_get_attachment_image_src( (int) $c['image'], 'large' );
				if ( $src ) {
					$og['og:image'] = $src[0];
				}
			}
			foreach ( $og as $property => $content ) {
				if ( '' !== (string) $content ) {
					echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( (string) $content ) . '">' . "\n";
				}
			}
		}
		$graph = array();
		if ( ! empty( $c['breadcrumbs'] ) ) {
			$items = array();
			foreach ( array_values( $c['breadcrumbs'] ) as $i => $crumb ) {
				$items[] = array_filter(
					array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'name'     => $crumb[0],
						'item'     => '' !== $crumb[1] ? $crumb[1] : ( $c['canonical'] ?? '' ),
					)
				);
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			);
		}
		if ( ! empty( $c['schema'] ) ) {
			$graph[] = $c['schema'];
		}
		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
		}
	}
}
