<?php
/**
 * Token-protected HTTP endpoint for an external scheduler (SiteGround cron, GitHub Actions, any
 * uptime pinger). It runs the same export as the internal cron event and is safe to call often
 * because the ledger makes the export idempotent.
 *
 * URL: /wp-json/cic/v1/sheets/sync?token=<token>
 *
 * @package CIC
 */

namespace CIC\Sheets;

defined( 'ABSPATH' ) || exit;

final class Endpoint {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'route' ) );
	}

	public function route(): void {
		register_rest_route(
			'cic/v1',
			'/sheets/sync',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'run' ),
				'permission_callback' => array( $this, 'allowed' ),
			)
		);
	}

	/** @return true|\WP_Error */
	public function allowed( \WP_REST_Request $request ) {
		$token = (string) $request->get_param( 'token' );
		if ( '' !== $token && hash_equals( Settings::token(), $token ) ) {
			return true;
		}
		return new \WP_Error( 'cic_sheets_forbidden', __( 'Invalid export token.', 'cater-ice-cream-core' ), array( 'status' => 401 ) );
	}

	/** @return \WP_REST_Response */
	public function run(): \WP_REST_Response {
		if ( ! Settings::ready() ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'error' => 'disabled',
				),
				409
			);
		}

		$result = Sync::run();
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'error' => $result->get_error_message(),
					'data'  => $result->get_error_data(),
				),
				502
			);
		}

		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'pending' => (int) $result['pending'],
				'synced'  => (int) $result['synced'],
			),
			200
		);
	}

	/** Public URL including the token (shown in the admin and printed by the CLI). */
	public static function url(): string {
		return add_query_arg( 'token', Settings::token(), rest_url( 'cic/v1/sheets/sync' ) );
	}
}