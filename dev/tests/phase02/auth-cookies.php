<?php
/**
 * Prints short-lived admin auth cookies (JSON) for the local headless browser test, or destroys
 * the session created for it. CLI only; never use outside local testing.
 *   wp eval-file dev/tests/phase02/auth-cookies.php create
 *   wp eval-file dev/tests/phase02/auth-cookies.php destroy <token>
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;
'local' === wp_get_environment_type() || WP_CLI::error( 'Local environment only.' );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID' ) );
$admin  = $admins[0] ?? WP_CLI::error( 'No administrator.' );

if ( 'destroy' === ( $args[0] ?? '' ) ) {
	WP_Session_Tokens::get_instance( $admin->ID )->destroy( (string) ( $args[1] ?? '' ) );
	WP_CLI::success( 'Test session destroyed.' );
	return;
}

$expires = time() + 2 * HOUR_IN_SECONDS;
$token   = WP_Session_Tokens::get_instance( $admin->ID )->create( $expires );
echo wp_json_encode(
	array(
		'token'   => $token,
		'cookies' => array(
			array( 'name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $admin->ID, $expires, 'secure_auth', $token ) ),
			array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $admin->ID, $expires, 'logged_in', $token ) ),
		),
	)
);
