<?php
/**
 * Restore-drill helper for a DISPOSABLE copy only: proves that a Flamingo enquiry, its CIC workflow
 * metadata and its coordination row survive backup and restore unchanged.
 *
 *   wp eval-file enquiry-drill.php create        Synthetic enquiry (@example.test, never mailed)
 *   wp eval-file enquiry-drill.php fingerprint   SHA-256 over the message, all its meta, terms and
 *                                                coordination row (prints id, counts and hash)
 *   wp eval-file enquiry-drill.php delete        Simulated loss: deletes it permanently
 *
 * Exits unless CIC_DISPOSABLE is defined. Prints no personal data.
 *
 * @package CIC\Tools
 */

defined( 'WP_CLI' ) || exit;

global $wpdb;

if ( ! defined( 'CIC_DISPOSABLE' ) || true !== CIC_DISPOSABLE || ! str_starts_with( DB_NAME, 'cic_' ) ) {
	WP_CLI::error( 'Refusing: this is not a disposable cic_* copy.' );
}

$find = static function () use ( $wpdb ): int {
	return (int) $wpdb->get_var( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_cic_drill' WHERE p.post_type = 'flamingo_inbound' ORDER BY p.ID LIMIT 1" );
};

switch ( $args[0] ?? '' ) {
	case 'create':
		if ( $find() ) {
			WP_CLI::error( 'A drill enquiry already exists.' );
		}
		$fields = array(
			'your-name'  => 'Drill Tester',
			'your-email' => 'drill-tester@example.test',
			'event'      => 'birthday',
			'event-date' => '2027-05-15',
			'guests'     => '40',
			'location'   => 'pasadena',
			'message'    => 'Synthetic restore-drill enquiry. Not a real customer.',
		);
		$msg = Flamingo_Inbound_Message::add(
			array(
				'channel'    => 'cic-quote',
				'status'     => 'mail_sent',
				'subject'    => 'Restore drill',
				'from'       => 'Drill Tester <drill-tester@example.test>',
				'from_name'  => 'Drill Tester',
				'from_email' => 'drill-tester@example.test',
				'fields'     => $fields,
				'meta'       => array( 'remote_ip' => '', 'user_agent' => 'restore-drill' ),
			)
		);
		$id = (int) $msg->id();
		update_post_meta( $id, '_cic_drill', '1' );
		$key = 'drill' . bin2hex( random_bytes( 12 ) );
		CIC\Enquiries\Store::claim( 'quote', $key );
		CIC\Enquiries\Store::complete( $key, $id, array( 'event' => 'Birthday', 'location' => 'Pasadena' ) );
		CIC\Enquiries\Store::set_channel( $id, 'operator', 'sent' );
		CIC\Enquiries\Store::set_channel( $id, 'customer', 'skipped', 'drill' );
		CIC\Enquiries\Workflow::init( $id, array( 'type' => 'quote', 'event' => 'birthday', 'event_label' => 'Birthday', 'date' => '2027-05-15', 'guests' => 40, 'location' => 'pasadena', 'location_label' => 'Pasadena', 'coverage' => 'unknown' ), array( 'first' => array( 'type' => 'event', 'key' => 'birthday' ) ) );
		CIC\Enquiries\Workflow::set_status( $id, 'contacted' );
		CIC\Enquiries\Workflow::set_follow_up( $id, '2027-04-01' );
		CIC\Enquiries\Workflow::add_note( $id, 'Restore drill note.' );
		WP_CLI::success( "Drill enquiry #{$id} created (status contacted, follow-up, note, coordination row)." );
		break;

	case 'fingerprint':
		$id = $find();
		if ( ! $id ) {
			WP_CLI::log( 'fingerprint: none' );
			break;
		}
		$post  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A );
		$meta  = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_key, meta_id", $id ), ARRAY_A );
		$terms = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = %d ORDER BY term_taxonomy_id", $id ) );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cic_submissions WHERE flamingo_id = %d", $id ), ARRAY_A );
		$cic   = array_values( array_filter( wp_list_pluck( $meta, 'meta_key' ), static fn( $k ) => str_starts_with( $k, '_cic_' ) ) );
		WP_CLI::log( sprintf( 'fingerprint: #%d meta=%d cic_meta=%s terms=%d coordination_row=%s status=%s sha256=%s', $id, count( $meta ), implode( ',', $cic ), count( $terms ), $row ? 'yes' : 'no', CIC\Enquiries\Workflow::status( $id ), hash( 'sha256', wp_json_encode( array( $post, $meta, $terms, $row ) ) ) ) );
		break;

	case 'delete':
		$id = $find();
		if ( $id ) {
			wp_delete_post( $id, true );
			$wpdb->delete( $wpdb->prefix . 'cic_submissions', array( 'flamingo_id' => $id ) );
		}
		WP_CLI::success( $id ? "Drill enquiry #{$id} deleted (simulated loss)." : 'Nothing to delete.' );
		break;

	default:
		WP_CLI::error( 'Use create, fingerprint or delete.' );
}
