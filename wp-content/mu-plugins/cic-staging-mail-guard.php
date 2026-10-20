<?php
/**
 * Plugin Name: Cater Ice Cream — staging mail guard
 * Description: STAGING ONLY. Stops WordPress sending email when the environment type is "staging", so staging enquiries, tests and reminders never reach real inboxes. Inactive on local (Mailpit capture) and production.
 * Version:     1.0.0
 * Author:      Web Karma
 * License:     GPL-2.0-or-later
 *
 * Why: BUILD_SPECIFICATION §17/§19 require that staging sends no real messages, and a staging copy
 * of the database still holds the live operator recipient. Without this file a staging host would
 * deliver notifications through its own mail() or SMTP setup.
 *
 * Behaviour on staging:
 * - Default: every wp_mail() call is refused (wp_mail() returns false). The enquiry is still saved;
 *   the Notifier records a failed channel and retries later, exactly as for a real mail outage.
 * - Optional capture inbox (wp-config.php on staging only, owner-approved sandbox address):
 *     define( 'CIC_STAGING_MAIL_TO', 'capture-inbox@example.test' );
 *   Every message then goes ONLY to that address (To replaced, Cc/Bcc removed).
 *
 * Production must not keep WP_ENVIRONMENT_TYPE = 'staging'; this guard is not a mail configuration.
 *
 * @package CIC
 */

defined( 'ABSPATH' ) || exit;

if ( 'staging' === wp_get_environment_type() ) {
	$cic_capture = defined( 'CIC_STAGING_MAIL_TO' ) && is_email( (string) CIC_STAGING_MAIL_TO ) ? (string) CIC_STAGING_MAIL_TO : '';

	if ( '' === $cic_capture ) {
		add_filter(
			'pre_wp_mail',
			static function ( $short_circuit ) {
				return null === $short_circuit ? false : $short_circuit;
			},
			1
		);
	} else {
		add_filter(
			'wp_mail',
			static function ( array $atts ) use ( $cic_capture ): array {
				$atts['to'] = array( $cic_capture );
				$headers    = is_array( $atts['headers'] ?? null ) ? $atts['headers'] : preg_split( "/\r\n|\n/", (string) ( $atts['headers'] ?? '' ) );
				$atts['headers'] = array_values(
					array_filter(
						(array) $headers,
						static fn( $h ): bool => '' !== trim( (string) $h ) && ! preg_match( '/^\s*(cc|bcc)\s*:/i', (string) $h )
					)
				);
				return $atts;
			},
			PHP_INT_MAX
		);
	}
	unset( $cic_capture );
}
