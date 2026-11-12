<?php
/**
 * Plugin Name: Cater Ice Cream — local mail capture
 * Description: LOCAL ONLY. Sends WordPress mail to Mailpit over SMTP (127.0.0.1:1025). Inactive on any environment other than "local".
 * Version:     1.0.0
 *
 * Why: on this Windows/Laragon setup PHP's mail() works from the CLI but fails inside the web
 * server's php-cgi process ("Could not instantiate mail function"), so form notifications sent
 * during web requests could not reach the local mail capture. Mailpit has no relay configured, so
 * nothing leaves this machine. Staging/production mail (verified sender domain) is configured at
 * deployment and is not affected by this file.
 *
 * @package CIC
 */

defined( 'ABSPATH' ) || exit;

if ( 'local' === wp_get_environment_type() ) {
	add_action(
		'phpmailer_init',
		static function ( $phpmailer ): void {
			$phpmailer->isSMTP();
			$phpmailer->Host        = '127.0.0.1';
			$phpmailer->Port        = 1025;
			$phpmailer->SMTPAuth    = false;
			$phpmailer->SMTPAutoTLS = false;
			$phpmailer->SMTPSecure  = '';
		}
	);
}
