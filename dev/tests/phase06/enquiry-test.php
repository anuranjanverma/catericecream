<?php
/**
 * Phase 06 server checks (local only): architecture, validation, coverage via labelled fixtures,
 * LA dates, atomic claims, abuse controls, settings, workflow/reminders, roles, export safety and
 * privacy surfaces. Only test-created records are removed (state.php restore in `finally`).
 *
 *   wp eval-file dev/tests/phase06/enquiry-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;
'local' === wp_get_environment_type() || WP_CLI::error( 'Local environment only.' );

use CIC\Enquiries\Admin\EnquiriesPage;
use CIC\Enquiries\Forms;
use CIC\Enquiries\Guard;
use CIC\Enquiries\Roles;
use CIC\Enquiries\Settings;
use CIC\Enquiries\Store;
use CIC\Enquiries\Validator;
use CIC\Enquiries\Workflow;

$GLOBALS['p6'] = array();
function p6_check( string $label, bool $ok, $detail = null ): void {
	$GLOBALS['p6'][] = $ok;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && null !== $detail ? ' — ' . wp_json_encode( $detail ) : '' ) );
}
$state = static function ( string $a, string $f = '' ): string {
	$args = array( $a, $f ); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	ob_start();
	require __DIR__ . '/state.php';
	return (string) ob_get_clean();
};
global $wpdb;
$out      = ABSPATH . 'dev/tests/output/phase06';
$snapshot = $out . '/state-server.json';
wp_mkdir_p( $out );
$state( 'snapshot', $snapshot );
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID' ) );
wp_set_current_user( $admins[0]->ID );

$tz     = new DateTimeZone( 'America/Los_Angeles' );
$today  = ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
$future = ( new DateTimeImmutable( 'now', $tz ) )->modify( '+60 days' )->format( 'Y-m-d' );
$base   = array(
	'event'        => 'birthday',
	'event_date'   => $future,
	'guests'       => '40',
	'location'     => '',
	'treats'       => array( 'dippin-dots' ),
	'your_name'    => 'Test Person',
	'your_email'   => 'p6-server@example.test',
	'contact_pref' => 'email',
);

try {
	WP_CLI::log( '== A. Architecture' );
	p6_check( 'A1 CF7 + Flamingo active and both forms configured (IDs from settings)', defined( 'WPCF7_VERSION' ) && defined( 'FLAMINGO_VERSION' ) && Forms::id( 'quote' ) > 0 && Forms::id( 'contact' ) > 0 && Forms::available() );
	p6_check( 'A2 no cic_lead post type', ! post_type_exists( 'cic_lead' ) );
	$routes = array_keys( rest_get_server()->get_routes() );
	$extra  = array_values( array_filter( $routes, static fn( $r ) => str_starts_with( $r, '/cic/' ) && ! in_array( $r, array( '/cic/v1', '/cic/v1/coverage', '/cic/v1/locations', '/cic/v1/location', '/cic/v1/quote-draft', '/cic/v1/quote-draft/save' ), true ) ) );
	p6_check( 'A3 no CIC submission endpoint (only coverage/locations, the location label lookup and the draft-only saved-progress routes; the final submission is CF7)', ! $extra, $extra );
	p6_check( 'A4 CF7 mail is skipped for our forms (core plugin is the single mail owner)', true === apply_filters( 'wpcf7_skip_mail', false, wpcf7_contact_form( Forms::id( 'quote' ) ) ) && true === apply_filters( 'wpcf7_skip_mail', false, wpcf7_contact_form( Forms::id( 'contact' ) ) ) );
	$markup = wpcf7_contact_form( Forms::id( 'quote' ) )->prop( 'form' );
	p6_check( 'A5 one CF7 form holds both steps (code-owned markup, 2026-10-23)', 2 === substr_count( $markup, 'class="cic-qf__step"' ) && str_contains( $markup, '[cic_field treats_unsure]' ) && str_contains( $markup, '[cic_guard cic_guard]' ) );
	p6_check( 'A6 Flamingo messages are private (not public, not in REST)', ! get_post_type_object( 'flamingo_inbound' )->public && ! get_post_type_object( 'flamingo_inbound' )->show_in_rest );

	WP_CLI::log( '== B. Validation' );
	// Fixture coverage cases, found from the data rather than assumed.
	$repo     = CIC\Locations\Service::repository();
	$zips     = $wpdb->get_col( 'SELECT zip FROM ' . CIC\Locations\Tables::name( 'zips' ) . ' ORDER BY zip' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$covered  = '';
	foreach ( $zips as $z ) {
		if ( 'covered' === $repo->evaluate_coverage( $z )['status'] ) {
			$covered = $z;
			break;
		}
	}
	$unknown = '';
	for ( $n = 93001; $n < 96100 && '' === $unknown; $n += 13 ) {
		$unknown = in_array( (string) $n, $zips, true ) ? '' : (string) $n;
	}
	$base['location'] = $covered;
	$ok               = Validator::quote( $base );
	p6_check( 'B1 a complete quote validates; labels resolved on the server; fixture coverage flagged', ! $ok['errors'] && 'covered' === $ok['data']['coverage']['status'] && true === $ok['data']['coverage']['fixture'] && 'Birthday' === $ok['data']['event_label'] && array( 'Dippin’ Dots' ) !== array() , $ok );
	$r = Validator::quote( array_merge( $base, array( 'event_date' => '', 'date_unknown' => '1' ) ) );
	p6_check( 'B2 "Date not set yet" works (no date, label Date to be confirmed)', ! $r['errors'] && '' === $r['data']['date'] && 'Date to be confirmed' === $r['data']['date_label'], $r['errors'] );
	$r = Validator::quote( array_merge( $base, array( 'event_date' => '' ) ) );
	p6_check( 'B3 date required unless "Date not set yet"', isset( $r['errors']['event_date'] ) );
	$yesterday = ( new DateTimeImmutable( 'now', $tz ) )->modify( '-1 day' )->format( 'Y-m-d' );
	$r1        = Validator::quote( array_merge( $base, array( 'event_date' => $yesterday ) ) );
	$r2        = Validator::quote( array_merge( $base, array( 'event_date' => $today ) ) );
	$r3        = Validator::quote( array_merge( $base, array( 'event_date' => '2027-02-30' ) ) );
	p6_check( 'B4 LA-local dates: today accepted, yesterday and impossible dates rejected', isset( $r1['errors']['event_date'] ) && ! isset( $r2['errors']['event_date'] ) && isset( $r3['errors']['event_date'] ) );
	// User-facing dates are MM/DD/YYYY (refinements, 2026-10-21).
	p6_check( 'B5 a DST-boundary date keeps its calendar day (no UTC shift)', '03/14/2027' === Validator::date_label( '2027-03-14' ) && '11/07/2027' === Validator::date_label( '2027-11-07' ) );
	$r = Validator::quote( array_merge( $base, array( 'event' => 'other', 'event_other' => 'Graduation party' ) ) );
	p6_check( 'B6 Other occasion works and keeps the description', ! $r['errors'] && 'other' === $r['data']['event'] && 'Graduation party' === $r['data']['event_label'] );
	$r = Validator::quote( array_merge( $base, array( 'event' => 'not-an-event' ) ) );
	p6_check( 'B7 unpublished/unknown event key rejected', isset( $r['errors']['event'] ) );
	$r = Validator::quote( array_merge( $base, array( 'treats' => array( 'dippin-dots', 'classic-ice-cream' ) ) ) );
	p6_check( 'B8 multiple treats accepted', ! $r['errors'] && 2 === count( $r['data']['treats'] ) );
	$r1 = Validator::quote( array_merge( $base, array( 'treats' => array(), 'treats_unsure' => '1' ) ) );
	$r2 = Validator::quote( array_merge( $base, array( 'treats_unsure' => '1' ) ) );
	$r3 = Validator::quote( array_merge( $base, array( 'treats' => array() ) ) );
	$r4 = Validator::quote( array_merge( $base, array( 'treats' => array( '../etc', 'dippin-dots' ) ) ) );
	p6_check( 'B9 "Not sure yet" alone accepted; with treats, without anything, or with a malformed key rejected', ! $r1['errors'] && isset( $r2['errors']['treats'] ) && isset( $r3['errors']['treats'] ) && isset( $r4['errors']['treats'] ), array( $r1['errors'], $r2['errors'], $r3['errors'], $r4['errors'] ) );
	$g = array();
	foreach ( array( '0', '-3', 'abc', '10001', '4.5' ) as $v ) {
		$g[ $v ] = isset( Validator::quote( array_merge( $base, array( 'guests' => $v ) ) )['errors']['guests'] );
	}
	p6_check( 'B10 guest count must be a whole number 1–10000', ! in_array( false, $g, true ), $g );
	$r1 = Validator::quote( array_merge( $base, array( 'contact_pref' => 'phone' ) ) );
	$r2 = Validator::quote( array_merge( $base, array( 'contact_pref' => 'phone', 'phone' => '818-555-0100' ) ) );
	$r3 = Validator::quote( array_merge( $base, array( 'phone' => '12345' ) ) );
	p6_check( 'B11 phone optional unless Phone is preferred; validated and normalised', isset( $r1['errors']['phone'] ) && ! $r2['errors'] && '(818) 555-0100' === $r2['data']['phone'] && isset( $r3['errors']['phone'] ) );
	$r1 = Validator::quote( array_merge( $base, array( 'your_email' => 'not-an-email' ) ) );
	$r2 = Validator::quote( array_merge( $base, array( 'notes' => str_repeat( 'x', 1501 ) ) ) );
	$r3 = Validator::quote( array_merge( $base, array( 'your_name' => '' ) ) );
	p6_check( 'B12 email format, text length limits and required name enforced', isset( $r1['errors']['your_email'], $r2['errors']['notes'], $r3['errors']['your_name'] ) );
	$c1 = Validator::contact( array( 'topic' => 'general', 'your_name' => 'A', 'your_email' => 'c@example.test', 'message' => 'Hello' ) );
	$c2 = Validator::contact( array( 'topic' => 'hack', 'your_name' => '', 'your_email' => 'x', 'message' => '' ) );
	p6_check( 'B13 contact form validation (topic allowlist, name, email, message)', ! $c1['errors'] && 4 === count( $c2['errors'] ), $c2['errors'] );

	WP_CLI::log( '== C. Coverage through the existing repository (fixtures only)' );
	$excluded = Validator::location( 'Compton', '' );
	$shared1  = Validator::location( '90220', '' );
	$shared2  = Validator::location( '90220', 'Compton' );
	$shared3  = Validator::location( '90220', 'Rancho Dominguez' );
	$unk1     = Validator::location( $unknown, '' );
	$unk2     = Validator::location( $unknown, 'Somewhere' );
	$bad      = Validator::location( '12', '' );
	p6_check( 'C1 excluded city is blocked (outside_area)', 'location' === $excluded['error_field'] && 'outside_area' === $excluded['coverage']['status'], $excluded );
	p6_check( 'C2 shared ZIP asks for the venue city; with an excluded venue it is blocked; with a served one accepted for review', 'location_detail' === $shared1['error_field'] && 'location' === $shared2['error_field'] && 'outside_area' === $shared2['coverage']['status'] && '' === $shared3['error_field'], array( $shared1, $shared2, $shared3 ) );
	p6_check( 'C3 unknown ZIP asks for clarification, then is accepted as "unknown" (never claimed covered)', 'location_detail' === $unk1['error_field'] && '' === $unk2['error_field'] && 'unknown' === $unk2['coverage']['status'], array( $unk1, $unk2 ) );
	p6_check( 'C4 malformed location rejected', 'location' === $bad['error_field'] );
	p6_check( 'C5 every fixture-based result is labelled fixture (not production coverage)', $excluded['coverage']['fixture'] && $shared3['coverage']['fixture'] );

	WP_CLI::log( '== D. Atomic claims' );
	$key = Forms::new_key();
	$c1  = Store::claim( 'quote', $key );
	$c2  = Store::claim( 'quote', $key );
	p6_check( 'D1 a submission key can be claimed once; a concurrent second claim sees "processing"', 'claimed' === $c1['state'] && 'processing' === $c2['state'] );
	Store::complete( $key, 999999999, array( 'event' => 'Birthday' ) );
	$c3 = Store::claim( 'quote', $key );
	p6_check( 'D2 a retry after storage replays the stored receipt', 'stored' === $c3['state'] && 32 === strlen( (string) $c3['row']['receipt'] ) && 'Birthday' === Store::receipt( (string) $c3['row']['receipt'] )['event'] );
	p6_check( 'D3 notification channels are claimed atomically (second claim fails)', Store::claim_channel( 999999999, 'operator' ) && ! Store::claim_channel( 999999999, 'operator' ) );
	$key2 = Forms::new_key();
	Store::claim( 'quote', $key2 );
	Store::release( $key2 );
	p6_check( 'D4 a failed storage releases the key so the same submission can retry', 'claimed' === Store::claim( 'quote', $key2 )['state'] );
	Store::release( $key2 );
	$wpdb->delete( Store::table(), array( 'flamingo_id' => 999999999 ) );
	p6_check( 'D5 malformed keys and receipts are refused', ! Store::valid_key( 'short' ) && ! Store::valid_key( str_repeat( 'a', 20 ) . '<' ) && null === Store::receipt( 'not-a-receipt' ) );

	WP_CLI::log( '== E. Abuse controls' );
	$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
	$state( 'reset-limits' );
	p6_check( 'E1 honeypot, forged token and too-fast submissions are spam; a normal one is not', 'honeypot' === Guard::spam_reason( array( 'cic_website' => 'x', 'cic_ts' => Guard::token( time() - 10 ) ) ) && 'token' === Guard::spam_reason( array( 'cic_ts' => '123.abc' ) ) && 'too_fast' === Guard::spam_reason( array( 'cic_ts' => Guard::token() ) ) && '' === Guard::spam_reason( array( 'cic_ts' => Guard::token( time() - 10 ) ) ) );
	p6_check( 'E2 an old token is "expired" (send again), not spam', Guard::expired( Guard::token( time() - Guard::MAX_AGE - 5 ) ) && ! Guard::expired( Guard::token( time() - 60 ) ) );
	for ( $i = 0; $i < Guard::ACCEPT_LIMIT; $i++ ) {
		Guard::accepted();
	}
	p6_check( 'E3 per-IP request limit (hashed address) turns further submissions into spam', 'rate_limit' === Guard::spam_reason( array( 'cic_ts' => Guard::token( time() - 10 ) ) ) );
	$acks = array();
	for ( $i = 0; $i < Guard::ACK_LIMIT + 1; $i++ ) {
		$acks[] = Guard::allow_ack( 'ack-limit@example.test' );
	}
	p6_check( 'E4 acknowledgements to one address are capped per day', array( true, true, true, false ) === $acks, $acks );
	$state( 'reset-limits' );
	$raw_ip = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value LIKE %s", '%203.0.113.9%', '%203.0.113.9%' ) );
	p6_check( 'E5 the raw IP address is never stored', 0 === (int) $raw_ip );

	WP_CLI::log( '== F. Settings' );
	p6_check( 'F1 initial operator recipient is anuranjan.verma25@gmail.com and separate from the public email', 'anuranjan.verma25@gmail.com' === Settings::defaults()['recipient'] && CIC\Site\Contact::get()['email'] !== Settings::defaults()['recipient'] );
	$saved = Settings::sanitize( array( 'recipient' => 'not an email', 'reminders_enabled' => '1', 'reminder_time' => '25:99' ) );
	p6_check( 'F2 invalid recipient is refused (previous kept); reminders need a valid time', Settings::get()['recipient'] === $saved['recipient'] && '' === $saved['reminder_time'] && ! Settings::reminders_ready() );
	p6_check( 'F3 reminders are off by default (timing pending)', 0 === Settings::defaults()['reminders_enabled'] && '' === Settings::defaults()['reminder_time'] );

	WP_CLI::log( '== G. Workflow and reminders on a stored enquiry' );
	$fid = (int) wp_insert_post( array( 'post_type' => 'flamingo_inbound', 'post_status' => 'publish', 'post_title' => 'p6 test' ) );
	update_post_meta( $fid, '_from_email', 'p6-workflow@example.test' );
	Workflow::init( $fid, array( 'type' => 'quote', 'event_label' => 'Birthday' ), array() );
	p6_check( 'G1 workflow starts as New and references the Flamingo record', Workflow::is_enquiry( $fid ) && 'new' === Workflow::status( $fid ) );
	$editor = wp_insert_user( array( 'user_login' => 'cic_p6_ed', 'user_pass' => wp_generate_password( 32 ), 'user_email' => 'cic-p6-ed@example.test', 'role' => 'editor' ) );
	update_user_meta( $editor, '_cic_p6_test', 1 );
	p6_check( 'G2 only enquiry managers can be assigned', is_wp_error( Workflow::set_assignee( $fid, $editor ) ) && true === Workflow::set_assignee( $fid, $admins[0]->ID ) );
	p6_check( 'G3 statuses, notes and follow-up validation', true === Workflow::set_status( $fid, 'contacted' ) && is_wp_error( Workflow::set_status( $fid, 'paid' ) ) && true === Workflow::add_note( $fid, 'Called, left a message' ) && 1 === count( Workflow::notes( $fid ) ) && is_wp_error( Workflow::set_follow_up( $fid, '2027-13-40' ) ) );
	Workflow::set_follow_up( $fid, $future );
	p6_check( 'G4 reminders disabled → nothing scheduled', 0 === (int) json_decode( $state( 'actions', (string) $fid ) ) );
	Settings::update( array( 'reminders_enabled' => 1, 'reminder_time' => '09:00', 'reminder_days' => 1 ) );
	Workflow::schedule( $fid );
	Workflow::schedule( $fid );
	Workflow::set_follow_up( $fid, ( new DateTimeImmutable( 'now', $tz ) )->modify( '+70 days' )->format( 'Y-m-d' ) );
	p6_check( 'G5 configured reminders schedule exactly one job per enquiry (rescheduling replaces it)', 1 === (int) json_decode( $state( 'actions', (string) $fid ) ) );
	Workflow::set_status( $fid, 'lost' );
	p6_check( 'G6 closing the enquiry cancels its reminder', 0 === (int) json_decode( $state( 'actions', (string) $fid ) ) );
	Workflow::set_status( $fid, 'contacted' );
	wp_trash_post( $fid );
	p6_check( 'G7 trashing the message cancels its reminder', 0 === (int) json_decode( $state( 'actions', (string) $fid ) ) );
	wp_delete_post( $fid, true );

	WP_CLI::log( '== H. Access and export safety' );
	p6_check( 'H1 administrators have manage_cic_enquiries; editors do not; the Enquiry manager role has only read + the capability', get_role( 'administrator' )->has_cap( Roles::CAPABILITY ) && ! get_role( 'editor' )->has_cap( Roles::CAPABILITY ) && array( 'read' => true, Roles::CAPABILITY => true ) == get_role( Roles::ROLE )->capabilities ); // phpcs:ignore Universal.Operators.StrictComparisons
	p6_check( 'H2 enquiry managers cannot open Flamingo screens or exports (contact book stays admin-only)', ! get_role( Roles::ROLE )->has_cap( 'edit_users' ) && ! get_role( Roles::ROLE )->has_cap( 'manage_options' ) );
	p6_check( 'H3 CSV export cells are formula-protected', "'=HYPERLINK(\"x\")" === EnquiriesPage::csv_cell( '=HYPERLINK("x")' ) && "'+1" === EnquiriesPage::csv_cell( '+1' ) && "'@a" === EnquiriesPage::csv_cell( '@a' ) && 'Plain' === EnquiriesPage::csv_cell( 'Plain' ) );
	p6_check( 'H4 enquiries are excluded from core sitemaps and public queries', ! isset( apply_filters( 'wp_sitemaps_post_types', get_post_types( array( 'public' => true ), 'objects' ) )['flamingo_inbound'] ) && ! is_post_type_viewable( 'flamingo_inbound' ) );
	require_once WP_PLUGIN_DIR . '/flamingo/admin/includes/privacy.php'; // Flamingo registers erasers on admin requests only.
	$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
	$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
	p6_check( 'H5 privacy export includes enquiries + workflow; Flamingo eraser removes the message (and its workflow meta)', isset( $exporters['cic-enquiries'], $erasers['flamingo-inbound'] ) );
} catch ( \Throwable $e ) {
	p6_check( 'Unexpected error: ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine(), false );
} finally {
	$state( 'restore', $snapshot );
	@unlink( $snapshot ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
WP_CLI::log( sprintf( 'Phase 06 server checks: %d/%d passed', count( array_filter( $GLOBALS['p6'] ) ), count( $GLOBALS['p6'] ) ) );
