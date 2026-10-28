<?php
/**
 * Adds a clearly flagged "Quote requests and saved progress" section to the DRAFT Privacy Policy,
 * describing how the two-step quote form stores partial inquiries (2026-10-23). The wording needs
 * the owner's (and, if they wish, legal) approval before the policy is published.
 *
 * Usage: wp eval-file dev/tools/content/privacy-quote-drafts.php [dry-run]
 *
 * Safe to re-run (the section is added once, identified by its anchor). Never publishes anything
 * and refuses to edit a policy that is already published: there it only prints the text for review.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

$cic_dry = in_array( 'dry-run', (array) ( $args ?? array() ), true );
$cic_id  = (int) get_option( 'wp_page_for_privacy_policy' );
if ( ! $cic_id || 'page' !== get_post_type( $cic_id ) ) {
	WP_CLI::error( 'No privacy policy page is configured (Settings → Privacy).' );
}
$cic_marker  = 'cic-quote-progress';
$cic_content = (string) get_post_field( 'post_content', $cic_id );
if ( str_contains( $cic_content, $cic_marker ) ) {
	WP_CLI::success( 'The section is already present; nothing to do.' );
	return;
}
$cic_days    = (int) \CIC\Enquiries\Settings::value( 'draft_retention_days' );
$cic_keep    = $cic_days > 0 ? sprintf( 'We delete requests that were never sent after %d days without activity.', $cic_days ) : '[RETENTION PERIOD TO BE DECIDED BY THE OWNER: how long requests that were never sent are kept before deletion.]';
$cic_section = "\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\" id=\"{$cic_marker}\">Quote requests and saved progress</h2>\n<!-- /wp:heading -->\n\n"
	. "<!-- wp:paragraph -->\n<p><strong>[DRAFT WORDING – NEEDS OWNER APPROVAL BEFORE PUBLISHING]</strong></p>\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:paragraph -->\n<p>Our quote form has two steps. Nothing you type is sent to us until you press <em>Continue</em> at the end of the first step. When you do, we save your name, email address, phone number (if given), preferred contact method and treat choices so your progress is kept, and we may contact you about your event using your preferred contact method. Changes you make afterwards, in either step, are saved to the same request automatically.</p>\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:paragraph -->\n<p>If you do not send the request, our team sees it marked as incomplete. We do not send automatic emails, calls or reminders about incomplete requests, but a member of our team may follow up personally using your preferred contact method. {$cic_keep} You can ask us to delete your information at any time.</p>\n<!-- /wp:paragraph -->\n\n"
	. "<!-- wp:paragraph -->\n<p>To keep your progress, the website sets one cookie (<code>cic_qd</code>) that links your browser to your saved request. It cannot be read by other websites or by scripts on the page and expires after 7 days. After you send your request, you can add optional details (notes, party theme, dietary requirements) for 24 hours. We do not put your details in web addresses or analytics.</p>\n<!-- /wp:paragraph -->";

WP_CLI::log( 'Section to add to "' . get_the_title( $cic_id ) . '" (#' . $cic_id . ', status ' . get_post_status( $cic_id ) . '):' );
WP_CLI::log( wp_strip_all_tags( str_replace( array( '</h2>', '</p>' ), "\n", $cic_section ) ) );
if ( $cic_dry ) {
	WP_CLI::success( 'Dry run: nothing changed.' );
	return;
}
if ( 'publish' === get_post_status( $cic_id ) ) {
	WP_CLI::warning( 'The policy is published: not edited. Review the text above and add it through the editor once approved.' );
	return;
}
wp_update_post( array( 'ID' => $cic_id, 'post_content' => $cic_content . $cic_section ) );
WP_CLI::success( 'Added to the draft policy (still a draft; flagged for approval).' );
