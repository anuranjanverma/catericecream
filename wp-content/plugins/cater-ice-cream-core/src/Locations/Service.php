<?php
/**
 * Location services: the repository adapter and the public coverage endpoint
 * POST /cic/v1/coverage (limited, validated, no personal data, never a date reservation).
 *
 * @package CIC
 */

namespace CIC\Locations;

use CIC\Quote\Context;

defined( 'ABSPATH' ) || exit;

final class Service {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public static function repository(): LocationRepository {
		static $repo = null;
		if ( null === $repo ) {
			$candidate = apply_filters( 'cic_location_repository', null );
			$repo      = $candidate instanceof LocationRepository ? $candidate : new PendingRepository();
		}
		return $repo;
	}

	public function routes(): void {
		register_rest_route(
			'cic/v1',
			'/coverage',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'args'                => array(
					'location' => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 60,
					),
				),
				'callback'            => array( $this, 'coverage' ),
			)
		);
	}

	public function coverage( \WP_REST_Request $request ): \WP_REST_Response {
		if ( $this->rate_limited() ) {
			return new \WP_REST_Response( array( 'status' => 'error', 'message' => __( 'Too many checks. Please wait a minute and try again.', 'cater-ice-cream-core' ) ), 429 );
		}
		$location = Context::location( (string) $request->get_param( 'location' ) );
		$result   = self::repository()->coverage( $location );
		$result['location']  = $location;
		$result['connected'] = self::repository()->is_connected();
		$response = new \WP_REST_Response( $result, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/** Simple per-IP limit (30 checks a minute); the IP is hashed and never stored in clear. */
	private function rate_limited(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'cic_cov_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );
		$n   = (int) get_transient( $key );
		if ( $n >= 30 ) {
			return true;
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		return false;
	}
}
