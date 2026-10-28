<?php
/**
 * SEO settings and metadata of 2026-10-28 (Yoast SEO 28.x; the code ships separately):
 *
 *   1. Yoast settings (Search appearance / Site representation), each changed ONLY while it still
 *      has Yoast's default value; anything an admin already chose is reported and kept:
 *      title separator "|"; Site representation = Organization named after Header, Footer &
 *      Contact, logo = the mascot Site Icon (the client logos stay header/footer only); author and
 *      date archives off (single-author business site; the author archive exposed the login name);
 *      category, tag, product category/tag/brand archives noindex (empty or redirected in catalogue
 *      mode); Events archive title/description.
 *   2. Default social image (pages without their own image): the "service-garden" photo (by asset
 *      key), only when none is set.
 *   3. Search titles/descriptions: Yoast per-page fields for Home, Our Treats, Service Areas, About,
 *      Contact, Get a Quote and the four treats; the CIC "Search result title/description" fields
 *      for the four events that have none (Graduation Parties and Proms already have them).
 *      Only empty fields are filled; existing values are reported and kept.
 *   4. The WordPress sample post "Hello world!" goes back to Draft (only if it is still the
 *      untouched sample). Nothing is deleted.
 *
 * Usage: wp eval-file dev/tools/content/seo-20261028.php [dry-run] [report=/path/seo-report.json]
 *
 * Safe to re-run: later runs report "already done" / "kept". WordPress and Yoast APIs only
 * (WPSEO_Options, WPSEO_Meta, post meta, wp_update_post); never Yoast's indexable tables. Afterwards
 * run `wp yoast index` so Yoast's indexables reflect the changes (see the deployment notes).
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Content\Catalog;
use CIC\Site\Contact;

$cic_args   = (array) ( $args ?? array() );
$cic_dry    = in_array( 'dry-run', $cic_args, true );
$cic_report = '';
foreach ( $cic_args as $cic_arg ) {
	if ( 0 === strpos( $cic_arg, 'report=' ) ) {
		$cic_report = substr( $cic_arg, 7 );
	}
}

if ( ! defined( 'WPSEO_VERSION' ) || ! class_exists( 'WPSEO_Options' ) || ! class_exists( 'WPSEO_Meta' ) ) {
	WP_CLI::error( 'Yoast SEO is not active. Nothing changed.' );
}
if ( ! class_exists( Catalog::class ) || ! class_exists( Contact::class ) ) {
	WP_CLI::error( 'The Cater Ice Cream core plugin is not active. Nothing changed.' );
}

WP_CLI::log( ( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' ) . ' Yoast SEO ' . WPSEO_VERSION . '.' );

$cic_log = array();
$cic_note = static function ( string $area, string $target, string $result, $before = null, $after = null ) use ( &$cic_log ): void {
	$cic_log[] = array_filter(
		array(
			'area'   => $area,
			'target' => $target,
			'result' => $result,
			'before' => $before,
			'after'  => $after,
		),
		static fn( $v ) => null !== $v
	);
	WP_CLI::log( sprintf( '%-9s %-46s %s', $result, $target, null !== $after ? '→ ' . ( is_scalar( $after ) ? (string) $after : wp_json_encode( $after ) ) : '' ) );
};

/* ------------------------------------------------------------------ *
 * 1. Yoast settings (only while still at Yoast's default).
 * ------------------------------------------------------------------ */
$cic_contact = Contact::get();
$cic_icon    = (int) get_option( 'site_icon' );
$cic_icon    = $cic_icon && wp_attachment_is_image( $cic_icon ) ? $cic_icon : 0;

/**
 * key => [ Yoast default(s), new value ].
 */
$cic_settings = array(
	'separator'                           => array( array( 'sc-dash' ), 'sc-pipe' ),
	'company_or_person'                   => array( array( 'company', '' ), 'company' ),
	'company_name'                        => array( array( '' ), (string) $cic_contact['business_name'] ),
	'disable-author'                      => array( array( false ), true ),
	'disable-date'                        => array( array( false ), true ),
	'noindex-tax-category'                => array( array( false ), true ),
	'noindex-tax-post_tag'                => array( array( false ), true ),
	'noindex-tax-product_cat'             => array( array( false ), true ),
	'noindex-tax-product_tag'             => array( array( false ), true ),
	'noindex-tax-product_brand'           => array( array( false ), true ),
	'title-ptarchive-cic_event'           => array( array( '%%pt_plural%% Archive %%page%% %%sep%% %%sitename%%' ), 'Ice Cream Catering by Occasion %%page%% %%sep%% %%sitename%%' ),
	'social-title-ptarchive-cic_event'    => array( array( '%%pt_plural%% Archive' ), 'Ice Cream Catering by Occasion' ),
	'metadesc-ptarchive-cic_event'        => array( array( '' ), 'Staffed ice cream catering for birthdays, weddings, graduation parties, proms, corporate events and anniversaries. See what suits your occasion and request a quote.' ),
	'social-description-ptarchive-cic_event' => array( array( '' ), 'Staffed ice cream catering for birthdays, weddings, graduation parties, proms, corporate events and anniversaries. See what suits your occasion and request a quote.' ),
);
foreach ( $cic_settings as $cic_key => [ $cic_defaults, $cic_new ] ) {
	$cic_now = WPSEO_Options::get( $cic_key );
	if ( $cic_now === $cic_new ) {
		$cic_note( 'yoast', $cic_key, 'done' );
	} elseif ( in_array( $cic_now, $cic_defaults, true ) && '' !== $cic_new ) {
		$cic_note( 'yoast', $cic_key, $cic_dry ? 'would set' : 'set', $cic_now, $cic_new );
		if ( ! $cic_dry ) {
			WPSEO_Options::set( $cic_key, $cic_new );
		}
	} else {
		$cic_note( 'yoast', $cic_key, 'kept', $cic_now );
	}
}
// Organization logo: the mascot Site Icon (already the public favicon), only when no logo is set.
if ( $cic_icon && $cic_icon === (int) WPSEO_Options::get( 'company_logo_id' ) ) {
	$cic_note( 'yoast', 'company_logo_id', 'done' );
} elseif ( (int) WPSEO_Options::get( 'company_logo_id' ) > 0 ) {
	$cic_note( 'yoast', 'company_logo_id', 'kept', (int) WPSEO_Options::get( 'company_logo_id' ) );
} elseif ( ! $cic_icon ) {
	$cic_note( 'yoast', 'company_logo_id', 'skipped', null, 'no Site Icon: set a logo in Yoast SEO → Settings → Site representation' );
} else {
	$cic_note( 'yoast', 'company_logo_id', $cic_dry ? 'would set' : 'set', 0, $cic_icon . ' (Site Icon)' );
	if ( ! $cic_dry ) {
		WPSEO_Options::set( 'company_logo', (string) wp_get_attachment_url( $cic_icon ) );
		WPSEO_Options::set( 'company_logo_id', $cic_icon );
	}
}

/* ------------------------------------------------------------------ *
 * 2. Default social image.
 * ------------------------------------------------------------------ */
$cic_garden = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_cic_asset_id', 'meta_value' => 'service-garden', 'numberposts' => 1, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
$cic_garden = $cic_garden ? (int) $cic_garden[0] : 0;
if ( $cic_garden && $cic_garden === (int) WPSEO_Options::get( 'og_default_image_id' ) ) {
	$cic_note( 'yoast', 'og_default_image', 'done' );
} elseif ( (int) WPSEO_Options::get( 'og_default_image_id' ) > 0 || '' !== (string) WPSEO_Options::get( 'og_default_image' ) ) {
	$cic_note( 'yoast', 'og_default_image', 'kept', (string) WPSEO_Options::get( 'og_default_image' ) );
} elseif ( ! $cic_garden ) {
	$cic_note( 'yoast', 'og_default_image', 'skipped', null, 'asset "service-garden" not found' );
} else {
	$cic_note( 'yoast', 'og_default_image', $cic_dry ? 'would set' : 'set', '', $cic_garden . ' (service-garden)' );
	if ( ! $cic_dry ) {
		WPSEO_Options::set( 'og_default_image', (string) wp_get_attachment_url( $cic_garden ) );
		WPSEO_Options::set( 'og_default_image_id', $cic_garden );
	}
}

/* ------------------------------------------------------------------ *
 * 3. Search titles and descriptions (only empty fields).
 * ------------------------------------------------------------------ */
$cic_sep  = ' %%sep%% %%sitename%%';
$cic_area = 'selected areas of Los Angeles, Ventura and Santa Barbara counties';
$cic_yoast = array(
	array( 'page', 'home', 'Ice Cream Catering in Southern California' . $cic_sep, 'Staffed ice cream catering with Dippin’ Dots, DipN’ Bananas, classic ice cream and frozen yogurt for events in ' . $cic_area . '.' ),
	array( 'page', 'shop', 'Catering Treats: Dippin’ Dots, DipN’ Bananas & More' . $cic_sep, 'Compare the treats we serve at events: Dippin’ Dots, DipN’ Bananas, classic ice cream and frozen yogurt. Choose one or more for your quote, or pick “Not sure yet.”' ),
	array( 'page', 'service-areas', 'Where We Cater: LA, Ventura & Santa Barbara Counties' . $cic_sep, 'We cater events in selected ZIP codes across Los Angeles, Ventura and Santa Barbara counties. Enter your venue city or ZIP code to check your location.' ),
	array( 'page', 'about', 'About Our Staffed Ice Cream Catering' . $cic_sep, 'CaterIceCream.com is a staffed ice cream catering service in Southern California, run by Frosty Yummy LLC. See how we plan events and who you’ll talk to when you call.' ),
	array( 'page', 'contact', 'Contact Us' . $cic_sep, 'Questions about ice cream catering for your event? Call or email us about treats, timing and your location, or request a quote online.' ),
	array( 'page', 'get-a-quote', 'Request an Ice Cream Catering Quote' . $cic_sep, 'Tell us about your occasion, date, guest count, location and favorite treats. We’ll confirm availability for your area and follow up with your quote.' ),
	array( 'product', 'dippin-dots', 'Dippin’ Dots Catering for Events' . $cic_sep, 'Dippin’ Dots served by our team at birthdays, weddings, graduation parties, proms and corporate events in ' . $cic_area . '.' ),
	array( 'product', 'dipn-bananas', 'DipN’ Bananas Event Catering' . $cic_sep, 'Bananas with a chocolate finish, served by our team: a playful change from cake for birthdays, weddings, graduation parties and more. Request a quote for your event.' ),
	array( 'product', 'classic-ice-cream', 'Classic Ice Cream Catering' . $cic_sep, 'Familiar, creamy scoops served by our team at birthdays, weddings, graduations and team gatherings. Flavors and serving options are discussed with your quote.' ),
	array( 'product', 'frozen-yogurt', 'Frozen Yogurt Catering for Events' . $cic_sep, 'Smooth frozen yogurt with a familiar tang, served by our team at birthdays, garden weddings, graduation parties and team gatherings. Request a quote for your event.' ),
);
foreach ( $cic_yoast as [ $cic_type, $cic_key, $cic_title, $cic_desc ] ) {
	$cic_id = Catalog::find_by_key( $cic_type, $cic_key );
	if ( ! $cic_id ) {
		$cic_note( 'meta', "$cic_type:$cic_key", 'skipped', null, 'not found' );
		continue;
	}
	foreach ( array( 'title' => $cic_title, 'metadesc' => $cic_desc ) as $cic_field => $cic_value ) {
		$cic_now = trim( (string) WPSEO_Meta::get_value( $cic_field, $cic_id ) );
		$cic_tgt = "#$cic_id $cic_key Yoast $cic_field";
		if ( $cic_now === $cic_value ) {
			$cic_note( 'meta', $cic_tgt, 'done' );
		} elseif ( '' !== $cic_now ) {
			$cic_note( 'meta', $cic_tgt, 'kept', $cic_now );
		} else {
			$cic_note( 'meta', $cic_tgt, $cic_dry ? 'would set' : 'set', '', $cic_value );
			if ( ! $cic_dry ) {
				WPSEO_Meta::set_value( $cic_field, $cic_value, $cic_id );
			}
		}
	}
}

$cic_events = array(
	'birthday'    => array( 'Birthday Party Ice Cream Catering', 'Staffed ice cream catering for kids’ parties and milestone birthdays, with Dippin’ Dots, DipN’ Bananas, classic ice cream or frozen yogurt. Request a quote for your date.' ),
	'wedding'     => array( 'Wedding Ice Cream Catering', 'Staffed ice cream catering for wedding receptions, with Dippin’ Dots, DipN’ Bananas and other favorites, in ' . $cic_area . '.' ),
	'corporate'   => array( 'Corporate Event Ice Cream Catering', 'Staffed ice cream catering for team appreciation, client gatherings and office celebrations in ' . $cic_area . '.' ),
	'anniversary' => array( 'Anniversary Party Ice Cream Catering', 'Celebrate your anniversary with staffed ice cream catering and the frozen treats you both love, in ' . $cic_area . '.' ),
);
foreach ( $cic_events as $cic_key => [ $cic_title, $cic_desc ] ) {
	$cic_id = Catalog::find_by_key( 'cic_event', $cic_key );
	if ( ! $cic_id ) {
		$cic_note( 'meta', "cic_event:$cic_key", 'skipped', null, 'not found' );
		continue;
	}
	foreach ( array( 'cic_seo_title' => $cic_title, 'cic_seo_description' => $cic_desc ) as $cic_field => $cic_value ) {
		$cic_now = trim( (string) get_post_meta( $cic_id, $cic_field, true ) );
		$cic_tgt = "#$cic_id $cic_key $cic_field";
		if ( $cic_now === $cic_value ) {
			$cic_note( 'meta', $cic_tgt, 'done' );
		} elseif ( '' !== $cic_now ) {
			$cic_note( 'meta', $cic_tgt, 'kept', $cic_now );
		} else {
			$cic_note( 'meta', $cic_tgt, $cic_dry ? 'would set' : 'set', '', $cic_value );
			if ( ! $cic_dry ) {
				update_post_meta( $cic_id, $cic_field, $cic_value );
				update_post_meta( $cic_id, '_' . $cic_field, 'field_' . $cic_field );
			}
		}
	}
}

/* ------------------------------------------------------------------ *
 * 4. WordPress sample post.
 * ------------------------------------------------------------------ */
$cic_sample = get_page_by_path( 'hello-world', OBJECT, 'post' );
if ( ! $cic_sample ) {
	$cic_note( 'content', 'post hello-world', 'done', null, 'not present' );
} elseif ( 'publish' !== $cic_sample->post_status ) {
	$cic_note( 'content', "post #{$cic_sample->ID} hello-world", 'done', $cic_sample->post_status );
} elseif ( 'Hello world!' !== $cic_sample->post_title || false === strpos( $cic_sample->post_content, 'Welcome to WordPress' ) ) {
	$cic_note( 'content', "post #{$cic_sample->ID} hello-world", 'kept', 'edited; not the untouched sample' );
} else {
	$cic_note( 'content', "post #{$cic_sample->ID} hello-world", $cic_dry ? 'would set' : 'set', 'publish', 'draft' );
	if ( ! $cic_dry ) {
		wp_update_post( array( 'ID' => $cic_sample->ID, 'post_status' => 'draft' ) );
	}
}

/* ------------------------------------------------------------------ */
$cic_counts = array_count_values( array_column( $cic_log, 'result' ) );
ksort( $cic_counts );
WP_CLI::log( 'Summary: ' . implode( ', ', array_map( static fn( $k, $v ) => "$k $v", array_keys( $cic_counts ), $cic_counts ) ) );
if ( '' !== $cic_report ) {
	$cic_json = wp_json_encode( array( 'run' => gmdate( 'c' ), 'site' => home_url( '/' ), 'dry_run' => $cic_dry, 'changes' => $cic_log ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === file_put_contents( $cic_report, $cic_json ) ) {
		WP_CLI::warning( 'Could not write the report to ' . $cic_report );
	} else {
		WP_CLI::log( 'Report: ' . $cic_report );
	}
}
WP_CLI::success( $cic_dry ? 'Dry run finished.' : 'Done. Next: wp yoast index' );
