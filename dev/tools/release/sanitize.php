<?php
/**
 * Cleans a DISPOSABLE database copy so it can travel in the release package. Never run it on the
 * working site: it exits unless the disposable config (CIC_DISPOSABLE) is loaded and the database is
 * a cic_* copy.
 *
 *   cd <disposable site> && CIC_DRILL_DB=cic_rc_build wp eval-file <repo>/dev/tools/release/sanitize.php --skip-plugins --skip-themes
 *
 * Removes: Flamingo messages, contacts and outbound records (private enquiry data) with their meta
 * and term links; enquiry coordination rows; WooCommerce sessions and rate limits; finished Action
 * Scheduler history; transients; database-stored keys/salts (WordPress regenerates them); login
 * sessions and location data from user meta; local fixture coverage (ignored outside local, never
 * approved) and its import history.
 * Neutralises: the local administrator's email and password, and the admin email in site settings.
 * Keeps: content, products, events, pairings, media records, menus, design/header/footer settings,
 * CF7 forms, Enquiry settings (initial operator recipient from the specification), seed logs and
 * the location cache version (bumped, never lowered).
 *
 * Prints counts only; no personal data or credentials.
 *
 * @package CIC\Tools
 */

defined( 'WP_CLI' ) || exit;

global $wpdb;

if ( ! defined( 'CIC_DISPOSABLE' ) || true !== CIC_DISPOSABLE || ! str_starts_with( DB_NAME, 'cic_' ) ) {
	WP_CLI::error( 'Refusing: this is not a disposable cic_* copy.' );
}

$p        = $wpdb->prefix;
$report   = array();
$placeholder = 'admin@example.invalid';

// Orders must not exist in a catalogue-only site; stop if any do (they would be customer data).
$orders = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}wc_orders" ) + (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}posts WHERE post_type IN ('shop_order','shop_order_refund','shop_order_placehold')" );
if ( $orders > 0 ) {
	WP_CLI::error( "Refusing: {$orders} order records found; review them before packaging." );
}

// 1. Flamingo (all private enquiry/contact data).
$ids = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$p}posts WHERE post_type IN ('flamingo_inbound','flamingo_contact','flamingo_outbound')" ) );
$report['flamingo records removed'] = count( $ids );
foreach ( array_chunk( $ids, 500 ) as $chunk ) {
	$in = implode( ',', $chunk );
	$wpdb->query( "DELETE FROM {$p}postmeta WHERE post_id IN ({$in})" );
	$wpdb->query( "DELETE FROM {$p}term_relationships WHERE object_id IN ({$in})" );
	$wpdb->query( "DELETE FROM {$p}comments WHERE comment_post_ID IN ({$in})" );
	$wpdb->query( "DELETE FROM {$p}posts WHERE ID IN ({$in}) OR (post_type = 'revision' AND post_parent IN ({$in}))" );
}
$report['enquiry coordination rows removed'] = (int) $wpdb->query( "DELETE FROM {$p}cic_submissions" );

// 2. WooCommerce runtime data.
$report['woocommerce sessions removed'] = (int) $wpdb->query( "DELETE FROM {$p}woocommerce_sessions" );
$wpdb->query( "DELETE FROM {$p}wc_rate_limits" );

// 3. Action Scheduler history (pending jobs stay; recurring jobs re-create themselves).
$report['finished scheduled actions removed'] = (int) $wpdb->query( "DELETE FROM {$p}actionscheduler_actions WHERE status IN ('complete','failed','canceled')" );
$wpdb->query( "DELETE FROM {$p}actionscheduler_logs" );
$wpdb->query( "DELETE FROM {$p}actionscheduler_claims" );

// 4. Transients and database-stored keys/salts.
$report['transients removed'] = (int) $wpdb->query( "DELETE FROM {$p}options WHERE option_name LIKE '\\_transient\\_%' OR option_name LIKE '\\_site\\_transient\\_%'" );
$keys = array( 'auth_key', 'auth_salt', 'secure_auth_key', 'secure_auth_salt', 'logged_in_key', 'logged_in_salt', 'nonce_key', 'nonce_salt', 'secret_key', 'recovery_keys' );
$report['stored keys/salts removed'] = (int) $wpdb->query( "DELETE FROM {$p}options WHERE option_name IN ('" . implode( "','", $keys ) . "')" );

// 5. Users: neutralise the local administrator(s); the target site sets its own account details.
$users = $wpdb->get_results( "SELECT ID, user_email FROM {$p}users", ARRAY_A );
$admin_emails = array();
foreach ( $users as $i => $u ) {
	$admin_emails[] = strtolower( (string) $u['user_email'] );
	$email = 0 === $i ? $placeholder : 'user' . (int) $u['ID'] . '@example.invalid';
	$wpdb->update(
		"{$p}users",
		array(
			'user_email'          => $email,
			'user_pass'           => wp_hash_password( wp_generate_password( 64, true, true ) ),
			'user_activation_key' => '',
			'user_url'            => '',
		),
		array( 'ID' => (int) $u['ID'] )
	);
}
$report['user accounts neutralised'] = count( $users );
$report['user meta removed'] = (int) $wpdb->query( "DELETE FROM {$p}usermeta WHERE meta_key IN ('session_tokens','community-events-location','wc_last_active','_woocommerce_persistent_cart_1','_application_passwords','default_password_nag') OR meta_key LIKE '\\_woocommerce\\_persistent\\_cart%'" );

// 6. The administrator's address anywhere in site settings, except the Enquiry settings (the
//    specification's initial operator recipient, admin-editable on the target).
$replace = static function ( $value ) use ( &$replace, $admin_emails, $placeholder ) {
	if ( is_array( $value ) ) {
		return array_map( $replace, $value );
	}
	return is_string( $value ) ? str_ireplace( $admin_emails, $placeholder, $value ) : $value;
};
$changed = array();
foreach ( $admin_emails as $email ) {
	$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$p}options WHERE option_value LIKE %s AND option_name <> 'cic_enquiry_settings'", '%' . $wpdb->esc_like( $email ) . '%' ) );
	foreach ( $names as $name ) {
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$p}options WHERE option_name = %s", $name ) );
		$new = maybe_serialize( $replace( maybe_unserialize( $raw ) ) );
		if ( $new !== $raw ) {
			$wpdb->update( "{$p}options", array( 'option_value' => $new ), array( 'option_name' => $name ) );
			$changed[] = $name;
		}
	}
}
$report['settings with the admin email replaced'] = count( array_unique( $changed ) ) . ( $changed ? ' (' . implode( ', ', array_unique( $changed ) ) . ')' : '' );

// 7. Local fixture coverage: test data, ignored outside local/development, never approved.
$report['fixture city-ZIP links removed'] = (int) $wpdb->query( "DELETE FROM {$p}cic_city_zips WHERE dataset = 'fixture'" );
$report['fixture ZIPs removed']           = (int) $wpdb->query( "DELETE FROM {$p}cic_zips WHERE dataset = 'fixture'" );
$fixture_cities = array_map( 'intval', $wpdb->get_col( "SELECT id FROM {$p}cic_cities WHERE dataset = 'fixture'" ) );
if ( $fixture_cities ) {
	$in        = implode( ',', $fixture_cities );
	$editorial = array_map( 'intval', $wpdb->get_col( "SELECT editorial_post_id FROM {$p}cic_city_content WHERE city_id IN ({$in})" ) );
	if ( $editorial ) {
		WP_CLI::error( 'Refusing: fixture cities have editorial content; review it before packaging.' );
	}
	$wpdb->query( "DELETE FROM {$p}cic_city_slugs WHERE city_id IN ({$in})" );
	$wpdb->query( "DELETE FROM {$p}cic_cities WHERE id IN ({$in})" );
}
$report['fixture cities removed'] = count( $fixture_cities );
$report['location import/audit history removed'] = (int) $wpdb->query( "DELETE FROM {$p}options WHERE option_name IN ('cic_location_imports','cic_location_audit','cic_location_unlinks')" );
// Cache version only ever goes up.
$cache = (int) $wpdb->get_var( "SELECT option_value FROM {$p}options WHERE option_name = 'cic_locations_cache_version'" );
$wpdb->query( $wpdb->prepare( "UPDATE {$p}options SET option_value = %d WHERE option_name = 'cic_locations_cache_version'", $cache + 1 ) );

// 8. Leftover checks.
$report['remaining flamingo records'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}posts WHERE post_type LIKE 'flamingo%'" );
$report['remaining fixture rows']     = (int) $wpdb->get_var( "SELECT (SELECT COUNT(*) FROM {$p}cic_cities WHERE dataset='fixture') + (SELECT COUNT(*) FROM {$p}cic_zips WHERE dataset='fixture') + (SELECT COUNT(*) FROM {$p}cic_city_zips WHERE dataset='fixture')" );

foreach ( $report as $k => $v ) {
	WP_CLI::log( str_pad( $k, 42 ) . ' ' . $v );
}
WP_CLI::success( 'Disposable copy sanitised.' );
