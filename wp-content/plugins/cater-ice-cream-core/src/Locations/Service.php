<?php
/**
 * Location services: repository selection, routes, editorial records, admin, CLI and the public
 * endpoints (BUILD_SPECIFICATION §15):
 *   GET  /cic/v1/locations?q=   limited autocomplete (validated query, no personal data or notes)
 *   POST /cic/v1/coverage       ZIP/city check: status + explanation, never a date reservation
 *   GET  /cic/v1/location?key=  visible label for a stored city key or ZIP ('' when unknown)
 * Both are rate limited per hashed IP and sent with Cache-Control: no-store.
 *
 * Repository order: an adapter from the cic_location_repository filter (extension point), else
 * DatabaseRepository once the location tables exist, else PendingRepository.
 *
 * @package CIC
 */

namespace CIC\Locations;

defined( 'ABSPATH' ) || exit;

final class Service {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		( new Routes() )->register();
		( new Editorial() )->register();
		if ( is_admin() ) {
			( new Admin\LocationsPage() )->register();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cic locations', Cli::class );
		}
	}

	public static function repository(): LocationRepository {
		static $repo = null;
		if ( null === $repo ) {
			$candidate = apply_filters( 'cic_location_repository', null );
			if ( $candidate instanceof LocationRepository ) {
				$repo = $candidate;
			} elseif ( Tables::ready() ) {
				$repo = new DatabaseRepository();
			} else {
				$repo = new PendingRepository();
			}
		}
		return $repo;
	}

	/**
	 * Visible label for a canonical location value: a ZIP stays the ZIP; a city key ("los-angeles-ca")
	 * becomes the repository label ("Los Angeles, CA"). Unknown keys return '' — a label is never
	 * guessed from a slug, so callers leave the field empty instead of showing the key.
	 */
	public static function display_label( string $key ): string {
		$key = trim( $key );
		if ( preg_match( '/^\d{5}$/', $key ) ) {
			return $key;
		}
		if ( '' === $key || ! preg_match( '/^[a-z0-9-]{1,80}$/', $key ) ) {
			return '';
		}
		$repo = self::repository();
		$city = method_exists( $repo, 'find_city' ) ? $repo->find_city( $key ) : null;
		return ( $city && 'published' === ( $city['publish_status'] ?? '' ) ) ? (string) $city['label'] : '';
	}

	public function routes(): void {
		register_rest_route(
			'cic/v1',
			'/location',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'key' => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 80,
					),
				),
				'callback'            => array( $this, 'location' ),
			)
		);
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
					'city'     => array(
						'type'      => 'string',
						'required'  => false,
						'maxLength' => 60,
					),
				),
				'callback'            => array( $this, 'coverage' ),
			)
		);
		register_rest_route(
			'cic/v1',
			'/locations',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'     => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 2,
						'maxLength' => 60,
					),
					'limit' => array(
						'type'    => 'integer',
						'default' => 8,
						'minimum' => 1,
						'maximum' => 10,
					),
				),
				'callback'            => array( $this, 'locations' ),
			)
		);
	}

	public function coverage( \WP_REST_Request $request ): \WP_REST_Response {
		if ( $this->rate_limited( 'cov', 30 ) ) {
			return $this->limited();
		}
		$result              = self::repository()->evaluate_coverage( (string) $request->get_param( 'location' ), (string) $request->get_param( 'city' ) );
		$result['connected'] = self::repository()->is_connected();
		unset( $result['reason'] );
		return $this->response( $result );
	}

	/** Label lookup for a stored canonical key (ZIP or city key); '' when unknown. */
	public function location( \WP_REST_Request $request ): \WP_REST_Response {
		if ( $this->rate_limited( 'loc', 90 ) ) {
			return $this->limited();
		}
		$key = (string) $request->get_param( 'key' );
		return $this->response( array( 'key' => $key, 'label' => self::display_label( $key ) ) );
	}

	public function locations( \WP_REST_Request $request ): \WP_REST_Response {
		if ( $this->rate_limited( 'loc', 90 ) ) {
			return $this->limited();
		}
		$items = self::repository()->search_locations( (string) $request->get_param( 'q' ), (int) $request->get_param( 'limit' ) );
		$items = array_map(
			static function ( $item ) {
				return array_intersect_key( $item, array_flip( array( 'type', 'label', 'key', 'url', 'status', 'action', 'message', 'fixture' ) ) );
			},
			$items
		);
		return $this->response( array( 'items' => $items ) );
	}

	/** @param array<string, mixed> $data Data. */
	private function response( array $data ): \WP_REST_Response {
		$response = new \WP_REST_Response( $data, 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	private function limited(): \WP_REST_Response {
		return new \WP_REST_Response( array( 'status' => 'error', 'message' => __( 'Too many checks. Please wait a minute and try again.', 'cater-ice-cream-core' ) ), 429 );
	}

	/** Simple per-IP limit per minute; the IP is hashed and never stored in clear. */
	private function rate_limited( string $bucket, int $limit ): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'cic_' . $bucket . '_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );
		$n   = (int) get_transient( $key );
		if ( $n >= $limit ) {
			return true;
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		return false;
	}
}
