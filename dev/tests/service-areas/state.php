<?php
/**
 * Snapshot / restore everything the Service Areas admin tests may change. CLI only.
 *   wp eval-file dev/tests/service-areas/state.php snapshot <file>
 *   wp eval-file dev/tests/service-areas/state.php restore <file>
 *   wp eval-file dev/tests/service-areas/state.php editor-cookies   (temporary editor without manage_locations)
 *   wp eval-file dev/tests/service-areas/state.php remove-users
 *   wp eval-file dev/tests/service-areas/state.php city-id <slug>
 *
 * Restores the location tables row-for-row, the audit/import/unlink options and deletes city
 * content records and temporary users created after the snapshot. The cache version is never
 * restored (it only increases); restore bumps it instead.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;
'local' === wp_get_environment_type() || WP_CLI::error( 'Local environment only.' );

use CIC\Locations\CityStore;
use CIC\Locations\Editorial;
use CIC\Locations\Tables;

global $wpdb;
$options = array( CityStore::AUDIT_OPTION, CityStore::UNLINK_OPTION, \CIC\Locations\Importer::LOG );
$action  = $args[0] ?? '';
$file    = (string) ( $args[1] ?? '' );

$remove_users = static function (): void {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( get_users( array( 'meta_key' => '_cic_sa_test', 'fields' => 'all' ) ) as $user ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		// Flamingo (Phase 06) adds an address-book contact for every new user: remove the test user's.
		foreach ( get_posts( array( 'post_type' => 'flamingo_contact', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_email', 'meta_value' => $user->user_email ) ) as $contact ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			wp_delete_post( (int) $contact, true );
		}
		WP_Session_Tokens::get_instance( (int) $user->ID )->destroy_all();
		wp_delete_user( (int) $user->ID );
	}
};

if ( 'test-data' === $action ) {
	// A ZIP shared by 2+ cities and a California ZIP nobody holds.
	$t      = Tables::all();
	$shared = (string) $wpdb->get_var( "SELECT zip FROM {$t['city_zips']} GROUP BY zip HAVING COUNT(*) > 1 ORDER BY zip LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$fresh  = '';
	for ( $n = 90003; $n < 96100 && '' === $fresh; $n += 11 ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT zip FROM {$t['zips']} WHERE zip = %s", (string) $n ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$fresh = (string) $n;
		}
	}
	echo wp_json_encode( array( 'shared' => $shared, 'fresh' => $fresh, 'others' => CityStore::zip_cities( $shared ) ) );
} elseif ( 'zip-cities' === $action ) {
	echo wp_json_encode( CityStore::zip_cities( $file, (int) ( $args[2] ?? 0 ) ) );
} elseif ( 'others-coverage' === $action ) {
	$repo = new \CIC\Locations\DatabaseRepository();
	$out  = array();
	foreach ( CityStore::zip_cities( $file, (int) ( $args[2] ?? 0 ) ) as $c ) {
		$out[ $c['city_id'] ] = $repo->city_coverage( $repo->city_by_id( $c['city_id'] ) )['status'];
	}
	echo wp_json_encode( $out );
} elseif ( 'mapping-count' === $action ) {
	echo count( CityStore::mappings( (int) $file ) );
} elseif ( 'zip-status' === $action ) {
	echo (string) $wpdb->get_var( $wpdb->prepare( 'SELECT service_status FROM ' . Tables::name( 'zips' ) . ' WHERE zip = %s', $file ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
} elseif ( 'eyebrow' === $action ) {
	echo '[' . get_post_meta( CityStore::editorial_id( (int) $file ), 'cic_city_eyebrow', true ) . ']';
} elseif ( 'city-id' === $action ) {
	echo (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Tables::name( 'cities' ) . ' WHERE city_slug = %s', $file ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
} elseif ( 'editor-cookies' === $action ) {
	$uid = wp_insert_user( array( 'user_login' => 'cic_sa_test_editor', 'user_pass' => wp_generate_password( 32 ), 'user_email' => 'cic-sa-test-editor@example.test', 'role' => 'editor' ) );
	is_wp_error( $uid ) && WP_CLI::error( $uid->get_error_message() );
	update_user_meta( $uid, '_cic_sa_test', 1 );
	$expires = time() + HOUR_IN_SECONDS;
	$token   = WP_Session_Tokens::get_instance( $uid )->create( $expires );
	wp_set_current_user( $uid );
	echo wp_json_encode(
		array(
			'can'     => current_user_can( Tables::CAPABILITY ),
			'nonce'   => wp_create_nonce( 'cic_city_create' ),
			'cookies' => array(
				array( 'name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $expires, 'secure_auth', $token ) ),
				array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $expires, 'logged_in', $token ) ),
			),
		)
	);
} elseif ( 'remove-users' === $action ) {
	$remove_users();
	WP_CLI::success( 'Temporary users removed.' );
} elseif ( 'snapshot' === $action ) {
	$data = array( 'tables' => array(), 'options' => array(), 'posts' => array() );
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
	$data = json_decode( (string) file_get_contents( $file ), true );
	is_array( $data ) || WP_CLI::error( 'Unreadable snapshot.' );
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
		'__absent__' === $value ? delete_option( $opt ) : update_option( $opt, $value, false );
	}
	$remove_users();
	Tables::bump_cache();
	WP_CLI::success( 'Restored.' );
}
