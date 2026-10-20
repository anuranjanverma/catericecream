<?php
/**
 * Non-destructive catalogue seeder.
 *
 * - Records are matched by stable cic_key; missing ones are created, existing ones are never
 *   replaced.
 * - On existing records only fields that were never seeded are filled. _cic_seeded_fields records
 *   what the seeder has set, so a field an editor later cleared deliberately is not refilled.
 * - Pairings are inserted only when that product+event pair does not exist yet.
 * - The seed log (option cic_seed_log) remembers every record and pairing the seeder created, so
 *   a record an editor deleted/trashed or a pairing an editor removed is never recreated.
 * - Re-running changes nothing once everything is seeded.
 *
 * @package CIC
 */

namespace CIC\Content;

use CIC\Database\Migrations;

defined( 'ABSPATH' ) || exit;

final class Seeder {

	public const SEEDED = '_cic_seeded_fields';
	public const LOG    = 'cic_seed_log';

	/** @var array{records: array<string, int>, pairs: string[]} */
	private array $log = array( 'records' => array(), 'pairs' => array() );

	/** @var array<string, int> */
	private array $report = array(
		'created' => 0,
		'filled'  => 0,
		'kept'    => 0,
		'pairs'   => 0,
		'media'   => 0,
	);

	/** @var string[] */
	private array $messages = array();

	/**
	 * @return array{report: array<string, int>, messages: string[]}
	 */
	public function run( bool $with_media = true ): array {
		( new Migrations() )->maybe_migrate();
		$saved_log = get_option( self::LOG, array() );
		$this->log = array(
			'records' => (array) ( $saved_log['records'] ?? array() ),
			'pairs'   => (array) ( $saved_log['pairs'] ?? array() ),
		);
		foreach ( Catalog::configure_routes() as $message ) {
			$this->messages[] = $message;
		}
		if ( $with_media ) {
			$this->import_media();
		}
		$events = array();
		foreach ( SeedData::events() as $key => $data ) {
			$events[ $key ] = $this->seed_event( $key, $data );
		}
		foreach ( SeedData::pages() as $key => $data ) {
			$this->seed_page( $key, $data );
		}
		foreach ( SeedData::products() as $key => $data ) {
			$product_id = $this->seed_product( $key, $data );
			foreach ( $data['pairings'] ?? array() as $event_key => $pair ) {
				if ( empty( $events[ $event_key ] ) || ! $product_id ) {
					continue;
				}
				$pair_key = $key . ':' . $event_key;
				if ( in_array( $pair_key, $this->log['pairs'], true ) ) {
					// Seeded before: never recreate or overwrite it; only fill a missing photo or
					// refresh text that still equals the previous seed text exactly.
					$image = isset( $pair['image'] ) ? (int) $this->resolve( $pair['image'] ) : 0;
					if ( Pairings::fill_seeded( $product_id, $events[ $event_key ], $image, (array) ( $pair['replaces'] ?? array() ), $pair ) ) {
						$this->report['filled']++;
					}
					continue;
				}
				unset( $pair['replaces'] );
				$row             = $pair;
				$row['event_id'] = $events[ $event_key ];
				$row['image_id'] = isset( $pair['image'] ) ? (int) $this->resolve( $pair['image'] ) : 0;
				if ( Pairings::add_if_missing( $product_id, $row ) ) {
					$this->report['pairs']++;
				}
				$this->log['pairs'][] = $pair_key;
			}
		}
		update_option( self::LOG, $this->log, false );
		flush_rewrite_rules( false );
		return array(
			'report'   => $this->report,
			'messages' => $this->messages,
		);
	}

	/** A record the seeder created before but that no longer exists (deleted or trashed): do not recreate. */
	private function was_seeded( string $record ): bool {
		if ( isset( $this->log['records'][ $record ] ) ) {
			$this->messages[] = sprintf( 'Skipped %s: it was seeded before and has since been removed or trashed by an editor.', $record );
			return true;
		}
		return false;
	}

	private function import_media(): void {
		$assets = Media::assets_for_pages( SeedData::MEDIA_PAGES );
		foreach ( array( SeedData::products(), SeedData::events(), SeedData::pages() ) as $set ) {
			array_walk_recursive(
				$set,
				static function ( $value ) use ( &$assets ) {
					if ( is_string( $value ) && str_starts_with( $value, 'asset:' ) ) {
						$assets[] = substr( $value, 6 );
					}
				}
			);
		}
		foreach ( array_unique( $assets ) as $asset ) {
			$before = Media::find( $asset );
			$id     = Media::import( $asset );
			if ( is_wp_error( $id ) ) {
				$this->messages[] = 'Media: ' . $id->get_error_message();
			} elseif ( ! $before ) {
				$this->report['media']++;
			}
		}
	}

	/**
	 * "asset:{id}" → attachment ID (0 if not imported); arrays resolved element-wise.
	 *
	 * @param mixed $value Seed value.
	 * @return mixed
	 */
	private function resolve( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'resolve' ), $value );
		}
		if ( is_string( $value ) && str_starts_with( $value, 'asset:' ) ) {
			return Media::id( substr( $value, 6 ) );
		}
		return $value;
	}

	/**
	 * Fill one field once. Returns true when written.
	 *
	 * @param mixed    $value  Value to seed.
	 * @param callable $read   Reads the current value.
	 * @param callable $write  Writes the value.
	 */
	private function seed_once( int $post_id, string $field, $value, callable $read, callable $write ): bool {
		$seeded = get_post_meta( $post_id, self::SEEDED, true );
		$seeded = is_array( $seeded ) ? $seeded : array();
		$value  = $this->resolve( $value );
		if ( in_array( $field, $seeded, true ) ) {
			$this->report['kept']++;
			return false;
		}
		$current = $read();
		$empty   = in_array( $current, array( '', null, false, 0, '0', array() ), true );
		if ( $empty && ! in_array( $value, array( '', 0, null, array() ), true ) ) {
			$write( $value );
			$this->report['filled']++;
			$written = true;
		} else {
			$this->report['kept']++;
			$written = false;
		}
		if ( ! $empty || $written ) {
			$seeded[] = $field;
			update_post_meta( $post_id, self::SEEDED, array_values( array_unique( $seeded ) ) );
		}
		return $written;
	}

	/**
	 * @param array<string, mixed> $meta  Meta key => value.
	 * @param string[]             $acf   ACF field names (get a field reference).
	 */
	private function seed_meta( int $post_id, array $meta, array $acf ): void {
		foreach ( $meta as $key => $value ) {
			$this->seed_once(
				$post_id,
				$key,
				$value,
				static fn() => get_post_meta( $post_id, $key, true ),
				static function ( $v ) use ( $post_id, $key, $acf ) {
					update_post_meta( $post_id, $key, $v );
					if ( in_array( $key, $acf, true ) ) {
						update_post_meta( $post_id, '_' . $key, 'field_' . $key );
					}
				}
			);
		}
	}

	/**
	 * Pages (Home, Get a Quote, Service Areas). The homepage becomes the static front page only while the site
	 * still shows latest posts, so a later front-page choice is never overridden.
	 *
	 * @param array<string, mixed> $data Seed definition.
	 */
	private function seed_page( string $key, array $data ): int {
		$id = Catalog::find_by_key( 'page', $key );
		if ( ! $id && $this->was_seeded( 'page:' . $key ) ) {
			return 0;
		}
		if ( ! $id ) {
			$existing = 'shop' === $key && function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ? get_post( wc_get_page_id( 'shop' ) ) : get_page_by_path( $data['slug'], OBJECT, 'page' );
			if ( ! $existing && ! empty( $data['slug'] ) ) {
				$drafts   = get_posts( array( 'post_type' => 'page', 'name' => $data['slug'], 'post_status' => array( 'draft', 'pending', 'private' ), 'numberposts' => 1 ) );
				$existing = $drafts ? $drafts[0] : null;
			}
			$id = $existing ? $existing->ID : (int) wp_insert_post(
				array(
					'post_type'    => 'page',
					// Unapproved pages (policies) are created as drafts and never published by the seeder.
					'post_status'  => $data['status'] ?? 'publish',
					'post_title'   => $data['title'],
					'post_name'    => $data['slug'],
					'post_content' => $data['content'] ?? '',
				),
				true
			);
			if ( ! $id || is_wp_error( $id ) ) {
				$this->messages[] = 'Could not create page ' . $key;
				return 0;
			}
			update_post_meta( $id, 'cic_key', $key );
			if ( ! $existing ) {
				$this->report['created']++;
			}
		}
		$this->log['records'][ 'page:' . $key ] = (int) $id;
		if ( ! empty( $data['front'] ) && 'posts' === get_option( 'show_on_front' ) && ! (int) get_option( 'page_on_front' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $id );
			$this->messages[] = 'Static front page set to "' . $data['title'] . '".';
		}
		if ( ! empty( $data['template'] ) && in_array( (string) get_post_meta( $id, '_wp_page_template', true ), array( '', 'default' ), true ) ) {
			update_post_meta( $id, '_wp_page_template', $data['template'] );
		}
		$groups = array(
			'home'    => array( Fields::class, 'home_group' ),
			'hub'     => array( Fields::class, 'hub_group' ),
			'shop'    => array( Fields::class, 'shop_group' ),
			'about'   => array( Fields::class, 'about_group' ),
			'contact' => array( Fields::class, 'contact_group' ),
			'policy'  => array( Fields::class, 'policy_group' ),
		);
		$group = call_user_func( $groups[ $data['group'] ?? 'home' ] ?? $groups['home'] );
		$this->seed_meta( $id, $data['meta'], Fields::names( $group ) );
		return (int) $id;
	}

	/**
	 * @param array<string, mixed> $data Seed definition.
	 */
	private function seed_event( string $key, array $data ): int {
		$id = Catalog::find_by_key( EventType::POST_TYPE, $key );
		if ( ! $id && $this->was_seeded( 'event:' . $key ) ) {
			return 0;
		}
		if ( ! $id ) {
			$existing = get_page_by_path( $data['slug'], OBJECT, EventType::POST_TYPE );
			$id       = $existing ? $existing->ID : (int) wp_insert_post(
				array(
					'post_type'   => EventType::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $data['title'],
					'post_name'   => $data['slug'],
					'menu_order'  => $data['order'],
				),
				true
			);
			if ( ! $id || is_wp_error( $id ) ) {
				$this->messages[] = 'Could not create event ' . $key;
				return 0;
			}
			update_post_meta( $id, 'cic_key', $key );
			if ( ! $existing ) {
				$this->report['created']++;
			}
		}
		$this->log['records'][ 'event:' . $key ] = (int) $id;
		$this->seed_once( $id, 'post_excerpt', $data['excerpt'], static fn() => get_post_field( 'post_excerpt', $id ), static fn( $v ) => wp_update_post( array( 'ID' => $id, 'post_excerpt' => $v ) ) );
		if ( ! empty( $data['image'] ) ) {
			$this->seed_once( $id, '_thumbnail_id', $data['image'], static fn() => (int) get_post_thumbnail_id( $id ), static fn( $v ) => set_post_thumbnail( $id, (int) $v ) );
		}
		$meta = array_merge( array( 'cic_preset' => $data['preset'] ), $data['meta'] );
		$this->seed_meta( $id, $meta, Fields::names( Fields::event_group() ) );
		return (int) $id;
	}

	/**
	 * @param array<string, mixed> $data Seed definition.
	 */
	private function seed_product( string $key, array $data ): int {
		$id = Catalog::find_by_key( 'product', $key );
		if ( ! $id && $this->was_seeded( 'product:' . $key ) ) {
			return 0;
		}
		if ( ! $id ) {
			$existing = get_page_by_path( $data['slug'], OBJECT, 'product' );
			if ( $existing ) {
				$id = $existing->ID;
			} else {
				$product = new \WC_Product_Simple();
				$product->set_name( $data['title'] );
				$product->set_slug( $data['slug'] );
				$product->set_status( 'publish' );
				$product->set_catalog_visibility( 'visible' );
				$product->set_menu_order( (int) $data['order'] );
				$product->set_regular_price( '' ); // No price: never a fake zero (spec §7).
				$product->set_reviews_allowed( false );
				$id = $product->save();
				$this->report['created']++;
			}
			update_post_meta( $id, 'cic_key', $key );
		}
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return 0;
		}
		$this->log['records'][ 'product:' . $key ] = (int) $id;
		$this->seed_once( $id, 'post_excerpt', $data['excerpt'], static fn() => $product->get_short_description(), static function ( $v ) use ( $product ) { $product->set_short_description( $v ); $product->save(); } );
		if ( ! empty( $data['content'] ) ) {
			$this->seed_once( $id, 'post_content', $data['content'], static fn() => $product->get_description(), static function ( $v ) use ( $product ) { $product->set_description( $v ); $product->save(); } );
		}
		$this->seed_once( $id, '_thumbnail_id', $data['image'], static fn() => (int) $product->get_image_id(), static function ( $v ) use ( $product ) { $product->set_image_id( (int) $v ); $product->save(); } );
		if ( ! empty( $data['gallery'] ) ) {
			$this->seed_once( $id, '_product_image_gallery', $data['gallery'], static fn() => $product->get_gallery_image_ids(), static function ( $v ) use ( $product ) { $product->set_gallery_image_ids( array_filter( array_map( 'intval', (array) $v ) ) ); $product->save(); } );
		}
		if ( ! empty( $data['brand'] ) && taxonomy_exists( 'product_brand' ) ) {
			$this->seed_once(
				$id,
				'product_brand',
				$data['brand'],
				static fn() => wp_get_object_terms( $id, 'product_brand', array( 'fields' => 'ids' ) ),
				static function ( $v ) use ( $id ) {
					$term = term_exists( $v, 'product_brand' );
					$term = $term ? $term : wp_insert_term( $v, 'product_brand' );
					if ( ! is_wp_error( $term ) ) {
						wp_set_object_terms( $id, (int) $term['term_id'], 'product_brand' );
					}
				}
			);
		}
		$meta = array_merge(
			array(
				'cic_catalog_role'   => 'showcase',
				'cic_catalog_active' => '1',
				'cic_brand_type'     => $data['brand_type'],
				'cic_preset'         => $data['preset'],
			),
			$data['meta']
		);
		$this->seed_meta( $id, $meta, Fields::names( Fields::treat_group() ) );
		return (int) $id;
	}
}
