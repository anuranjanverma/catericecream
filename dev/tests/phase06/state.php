<?php
/**
 * Phase 06 test helper (CLI, local only). Snapshot/restore removes ONLY records the tests created:
 * Flamingo messages and contacts created after the snapshot with a synthetic @example.test
 * address, their coordination rows and scheduled actions, temporary users, rate-limit counters,
 * the observer fixture and its options; enquiry settings are restored.
 *
 *   wp eval-file dev/tests/phase06/state.php snapshot <file>
 *   wp eval-file dev/tests/phase06/state.php restore <file>
 *   wp eval-file dev/tests/phase06/state.php info                 (form ids, unit tags, container posts, events/treats)
 *   wp eval-file dev/tests/phase06/state.php token <age-seconds>  (signed form token)
 *   wp eval-file dev/tests/phase06/state.php reset-limits
 *   wp eval-file dev/tests/phase06/state.php recipient <email>
 *   wp eval-file dev/tests/phase06/state.php observer on|off
 *   wp eval-file dev/tests/phase06/state.php fail on|off
 *   wp eval-file dev/tests/phase06/state.php count                (enquiries with @example.test, since snapshot)
 *   wp eval-file dev/tests/phase06/state.php enquiry <email>      (latest enquiry for a synthetic email: id, meta, row)
 *   wp eval-file dev/tests/phase06/state.php observe              (observer log)
 *   wp eval-file dev/tests/phase06/state.php plugin deactivate|activate flamingo
 *   wp eval-file dev/tests/phase06/state.php user <role> <login>  (temporary user + auth cookies)
 *   wp eval-file dev/tests/phase06/state.php retry <id>
 *   wp eval-file dev/tests/phase06/state.php settings <json>      (merge enquiry settings)
 *   wp eval-file dev/tests/phase06/state.php actions <id>         (pending scheduled reminder actions for an enquiry)
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;
'local' === wp_get_environment_type() || WP_CLI::error( 'Local environment only.' );

use CIC\Enquiries\Forms;
use CIC\Enquiries\Settings;
use CIC\Enquiries\Store;
use CIC\Enquiries\Workflow;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;
$action  = $args[0] ?? '';
$arg     = (string) ( $args[1] ?? '' );
$fixture = __DIR__ . '/fixtures/cic-p6-observer.php';
$mu      = WP_CONTENT_DIR . '/mu-plugins/cic-p6-observer.php';
$out     = static function ( $v ): void {
	echo wp_json_encode( $v );
};
$test_posts = static function ( int $after ) use ( $wpdb ): array {
	return array_map(
		'intval',
		$wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('_from_email','_email') WHERE p.post_type IN ('flamingo_inbound','flamingo_contact') AND p.ID > %d AND m.meta_value LIKE %s", $after, '%@example.test' ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	);
};

switch ( $action ) {
	case 'snapshot':
		$data = array(
			'max_post'   => (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'max_row'    => (int) $wpdb->get_var( 'SELECT COALESCE(MAX(id),0) FROM ' . Store::table() ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'settings'   => get_option( Settings::OPTION, '__absent__' ),
			'mu_present' => file_exists( $mu ),
		);
		file_put_contents( $file = $arg, wp_json_encode( $data ) );
		WP_CLI::success( 'Snapshot written.' );
		break;

	case 'restore':
		$data = json_decode( (string) file_get_contents( $arg ), true );
		is_array( $data ) || WP_CLI::error( 'Unreadable snapshot.' );
		$ids = $test_posts( (int) $data['max_post'] );
		foreach ( $ids as $id ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( Workflow::HOOK, array( 'id' => $id ), Workflow::GROUP );
				as_unschedule_all_actions( \CIC\Enquiries\Notifier::RETRY_HOOK, array( 'id' => $id ), Workflow::GROUP );
			}
			wp_delete_post( $id, true );
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Store::table() . ' WHERE id > %d', (int) $data['max_row'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		'__absent__' === $data['settings'] ? delete_option( Settings::OPTION ) : update_option( Settings::OPTION, $data['settings'], false );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_cic\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_cic\\_rl\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_option( 'cic_p6_fail_mail' );
		delete_option( 'cic_p6_observe' );
		if ( ! $data['mu_present'] && file_exists( $mu ) ) {
			unlink( $mu ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		if ( ! is_plugin_active( 'flamingo/flamingo.php' ) ) {
			activate_plugin( 'flamingo/flamingo.php' );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( get_users( array( 'meta_key' => '_cic_p6_test', 'fields' => 'ID' ) ) as $uid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			WP_Session_Tokens::get_instance( (int) $uid )->destroy_all();
			wp_delete_user( (int) $uid );
		}
		WP_CLI::success( sprintf( 'Restored (%d test records removed).', count( $ids ) ) );
		break;

	case 'info':
		// Fixture coverage cases from the data (local test data, never production coverage).
		$repo    = \CIC\Locations\Service::repository();
		$zips    = $wpdb->get_col( 'SELECT zip FROM ' . \CIC\Locations\Tables::name( 'zips' ) . ' ORDER BY zip' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$covered = '';
		foreach ( $zips as $z ) {
			if ( 'covered' === $repo->evaluate_coverage( $z )['status'] ) {
				$covered = $z;
				break;
			}
		}
		$unknown = '';
		for ( $n = 93001; $n < 96100 && '' === $unknown; $n += 13 ) {
			$unknown = in_array( (string) $n, $zips, true ) ? '' : (string) $n;
		}
		$out(
			array(
				'quote'    => Forms::id( 'quote' ),
				'contact'  => Forms::id( 'contact' ),
				'quotePg'  => \CIC\Content\Catalog::find_by_key( 'page', 'get-a-quote' ),
				'contactPg' => \CIC\Content\Catalog::find_by_key( 'page', 'contact' ),
				'version'  => defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : '',
				'events'   => wp_list_pluck( \CIC\Content\Catalog::selectable_events(), 'key' ),
				'treats'   => wp_list_pluck( \CIC\Content\Catalog::selectable_products(), 'key' ),
				'today'    => \CIC\Quote\Context::client_config()['today'],
				'future'   => ( new DateTimeImmutable( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->modify( '+45 days' )->format( 'Y-m-d' ),
				'covered'  => $covered,
				'unknown'  => $unknown,
			)
		);
		break;

	case 'token':
		echo esc_html( \CIC\Enquiries\Guard::token( time() - (int) $arg ) );
		break;

	case 'reset-limits':
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_cic\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_cic\\_rl\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();
		echo 'ok';
		break;

	case 'recipient':
		Settings::update( array( 'recipient' => sanitize_email( $arg ) ) );
		echo 'ok';
		break;

	case 'settings':
		Settings::update( (array) json_decode( $arg, true ) );
		echo 'ok';
		break;

	case 'observer':
		if ( 'on' === $arg ) {
			copy( $fixture, $mu );
		} elseif ( file_exists( $mu ) ) {
			unlink( $mu ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		echo 'ok';
		break;

	case 'fail':
		'on' === $arg ? update_option( 'cic_p6_fail_mail', 1, false ) : delete_option( 'cic_p6_fail_mail' );
		echo 'ok';
		break;

	case 'observe':
		$out( get_option( 'cic_p6_observe', array() ) );
		break;

	case 'count':
		$out( count( array_filter( $test_posts( (int) $arg ), static fn( $id ) => 'flamingo_inbound' === get_post_type( $id ) ) ) );
		break;

	case 'enquiry':
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_from_email' WHERE p.post_type = 'flamingo_inbound' AND m.meta_value = %s ORDER BY p.ID DESC LIMIT 1", $arg ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$n  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_from_email' WHERE p.post_type = 'flamingo_inbound' AND m.meta_value = %s", $arg ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out(
			array(
				'id'      => $id,
				'count'   => $n,
				'status'  => $id ? get_post_status( $id ) : '',
				'type'    => $id ? get_post_meta( $id, '_cic_type', true ) : '',
				'wf'      => $id ? Workflow::status( $id ) : '',
				'context' => $id ? Workflow::context( $id ) : array(),
				'source'  => $id ? Workflow::source( $id ) : array(),
				'meta'    => $id ? array_keys( (array) get_post_meta( $id, '_meta', true ) ) : array(),
				'fields'  => $id ? array_keys( (array) get_post_meta( $id, '_fields', true ) ) : array(),
				'row'     => $id ? Store::by_flamingo( $id ) : null,
			)
		);
		break;

	case 'plugin':
		'deactivate' === $arg ? deactivate_plugins( 'flamingo/flamingo.php' ) : activate_plugin( 'flamingo/flamingo.php' );
		echo is_plugin_active( 'flamingo/flamingo.php' ) ? 'active' : 'inactive';
		break;

	case 'user':
		$role  = $arg;
		$login = sanitize_user( (string) ( $args[2] ?? 'cic_p6_user' ) );
		$uid   = username_exists( $login ) ?: wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 32 ), 'user_email' => $login . '@example.test', 'role' => $role, 'display_name' => 'Test ' . $role ) );
		is_wp_error( $uid ) && WP_CLI::error( $uid->get_error_message() );
		update_user_meta( $uid, '_cic_p6_test', 1 );
		$expires = time() + HOUR_IN_SECONDS;
		$token   = WP_Session_Tokens::get_instance( $uid )->create( $expires );
		$out(
			array(
				'id'      => $uid,
				'cookies' => array(
					array( 'name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $expires, 'secure_auth', $token ) ),
					array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $expires, 'logged_in', $token ) ),
				),
			)
		);
		break;

	case 'retry':
		$out( \CIC\Enquiries\Notifier::send_all( (int) $arg ) );
		break;

	case 'actions':
		$n = 0;
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			foreach ( as_get_scheduled_actions( array( 'hook' => Workflow::HOOK, 'group' => Workflow::GROUP, 'status' => 'pending', 'per_page' => 200 ), 'ids' ) as $aid ) {
				$a = ActionScheduler::store()->fetch_action( $aid );
				$n += ( (int) ( $a->get_args()['id'] ?? 0 ) === (int) $arg ) ? 1 : 0;
			}
		}
		$out( $n );
		break;
}
