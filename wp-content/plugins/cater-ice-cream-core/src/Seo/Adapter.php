<?php
/**
 * SEO adapter boundary (BUILD_SPECIFICATION §11): exactly one adapter writes metadata for the
 * location routes, so theme and plugin never print competing tags. CoreAdapter integrates with
 * WordPress core (no SEO plugin is installed); an installed SEO plugin needs its own adapter.
 *
 * @package CIC
 */

namespace CIC\Seo;

defined( 'ABSPATH' ) || exit;

interface Adapter {

	/** Human-readable adapter name (Site Health / docs). */
	public function name(): string;

	/**
	 * Apply metadata for the current request context.
	 *
	 * @param array<string, mixed> $context title, description, canonical, noindex, image, breadcrumbs, schema.
	 */
	public function apply( array $context ): void;
}
