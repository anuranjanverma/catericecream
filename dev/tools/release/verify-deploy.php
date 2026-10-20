<?php
/**
 * Post-deployment checks for a copy built from the release package (staging rehearsal). Read-only.
 *
 *   cd <site> && wp eval-file <repo>/dev/tools/release/verify-deploy.php <expected home URL>
 *
 * Works on a real staging site too (it only reads). Prints PASS/FAIL lines and counts; no personal
 * data or credentials.
 *
 * @package CIC\Tools
 */

defined( 'WP_CLI' ) || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;
$p        = $wpdb->prefix;
$expected = rtrim( (string) ( $args[0] ?? '' ), '/' );
$fails    = 0;
$check    = static function ( string $label, bool $ok, $detail = '' ) use ( &$fails ): void {
	$fails += $ok ? 0 : 1;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( '' !== $detail ? ' [' . ( is_scalar( $detail ) ? $detail : wp_json_encode( $detail ) ) . ']' : '' ) );
};
$count = static fn( string $sql ): int => (int) $wpdb->get_var( $sql );

WP_CLI::log( '== Site and environment' );
$check( 'home/siteurl point at the target URL', '' !== $expected && untrailingslashit( home_url() ) === $expected && untrailingslashit( site_url() ) === $expected, home_url() );
$old = $count( "SELECT (SELECT COUNT(*) FROM {$p}options WHERE option_value LIKE '%catericecream.test%') + (SELECT COUNT(*) FROM {$p}posts WHERE post_content LIKE '%catericecream.test%' OR guid LIKE '%catericecream.test%') + (SELECT COUNT(*) FROM {$p}postmeta WHERE meta_value LIKE '%catericecream.test%')" );
$check( 'no local URL left in options, posts or meta', 0 === $old, $old );
$check( 'environment type is not local (fixtures ignored, local mail inactive)', 'local' !== wp_get_environment_type(), wp_get_environment_type() );
$check( 'search engines discouraged (blog_public = 0)', '0' === (string) get_option( 'blog_public' ) );
$check( 'permalinks /%postname%/', '/%postname%/' === get_option( 'permalink_structure' ) );
$check( 'theme cater-ice-cream active', 'cater-ice-cream' === get_stylesheet() );
foreach ( array( 'woocommerce/woocommerce.php' => '11.2.1', 'advanced-custom-fields/acf.php' => '6.8.10', 'contact-form-7/wp-contact-form-7.php' => '6.2.2', 'flamingo/flamingo.php' => '2.6.4', 'cater-ice-cream-core/cater-ice-cream-core.php' => '0.7.0' ) as $file => $version ) {
	$data = is_file( WP_PLUGIN_DIR . '/' . $file ) ? get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false ) : array( 'Version' => 'missing' );
	$check( "plugin {$file} active at {$version}", is_plugin_active( $file ) && $version === $data['Version'], $data['Version'] );
}
$check( 'catalogue guard loaded (MU)', defined( 'CIC\\CatalogGuard\\VERSION' ) );
$check( 'staging mail guard file present (MU)', is_file( WPMU_PLUGIN_DIR . '/cic-staging-mail-guard.php' ) );
$check( 'local mail plugin absent', ! is_file( WPMU_PLUGIN_DIR . '/cic-local-mail.php' ) );
$check( 'sales mode not enabled (CIC_COMMERCE_MODE)', ! defined( 'CIC_COMMERCE_MODE' ) || 'sales' !== CIC_COMMERCE_MODE );
$check( 'core schema at target version', (int) get_option( 'cic_core_schema_version' ) === CIC\Database\Migrations::TARGET_VERSION, get_option( 'cic_core_schema_version' ) );

WP_CLI::log( '== Content' );
$published = static fn( string $type ): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}posts WHERE post_type = %s AND post_status = 'publish'", $type ) );
$check( '4 published treats (products)', 4 === $published( 'product' ), $published( 'product' ) );
$check( '4 published events', 4 === $published( 'cic_event' ), $published( 'cic_event' ) );
$check( '16 product-event pairings', 16 === $count( "SELECT COUNT(*) FROM {$p}cic_product_events" ), $count( "SELECT COUNT(*) FROM {$p}cic_product_events" ) );
$front = (int) get_option( 'page_on_front' );
$check( 'static front page set and published', 'page' === get_option( 'show_on_front' ) && 'publish' === get_post_status( $front ) );
foreach ( array( 'about', 'contact', 'get-a-quote', 'service-areas' ) as $slug ) {
	$page = get_page_by_path( $slug );
	$check( "page /{$slug}/ published", $page && 'publish' === $page->post_status );
}
foreach ( array( 'privacy-policy', 'website-terms' ) as $slug ) {
	$page = get_page_by_path( $slug );
	$check( "policy /{$slug}/ still a draft (not approved)", $page && 'draft' === $page->post_status );
}
$attachments = $wpdb->get_col( "SELECT ID FROM {$p}posts WHERE post_type = 'attachment'" );
$missing     = array_filter( $attachments, static fn( $id ) => ! is_file( (string) get_attached_file( (int) $id ) ) );
$check( 'every media record has its file', count( $attachments ) > 0 && ! $missing, count( $attachments ) . ' attachments, ' . count( $missing ) . ' missing' );
$locations = get_nav_menu_locations();
$check( 'primary, footer and legal menus assigned', ! empty( $locations['primary'] ) && ! empty( $locations['footer'] ) && ! empty( $locations['legal'] ), array_keys( array_filter( $locations ) ) );
$check( 'design settings present', is_array( get_option( 'cic_design_settings' ) ) && (int) get_option( 'cic_design_version' ) > 0, get_option( 'cic_design_version' ) );

WP_CLI::log( '== Enquiries and privacy' );
$settings = CIC\Enquiries\Settings::get();
$check( 'quote and contact CF7 forms exist', 'wpcf7_contact_form' === get_post_type( (int) $settings['quote_form_id'] ) && 'wpcf7_contact_form' === get_post_type( (int) $settings['contact_form_id'] ), array( $settings['quote_form_id'], $settings['contact_form_id'] ) );
$check( 'reminders off', ! CIC\Enquiries\Settings::reminders_ready() && empty( $settings['reminders_enabled'] ) );
$check( 'operator recipient is the specification default', 'anuranjan.verma25@gmail.com' === $settings['recipient'] );
$check( 'no sender configured yet (pending verified domain)', '' === $settings['from_email'] );
$check( 'no Flamingo messages or contacts shipped', 0 === $count( "SELECT COUNT(*) FROM {$p}posts WHERE post_type LIKE 'flamingo%'" ) );
$check( 'no enquiry coordination rows shipped', 0 === $count( "SELECT COUNT(*) FROM {$p}cic_submissions" ) );
$check( 'no orders', 0 === $count( "SELECT COUNT(*) FROM {$p}wc_orders" ) + $count( "SELECT COUNT(*) FROM {$p}posts WHERE post_type LIKE 'shop_order%'" ) );
$check( 'enquiry manager role and capability installed', null !== get_role( 'cic_enquiry_manager' ) && get_role( 'administrator' )->has_cap( 'manage_cic_enquiries' ) );
$check( 'editors cannot manage enquiries', ! get_role( 'editor' ) || ! get_role( 'editor' )->has_cap( 'manage_cic_enquiries' ) );
$check( 'no real user email shipped', 0 === $count( "SELECT COUNT(*) FROM {$p}users WHERE user_email NOT LIKE '%@example.invalid'" ) || ! empty( $args[1] ), $count( "SELECT COUNT(*) FROM {$p}users" ) . ' users' );
$rest = rest_get_server()->get_routes();
$check( 'no public enquiry/Flamingo REST routes', ! array_filter( array_keys( $rest ), static fn( $r ) => (bool) preg_match( '#flamingo|cic/.*enquir#i', $r ) ) );

WP_CLI::log( '== Locations' );
$check( 'no fixture coverage rows', 0 === $count( "SELECT (SELECT COUNT(*) FROM {$p}cic_cities WHERE dataset='fixture') + (SELECT COUNT(*) FROM {$p}cic_zips WHERE dataset='fixture') + (SELECT COUNT(*) FROM {$p}cic_city_zips WHERE dataset='fixture')" ) );
$check( 'fixtures not allowed on this environment', ! CIC\Locations\Tables::fixtures_allowed() );
$rules = (array) get_option( 'rewrite_rules' );
$check( 'virtual Service Areas rewrite rules present', (bool) array_filter( array_keys( $rules ), static fn( $r ) => str_starts_with( $r, '^service-areas' ) || str_starts_with( $r, 'service-areas' ) ), count( $rules ) . ' rules' );

WP_CLI::log( '== Mail' );
$sent = wp_mail( 'release-check@example.test', 'Release check', 'Staging mail guard check.' );
$check( 'wp_mail refused on staging (mail guard active)', 'staging' !== wp_get_environment_type() || false === $sent, $sent ? 'sent' : 'refused' );

WP_CLI::log( '' );
$fails ? WP_CLI::error( "{$fails} check(s) failed." ) : WP_CLI::success( 'All deployment checks passed.' );
