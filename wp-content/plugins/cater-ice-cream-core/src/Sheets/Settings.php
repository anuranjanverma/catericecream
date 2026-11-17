<?php
/**
 * Google Sheets export settings (Enquiries → Google Sheet, administrators only).
 *
 * The service-account key is a file outside the web root: it is never stored in the database,
 * never in the repository and never rendered in the admin. Only its path is configurable here.
 *
 * @package CIC
 */

namespace CIC\Sheets;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'cic_sheets_settings';
	public const STATUS = 'cic_sheets_status';
	public const TOKEN  = 'cic_sheets_webhook_token';
	public const PAGE   = 'cic-sheets';

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return array(
			'enabled'          => 0,
			'spreadsheet_id'   => '',
			'sheet_name'       => 'Form responses',
			'credentials_path' => '',
			'sync_limit'       => 200,
		);
	}

	/** @return array<string, mixed> */
	public static function get(): array {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/** @return mixed */
	public static function value( string $key ) {
		return self::get()[ $key ] ?? null;
	}

	public static function update( array $changes ): void {
		update_option( self::OPTION, array_merge( self::get(), $changes ), false );
	}

	public static function enabled(): bool {
		return ! empty( self::get()['enabled'] );
	}

	/** A usable spreadsheet id is present (independent of the enable switch). */
	public static function configured(): bool {
		return (bool) preg_match( '/^[A-Za-z0-9_-]{20,90}$/', (string) self::value( 'spreadsheet_id' ) );
	}

	/** Export runs only when switched on and pointed at a spreadsheet. */
	public static function ready(): bool {
		return self::enabled() && self::configured();
	}

	/** @return array{last_run: string, last_rows: int, last_error: string, last_ok: int} */
	public static function status(): array {
		return wp_parse_args(
			(array) get_option( self::STATUS, array() ),
			array(
				'last_run'   => '',
				'last_rows'  => 0,
				'last_error' => '',
				'last_ok'    => 0,
			)
		);
	}

	public static function set_status( array $changes ): void {
		update_option( self::STATUS, array_merge( self::status(), $changes ), false );
	}

	/** Shared secret for the external cron endpoint; generated once on first use. */
	public static function token(): string {
		$token = (string) get_option( self::TOKEN, '' );
		if ( ! preg_match( '/^[a-f0-9]{48}$/', $token ) ) {
			$token = bin2hex( random_bytes( 24 ) );
			update_option( self::TOKEN, $token, false );
		}
		return $token;
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	public function register_setting(): void {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * @param mixed $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$old   = self::get();
		$out   = $old;

		$out['enabled'] = empty( $input['enabled'] ) ? 0 : 1;

		$id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $input['spreadsheet_id'] ?? '' ) );
		if ( '' === $id || strlen( $id ) >= 20 ) {
			$out['spreadsheet_id'] = $id;
		} else {
			$out['spreadsheet_id'] = $old['spreadsheet_id'];
			add_settings_error( self::OPTION, 'cic_sheets_id', __( 'Spreadsheet ID looks too short. Paste the ID from the Sheet URL (the long value between /d/ and /edit).', 'cater-ice-cream-core' ) );
		}

		$name = trim( sanitize_text_field( (string) ( $input['sheet_name'] ?? '' ) ) );
		$out['sheet_name'] = '' === $name ? self::defaults()['sheet_name'] : mb_substr( $name, 0, 60 );

		$path = trim( (string) ( $input['credentials_path'] ?? '' ) );
		if ( '' !== $path && ( ! preg_match( '#^/#', $path ) || ! is_readable( $path ) ) ) {
			$out['credentials_path'] = $old['credentials_path'];
			add_settings_error( self::OPTION, 'cic_sheets_cred', __( 'Credentials file must be an absolute path that the web server can read. The previous value was kept.', 'cater-ice-cream-core' ) );
		} else {
			$out['credentials_path'] = $path;
		}

		$out['sync_limit'] = max( 1, min( 500, (int) ( $input['sync_limit'] ?? 200 ) ) );

		return $out;
	}
}