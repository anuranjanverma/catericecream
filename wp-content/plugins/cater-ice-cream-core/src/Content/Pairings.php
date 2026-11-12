<?php
/**
 * Product–event pairings (BUILD_SPECIFICATION §7). The {prefix}cic_product_events table is the only
 * store of pairing copy and its indexed relationship: one row per product+event (unique key), the
 * same description shown on the treat page and the event page. Every write goes through this class.
 *
 * @package CIC
 */

namespace CIC\Content;

defined( 'ABSPATH' ) || exit;

final class Pairings {

	public const REST_FIELD = 'cic_pairings';

	public function register(): void {
		add_action( 'before_delete_post', array( $this, 'delete_for_post' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ) );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cic_product_events';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ): array {
		return array(
			'product_id'   => (int) $row['product_id'],
			'event_id'     => (int) $row['event_id'],
			'headline'     => (string) $row['headline'],
			'description'  => (string) $row['description'],
			'image_id'     => (int) $row['image_id'],
			'link_label'   => (string) $row['link_label'],
			'event_intro'  => (string) $row['event_intro'],
			'product_sort' => (int) $row['product_sort'],
			'event_sort'   => (int) $row['event_sort'],
		);
	}

	/**
	 * Pairings of a product, ordered for the treat page.
	 *
	 * @param bool $public_only Only rows whose event is published (public rendering and quote controls).
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_product( int $product_id, bool $public_only = false ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE product_id = %d ORDER BY product_sort ASC, id ASC', $product_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is internal.
		$rows = array_map( array( self::class, 'hydrate' ), (array) $rows );
		return $public_only ? array_values( array_filter( $rows, static fn( $r ) => 'publish' === get_post_status( $r['event_id'] ) ) ) : $rows;
	}

	/**
	 * Reverse lookup for an event page, using the indexed event_id column (no meta LIKE queries).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_event( int $event_id, bool $public_only = false ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE event_id = %d ORDER BY event_sort ASC, id ASC', $event_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is internal.
		$rows = array_map( array( self::class, 'hydrate' ), (array) $rows );
		return $public_only ? array_values( array_filter( $rows, static fn( $r ) => 'publish' === get_post_status( $r['product_id'] ) && Catalog::is_active( $r['product_id'] ) ) ) : $rows;
	}

	/**
	 * Validate one pairing row. Returns [clean row|null, error|null].
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array{0: array<string, mixed>|null, 1: string|null}
	 */
	public static function validate( int $product_id, array $row ): array {
		if ( 'product' !== get_post_type( $product_id ) ) {
			return array( null, __( 'Pairings can only belong to a WooCommerce product.', 'cater-ice-cream-core' ) );
		}
		$event_id = absint( $row['event_id'] ?? 0 );
		if ( ! $event_id || EventType::POST_TYPE !== get_post_type( $event_id ) || 'trash' === get_post_status( $event_id ) ) {
			return array( null, sprintf( __( 'Pairing skipped: %s is not an existing event.', 'cater-ice-cream-core' ), (string) ( $row['event_id'] ?? '' ) ) );
		}
		$image_id = absint( $row['image_id'] ?? 0 );
		if ( $image_id && ! wp_attachment_is_image( $image_id ) ) {
			$image_id = 0;
		}
		return array(
			array(
				'product_id'   => $product_id,
				'event_id'     => $event_id,
				'headline'     => mb_substr( sanitize_text_field( (string) ( $row['headline'] ?? '' ) ), 0, 200 ),
				'description'  => sanitize_textarea_field( (string) ( $row['description'] ?? '' ) ),
				'image_id'     => $image_id,
				'link_label'   => mb_substr( sanitize_text_field( (string) ( $row['link_label'] ?? '' ) ), 0, 120 ),
				'event_intro'  => sanitize_textarea_field( (string) ( $row['event_intro'] ?? '' ) ),
				'product_sort' => (int) ( $row['product_sort'] ?? 0 ),
				'event_sort'   => (int) ( $row['event_sort'] ?? 0 ),
			),
			null,
		);
	}

	/**
	 * Replace all pairings of one product (the editing unit). Invalid rows and duplicate events are
	 * rejected with messages; valid rows are written in one transaction.
	 *
	 * @param array<int, array<string, mixed>> $rows   Raw rows.
	 * @param bool                             $strict Reject the whole set if any row is invalid (REST).
	 * @return string[] Errors.
	 */
	public static function replace_for_product( int $product_id, array $rows, bool $strict = false ): array {
		global $wpdb;
		$errors = array();
		$clean  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['event_id'] ) ) {
				continue;
			}
			list( $valid, $error ) = self::validate( $product_id, $row );
			if ( $error ) {
				$errors[] = $error;
				continue;
			}
			if ( isset( $clean[ $valid['event_id'] ] ) ) {
				$errors[] = sprintf( __( 'Duplicate pairing with "%s" ignored: one pairing per event.', 'cater-ice-cream-core' ), get_the_title( $valid['event_id'] ) );
				continue;
			}
			$clean[ $valid['event_id'] ] = $valid;
		}
		if ( 'product' !== get_post_type( $product_id ) || ( $strict && $errors ) ) {
			return $errors;
		}
		$table = self::table();
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->delete( $table, array( 'product_id' => $product_id ), array( '%d' ) );
		foreach ( $clean as $row ) {
			$row['updated_gmt'] = current_time( 'mysql', true );
			$wpdb->insert( $table, $row, array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s' ) );
		}
		$wpdb->query( 'COMMIT' );
		self::flush( $product_id, array_keys( $clean ) );
		do_action( 'cic_pairings_saved', $product_id, array_values( $clean ) );
		return $errors;
	}

	/**
	 * Insert a pairing only if that product+event pair does not exist yet (used by the seeder so
	 * edited pairings are never overwritten).
	 *
	 * @param array<string, mixed> $row Raw row.
	 */
	public static function add_if_missing( int $product_id, array $row ): bool {
		global $wpdb;
		list( $valid ) = self::validate( $product_id, $row );
		if ( ! $valid ) {
			return false;
		}
		$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE product_id = %d AND event_id = %d', $product_id, $valid['event_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $exists ) {
			return false;
		}
		$valid['updated_gmt'] = current_time( 'mysql', true );
		$ok                   = (bool) $wpdb->insert( self::table(), $valid, array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s' ) );
		self::flush( $product_id, array( $valid['event_id'] ) );
		return $ok;
	}

	/**
	 * Seeder-only refresh of a pairing it created earlier, without touching editor changes:
	 * an image is set only where none exists, and text changes only where it still equals the
	 * previous seed text exactly (so it cannot be an editor's edit).
	 *
	 * @param array<string, string> $replaces Previous seed values (headline, description, link_label).
	 * @param array<string, string> $values   New seed values for those fields.
	 * @return string[] Fields updated.
	 */
	public static function fill_seeded( int $product_id, int $event_id, int $image_id, array $replaces, array $values ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE product_id = %d AND event_id = %d', $product_id, $event_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $row ) {
			return array();
		}
		$changes = array();
		if ( $image_id && 0 === (int) $row['image_id'] && wp_attachment_is_image( $image_id ) ) {
			$changes['image_id'] = $image_id;
		}
		foreach ( array( 'headline', 'description', 'link_label' ) as $field ) {
			if ( isset( $replaces[ $field ], $values[ $field ] ) && (string) $row[ $field ] === $replaces[ $field ] ) {
				$changes[ $field ] = sanitize_text_field( $values[ $field ] );
			}
		}
		if ( $changes ) {
			$changes['updated_gmt'] = current_time( 'mysql', true );
			$wpdb->update( self::table(), $changes, array( 'id' => (int) $row['id'] ) );
			self::flush( $product_id, array( $event_id ) );
		}
		return array_keys( array_diff_key( $changes, array( 'updated_gmt' => 1 ) ) );
	}

	/** Permanently deleting a product or event removes its pairings (trash keeps them, hidden publicly). */
	public function delete_for_post( int $post_id ): void {
		global $wpdb;
		$type = get_post_type( $post_id );
		if ( 'product' === $type ) {
			$wpdb->delete( self::table(), array( 'product_id' => $post_id ), array( '%d' ) );
		} elseif ( EventType::POST_TYPE === $type ) {
			$wpdb->delete( self::table(), array( 'event_id' => $post_id ), array( '%d' ) );
		}
	}

	/**
	 * @param int[] $event_ids Affected events.
	 */
	private static function flush( int $product_id, array $event_ids ): void {
		clean_post_cache( $product_id );
		foreach ( $event_ids as $event_id ) {
			clean_post_cache( $event_id );
		}
		\CIC\Design\Service::purge_page_caches( $product_id );
	}

	/* ------------------------------------------------------------------ *
	 * REST: read on products and events, write on products (edit_post).
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<string, mixed>
	 */
	public static function rest_schema(): array {
		return array(
			'description' => __( 'Product-event pairings (single source; same copy on both pages).', 'cater-ice-cream-core' ),
			'type'        => 'array',
			'context'     => array( 'view', 'edit' ),
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'product_id'   => array( 'type' => 'integer', 'readonly' => true ),
					'event_id'     => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ),
					'headline'     => array( 'type' => 'string', 'maxLength' => 200 ),
					'description'  => array( 'type' => 'string' ),
					'image_id'     => array( 'type' => 'integer', 'minimum' => 0 ),
					'link_label'   => array( 'type' => 'string', 'maxLength' => 120 ),
					'event_intro'  => array( 'type' => 'string' ),
					'product_sort' => array( 'type' => 'integer' ),
					'event_sort'   => array( 'type' => 'integer' ),
				),
			),
		);
	}

	public function register_rest_fields(): void {
		register_rest_field(
			'product',
			self::REST_FIELD,
			array(
				'get_callback'    => static function ( $post ) {
					$public = ! current_user_can( 'edit_post', (int) $post['id'] );
					return self::for_product( (int) $post['id'], $public );
				},
				'update_callback' => static function ( $value, $post ) {
					if ( ! current_user_can( 'edit_post', $post->ID ) ) {
						return new \WP_Error( 'rest_forbidden', __( 'You cannot edit pairings for this product.', 'cater-ice-cream-core' ), array( 'status' => 403 ) );
					}
					$errors = self::replace_for_product( $post->ID, (array) $value, true );
					return $errors ? new \WP_Error( 'cic_pairing_invalid', implode( ' ', $errors ), array( 'status' => 400 ) ) : true;
				},
				'schema'          => self::rest_schema(),
			)
		);
		register_rest_field(
			EventType::POST_TYPE,
			self::REST_FIELD,
			array(
				'get_callback' => static function ( $post ) {
					$public = ! current_user_can( 'edit_post', (int) $post['id'] );
					return self::for_event( (int) $post['id'], $public );
				},
				'schema'       => array_merge( self::rest_schema(), array( 'readonly' => true ) ),
			)
		);
	}
}
