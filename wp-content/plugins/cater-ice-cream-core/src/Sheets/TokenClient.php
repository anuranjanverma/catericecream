<?php
/**
 * Google OAuth2 access tokens from a service account (RS256 JWT, no SDK or Composer dependency).
 * The token is cached in a transient for its remaining lifetime.
 *
 * @package CIC
 */

namespace CIC\Sheets;

defined( 'ABSPATH' ) || exit;

final class TokenClient {

	private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
	private const SCOPE     = 'https://www.googleapis.com/auth/spreadsheets';

	/**
	 * @return string|\WP_Error
	 */
	public static function access_token() {
		$cred = Credentials::load();
		if ( is_wp_error( $cred ) ) {
			return $cred;
		}

		$cache_key = 'cic_sheets_token_' . md5( $cred['client_email'] );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$now    = time();
		$header = self::b64( (string) wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::b64(
			(string) wp_json_encode(
				array(
					'iss'   => $cred['client_email'],
					'scope' => self::SCOPE,
					'aud'   => self::TOKEN_URI,
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);

		$signature = '';
		if ( ! openssl_sign( $header . '.' . $claims, $signature, $cred['private_key'], OPENSSL_ALGO_SHA256 ) ) {
			return new \WP_Error( 'cic_sheets_sign', __( 'Could not sign the Google token request. Check the service-account private key.', 'cater-ice-cream-core' ) );
		}

		$response = wp_remote_post(
			self::TOKEN_URI,
			array(
				'timeout' => 25,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $header . '.' . $claims . '.' . self::b64( $signature ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'cic_sheets_token', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$detail = is_array( $body ) ? (string) ( $body['error_description'] ?? $body['error'] ?? '' ) : '';
			return new \WP_Error(
				'cic_sheets_token',
				sprintf(
					/* translators: 1: HTTP status, 2: Google error detail. */
					__( 'Google refused the token request (HTTP %1$s). %2$s', 'cater-ice-cream-core' ),
					$code,
					$detail
				)
			);
		}

		$token = (string) $body['access_token'];
		set_transient( $cache_key, $token, max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 120 ) );
		return $token;
	}

	public static function forget(): void {
		$cred = Credentials::load();
		if ( ! is_wp_error( $cred ) ) {
			delete_transient( 'cic_sheets_token_' . md5( $cred['client_email'] ) );
		}
	}

	private static function b64( string $raw ): string {
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- JWT requires base64url.
	}
}