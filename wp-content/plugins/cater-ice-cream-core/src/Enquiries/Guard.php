<?php
/**
 * Layered abuse controls for the enquiry forms (BUILD_SPECIFICATION §15). Anonymous nonces are not
 * used as spam protection. Instead:
 * - honeypot field that people never see;
 * - signed render-time token: sent faster than MIN_SECONDS → spam; older than MAX_AGE → the visitor
 *   is asked to press Send again (a fresh token is returned, entered values are kept);
 * - request limit per hashed IP (no raw IP is stored): ACCEPT_LIMIT accepted enquiries per window;
 * - acknowledgement limit per hashed recipient address, so the form cannot be used to send
 *   repeated emails to a third party.
 * Spam is never stored as an enquiry and never acknowledged.
 *
 * @package CIC
 */

namespace CIC\Enquiries;

defined( 'ABSPATH' ) || exit;

final class Guard {

	public const MIN_SECONDS  = 3;
	public const MAX_AGE      = 12 * HOUR_IN_SECONDS;
	public const ACCEPT_LIMIT = 5;
	public const WINDOW       = 10 * MINUTE_IN_SECONDS;
	public const ACK_LIMIT    = 3;

	public static function token( ?int $time = null ): string {
		$time = $time ?? time();
		return $time . '.' . substr( hash_hmac( 'sha256', 'cic-form|' . $time, wp_salt( 'nonce' ) ), 0, 24 );
	}

	/** Render time from a token, or 0 when the signature is wrong. */
	public static function token_time( string $token ): int {
		if ( ! preg_match( '/^(\d{10})\.([a-f0-9]{24})$/', $token, $m ) ) {
			return 0;
		}
		return hash_equals( substr( hash_hmac( 'sha256', 'cic-form|' . $m[1], wp_salt( 'nonce' ) ), 0, 24 ), $m[2] ) ? (int) $m[1] : 0;
	}

	public static function expired( string $token ): bool {
		$time = self::token_time( $token );
		return $time > 0 && time() - $time > self::MAX_AGE;
	}

	/**
	 * Why a submission is spam ('' when it is not).
	 *
	 * @param array<string, mixed> $post Raw POST values.
	 */
	public static function spam_reason( array $post ): string {
		if ( '' !== trim( (string) ( $post['cic_website'] ?? '' ) ) ) {
			return 'honeypot';
		}
		$time = self::token_time( (string) ( $post['cic_ts'] ?? '' ) );
		if ( ! $time ) {
			return 'token';
		}
		if ( time() - $time < self::MIN_SECONDS ) {
			return 'too_fast';
		}
		if ( self::count( 'ip', self::client_hash() ) >= self::ACCEPT_LIMIT ) {
			return 'rate_limit';
		}
		return '';
	}

	/** Hash of the client address (never stored or logged raw). */
	public static function client_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 32 );
	}

	public static function count( string $bucket, string $hash ): int {
		return (int) get_transient( 'cic_rl_' . $bucket . '_' . $hash );
	}

	public static function hit( string $bucket, string $hash, int $window ): void {
		$key = 'cic_rl_' . $bucket . '_' . $hash;
		set_transient( $key, (int) get_transient( $key ) + 1, $window );
	}

	public static function accepted(): void {
		self::hit( 'ip', self::client_hash(), self::WINDOW );
	}

	/** May we send one more acknowledgement to this address today? Counts it when allowed. */
	public static function allow_ack( string $email ): bool {
		$hash = substr( hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'auth' ) ), 0, 32 );
		if ( self::count( 'ack', $hash ) >= self::ACK_LIMIT ) {
			return false;
		}
		self::hit( 'ack', $hash, DAY_IN_SECONDS );
		return true;
	}
}
