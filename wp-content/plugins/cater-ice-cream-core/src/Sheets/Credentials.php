<?php
/**
 * Service-account key loading. The JSON file is read from a path outside the web root and is
 * never persisted in the database, the repository or the admin output.
 *
 * A CIC_SHEETS_CREDENTIALS constant in wp-config.php takes precedence over the stored path.
 *
 * @package CIC
 */

namespace CIC\Sheets;

defined( 'ABSPATH' ) || exit;

final class Credentials {

	public static function path(): string {
		$stored = trim( (string) Settings::value( 'credentials_path' ) );
		if ( '' === $stored && defined( 'CIC_SHEETS_CREDENTIALS' ) ) {
			$stored = (string) CIC_SHEETS_CREDENTIALS;
		}
		if ( '' === $stored ) {
			$stored = dirname( ABSPATH ) . '/secure/google-service-account.json';
		}
		return $stored;
	}

	/**
	 * @return array{client_email: string, private_key: string}|\WP_Error
	 */
	public static function load() {
		$path = self::path();
		if ( ! is_readable( $path ) ) {
			return new \WP_Error( 'cic_sheets_credentials', __( 'Service-account key file is missing or unreadable.', 'cater-ice-cream-core' ) );
		}
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local readable file, not a remote request.
		$json = json_decode( (string) $raw, true );
		if ( ! is_array( $json ) ) {
			return new \WP_Error( 'cic_sheets_credentials', __( 'Service-account key file is not valid JSON.', 'cater-ice-cream-core' ) );
		}
		$email = (string) ( $json['client_email'] ?? '' );
		$key   = (string) ( $json['private_key'] ?? '' );
		if ( ! is_email( $email ) || false === strpos( $key, 'PRIVATE KEY' ) ) {
			return new \WP_Error( 'cic_sheets_credentials', __( 'Service-account key file is missing client_email or private_key.', 'cater-ice-cream-core' ) );
		}
		return array(
			'client_email' => $email,
			'private_key'  => $key,
		);
	}

	public static function email(): string {
		$cred = self::load();
		return is_wp_error( $cred ) ? '' : $cred['client_email'];
	}
}