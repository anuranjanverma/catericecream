<?php
/**
 * Phase 08 server-side checks: Our Treats, Events overview, About, Contact, policy template and
 * branded 404 — statuses, dynamic queries (temporary records), jump links, contextual quote links,
 * configured contact values, no alternative form/fake success, verified facts, internal links,
 * navigation, editability through stored content, policy outline, seeding. Restored in `finally`.
 *
 * Usage: wp eval-file dev/tests/phase08/pages-test.php
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Content\Catalog;
use CIC\Content\EventsOverview;
use CIC\Content\ViewModel;
use CIC\Site\Contact;

global $cic8;
$cic8 = array( 'pass' => 0, 'fail' => 0 );

function cic8_check( string $label, bool $ok, $detail = '' ): void {
	global $cic8;
	$cic8[ $ok ? 'pass' : 'fail' ]++;
	WP_CLI::log( ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( ! $ok && '' !== $detail ? ' — ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' ) );
}

/** @return array{code: int, body: string} */
function cic8_get( string $path ): array {
	$url = str_starts_with( $path, 'http' ) ? $path : home_url( $path );
	$r   = wp_remote_get( $url, array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 30, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
	return is_wp_error( $r ) ? array( 'code' => 0, 'body' => $r->get_error_message() ) : array( 'code' => (int) wp_remote_retrieve_response_code( $r ), 'body' => (string) wp_remote_retrieve_body( $r ) );
}

function cic8_main( string $html ): string {
	return preg_match( '#<main\b.*?</main>#s', $html, $m ) ? $m[0] : $html;
}

$snapshot = wp_normalize_path( ABSPATH . 'dev/tests/output/phase08/state-server.json' );
wp_mkdir_p( dirname( $snapshot ) );
WP_CLI::runcommand( 'eval-file dev/tests/phase08/state.php snapshot ' . $snapshot, array( 'launch' => false, 'exit_error' => true ) );
$contact = Contact::get();

try {
	/* ---------------- P1 statuses ---------------- */
	$codes = array(
		'/treats/'                           => 200,
		'/events/'                           => 200,
		'/about/'                            => 200,
		'/contact/'                          => 200,
		'/privacy-policy/'                   => 404,
		'/website-terms/'                    => 404,
		'/sample-page/'                      => 404,
		'/no-such-page-phase08/'             => 404,
		'/treats/not-a-treat/'               => 404,
		'/events/not-an-event/'              => 404,
		'/quote-request-received/'           => 404,
	);
	$wrong = array();
	$pages = array();
	foreach ( $codes as $path => $code ) {
		$r              = cic8_get( $path );
		$pages[ $path ] = $r['body'];
		if ( $r['code'] !== $code ) {
			$wrong[] = "{$path} → {$r['code']}";
		}
	}
	cic8_check( 'P1.1 the four pages return 200; drafts (policies, sample page), unknown routes and the Phase 06 success route are real 404s', ! $wrong, $wrong );
	$nf = $pages['/no-such-page-phase08/'];
	cic8_check( 'P1.2 the 404 response uses the branded template, is noindex and links home, treats and the quote route', str_contains( $nf, 'This scoop went missing.' ) && preg_match( '/<meta name=[\'"]robots[\'"] content=[\'"][^\'"]*noindex/', $nf ) && str_contains( $nf, 'cic-404' ) && str_contains( cic8_main( $nf ), 'href="' . $contact['quote_url'] . '"' ) );
	cic8_check( 'P1.3 policy pages are drafts with the Policy template; none published by the seeder', 'draft' === get_post_status( Catalog::find_by_key( 'page', 'privacy-policy' ) ) && 'draft' === get_post_status( Catalog::find_by_key( 'page', 'website-terms' ) ) && 'template-policy.php' === get_post_meta( Catalog::find_by_key( 'page', 'website-terms' ), '_wp_page_template', true ) && 'template-policy.php' === get_post_meta( Catalog::find_by_key( 'page', 'privacy-policy' ), '_wp_page_template', true ) );

	/* ---------------- P2 Our Treats ---------------- */
	$treats = cic8_main( $pages['/treats/'] );
	preg_match_all( '/<section class="cic-cat-row[^"]*" id="(treat-[a-z0-9-]+)"/', $treats, $rows );
	preg_match_all( '/<a href="#(treat-[a-z0-9-]+)">/', $treats, $jumps );
	$keys = wp_list_pluck( Catalog::selectable_products(), 'key' );
	cic8_check( 'P2.1 one detailed row per published treat, in menu order, each with a matching jump link', array_map( static fn( $k ) => 'treat-' . $k, $keys ) === $rows[1] && $rows[1] === $jumps[1], array( $rows[1], $jumps[1] ) );
	$detail = array();
	foreach ( $keys as $k ) {
		$id = Catalog::find_by_key( 'product', $k );
		if ( ! str_contains( $treats, esc_html( wp_strip_all_tags( wc_get_product( $id )->get_description() ) ) ) || ! str_contains( $treats, 'href="' . get_permalink( $id ) . '"' ) || ! str_contains( $treats, '/get-a-quote/?treat=' . $k . '"' ) ) {
			$detail[] = $k;
		}
	}
	cic8_check( 'P2.2 rows show each treat\'s long description, Explore (detail page) and Ask about this treat (quote route with the treat)', ! $detail, $detail );
	$test_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Phase Eight Test Sorbet', 'post_name' => 'phase-eight-test-sorbet', 'menu_order' => 99, 'post_content' => 'A temporary test treat description.' ) );
	update_post_meta( $test_id, '_cic_phase08_test', '1' );
	update_post_meta( $test_id, 'cic_key', 'phase-eight-test-sorbet' );
	wp_set_object_terms( $test_id, 'simple', 'product_type' );
	$t2 = cic8_main( cic8_get( '/treats/' )['body'] );
	cic8_check( 'P2.3 a newly published treat appears automatically (live product query, not a fixed list)', str_contains( $t2, 'id="treat-phase-eight-test-sorbet"' ) && str_contains( $t2, 'A temporary test treat description.' ), 'test product id ' . $test_id );
	wp_delete_post( $test_id, true );

	/* ---------------- P3 Events overview ---------------- */
	$events = cic8_main( $pages['/events/'] );
	$cards  = array();
	foreach ( Catalog::selectable_events() as $ev ) {
		if ( ! str_contains( $events, 'data-cic-event="' . $ev['key'] . '"' ) || ! str_contains( $events, 'href="' . get_permalink( $ev['id'] ) . '"' ) ) {
			$cards[] = $ev['key'];
		}
	}
	cic8_check( 'P3.1 all six occasions (Anniversary included) with Explore opening each event page', 6 === count( Catalog::selectable_events() ) && ! $cards && str_contains( $events, 'Anniversary' ), $cards );
	cic8_check( 'P3.2 "Tell us about your event" opens the quote route with Other occasion; treats link their pages', str_contains( $events, '/get-a-quote/?event=other"' ) && str_contains( $events, 'href="' . get_permalink( Catalog::find_by_key( 'product', 'dippin-dots' ) ) . '"' ) );
	$ev_id = wp_insert_post( array( 'post_type' => 'cic_event', 'post_status' => 'publish', 'post_title' => 'Phase Eight Graduations', 'post_name' => 'phase-eight-graduations', 'menu_order' => 99, 'post_excerpt' => 'A temporary test occasion.' ) );
	update_post_meta( $ev_id, '_cic_phase08_test', '1' );
	update_post_meta( $ev_id, 'cic_key', 'phase-eight-graduations' );
	$e2 = cic8_main( cic8_get( '/events/' )['body'] );
	cic8_check( 'P3.3 a newly published event appears automatically on the overview', str_contains( $e2, 'data-cic-event="phase-eight-graduations"' ) && str_contains( $e2, 'A temporary test occasion.' ) );
	wp_delete_post( $ev_id, true );
	update_option( EventsOverview::OPTION, array_merge( (array) get_option( EventsOverview::OPTION, array() ), array( 'cards_heading' => 'Edited overview heading for the test' ) ) );
	$e3 = cic8_get( '/events/' )['body'];
	cic8_check( 'P3.4 the overview text is editable (Events → Overview page option) without code changes', str_contains( $e3, 'Edited overview heading for the test' ) );

	/* ---------------- P4 About ---------------- */
	$about = cic8_main( $pages['/about/'] );
	cic8_check( 'P4.1 About uses configured business facts (entity, location, phone, email) and the initials placeholder, not an invented portrait', str_contains( $about, esc_html( $contact['legal_entity'] ) ) && str_contains( $about, esc_html( $contact['location'] ) ) && str_contains( $about, 'href="' . $contact['phone_href'] . '"' ) && str_contains( $about, 'href="mailto:' . $contact['email'] . '"' ) && str_contains( $about, 'cic-meet__initials' ) );
	cic8_check( 'P4.2 no invented history, hours, testimonials, awards or office address', ! preg_match( '/founded|since (19|20)\d\d|years of experience|award|testimonial|★|office hours|open (daily|monday)|suite \d|\d{3,5} [A-Z][a-z]+ (St|Ave|Blvd)/i', wp_strip_all_tags( $about ) ) );
	$about_id = Catalog::find_by_key( 'page', 'about' );
	update_post_meta( $about_id, 'cic_about_story_heading', 'Edited story heading for the test.' );
	cic8_check( 'P4.3 About content is editable through its fields', str_contains( cic8_get( '/about/' )['body'], 'Edited story heading for the test.' ) );

	/* ---------------- P5 Contact ---------------- */
	$contact_html = cic8_main( $pages['/contact/'] );
	preg_match_all( '/href="(tel:[^"]+|mailto:[^"]+)"/', $contact_html, $tm );
	$tel = array_unique( array_filter( $tm[1], static fn( $h ) => str_starts_with( $h, 'tel:' ) ) );
	$mail = array_unique( array_filter( $tm[1], static fn( $h ) => str_starts_with( $h, 'mailto:' ) ) );
	cic8_check( 'P5.1 every tel/mailto link equals the global contact values (' . $contact['phone'] . ', ' . $contact['email'] . ')', array( $contact['phone_href'] ) === array_values( $tel ) && array( 'mailto:' . $contact['email'] ) === array_values( $mail ) && str_contains( $contact_html, esc_html( $contact['phone'] ) ) );
	cic8_check( 'P5.2 the client-confirmed phone (2026-10-22) and the current public email are what is configured; contact name Alex Frost', 'tel:+14242354942' === $contact['phone_href'] && 'ceo.frostyyummy@gmail.com' === $contact['email'] && 'Alex Frost' === $contact['contact_name'] );
	preg_match_all( '/<form\b[^>]*>/', $contact_html, $forms );
	cic8_check( 'P5.3 Contact has exactly one form — the configured CF7 Contact form (Phase 06) — and shows no success before a message is sent', 1 === count( $forms[0] ) && str_contains( $forms[0][0], 'wpcf7-form' ) && str_contains( $contact_html, 'name="_wpcf7" value="' . \CIC\Enquiries\Forms::id( 'contact' ) . '"' ) && str_contains( $contact_html, 'data-cic-received hidden' ) && ! str_contains( $contact_html, 'cic-contact-pending' ) );
	cic8_check( 'P5.4 Contact offers the event-quote alternative and FAQ accordions', str_contains( $contact_html, 'href="' . $contact['quote_url'] . '"' ) && substr_count( $contact_html, 'cic-accordion__item' ) >= 3 );

	/* ---------------- P6 links, navigation, copy ---------------- */
	$links  = array();
	$copy   = array();
	foreach ( array( '/treats/', '/events/', '/about/', '/contact/', '/no-such-page-phase08/' ) as $path ) {
		$html = $pages[ $path ];
		preg_match_all( '/<a\b[^>]*href="([^"]+)"/', $html, $m );
		foreach ( $m[1] as $href ) {
			$href = html_entity_decode( $href );
			if ( preg_match( '/^(#|tel:|mailto:)/', $href ) ) {
				continue;
			}
			$links[ strtok( $href, '#' ) ] = true;
		}
		if ( preg_match( '/minimum order|order minimum|\$\s?\d|®/i', wp_strip_all_tags( cic8_main( $html ) ), $cm ) ) {
			$copy[] = "{$path}: {$cm[0]}";
		}
	}
	$external = array_filter( array_keys( $links ), static fn( $u ) => wp_parse_url( $u, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) );
	$broken   = array();
	foreach ( array_diff( array_keys( $links ), $external ) as $url ) {
		$code = cic8_get( $url )['code'];
		if ( 200 !== $code ) {
			$broken[] = "{$url} → {$code}";
		}
	}
	cic8_check( 'P6.1 every link on the five pages (header, content, footer) is internal and resolves (' . count( $links ) . ' unique)', ! $external && ! $broken, array( 'external' => array_values( $external ), 'broken' => $broken ) );
	$home = cic8_get( '/' )['body'];
	// About left the header menu on 2026-10-24 (owner); the footer keeps About and Contact.
	$cic8_head   = substr( $home, 0, (int) strpos( $home, '<main' ) );
	$cic8_footer = substr( $home, (int) strpos( $home, '<footer' ) );
	cic8_check( 'P6.2 menus: the header (desktop and drawer) no longer links About; footer links About and Contact; links to unpublished policy drafts are hidden (no 404 links)', ! str_contains( $cic8_head, 'href="' . home_url( '/about/' ) . '"' ) && str_contains( $cic8_footer, 'href="' . home_url( '/about/' ) . '"' ) && str_contains( $cic8_footer, 'href="' . home_url( '/contact/' ) . '"' ) && ! str_contains( $home, '/privacy-policy/' ) && ! str_contains( $home, '/website-terms/' ) );
	cic8_check( 'P6.3 no public pricing, minimum-order or ® copy on these pages', ! $copy, $copy );

	/* ---------------- P7 policy template ---------------- */
	$policy = ViewModel::policy( Catalog::find_by_key( 'page', 'website-terms' ) );
	cic8_check( 'P7.1 the policy outline is built from the page headings; unapproved until ticked', array( 'About this website', 'Enquiries and quotes', 'Website content' ) === wp_list_pluck( $policy['sections'], 'label' ) && false === $policy['approved'] && str_contains( $policy['content'], 'id="about-this-website"' ) );
	cic8_check( 'P7.2 the placeholder policy wording is not presented as final (draft + pending label in the template)', str_contains( (string) file_get_contents( get_template_directory() . '/template-policy.php' ), 'Content pending approval' ) && 'draft' === get_post_status( Catalog::find_by_key( 'page', 'website-terms' ) ) );

	/* ---------------- P8 seeding ---------------- */
	WP_CLI::runcommand( 'eval-file dev/tests/phase08/state.php restore ' . $snapshot, array( 'launch' => false, 'exit_error' => false ) );
	$report = ( new CIC\Content\Seeder() )->run( false )['report'];
	cic8_check( 'P8.1 re-running the seeder changes nothing and never publishes the policy drafts', 0 === $report['created'] && 0 === $report['filled'] && 'draft' === get_post_status( Catalog::find_by_key( 'page', 'website-terms' ) ) && 'draft' === get_post_status( Catalog::find_by_key( 'page', 'privacy-policy' ) ), $report );
} finally {
	WP_CLI::runcommand( 'eval-file dev/tests/phase08/state.php restore ' . $snapshot, array( 'launch' => false, 'exit_error' => false ) );
	$left = get_posts( array( 'post_type' => array( 'product', 'cic_event' ), 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_cic_phase08_test', 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	cic8_check( 'P9.1 cleanup: temporary records removed, edited content and the overview option restored', ! $left && ! str_contains( (string) wp_json_encode( get_option( EventsOverview::OPTION, array() ) ), 'Edited overview heading' ) && 'Edited story heading for the test.' !== get_post_meta( Catalog::find_by_key( 'page', 'about' ), 'cic_about_story_heading', true ) );
}

WP_CLI::log( sprintf( '== RESULT: %d passed, %d failed', $cic8['pass'], $cic8['fail'] ) );
