<?php
/**
 * Read-only helper for the quote-flow browser test (local only): inquiry records for an address.
 *
 * Usage: wp eval-file dev/tests/quote-flow/state.php records <email>
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;
'local' === wp_get_environment_type() || WP_CLI::error( 'Local environment only.' );

use CIC\Enquiries\Drafts;
use CIC\Enquiries\Workflow;

global $wpdb;
$action = $args[0] ?? '';
$email  = (string) ( $args[1] ?? '' );

if ( 'records' === $action ) {
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_from_email' AND meta_value = %s ORDER BY post_id", $email ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out = array();
	foreach ( $ids as $id ) {
		$id    = (int) $id;
		$out[] = array(
			'id'       => $id,
			'stage'    => Drafts::stage( $id ),
			'status'   => get_post_meta( $id, '_cic_status', true ),
			'assignee' => (int) get_post_meta( $id, '_cic_assignee', true ),
			'name'     => get_post_meta( $id, '_field_your_name', true ),
			'guests'   => get_post_meta( $id, '_field_guests', true ),
			'event'    => get_post_meta( $id, '_field_event', true ),
			'notes'    => get_post_meta( $id, '_field_notes', true ),
			'dietary'  => get_post_meta( $id, '_field_dietary', true ),
			'treats'   => array_values( array_filter( (array) get_post_meta( $id, '_field_treats', true ) ) ),
			'unsure'   => (string) get_post_meta( $id, '_field_treats_unsure', true ),
			'ctx'      => Workflow::context( $id ),
		);
	}
	echo wp_json_encode( $out );
}
