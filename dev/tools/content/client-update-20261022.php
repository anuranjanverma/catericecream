<?php
/**
 * Client content update (2026-10-22): event order, Graduation Parties + Proms (through the
 * non-destructive seeder), public contact name in content, personal copy, American English, the
 * draft Kosher FAQ, acknowledgement email text, menu links and the stored CF7 markup copy.
 *
 * Usage: wp eval-file dev/tools/content/client-update-20261022.php [dry-run]
 *
 * Safe to re-run. Every change is conditional: rewrites apply only while a field still holds the
 * old seeded text (an editor's own wording is kept and reported), spelling fixes are whole-word
 * replacements, pairing text changes only through CIC\Content\Pairings::fill_seeded(). Draft
 * policy pages and historical enquiries are never touched.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Content\Catalog;
use CIC\Content\EventType;
use CIC\Content\Pairings;
use CIC\Content\Seeder;
use CIC\Enquiries\Forms;
use CIC\Enquiries\Settings;

$cic_dry = in_array( 'dry-run', (array) ( $args ?? array() ), true );
$cic_log = array();
$cic_say = static function ( string $line ) use ( &$cic_log ): void {
	$cic_log[] = $line;
	WP_CLI::log( $line );
};

/* 1. New events and pairings: the regular, non-destructive seeder (never overwrites edits). */
if ( ! $cic_dry ) {
	$result = ( new Seeder() )->run( true );
	$cic_say( 'Seeder: ' . wp_json_encode( $result['report'] ) );
	foreach ( $result['messages'] as $message ) {
		$cic_say( '  ' . $message );
	}
}

/* 2. Central event order (the events' Order field). */
$cic_order = array( 'birthday' => 10, 'wedding' => 20, 'graduation' => 30, 'prom' => 40, 'corporate' => 50, 'anniversary' => 60 );
foreach ( $cic_order as $key => $order ) {
	$id = Catalog::find_by_key( EventType::POST_TYPE, $key );
	if ( ! $id ) {
		$cic_say( "Order: event {$key} not found" );
		continue;
	}
	if ( (int) get_post_field( 'menu_order', $id ) !== $order ) {
		$cic_say( "Order: {$key} (#{$id}) " . get_post_field( 'menu_order', $id ) . " -> {$order}" );
		if ( ! $cic_dry ) {
			wp_update_post( array( 'ID' => $id, 'menu_order' => $order ) );
		}
	}
}

/* 3. Copy rewrites: [page key, meta key, test(current) => bool, new value]. */
$cic_page   = static fn( string $key ): int => Catalog::find_by_key( 'page', $key );
$cic_equals = static fn( string $old ): callable => static fn( string $v ): bool => trim( str_replace( "\r\n", "\n", $v ) ) === $old;
$cic_starts = static fn( string $old ): callable => static fn( string $v ): bool => str_starts_with( trim( $v ), $old );
$cic_rewrites = array(
	array( 'home', 'cic_home_intro', $cic_equals( 'Staffed ice cream catering for birthdays, weddings, anniversaries and corporate events.' ), 'Staffed ice cream catering for birthdays, weddings, graduation parties, proms, corporate events and anniversaries.' ),
	array( 'about', 'cic_about_intro', $cic_starts( 'Cater Ice Cream helps hosts create a sweet moment' ), 'Cater Ice Cream brings staffed ice cream catering to birthdays, weddings, graduation parties, proms, corporate events and anniversaries in selected parts of Los Angeles, Ventura and Santa Barbara counties.' ),
	array( 'about', 'cic_about_story_text', $cic_starts( 'A birthday calls for a little wonder.' ), "We're Cater Ice Cream, a staffed ice cream catering service in Southern California. The company behind the name is Frosty Yummy LLC.\n\nWe distribute Dippin' Dots, and we bring them to events along with DipN' Bananas, classic ice cream and frozen yogurt. Our team serves your guests, so you can stay with the people you invited.\n\nEvery event starts with a conversation about what you're celebrating, who's coming and where. From there, we help you pick the treats and plan the serving details for your quote." ),
	array( 'about', 'cic_about_brands_line', $cic_equals( 'Explore classic ice cream, frozen yogurt and other event ideas with our team.' ), "Dippin' Dots and DipN' Bananas, plus classic ice cream and frozen yogurt." ),
	array( 'about', 'cic_about_meet_heading', $cic_equals( 'Meet Yan Kriv.' ), 'Say hello to {contact_name}.' ),
	array( 'about', 'cic_about_meet_text', $cic_equals( 'Yan is your contact for discussing the occasion, the treats and the details of your event.' ), "When you call Cater Ice Cream, you'll be talking with {contact_name}. Share what you're celebrating, how many guests you expect and where you're gathering, and you'll get friendly help choosing treats and sorting out the serving details for your quote." ),
	array( 'about', 'cic_about_meet_initials', $cic_equals( 'YK' ), 'AF' ),
	array( 'contact', 'cic_contact_call_title', $cic_equals( 'Call Yan' ), 'Call {contact_name}' ),
	array( 'contact', 'cic_contact_success_heading', $cic_equals( 'Your message is received.' ), 'Thanks! We received your message.' ),
	array( 'get-a-quote', 'cic_quote_success_heading', $cic_equals( 'Your quote request is received.' ), 'Thanks! We received your quote request.' ),
	array( 'get-a-quote', 'cic_quote_next_2_text', $cic_equals( 'Our team will be in touch to explore the best options for you.' ), "We'll call or email to talk through treats and serving details." ),
	array( 'get-a-quote', 'cic_quote_next_3_text', $cic_equals( "We'll share a personalised quote based on your event and needs." ), "We'll send a quote based on your event and what you need." ),
	array( 'shop', 'cic_shop_help_1_text', $cic_equals( 'Birthday, wedding, corporate event or another celebration.' ), 'Birthday, wedding, graduation party, prom, corporate event or something else.' ),
	array( 'service-areas', 'cic_sa_occasions_intro', $cic_starts( 'From backyard birthdays to elegant weddings' ), 'Birthdays, weddings, graduation parties, proms, corporate events and anniversaries: pick your occasion to see how we can help.' ),
	array( 'service-areas', 'cic_citydef_intro', $cic_starts( 'From a backyard birthday to a wedding reception or team gathering' ), 'From a backyard birthday or graduation party to a wedding reception or team gathering, explore frozen treats and staffed catering for your {city} event.' ),
	array( 'service-areas', 'cic_citydef_seo_description', $cic_starts( 'Staffed ice cream catering for birthdays, weddings, anniversaries and corporate events in {city}' ), 'Staffed ice cream catering for birthdays, weddings, graduation parties, proms, corporate events and anniversaries in {city}, {state_code}. Check your event ZIP code and request a quote.' ),
);
$cic_birthday = Catalog::find_by_key( EventType::POST_TYPE, 'birthday' );
foreach ( $cic_rewrites as $rw ) {
	list( $key, $meta, $test, $new ) = $rw;
	$id = $cic_page( $key );
	if ( ! $id ) {
		$cic_say( "Copy: page {$key} not found" );
		continue;
	}
	$current = (string) get_post_meta( $id, $meta, true );
	if ( $current === $new ) {
		continue;
	}
	if ( ! $test( $current ) ) {
		$cic_say( "Copy: kept {$key}/{$meta} (edited by someone; not the old seeded text)" );
		continue;
	}
	$cic_say( "Copy: {$key}/{$meta} updated" );
	if ( ! $cic_dry ) {
		update_post_meta( $id, $meta, $new );
	}
}
if ( $cic_birthday && 'How far ahead should we enquire?' === get_post_meta( $cic_birthday, 'cic_faq_3_q', true ) ) {
	$cic_say( 'Copy: birthday/cic_faq_3_q updated' );
	if ( ! $cic_dry ) {
		update_post_meta( $cic_birthday, 'cic_faq_3_q', 'How far ahead should we reach out?' );
	}
}

/* 4. American English (whole words) in public text: cic_* meta, excerpts and treat/event content. */
$cic_words = array(
	'favourites' => 'favorites', 'Favourites' => 'Favorites', 'favourite' => 'favorite', 'Favourite' => 'Favorite',
	'flavours' => 'flavors', 'Flavours' => 'Flavors', 'flavour' => 'flavor', 'Flavour' => 'Flavor',
	'colourful' => 'colorful', 'Colourful' => 'Colorful', 'colours' => 'colors', 'colour' => 'color', 'Colour' => 'Color',
	'personalised' => 'personalized', 'recognisable' => 'recognizable', 'Recognisable' => 'Recognizable',
	'savour' => 'savor', 'Savour' => 'Savor', 'centred' => 'centered', 'organised' => 'organized',
	'enquiries' => 'inquiries', 'Enquiries' => 'Inquiries', 'enquiry' => 'inquiry', 'enquire' => 'inquire',
);
$cic_fix = static function ( string $text ) use ( $cic_words ): string {
	foreach ( $cic_words as $from => $to ) {
		$text = preg_replace( '/\b' . $from . '\b/', $to, $text );
	}
	return $text;
};
$cic_targets = get_posts(
	array(
		'post_type'   => array( 'page', 'product', EventType::POST_TYPE, \CIC\Locations\Editorial::POST_TYPE ),
		'post_status' => array( 'publish', 'draft', 'private', 'pending' ),
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
$cic_policies = array_filter( array( $cic_page( 'privacy-policy' ), $cic_page( 'website-terms' ), (int) get_option( 'wp_page_for_privacy_policy' ) ) );
$cic_spelling = 0;
foreach ( $cic_targets as $id ) {
	if ( in_array( (int) $id, $cic_policies, true ) || 'template-policy.php' === get_post_meta( $id, '_wp_page_template', true ) ) {
		continue; // Policy drafts await legal review: never edited here.
	}
	foreach ( get_post_meta( $id ) as $meta => $values ) {
		if ( ! str_starts_with( $meta, 'cic_' ) || ! is_string( $values[0] ) || is_serialized( $values[0] ) ) {
			continue;
		}
		$fixed = $cic_fix( $values[0] );
		if ( $fixed !== $values[0] ) {
			++$cic_spelling;
			if ( ! $cic_dry ) {
				update_post_meta( $id, $meta, wp_slash( $fixed ) );
			}
		}
	}
	if ( in_array( get_post_type( $id ), array( 'product', EventType::POST_TYPE ), true ) ) {
		$post    = get_post( $id );
		$changes = array();
		foreach ( array( 'post_excerpt', 'post_content' ) as $field ) {
			$fixed = $cic_fix( (string) $post->$field );
			if ( $fixed !== (string) $post->$field ) {
				$changes[ $field ] = $fixed;
			}
		}
		if ( $changes ) {
			$cic_spelling += count( $changes );
			if ( ! $cic_dry ) {
				wp_update_post( wp_slash( array_merge( array( 'ID' => $id ), $changes ) ) );
			}
		}
	}
}
$cic_say( "Spelling: {$cic_spelling} field(s) changed to American English" );

/* Image alt text: American spelling, and drop leftover image-generation instructions. */
$cic_alts = 0;
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids' ) ) as $att ) {
	$alt   = (string) get_post_meta( $att, '_wp_attachment_image_alt', true );
	$fixed = $cic_fix( str_replace( array( 'centrepiece', 'centre' ), array( 'centerpiece', 'center' ), $alt ) );
	$fixed = trim( (string) preg_replace( '/(,\s*no (text|lettering|labels|logos|watermark|invented [a-z ]+)( or logos)?)+\s*$/i', '', $fixed ) );
	if ( '' !== $alt && $fixed !== $alt ) {
		++$cic_alts;
		if ( ! $cic_dry ) {
			update_post_meta( $att, '_wp_attachment_image_alt', wp_slash( $fixed ) );
		}
	}
}
$cic_say( "Alt text: {$cic_alts} image(s) cleaned" );

/* Pairing copy: only through the pairing service, only while the text is unchanged. */
$cic_pairs = 0;
foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $product_id ) {
	foreach ( Pairings::for_product( (int) $product_id ) as $row ) {
		$replaces = array();
		$values   = array();
		foreach ( array( 'headline', 'description', 'link_label' ) as $field ) {
			$fixed = $cic_fix( (string) $row[ $field ] );
			if ( $fixed !== (string) $row[ $field ] ) {
				$replaces[ $field ] = (string) $row[ $field ];
				$values[ $field ]   = $fixed;
			}
		}
		if ( $replaces ) {
			++$cic_pairs;
			if ( ! $cic_dry ) {
				Pairings::fill_seeded( (int) $product_id, (int) $row['event_id'], 0, $replaces, $values );
			}
		}
	}
}
$cic_say( "Spelling: {$cic_pairs} pairing(s) updated" );

/* 5. Kosher FAQ: saved as a DRAFT on the homepage FAQ (not shown) until DipN' Bananas is confirmed. */
$cic_home = $cic_page( 'home' );
$cic_slot = 0;
for ( $i = 1; $cic_home && $i <= \CIC\Content\Fields::FAQ_SLOTS; $i++ ) {
	$q = (string) get_post_meta( $cic_home, "cic_faq_{$i}_q", true );
	if ( 'Are your products Kosher?' === $q ) {
		$cic_slot = -1;
		break;
	}
	if ( '' === $q && '' === (string) get_post_meta( $cic_home, "cic_faq_{$i}_a", true ) ) {
		$cic_slot = $i;
		break;
	}
}
if ( $cic_slot > 0 ) {
	$cic_say( "Kosher FAQ: saved as a draft in homepage question {$cic_slot} (not shown on the website)" );
	if ( ! $cic_dry ) {
		update_post_meta( $cic_home, "cic_faq_{$cic_slot}_q", 'Are your products Kosher?' );
		update_post_meta( $cic_home, "cic_faq_{$cic_slot}_a", 'Dippin’ Dots are Kosher, and so are DipN’ Bananas.' );
		update_post_meta( $cic_home, "cic_faq_{$cic_slot}_draft", '1' );
		update_post_meta( $cic_home, "cic_faq_{$cic_slot}_note", "Client-requested answer. Keep as a draft until certification for the supplied DipN' Bananas products is confirmed. Dippin' Dots certification info: https://www.dippindots.com/about/quality-social-responsibility/ . Do not extend to other treats, toppings, serving setups or the catering service, and add no certification symbols without approved source assets." );
		foreach ( array( 'q', 'a', 'draft', 'note' ) as $part ) {
			update_post_meta( $cic_home, "_cic_faq_{$cic_slot}_{$part}", "field_cic_faq_{$cic_slot}_{$part}" );
		}
	}
} elseif ( 0 === $cic_slot ) {
	$cic_say( 'Kosher FAQ: no free homepage FAQ slot; not added' );
}

/* 6. Acknowledgement emails: new defaults where the saved text still equals the old default. */
$cic_old = array(
	'quote_ack_subject'   => 'We have received your quote request',
	'quote_ack_body'      => "Hi {name},\n\nThank you for sharing your plans with Cater Ice Cream. We have received your quote request and our team will review your event details and contact you to discuss availability and your quote.\n\nThis is not a booking confirmation: your date and serving details are confirmed with your quote.\n\n{summary}\n\nIf you have a question in the meantime, call us on {phone}.\n\nCater Ice Cream",
	'contact_ack_subject' => 'We have received your message',
	'contact_ack_body'    => "Hi {name},\n\nThank you for contacting Cater Ice Cream. We have received your message and will reply as soon as we can.\n\nIf it is urgent, call us on {phone}.\n\nCater Ice Cream",
);
$cic_saved   = (array) get_option( Settings::OPTION, array() );
$cic_changes = array();
foreach ( $cic_old as $key => $old ) {
	if ( isset( $cic_saved[ $key ] ) && str_replace( "\r\n", "\n", (string) $cic_saved[ $key ] ) === $old ) {
		$cic_changes[ $key ] = Settings::defaults()[ $key ];
	}
}
if ( $cic_changes ) {
	$cic_say( 'Emails: updated ' . implode( ', ', array_keys( $cic_changes ) ) . ' (recipient, sender and forms unchanged)' );
	if ( ! $cic_dry ) {
		Settings::update( $cic_changes );
	}
}

/* 7. Primary menu: links for the new events under "Events" (their order comes from the events). */
$cic_locations = get_nav_menu_locations();
$cic_menu      = (int) ( $cic_locations['primary'] ?? 0 );
if ( $cic_menu ) {
	$items  = wp_get_nav_menu_items( $cic_menu );
	$parent = 0;
	$urls   = array();
	foreach ( $items as $item ) {
		$path = trim( (string) wp_parse_url( $item->url, PHP_URL_PATH ), '/' );
		if ( 'events' === $path && ! $item->menu_item_parent ) {
			$parent = (int) $item->ID;
		}
		$urls[] = $path;
	}
	foreach ( array( 'graduation', 'prom' ) as $key ) {
		$id   = Catalog::find_by_key( EventType::POST_TYPE, $key );
		$path = $id ? trim( (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH ), '/' ) : '';
		if ( ! $id || ! $parent || in_array( $path, $urls, true ) ) {
			continue;
		}
		$cic_say( 'Menu: added ' . get_the_title( $id ) . ' under Events' );
		if ( ! $cic_dry ) {
			wp_update_nav_menu_item(
				$cic_menu,
				0,
				array(
					'menu-item-title'     => get_the_title( $id ),
					'menu-item-url'       => (string) get_permalink( $id ),
					'menu-item-type'      => 'custom',
					'menu-item-parent-id' => $parent,
					'menu-item-status'    => 'publish',
				)
			);
		}
	}
}

/* 8. Stored CF7 markup copy (the live form markup always comes from code). */
if ( ! $cic_dry && class_exists( 'WPCF7_ContactForm' ) ) {
	foreach ( array( 'quote', 'contact' ) as $type ) {
		$form = Forms::id( $type ) ? \WPCF7_ContactForm::get_instance( Forms::id( $type ) ) : null;
		if ( $form && (string) get_post_meta( $form->id(), '_form', true ) !== Forms::markup( $type ) ) {
			$form->set_properties( array( 'form' => Forms::markup( $type ) ) );
			$form->save();
			$cic_say( "CF7: refreshed the stored {$type} form markup" );
		}
	}
}

WP_CLI::success( ( $cic_dry ? 'Dry run: ' : '' ) . count( $cic_log ) . ' log line(s).' );
