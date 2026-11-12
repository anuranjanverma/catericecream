<?php
/**
 * Google Analytics 4 (gtag.js), added once to the front-end <head> (wp_head).
 *
 * The tag is printed only when ALL of these hold (status()):
 * - Analytics is enabled and a valid Measurement ID is saved (Settings);
 * - the environment type is "production" (local, development and staging never send data);
 * - both the site address and the requested host are catericecream.com or www.catericecream.com
 *   (so .test, localhost, IPs, preview hosts and copies of the database never send data);
 * - the visitor is not a logged-in administrator;
 * - it is a normal front-end page (not wp-admin, AJAX, REST, cron, CLI, feeds, previews or the
 *   Customizer).
 *
 * Privacy: the snippet keeps any existing window.dataLayer (it only creates the array when it is
 * missing) and sets no consent state of its own, so consent defaults printed earlier (e.g. by a
 * consent plugin at a lower wp_head priority or as an enqueued head script) stay in charge. Quote
 * and contact receipts (?received= / ?sent=) are removed from the page location and referrer
 * before the config call, so no per-inquiry identifier reaches Analytics. No custom events are sent
 * here; the existing keys-only quote pushes in quote.js are unchanged.
 *
 * @package CIC
 */

namespace CIC\Analytics;

defined( 'ABSPATH' ) || exit;

final class Service {

	/** The only hosts that may send Analytics data. */
	public const PRODUCTION_HOSTS = array( 'catericecream.com', 'www.catericecream.com' );

	/** After enqueued head scripts (priority 9), e.g. consent defaults, and before late head output. */
	public const HEAD_PRIORITY = 10;

	private static bool $printed = false;

	public function register(): void {
		( new Settings() )->register();
		add_action( 'wp_head', array( $this, 'head' ), self::HEAD_PRIORITY );
	}

	/**
	 * Where the site runs. Tests may filter this to simulate the production host; nothing in the
	 * product code does.
	 *
	 * @return array{type: string, home_host: string, request_host: string}
	 */
	public static function environment(): array {
		$request = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		$env     = array(
			'type'         => wp_get_environment_type(),
			'home_host'    => strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
			'request_host' => (string) preg_replace( '/:\d+$/', '', $request ),
		);
		$env = (array) apply_filters( 'cic_analytics_environment', $env );
		return array(
			'type'         => (string) ( $env['type'] ?? '' ),
			'home_host'    => strtolower( (string) ( $env['home_host'] ?? '' ) ),
			'request_host' => strtolower( (string) ( $env['request_host'] ?? '' ) ),
		);
	}

	/**
	 * Whether the tag may be printed, with the reason when it may not.
	 *
	 * @param bool $request Also check the current request (front-end page, visitor role). The
	 *                      settings screen passes false to report the site-wide decision.
	 * @return array{active: bool, reason: string, label: string}
	 */
	public static function status( bool $request = true ): array {
		$s   = Settings::get();
		$env = self::environment();
		if ( empty( $s['enabled'] ) ) {
			return self::result( 'disabled', __( 'Off: Google Analytics is switched off.', 'cater-ice-cream-core' ) );
		}
		if ( '' === Settings::measurement_id() ) {
			return self::result( 'no_id', __( 'Off: no valid Measurement ID is saved.', 'cater-ice-cream-core' ) );
		}
		if ( 'production' !== $env['type'] ) {
			/* translators: %s: environment type. */
			return self::result( 'environment', sprintf( __( 'Off on this site: the environment is “%s”. Only the production site sends data.', 'cater-ice-cream-core' ), $env['type'] ) );
		}
		$hosts = self::PRODUCTION_HOSTS;
		if ( ! in_array( $env['home_host'], $hosts, true ) || ( ( $request || '' !== $env['request_host'] ) && ! in_array( $env['request_host'], $hosts, true ) ) ) {
			/* translators: %s: host name. */
			return self::result( 'host', sprintf( __( 'Off on this site: %s is not a production address (catericecream.com or www.catericecream.com).', 'cater-ice-cream-core' ), in_array( $env['home_host'], $hosts, true ) ? $env['request_host'] : $env['home_host'] ) );
		}
		if ( $request ) {
			if ( ! self::front_end_page() ) {
				return self::result( 'context', __( 'Off for this request: not a public front-end page.', 'cater-ice-cream-core' ) );
			}
			if ( is_user_logged_in() && ( current_user_can( 'manage_options' ) || is_super_admin() ) ) {
				return self::result( 'administrator', __( 'Off for this visit: logged-in administrators are not tracked.', 'cater-ice-cream-core' ) );
			}
		}
		return array(
			'active' => true,
			'reason' => 'active',
			'label'  => __( 'Active: the Google tag is added to public pages for visitors (not for logged-in administrators).', 'cater-ice-cream-core' ),
		);
	}

	/** @return array{active: bool, reason: string, label: string} */
	private static function result( string $reason, string $label ): array {
		return array( 'active' => false, 'reason' => $reason, 'label' => $label );
	}

	private static function front_end_page(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}
		return ! ( is_feed() || is_preview() || is_customize_preview() || is_embed() || is_robots() || is_trackback() );
	}

	/** The Google tag, printed at most once per page. */
	public function head(): void {
		if ( self::$printed || ! self::status()['active'] ) {
			return;
		}
		self::$printed = true;
		echo self::snippet( Settings::measurement_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup; the ID matches Settings::ID_PATTERN.
	}

	/** The approved gtag.js snippet plus the receipt-redaction line. */
	public static function snippet( string $id ): string {
		if ( ! preg_match( Settings::ID_PATTERN, $id ) ) {
			return '';
		}
		return "<!-- Google tag (gtag.js) -->\n"
			. '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $id . '"></script>' . "\n"
			. "<script>\n"
			. "  window.dataLayer = window.dataLayer || [];\n"
			. "  function gtag(){dataLayer.push(arguments);}\n"
			. "  gtag('js', new Date());\n"
			. "  (function(){function c(u){try{u=new URL(u);u.searchParams.delete('received');u.searchParams.delete('sent');u.hash='';return u.href;}catch(e){return '';}}gtag('set',{page_location:c(location.href),page_referrer:c(document.referrer)});})();\n"
			. "  gtag('config', '" . $id . "');\n"
			. "</script>\n";
	}
}
