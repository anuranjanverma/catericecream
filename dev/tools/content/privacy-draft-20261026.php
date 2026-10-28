<?php
/**
 * Editable DRAFT Privacy Policy for CaterIceCream.com (owner QA request, 2026-10-26).
 *
 * No approved policy exists: the policy page is a draft holding WordPress's sample text ("Suggested
 * text: …" about comments, Gravatar and embeds, which this site does not use) plus the flagged
 * "Quote requests and saved progress" section. This replaces that sample with a draft that describes
 * what the site actually does (forms, saved progress, cookie, browser storage, spam protection,
 * emails, analytics). Business and legal decisions are left as [BRACKETED] placeholders.
 *
 * Safety: the page is NEVER published here. It is written only when the policy page (Settings →
 * Privacy) is not published AND still contains WordPress's sample text marker; otherwise the draft
 * is printed for manual review and nothing is changed. The previous content stays in the page's
 * revisions. The form notices and the footer link to the policy automatically once the owner
 * publishes it (Forms::privacy(), site-footer.php).
 *
 * Usage: wp eval-file dev/tools/content/privacy-draft-20261026.php [dry-run]
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

$cic_args = (array) ( $args ?? array() );
$cic_dry  = in_array( 'dry-run', $cic_args, true );
$cic_id   = (int) get_option( 'wp_page_for_privacy_policy' );
if ( ! $cic_id || 'page' !== get_post_type( $cic_id ) ) {
	WP_CLI::error( 'No privacy policy page is configured (Settings → Privacy).' );
}

$cic_c     = \CIC\Site\Contact::get();
$cic_name  = (string) ( $cic_c['business_name'] ?? '' ) ?: 'CaterIceCream.com';
$cic_legal = (string) ( $cic_c['legal_entity'] ?? '' ) ?: 'Frosty Yummy LLC';
$cic_phone = (string) ( $cic_c['phone'] ?? '' ) ?: '[PHONE]';
$cic_email = (string) ( $cic_c['email'] ?? '' ) ?: '[EMAIL]';
$cic_place = (string) ( $cic_c['location'] ?? '' ) ?: '[LOCATION]';
$cic_days  = (int) \CIC\Enquiries\Settings::value( 'draft_retention_days' );
$cic_keep  = $cic_days > 0 ? sprintf( 'Requests that were never sent are deleted after %d days without activity.', $cic_days ) : '[OWNER TO DECIDE: how long requests that were never sent are kept before they are deleted.]';
$cic_ga    = class_exists( '\CIC\Analytics\Settings' ) && ! empty( \CIC\Analytics\Settings::get()['enabled'] );

$cic_h = static fn( string $text, string $id = '' ): string => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\"" . ( '' !== $id ? ' id="' . $id . '"' : '' ) . ">{$text}</h2>\n<!-- /wp:heading -->\n\n";
$cic_p = static fn( string $html ): string => "<!-- wp:paragraph -->\n<p>{$html}</p>\n<!-- /wp:paragraph -->\n\n";
$cic_l = static fn( array $items ): string => "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode( '', array_map( static fn( $i ) => "<!-- wp:list-item -->\n<li>{$i}</li>\n<!-- /wp:list-item -->", $items ) ) . "</ul>\n<!-- /wp:list -->\n\n";

$cic_draft = $cic_p( '<strong>[DRAFT – NOT APPROVED. This text describes how the website works today. The owner (and, if they wish, a legal adviser) must review and complete the bracketed items before publishing.]</strong>' )
	. $cic_p( 'Last updated: [DATE OF PUBLICATION]' )
	. $cic_h( 'Who we are' )
	. $cic_p( esc_html( "{$cic_name} is operated by {$cic_legal}, {$cic_place}. We provide staffed ice cream catering for events. You can reach us at {$cic_phone} or {$cic_email}." ) )
	. $cic_h( 'What we collect and why' )
	. $cic_p( 'We collect only what you choose to send us through the forms on this website:' )
	. $cic_l(
		array(
			'<strong>Quote requests:</strong> your name, email address, phone number (optional unless you prefer a call), preferred contact method and treat choices (step 1); and the occasion, date (or that it is not set yet), approximate guest count, event city or ZIP code and, if you add them, venue, start time and serving time (step 2). After sending, you can add optional notes, a party theme, flavor preferences or dietary requirements.',
			'<strong>Contact messages:</strong> your name, email address, phone number (optional), topic and message.',
		)
	)
	. $cic_p( 'We use these details to answer your inquiry, prepare a quote and talk with you about your event. Checking a city or ZIP code tells you whether we may serve that area; it does not reserve a date. [OWNER TO CONFIRM: we do not use your details for marketing or add you to a mailing list.]' )
	. $cic_h( 'Quote requests and saved progress', 'cic-quote-progress' )
	. $cic_p( 'Our quote form has two steps. Nothing you type is sent to us until you press <em>Continue</em> at the end of the first step. When you do, we save your step 1 details so your progress is kept, and changes you make afterwards are saved to the same request automatically. If you do not send the request, our team sees it marked as incomplete. We do not send automatic emails, calls or reminders about incomplete requests, but a member of our team may follow up personally using your preferred contact method. ' . esc_html( $cic_keep ) )
	. $cic_h( 'Emails' )
	. $cic_p( 'When you send a request or message, our team receives a notification email with your details, and we send you one confirmation email. These emails are sent through our website’s email service [OWNER TO CONFIRM: the email provider used on the live site].' )
	. $cic_h( 'Cookies and browser storage' )
	. $cic_l(
		array_filter(
			array(
				'<strong>Saved progress cookie</strong> (<code>cic_qd</code>): links your browser to your saved quote request. It cannot be read by other websites or by scripts on the page, contains no personal details, and expires after 7 days. After you send your request, it lets you add optional details for 24 hours.',
				'<strong>Session storage</strong> in your browser keeps the occasion, treats and location you picked while you browse (no name, email, phone or message) and is cleared when you close the tab.',
				$cic_ga ? '<strong>Google Analytics</strong> (Google LLC) measures how visitors use the website (for example pages viewed) using cookies such as <code>_ga</code>. We do not send your name, email address, phone number, address, form contents or saved-progress codes to Google Analytics. [OWNER TO CONFIRM: Google Analytics settings, data retention and whether a consent banner is used.]' : '',
				'Logged-in website administrators use WordPress’s standard login cookies.',
			)
		)
	)
	. $cic_h( 'Spam protection' )
	. $cic_p( 'To limit spam, the website keeps a one-way coded (hashed) version of your internet (IP) address for about 10 minutes when a form is sent. Your IP address and browser details are not stored with your inquiry.' )
	. $cic_h( 'Who can see your information' )
	. $cic_p( esc_html( "Your inquiry is stored in our website's database and is visible only to our team members who handle inquiries. We use service providers to run the website: hosting [OWNER TO CONFIRM: hosting provider], email delivery" . ( $cic_ga ? ' and Google Analytics' : '' ) . ". We do not sell your personal information. [LEGAL REVIEW: any other sharing.]" ) )
	. $cic_h( 'How long we keep it' )
	. $cic_p( esc_html( "{$cic_keep} [OWNER TO DECIDE: how long submitted inquiries are kept.]" ) )
	. $cic_h( 'Your choices' )
	. $cic_p( esc_html( "You can ask us for a copy of the information we hold about you, or ask us to correct or delete it, by calling {$cic_phone} or emailing {$cic_email}. [LEGAL REVIEW: California privacy rights wording, if it applies to the business.]" ) )
	. $cic_h( 'Changes to this policy' )
	. $cic_p( 'If we change this policy, we will update it on this page and change the date above.' );

$cic_post    = get_post( $cic_id );
$cic_sample  = false !== strpos( (string) $cic_post->post_content, 'privacy-policy-tutorial' );
$cic_allowed = 'publish' !== $cic_post->post_status && $cic_sample;

WP_CLI::log( sprintf( 'Policy page #%d "%s": status %s; WordPress sample text present: %s.', $cic_id, $cic_post->post_title, $cic_post->post_status, $cic_sample ? 'yes' : 'no' ) );
WP_CLI::log( "--- Draft text ---\n" . trim( html_entity_decode( wp_strip_all_tags( str_replace( array( '</h2>', '</p>', '</li>' ), "\n", $cic_draft ) ) ) ) . "\n--- end ---" );
if ( ! $cic_sample && false !== strpos( (string) $cic_post->post_content, '[DRAFT – NOT APPROVED. This text describes how the website works today.' ) ) {
	WP_CLI::success( 'This draft is already on the page (already done). Nothing changed.' );
	return;
}
if ( ! $cic_allowed ) {
	WP_CLI::warning( 'The policy page is published or has been edited: nothing was changed. Review the draft above and edit the page by hand.' );
	return;
}
if ( $cic_dry ) {
	WP_CLI::success( 'Dry run: the draft page would be replaced with the text above (it stays a draft).' );
	return;
}
kses_remove_filters();
wp_save_post_revision( $cic_id );
$cic_result = wp_update_post( array( 'ID' => $cic_id, 'post_content' => wp_slash( trim( $cic_draft ) ), 'post_status' => $cic_post->post_status ), true );
if ( is_wp_error( $cic_result ) ) {
	WP_CLI::error( $cic_result->get_error_message() );
}
WP_CLI::success( 'Draft saved (status still "' . get_post_status( $cic_id ) . '"). The previous text is in the page revisions.' );
