<?php
/**
 * Catalogue identity and routing for treats (WooCommerce products) and events.
 *
 * - Identity meta with REST schemas: cic_key (stable, unique), cic_catalog_role, cic_catalog_active,
 *   cic_brand_type, cic_preset.
 * - Routes: shop page /treats/, products /treats/{slug}/; legacy /product/… and /shop/ redirect
 *   permanently; brand/category/tag archives do not compete with the product destinations.
 * - Public selection lists (future quote controls) contain published records only.
 *
 * @package CIC
 */

namespace CIC\Content;

use CIC\Design\Schema;

defined( 'ABSPATH' ) || exit;

final class Catalog {

	public const SHOP_SLUG    = 'treats';
	public const PRODUCT_BASE = '/treats';
	public const NONCE        = 'cic_identity_nonce';

	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_identity_box' ) );
		add_action( 'save_post', array( $this, 'save_identity' ), 5, 2 );
		add_filter( 'cic_design_page_context', array( $this, 'page_context' ), 5 );
		add_action( 'template_redirect', array( $this, 'redirects' ), 1 );
		add_action( 'woocommerce_product_query', array( $this, 'hide_inactive' ) );
		add_filter( 'wp_robots', array( $this, 'noindex_inactive' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Identity meta
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<string, array<string, mixed>> meta key => args
	 */
	public static function meta_definitions(): array {
		$presets = array_keys( Schema::contexts() );
		$auth    = static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id );
		return array(
			'cic_key'            => array(
				'types'  => array( 'product', EventType::POST_TYPE ),
				'type'   => 'string',
				'desc'   => 'Stable catalogue key (lowercase letters, digits and hyphens). Set once.',
				'schema' => array( 'type' => 'string', 'pattern' => '^[a-z0-9]+(-[a-z0-9]+)*$', 'maxLength' => 60 ),
				'clean'  => static fn( $v ) => substr( sanitize_title( (string) $v ), 0, 60 ),
				'auth'   => $auth,
			),
			'cic_preset'         => array(
				'types'  => array( 'product', EventType::POST_TYPE ),
				'type'   => 'string',
				'desc'   => 'Design System preset used for this page.',
				'schema' => array( 'type' => 'string', 'enum' => array_merge( array( '' ), $presets ) ),
				'clean'  => static fn( $v ) => in_array( (string) $v, $presets, true ) ? (string) $v : '',
				'auth'   => $auth,
			),
			'cic_catalog_role'   => array(
				'types'  => array( 'product' ),
				'type'   => 'string',
				'desc'   => 'Catalogue role. "showcase" products are never purchasable in this release.',
				'schema' => array( 'type' => 'string', 'enum' => array( 'showcase' ) ),
				'clean'  => static fn( $v ) => 'showcase',
				'auth'   => $auth,
			),
			'cic_catalog_active' => array(
				'types'  => array( 'product' ),
				'type'   => 'string',
				'desc'   => '"1" = shown in the catalogue and selectable in quote controls (when published); "0" = hidden.',
				'schema' => array( 'type' => 'string', 'enum' => array( '1', '0' ) ),
				'clean'  => static fn( $v ) => rest_sanitize_boolean( $v ) ? '1' : '0',
				'auth'   => $auth,
			),
			'cic_brand_type'     => array(
				'types'  => array( 'product' ),
				'type'   => 'string',
				'desc'   => 'brand = named supplier brand (uses the WooCommerce Brands taxonomy); generic = a treat type with no supplier brand.',
				'schema' => array( 'type' => 'string', 'enum' => array( 'brand', 'generic' ) ),
				'clean'  => static fn( $v ) => 'brand' === $v ? 'brand' : 'generic',
				'auth'   => $auth,
			),
		);
	}

	public function register_meta(): void {
		foreach ( self::meta_definitions() as $key => $def ) {
			foreach ( $def['types'] as $type ) {
				$args = array(
					'type'              => $def['type'],
					'description'       => $def['desc'],
					'single'            => true,
					'sanitize_callback' => $def['clean'],
					'auth_callback'     => $def['auth'],
					'show_in_rest'      => array( 'schema' => $def['schema'] ),
					'revisions_enabled' => EventType::POST_TYPE === $type,
				);
				// A registered default must satisfy the schema, so only set valid ones.
				if ( 'cic_catalog_active' === $key ) {
					$args['default'] = '1';
				} elseif ( 'cic_preset' === $key ) {
					$args['default'] = '';
				}
				register_post_meta( $type, $key, $args );
			}
		}
	}

	public static function key( int $post_id ): string {
		return (string) get_post_meta( $post_id, 'cic_key', true );
	}

	public static function find_by_key( string $post_type, string $key ): int {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'meta_key'         => 'cic_key', // phpcs:ignore WordPress.DB.SlowDBQuery -- indexed meta_key lookup, small set.
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/** Products are active unless explicitly switched off (stored "0"). */
	public static function is_active( int $product_id ): bool {
		$raw = get_post_meta( $product_id, 'cic_catalog_active', true );
		return '0' !== (string) $raw;
	}

	/**
	 * Published, active treats for public selection (quote controls, listings).
	 *
	 * @return array<int, array{id: int, key: string, title: string}>
	 */
	public static function selectable_products(): array {
		$ids = get_posts(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
				'fields'      => 'ids',
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			if ( self::is_active( $id ) && '' !== self::key( $id ) ) {
				$out[] = array( 'id' => (int) $id, 'key' => self::key( $id ), 'title' => get_the_title( $id ) );
			}
		}
		return $out;
	}

	/**
	 * Published events for public selection.
	 *
	 * @return array<int, array{id: int, key: string, title: string}>
	 */
	public static function selectable_events(): array {
		$ids = get_posts(
			array(
				'post_type'   => EventType::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
				'fields'      => 'ids',
			)
		);
		return array_map( static fn( $id ) => array( 'id' => (int) $id, 'key' => self::key( $id ), 'title' => get_the_title( $id ) ), $ids );
	}

	/** Unique key for a post type, derived from a base string. */
	public static function unique_key( string $post_type, string $base, int $exclude = 0 ): string {
		$base = substr( sanitize_title( $base ), 0, 50 );
		$key  = $base;
		$n    = 2;
		while ( ( $found = self::find_by_key( $post_type, $key ) ) && $found !== $exclude ) {
			$key = $base . '-' . $n++;
		}
		return $key;
	}

	/* ------------------------------------------------------------------ *
	 * Identity box (both post types)
	 * ------------------------------------------------------------------ */

	public function add_identity_box(): void {
		foreach ( array( 'product', EventType::POST_TYPE ) as $type ) {
			add_meta_box( 'cic-identity', __( 'Catalogue identity', 'cater-ice-cream-core' ), array( $this, 'render_identity_box' ), $type, 'side', 'high' );
		}
	}

	public function render_identity_box( \WP_Post $post ): void {
		wp_nonce_field( 'cic_identity_' . $post->ID, self::NONCE );
		$key    = self::key( $post->ID );
		$preset = (string) get_post_meta( $post->ID, 'cic_preset', true );
		echo '<p><label for="cic-key"><strong>' . esc_html__( 'Stable key', 'cater-ice-cream-core' ) . '</strong></label><br>';
		if ( '' !== $key ) {
			echo '<code id="cic-key">' . esc_html( $key ) . '</code><br><span class="description">' . esc_html__( 'Used by quote links and integrations; it does not change when the title or URL changes.', 'cater-ice-cream-core' ) . '</span>';
		} else {
			echo '<input type="text" id="cic-key" name="cic_identity[key]" class="widefat" pattern="[a-z0-9-]+" placeholder="' . esc_attr__( 'Leave empty to use the URL slug', 'cater-ice-cream-core' ) . '">';
		}
		echo '</p><p><label for="cic-preset"><strong>' . esc_html__( 'Design preset', 'cater-ice-cream-core' ) . '</strong></label><br><select id="cic-preset" name="cic_identity[preset]" class="widefat"><option value="">' . esc_html__( 'Default brand', 'cater-ice-cream-core' ) . '</option>';
		foreach ( Schema::contexts() as $ckey => $def ) {
			if ( 'brand' !== $ckey ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $ckey ), selected( $preset, $ckey, false ), esc_html( $def['label'] ) );
			}
		}
		echo '</select></p>';
		if ( 'product' === $post->post_type ) {
			$type = (string) get_post_meta( $post->ID, 'cic_brand_type', true );
			printf(
				'<p><label><input type="checkbox" name="cic_identity[active]" value="1"%1$s> %2$s</label></p>',
				checked( self::is_active( $post->ID ), true, false ),
				esc_html__( 'Active in catalogue and quote choices', 'cater-ice-cream-core' )
			);
			echo '<p><label for="cic-brand-type"><strong>' . esc_html__( 'Treat type', 'cater-ice-cream-core' ) . '</strong></label><br><select id="cic-brand-type" name="cic_identity[brand_type]" class="widefat">';
			printf( '<option value="generic"%1$s>%2$s</option>', selected( $type, 'generic', false ), esc_html__( 'Generic treat (no supplier brand)', 'cater-ice-cream-core' ) );
			printf( '<option value="brand"%1$s>%2$s</option>', selected( $type, 'brand', false ), esc_html__( 'Named brand (set it in Brands)', 'cater-ice-cream-core' ) );
			echo '</select></p><p class="description">' . esc_html__( 'Catalogue role: showcase. Showcase treats are never purchasable in this release; prices stay empty.', 'cater-ice-cream-core' ) . '</p>';
		}
	}

	public function save_identity( int $post_id, \WP_Post $post ): void {
		if ( ! in_array( $post->post_type, array( 'product', EventType::POST_TYPE ), true ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		$posted = isset( $_POST[ self::NONCE ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'cic_identity_' . $post_id ) && current_user_can( 'edit_post', $post_id );
		$input  = $posted && isset( $_POST['cic_identity'] ) && is_array( $_POST['cic_identity'] ) ? wp_unslash( $_POST['cic_identity'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.

		// Every product/event gets a stable key once (so a fifth record needs no source edits).
		if ( '' === self::key( $post_id ) ) {
			$base = '' !== trim( (string) ( $input['key'] ?? '' ) ) ? (string) $input['key'] : ( $post->post_name ? $post->post_name : $post->post_title );
			if ( '' !== trim( $base ) ) {
				update_post_meta( $post_id, 'cic_key', self::unique_key( $post->post_type, $base, $post_id ) );
			}
		}
		if ( 'product' === $post->post_type && '' === (string) get_post_meta( $post_id, 'cic_catalog_role', true ) ) {
			update_post_meta( $post_id, 'cic_catalog_role', 'showcase' );
		}
		if ( ! $posted ) {
			return;
		}
		update_post_meta( $post_id, 'cic_preset', (string) ( $input['preset'] ?? '' ) );
		if ( 'product' === $post->post_type ) {
			update_post_meta( $post_id, 'cic_catalog_active', ! empty( $input['active'] ) ? '1' : '0' );
			update_post_meta( $post_id, 'cic_brand_type', (string) ( $input['brand_type'] ?? 'generic' ) );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Presentation and routes
	 * ------------------------------------------------------------------ */

	/** A product/event page uses its preset unless the page override picks another one. */
	public function page_context( string $context ): string {
		if ( ! is_singular( array( 'product', EventType::POST_TYPE ) ) ) {
			return $context;
		}
		$id        = (int) get_queried_object_id();
		$overrides = \CIC\Design\Overrides::page_overrides( $id );
		if ( isset( $overrides['context'] ) ) {
			return $context;
		}
		$preset = (string) get_post_meta( $id, 'cic_preset', true );
		return '' !== $preset ? $preset : $context;
	}

	/**
	 * Legacy and competing URLs redirect permanently to the canonical destination.
	 */
	public function redirects(): void {
		if ( is_admin() || ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path only, compared below.
		if ( preg_match( '#^product/([^/]+)/?$#', $path, $m ) && ! is_singular( 'product' ) ) {
			$post = get_page_by_path( sanitize_title( $m[1] ), OBJECT, 'product' );
			if ( $post && 'publish' === $post->post_status ) {
				wp_safe_redirect( get_permalink( $post ), 301 );
				exit;
			}
		}
		if ( 'shop' === $path ) {
			wp_safe_redirect( get_permalink( wc_get_page_id( 'shop' ) ), 301 );
			exit;
		}
		if ( is_tax( array( 'product_brand', 'product_cat', 'product_tag' ) ) ) {
			$target = get_permalink( wc_get_page_id( 'shop' ) );
			if ( is_tax( 'product_brand' ) ) {
				$ids = get_posts(
					array(
						'post_type'   => 'product',
						'post_status' => 'publish',
						'numberposts' => 2,
						'fields'      => 'ids',
						'tax_query'   => array( array( 'taxonomy' => 'product_brand', 'field' => 'term_id', 'terms' => get_queried_object_id() ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
					)
				);
				if ( 1 === count( $ids ) ) {
					$target = get_permalink( $ids[0] );
				}
			}
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}

	/**
	 * Inactive treats stay editable but leave the public catalogue.
	 *
	 * @param \WP_Query $query Product query.
	 */
	public function hide_inactive( $query ): void {
		$meta   = (array) $query->get( 'meta_query' );
		$meta[] = array(
			'relation' => 'OR',
			array( 'key' => 'cic_catalog_active', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'cic_catalog_active', 'value' => '0', 'compare' => '!=' ),
		);
		$query->set( 'meta_query', $meta );
	}

	/**
	 * @param array<string, bool> $robots Robots directives.
	 * @return array<string, bool>
	 */
	public function noindex_inactive( array $robots ): array {
		if ( is_singular( 'product' ) && ! self::is_active( (int) get_queried_object_id() ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * Configure /treats/ routes: shop page slug and product base. Only changes values still at the
	 * WooCommerce defaults, so later deliberate edits are kept. Returns messages.
	 *
	 * @return string[]
	 */
	public static function configure_routes(): array {
		$messages = array();
		$shop_id  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		if ( $shop_id > 0 ) {
			$shop = get_post( $shop_id );
			if ( $shop && 'shop' === $shop->post_name ) {
				$clash = get_page_by_path( self::SHOP_SLUG );
				if ( $clash && $clash->ID !== $shop_id ) {
					$messages[] = 'Shop page not renamed: another page already uses /treats/.';
				} else {
					wp_update_post( array( 'ID' => $shop_id, 'post_name' => self::SHOP_SLUG, 'post_title' => 'Our Treats' ) );
					$messages[] = 'Shop page is now /treats/ ("Our Treats").';
				}
			} else {
				$messages[] = 'Shop page slug left as "' . ( $shop ? $shop->post_name : '?' ) . '".';
			}
		}
		$permalinks = (array) get_option( 'woocommerce_permalinks', array() );
		if ( empty( $permalinks['product_base'] ) || in_array( trim( (string) $permalinks['product_base'], '/' ), array( 'product', 'shop' ), true ) ) {
			$permalinks['product_base'] = self::PRODUCT_BASE;
			update_option( 'woocommerce_permalinks', $permalinks );
			$messages[] = 'Product base is now /treats/{product}/.';
		} else {
			$messages[] = 'Product base left as "' . $permalinks['product_base'] . '".';
		}
		return $messages;
	}
}
