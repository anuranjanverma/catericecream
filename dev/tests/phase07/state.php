<?php
/**
 * Snapshot / restore what the Phase 07 tests may change. CLI only.
 *   wp eval-file dev/tests/phase07/state.php snapshot <file>
 *   wp eval-file dev/tests/phase07/state.php restore <file>
 *   wp eval-file dev/tests/phase07/state.php ids
 *   wp eval-file dev/tests/phase07/state.php set-action-color <hex>   (Design System test value)
 *
 * Restores every post meta key, excerpt and content of the eight detail records exactly (keys a
 * test added are removed), and the Design System settings option.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Content\Catalog;

$ids = array();
foreach ( array( 'birthday', 'wedding', 'anniversary', 'corporate' ) as $key ) {
	$ids[ $key ] = Catalog::find_by_key( 'cic_event', $key );
}
foreach ( array( 'dippin-dots', 'dipn-bananas', 'classic-ice-cream', 'frozen-yogurt' ) as $key ) {
	$ids[ $key ] = Catalog::find_by_key( 'product', $key );
}
$action = $args[0] ?? '';
$file   = (string) ( $args[1] ?? '' );

if ( 'ids' === $action ) {
	echo wp_json_encode( $ids );
} elseif ( 'snapshot' === $action ) {
	$data = array( 'posts' => array(), 'design' => get_option( 'cic_design_settings', '__absent__' ) );
	foreach ( $ids as $id ) {
		$data['posts'][ $id ] = array(
			'meta'    => array_map( static fn( $v ) => array_map( 'maybe_unserialize', $v ), get_post_meta( $id ) ),
			'excerpt' => get_post_field( 'post_excerpt', $id ),
			'content' => get_post_field( 'post_content', $id ),
		);
	}
	file_put_contents( $file, wp_json_encode( $data ) );
	WP_CLI::success( 'Snapshot written.' );
} elseif ( 'restore' === $action ) {
	$data = json_decode( (string) file_get_contents( $file ), true );
	foreach ( (array) $data['posts'] as $id => $post ) {
		$id      = (int) $id;
		$current = get_post_meta( $id );
		foreach ( array_keys( $current ) as $key ) {
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
		wp_update_post( array( 'ID' => $id, 'post_excerpt' => $post['excerpt'], 'post_content' => $post['content'] ) );
		clean_post_cache( $id );
	}
	'__absent__' === $data['design'] ? delete_option( 'cic_design_settings' ) : update_option( 'cic_design_settings', $data['design'] );
	CIC\Design\Service::purge_page_caches( 0 );
	WP_CLI::success( 'Restored.' );
} elseif ( 'set-action-color' === $action ) {
	$errors   = array();
	$settings = (array) get_option( 'cic_design_settings', array() );
	$settings['colors']['action_primary'] = (string) ( $args[1] ?? '' );
	$clean = CIC\Design\Settings::sanitize( $settings, (array) get_option( 'cic_design_settings', array() ), $errors );
	update_option( 'cic_design_settings', $clean );
	CIC\Design\Service::purge_page_caches( 0 );
	echo $errors ? 'errors: ' . wp_json_encode( $errors ) : 'ok';
}
