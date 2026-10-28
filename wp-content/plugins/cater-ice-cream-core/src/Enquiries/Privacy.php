<?php
/**
 * Privacy tools for enquiries. Erasure: Flamingo's "Flamingo Inbound Messages" eraser deletes the
 * message, which removes all workflow meta with it (and cancels reminders through the delete hook);
 * the coordination row is removed by Store::cleanup(). Export: Flamingo has no exporter, so this
 * one exports each enquiry's submitted fields plus its workflow data (status, follow-up, notes).
 *
 * @package CIC
 */

namespace CIC\Enquiries;

defined( 'ABSPATH' ) || exit;

final class Privacy {

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
	}

	public function exporters( array $exporters ): array {
		$exporters['cic-enquiries'] = array(
			'exporter_friendly_name' => __( 'CaterIceCream.com inquiries', 'cater-ice-cream-core' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/** @return array{data: array<int, array<string, mixed>>, done: bool} */
	public static function export( string $email, int $page = 1 ): array {
		$ids  = get_posts( array( 'post_type' => Workflow::POST_TYPE, 'post_status' => 'any', 'numberposts' => 50, 'paged' => $page, 'fields' => 'ids', 'meta_key' => '_from_email', 'meta_value' => $email ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$data = array();
		foreach ( $ids as $id ) {
			if ( ! Workflow::is_enquiry( (int) $id ) ) {
				continue;
			}
			$items = array();
			foreach ( (array) get_post_meta( $id, '_fields', true ) as $name => $unused ) {
				$value = get_post_meta( $id, '_field_' . $name, true );
				if ( '' !== $value && array() !== $value ) {
					$items[] = array( 'name' => $name, 'value' => is_array( $value ) ? implode( ', ', $value ) : (string) $value );
				}
			}
			$items[] = array( 'name' => 'status', 'value' => Workflow::status( (int) $id ) );
			$items[] = array( 'name' => 'next_follow_up', 'value' => (string) get_post_meta( $id, '_cic_follow_up', true ) );
			foreach ( Workflow::notes( (int) $id ) as $n ) {
				$items[] = array( 'name' => 'note ' . $n['time'], 'value' => $n['text'] );
			}
			$data[] = array( 'group_id' => 'cic-enquiries', 'group_label' => __( 'Enquiries', 'cater-ice-cream-core' ), 'item_id' => 'cic-enquiry-' . $id, 'data' => $items );
		}
		return array( 'data' => $data, 'done' => count( $ids ) < 50 );
	}
}
