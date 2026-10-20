<?php
/**
 * Snapshot / restore what the Phase 08 tests may change. CLI only.
 *   wp eval-file dev/tests/phase08/state.php snapshot <file>
 *   wp eval-file dev/tests/phase08/state.php restore <file>
 *   wp eval-file dev/tests/phase08/state.php ids
 *
 * Restores the Events overview option, all meta/content/status of the shop, About, Contact and
 * policy pages and of the four treats, and deletes any test records (meta _cic_phase08_test).
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Content\Catalog;

$ids = array(
	'shop'           => (int) wc_get_page_id( 'shop' ),
	'about'          => Catalog::find_by_key( 'page', 'about' ),
	'contact'        => Catalog::find_by_key( 'page', 'contact' ),
	'privacy-policy' => Catalog::find_by_key( 'page', 'privacy-policy' ),
	'website-terms'  => Catalog::find_by_key( 'page', 'website-terms' ),
);
foreach ( array( 'dippin-dots', 'dipn-bananas', 'classic-ice-cream', 'frozen-yogurt' ) as $key ) {
	$ids[ $key ] = Catalog::find_by_key( 'product', $key );
}
$action = $args[0] ?? '';
$file   = (string) ( $args[1] ?? '' );

if ( 'ids' === $action ) {
	echo wp_json_encode( $ids );
} elseif ( 'snapshot' === $action ) {
	$data = array( 'overview' => get_option( 'cic_events_overview', '__absent__' ), 'posts' => array() );
	foreach ( $ids as $id ) {
		$data['posts'][ $id ] = array(
			'meta'    => array_map( static fn( $v ) => array_map( 'maybe_unserialize', $v ), get_post_meta( $id ) ),
			'content' => get_post_field( 'post_content', $id ),
			'status'  => get_post_status( $id ),
		);
	}
	file_put_contents( $file, wp_json_encode( $data ) );
	WP_CLI::success( 'Snapshot written.' );
} elseif ( 'restore' === $action ) {
	$data = json_decode( (string) file_get_contents( $file ), true );
	foreach ( (array) $data['posts'] as $id => $post ) {
		$id = (int) $id;
		foreach ( array_keys( get_post_meta( $id ) ) as $key ) {
			if ( ! array_key_exists( $key, $post['meta'] ) ) {
				delete_post_meta( $id, $key );
			}
		}
		foreach ( $post['meta'] as $key => $values ) {
			delete_post_meta( $id, $key );
			foreach ( $values as $value ) {
				add_post_meta( $id, $key, $value );
			}
		}
		wp_update_post( array( 'ID' => $id, 'post_content' => $post['content'], 'post_status' => $post['status'] ) );
		clean_post_cache( $id );
	}
	'__absent__' === $data['overview'] ? delete_option( 'cic_events_overview' ) : update_option( 'cic_events_overview', $data['overview'] );
	foreach ( get_posts( array( 'post_type' => array( 'product', 'cic_event', 'page' ), 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_cic_phase08_test', 'fields' => 'ids' ) ) as $test ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		wp_delete_post( $test, true );
	}
	WP_CLI::success( 'Restored.' );
}
