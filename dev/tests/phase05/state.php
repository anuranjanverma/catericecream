<?php
/**
 * Snapshot / restore everything the Phase 05 tests may change. CLI only.
 *   wp eval-file dev/tests/phase05/state.php snapshot <file>
 *   wp eval-file dev/tests/phase05/state.php restore <file>
 *   wp eval-file dev/tests/phase05/state.php city-id <slug>     (prints the city id, 0 if none)
 *   wp eval-file dev/tests/phase05/state.php city-row <slug>    (prints status/dataset/source JSON)
 *   wp eval-file dev/tests/phase05/state.php leftovers <slug>   (city content records + rows with that slug)
 *
 * Restores the location tables row-for-row (rows a test added are removed, changed rows put
 * back), the related options, and deletes city content records created after the snapshot.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Locations\Editorial;
use CIC\Locations\Tables;

// The cache version is never restored: it must only increase, or stale cached reads would be reused.
$options = array( 'blog_public', 'cic_location_audit', 'cic_location_imports' );
$action  = $args[0] ?? '';
$file    = (string) ( $args[1] ?? '' );

if ( 'city-id' === $action || 'city-row' === $action || 'leftovers' === $action ) {
	global $wpdb;
	$table = Tables::name( 'cities' );
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, publish_status, index_status, dataset, source_reference FROM {$table} WHERE city_slug = %s", $file ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( 'city-id' === $action ) {
		echo (int) ( $row['id'] ?? 0 );
	} elseif ( 'city-row' === $action ) {
		echo wp_json_encode( $row );
	} else {
		echo count( get_posts( array( 'post_type' => Editorial::POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) ) + ( $row ? 1 : 0 );
	}
} elseif ( 'snapshot' === $action ) {
	global $wpdb;
	$data = array( 'tables' => array(), 'options' => array(), 'posts' => array(), 'rewrite' => md5( (string) wp_json_encode( get_option( 'rewrite_rules' ) ) ) );
	foreach ( Tables::all() as $key => $table ) {
		$data['tables'][ $key ] = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	foreach ( $options as $opt ) {
		$data['options'][ $opt ] = get_option( $opt, '__absent__' );
	}
	$data['posts'] = get_posts( array( 'post_type' => Editorial::POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	file_put_contents( $file, wp_json_encode( $data ) );
	WP_CLI::success( 'Snapshot written.' );
} elseif ( 'restore' === $action ) {
	global $wpdb;
	$data = json_decode( (string) file_get_contents( $file ), true );
	foreach ( Tables::all() as $key => $table ) {
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $data['tables'][ $key ] as $row ) {
			$wpdb->insert( $table, $row );
		}
	}
	foreach ( get_posts( array( 'post_type' => Editorial::POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
		if ( ! in_array( $id, (array) $data['posts'], true ) ) {
			wp_delete_post( $id, true );
		}
	}
	foreach ( (array) $data['options'] as $opt => $value ) {
		'__absent__' === $value ? delete_option( $opt ) : update_option( $opt, $value );
	}
	Tables::bump_cache();
	WP_CLI::success( 'Restored.' );
}
