<?php
/**
 * Virtual state and city routes (BUILD_SPECIFICATION §3, §10):
 *   /service-areas/{state}/          state hub (database, location renderer)
 *   /service-areas/{state}/{city}-ice-cream-catering/   city page (DatabaseRepository::URL_SUFFIX;
 *                                    the bare /{city}/ address 301s there)
 * /service-areas/ itself is a normal Page (the hub).
 *
 * Explicit rewrite rules, flushed only when ROUTE_VERSION changes (activation or a versioned
 * route change), never on normal requests. Unknown states/slugs and unpublished cities return a
 * real 404; recorded aliases and previous slugs redirect once (301) to the canonical URL.
 * Authenticated managers can preview a draft city through its editorial record.
 * No city × event × treat permutation routes exist.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class Routes {

	public const ROUTE_VERSION = '1';
	public const VERSION_OPTION = 'cic_location_routes_version';

	/** @var array<string, mixed>|null Resolved request: type state|city, state, city, preview. */
	private static ?array $current = null;

	public function register(): void {
		add_action( 'init', array( $this, 'rules' ) );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'parse_query', array( $this, 'parse_query' ) );
		add_filter( 'posts_pre_query', array( $this, 'skip_main_query' ), 10, 2 );
		add_filter( 'pre_handle_404', array( $this, 'pre_handle_404' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'resolve' ), 1 );
		add_filter( 'template_include', array( $this, 'template' ), 20 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	public function rules(): void {
		add_rewrite_rule( '^service-areas/([^/]+)/?$', 'index.php?cic_state=$matches[1]', 'top' );
		add_rewrite_rule( '^service-areas/([^/]+)/([^/]+)/?$', 'index.php?cic_state=$matches[1]&cic_city=$matches[2]', 'top' );
	}

	/** One-time flush per route version (never on every request). */
	public function maybe_flush(): void {
		if ( self::ROUTE_VERSION !== get_option( self::VERSION_OPTION ) ) {
			flush_rewrite_rules( false );
			update_option( self::VERSION_OPTION, self::ROUTE_VERSION, true );
		}
	}

	/** @param string[] $vars Vars. */
	public function query_vars( array $vars ): array {
		$vars[] = 'cic_state';
		$vars[] = 'cic_city';
		return $vars;
	}

	private static function is_location_query( \WP_Query $q ): bool {
		return $q->is_main_query() && '' !== (string) $q->get( 'cic_state' );
	}

	/** A virtual route is neither the blog home nor a post listing. */
	public function parse_query( \WP_Query $q ): void {
		if ( ! self::is_location_query( $q ) ) {
			return;
		}
		$q->is_home       = false;
		$q->is_archive    = false;
		$q->is_singular   = false;
		$q->is_page       = false;
		$q->is_posts_page = false;
	}

	/**
	 * No posts query for virtual routes.
	 *
	 * @param array<int, mixed>|null $posts Posts.
	 * @return array<int, mixed>|null
	 */
	public function skip_main_query( $posts, \WP_Query $q ) {
		return self::is_location_query( $q ) ? array() : $posts;
	}

	/** Valid virtual routes are not 404s; invalid ones are set to 404 explicitly in resolve(). */
	public function pre_handle_404( bool $preempt, \WP_Query $query ): bool {
		return '' !== (string) $query->get( 'cic_state' ) ? true : $preempt;
	}

	public function resolve(): void {
		$state_slug = (string) get_query_var( 'cic_state' );
		if ( '' === $state_slug ) {
			return;
		}
		$city_slug = (string) get_query_var( 'cic_city' );
		$repo      = Service::repository();
		$lower     = strtolower( rawurldecode( $state_slug ) );

		if ( '' === Tables::state_code( $lower ) ) {
			$this->not_found();
			return;
		}
		if ( '' === $city_slug ) {
			$this->canonical_redirect( home_url( user_trailingslashit( 'service-areas/' . $lower ) ), $lower !== $state_slug );
			self::$current = array(
				'type'  => 'state',
				'state' => Tables::state_code( $lower ),
				'slug'  => $lower,
			);
			return;
		}

		$preview = Editorial::preview_request();
		$slug    = strtolower( rawurldecode( $city_slug ) );
		// City-level preview (no content record yet) is flagged as -1: preview banner + noindex, template content only.
		$city_preview = $preview ? 0 : Editorial::preview_city_request();
		$unpublished  = $preview > 0 || $city_preview > 0;
		$city         = null;
		// Public addresses end in DatabaseRepository::URL_SUFFIX; the bare slug (old address) still
		// resolves and is 301'd to the suffixed canonical by canonical_redirect() below.
		$suffix = DatabaseRepository::URL_SUFFIX;
		if ( strlen( $slug ) > strlen( $suffix ) && str_ends_with( $slug, $suffix ) ) {
			$city = $repo->resolve_city( $lower, substr( $slug, 0, -strlen( $suffix ) ), $unpublished );
		}
		if ( ! $city ) {
			$city = $repo->resolve_city( $lower, $slug, $unpublished );
		}
		if ( ! $city ) {
			$this->not_found();
			return;
		}
		if ( $preview && CityStore::city_for_editorial( $preview ) !== (int) $city['id'] ) {
			$preview = 0;
			if ( 'published' !== $city['publish_status'] ) {
				$this->not_found();
				return;
			}
		}
		if ( $city_preview ) {
			if ( $city_preview === (int) $city['id'] ) {
				$preview = -1;
			} elseif ( 'published' !== $city['publish_status'] ) {
				$this->not_found();
				return;
			}
		}
		$canonical = DatabaseRepository::url( $city['state_slug'], $city['slug'] );
		if ( ! $preview ) {
			$this->canonical_redirect( $canonical, ! empty( $city['redirect'] ) || $slug !== $city_slug || $lower !== $state_slug );
		}
		if ( $preview ) {
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
		self::$current = array(
			'type'    => 'city',
			'state'   => $city['state_code'],
			'slug'    => $lower,
			'city'    => $city,
			'preview' => $preview,
		);
	}

	/**
	 * One-hop 301 to the canonical URL for aliases/old slugs/case, or a missing trailing slash.
	 * Query strings are kept only for preview (handled above) — other parameters stay on the
	 * page, which is then noindex with a canonical to the clean URL (see SEO adapter).
	 */
	private function canonical_redirect( string $canonical, bool $force ): void {
		$path      = (string) wp_parse_url( $canonical, PHP_URL_PATH );
		$requested = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : $path;
		if ( $force || $requested !== $path ) {
			// wp_safe_redirect() sanitises the final location; percent-encoding is preserved.
			$query = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			wp_safe_redirect( $canonical . $query, 301, 'Cater Ice Cream locations' );
			exit;
		}
	}

	private function not_found(): void {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		self::$current = null;
	}

	/** @return array<string, mixed>|null */
	public static function current(): ?array {
		return self::$current;
	}

	public function template( string $template ): string {
		if ( null === self::$current ) {
			return $template;
		}
		$file  = 'city' === self::$current['type'] ? 'location-city.php' : 'location-hub.php';
		$found = locate_template( $file );
		return '' !== $found ? $found : $template;
	}

	/** @param string[] $classes Classes. */
	public function body_class( array $classes ): array {
		if ( null !== self::$current ) {
			$classes   = array_diff( $classes, array( 'home', 'blog', 'error404' ) );
			$classes[] = 'cic-location';
			$classes[] = 'cic-location--' . self::$current['type'];
		}
		return $classes;
	}
}
