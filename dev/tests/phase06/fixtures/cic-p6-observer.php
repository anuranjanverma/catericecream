<?php
/**
 * TEST FIXTURE (Phase 06) — copied into wp-content/mu-plugins only while the Phase 06 tests run and
 * removed in their `finally`. Never deploy.
 *
 * - Records, for every wp_mail call, whether the enquiry being notified was already stored in
 *   Flamingo with its workflow meta (booleans and ids only; no addresses or content).
 * - When the option cic_p6_fail_mail is set, makes wp_mail fail (pre_wp_mail short-circuit) to
 *   test the saved-enquiry / mail-failure recovery path.
 *
 * @package CIC
 */

defined( 'ABSPATH' ) || exit;

// Test diagnostics only (synthetic @example.test addresses): last mail error text.
add_action(
	'wp_mail_failed',
	static function ( $error ): void {
		update_option( 'cic_p6_mail_error', $error instanceof WP_Error ? $error->get_error_message() : 'unknown', false );
	}
);

add_filter(
	'pre_wp_mail',
	static function ( $short ) {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT flamingo_id FROM ' . $wpdb->prefix . "cic_submissions WHERE operator_status = 'sending' OR customer_status = 'sending'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		$log  = (array) get_option( 'cic_p6_observe', array() );
		foreach ( $rows as $row ) {
			$id    = (int) $row['flamingo_id'];
			$log[] = array(
				'id'     => $id,
				'stored' => $id > 0 && 'flamingo_inbound' === get_post_type( $id ) && '' !== (string) get_post_meta( $id, '_cic_type', true ),
				'fail'   => (bool) get_option( 'cic_p6_fail_mail' ),
			);
		}
		if ( ! $rows ) {
			$log[] = array( 'id' => 0, 'stored' => false, 'fail' => (bool) get_option( 'cic_p6_fail_mail' ) );
		}
		update_option( 'cic_p6_observe', array_slice( $log, -200 ), false );
		return get_option( 'cic_p6_fail_mail' ) ? false : $short;
	},
	1
);
