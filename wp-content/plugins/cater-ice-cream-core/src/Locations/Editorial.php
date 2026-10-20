<?php
/**
 * Private, revisioned editorial records for city content (spec §9 cic_city_content fallback).
 *
 * Public city pages stay virtual routes; these records only hold the city-specific overrides
 * (ACF Free fields, see CIC\Content\Fields::city_group). They are never public, never queryable,
 * never in sitemaps or search. Preview opens the virtual city URL for authenticated managers.
 * Revisions come from WordPress (ACF copies its field values onto each revision).
 *
 * "Reviewed" (cic_city_reviewed) marks content approved for indexing; empty overrides inherit the
 * city template from the Service Areas page and stay noindex until reviewed.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class Editorial {

	public const POST_TYPE = 'cic_city_editorial';
	public const REVIEWED  = 'cic_city_reviewed';
	public const PREVIEW   = 'cic_city_preview';

	public function register(): void {
		add_action( 'init', array( $this, 'register_type' ) );
		add_filter( 'preview_post_link', array( $this, 'preview_link' ), 10, 2 );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'saved' ), 20, 2 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'meta_boxes' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'exclude_from_sitemaps' ) );
	}

	public function register_type(): void {
		$cap = Tables::CAPABILITY;
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'City content', 'cater-ice-cream-core' ),
					'singular_name' => __( 'City content', 'cater-ice-cream-core' ),
					'edit_item'     => __( 'Edit city content', 'cater-ice-cream-core' ),
					'search_items'  => __( 'Search city content', 'cater-ice-cream-core' ),
					'not_found'     => __( 'No city content yet. Open a city under Service Areas → Cities and choose “Create city content”.', 'cater-ice-cream-core' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => Admin\LocationsPage::SLUG,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'revisions' ),
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'              => $cap,
					'read_post'              => $cap,
					'delete_post'            => $cap,
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'edit_published_posts'   => $cap,
					'edit_private_posts'     => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'delete_posts'           => $cap,
					'delete_others_posts'    => $cap,
					'delete_published_posts' => $cap,
					'delete_private_posts'   => $cap,
					'create_posts'           => 'do_not_allow',
				),
			)
		);
	}

	/** Preview the virtual city route (authenticated, nonce-protected, never cached/indexed). */
	public function preview_link( string $link, \WP_Post $post ): string {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $link;
		}
		$url = self::preview_url( (int) $post->ID );
		return '' !== $url ? $url : $link;
	}

	public static function preview_url( int $post_id ): string {
		$city_id = CityStore::city_for_editorial( $post_id );
		$row     = $city_id ? CityStore::row( $city_id ) : null;
		if ( ! $row || '' === Tables::state_slug( $row['state_code'] ) ) {
			return '';
		}
		return add_query_arg(
			array(
				self::PREVIEW => $post_id,
				'_wpnonce'    => wp_create_nonce( self::PREVIEW . '_' . $post_id ),
			),
			DatabaseRepository::url( Tables::state_slug( $row['state_code'] ), $row['city_slug'] )
		);
	}

	/**
	 * Valid preview request for the current user: the editorial record id (its newest autosave
	 * when one exists), else 0.
	 */
	public static function preview_request(): int {
		$id = isset( $_GET[ self::PREVIEW ] ) ? absint( $_GET[ self::PREVIEW ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
		if ( ! $id || ! current_user_can( Tables::CAPABILITY ) ) {
			return 0;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( $nonce, self::PREVIEW . '_' . $id ) || self::POST_TYPE !== get_post_type( $id ) ) {
			return 0;
		}
		return $id;
	}

	/** Meta source for a preview: the user's newer autosave if present, else the record. */
	public static function preview_source( int $post_id ): int {
		$autosave = wp_get_post_autosave( $post_id, get_current_user_id() );
		if ( $autosave && strtotime( $autosave->post_modified_gmt ) >= strtotime( (string) get_post_field( 'post_modified_gmt', $post_id ) ) ) {
			return (int) $autosave->ID;
		}
		return $post_id;
	}

	public function saved( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		Tables::bump_cache();
	}

	public function meta_boxes( \WP_Post $post ): void {
		add_meta_box( 'cic-city-link', __( 'City', 'cater-ice-cream-core' ), array( $this, 'render_link_box' ), self::POST_TYPE, 'side', 'high' );
	}

	public function render_link_box( \WP_Post $post ): void {
		$city_id = CityStore::city_for_editorial( (int) $post->ID );
		$row     = $city_id ? CityStore::row( $city_id ) : null;
		if ( ! $row ) {
			echo '<p>' . esc_html__( 'This record is not linked to a city.', 'cater-ice-cream-core' ) . '</p>';
			return;
		}
		$edit = add_query_arg( array( 'page' => Admin\LocationsPage::SLUG, 'city' => $city_id ), admin_url( 'admin.php' ) );
		echo '<p><strong>' . esc_html( $row['city_name'] . ', ' . $row['state_code'] ) . '</strong></p>';
		/* translators: 1: publish status, 2: index status. */
		echo '<p>' . esc_html( sprintf( __( 'City page: %1$s, %2$s.', 'cater-ice-cream-core' ), $row['publish_status'], $row['index_status'] ) ) . '</p>';
		// WordPress hides its own Preview button for non-public types, so offer the virtual-route preview.
		$preview = self::preview_url( (int) $post->ID );
		if ( '' !== $preview ) {
			echo '<p><a class="button" id="cic-city-preview" target="_blank" rel="noopener" href="' . esc_url( $preview ) . '">' . esc_html__( 'Preview city page', 'cater-ice-cream-core' ) . '</a></p>';
			echo '<p class="description">' . esc_html__( 'Save your changes first; the preview shows the saved draft.', 'cater-ice-cream-core' ) . '</p>';
		}
		echo '<p><a href="' . esc_url( $edit ) . '">' . esc_html__( 'City settings and coverage', 'cater-ice-cream-core' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Empty fields inherit the city template on the Service Areas page. The page stays out of search engines until “Content reviewed” is ticked, this record is published and the city is set to Published + Index.', 'cater-ice-cream-core' ) . '</p>';
	}

	/** @param array<string, \WP_Post_Type> $post_types Types. */
	public function exclude_from_sitemaps( array $post_types ): array {
		unset( $post_types[ self::POST_TYPE ] );
		return $post_types;
	}
}
