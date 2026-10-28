<?php
/**
 * Public brand name "Cater Ice Cream" → "CaterIceCream.com" (owner QA request, 2026-10-26).
 * Content and settings only; the code defaults ship separately.
 *
 * What it changes (WordPress APIs only, any table prefix; serialized data is read and written as
 * PHP arrays, never edited as text; no SQL REPLACE):
 *   1. Site identity: Site Title (blogname) and Tagline.
 *   2. Central business details (Header, Footer & Contact): business name, and the website label
 *      when it is still the lower-case domain "catericecream.com".
 *   3. Text settings: header/footer layout (copyright line, ...), enquiry email texts and subjects
 *      and the sender name, Yoast SEO titles/social settings, text widgets.
 *   4. Public content: pages, posts, products, events, city content posts, reusable blocks and menu
 *      item labels (title, content, excerpt) and their text fields (ACF and Yoast per-post titles,
 *      descriptions and social texts).
 *   5. Media: image alt text, captions and descriptions.
 *
 * Never touched: inquiries (Flamingo messages and contacts), CF7 form definitions (admin titles;
 * their mail tabs are unused), revisions, slugs, URLs, file names, technical identifiers
 * (e.g. "cater-ice-cream"), user accounts, and Frosty Yummy LLC (the legal name). Rows in the
 * custom location/pairing tables are reported for editing in wp-admin, never written here.
 *
 * Matching: "Cater Ice Cream" with spacing/hyphen/case variants ("Cater Ice-Cream", "cater
 * icecream", "CATER ICE CREAM"). Forms without any separator ("catericecream", domains, paths) and
 * the lower-case slug "cater-ice-cream" are left alone.
 *
 * Usage: wp eval-file dev/tools/content/brand-name-20261026.php [dry-run] [report=<file.json>]
 *
 * Prints every change as before → after (a short excerpt around each match). Safe to re-run: a
 * second run finds nothing to change.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

$cic_args   = (array) ( $args ?? array() );
$cic_dry    = in_array( 'dry-run', $cic_args, true );
$cic_report = '';
foreach ( $cic_args as $cic_arg ) {
	if ( 0 === strpos( (string) $cic_arg, 'report=' ) ) {
		$cic_report = substr( (string) $cic_arg, 7 );
	}
}
$cic_brand = 'CaterIceCream.com';
// Content is updated as stored: no kses filtering of existing markup by the CLI user.
kses_remove_filters();

$cic_changes = array();
$cic_notes   = array();

/** Replaces brand variants in one string; technical forms are kept. */
$cic_replace = static function ( string $text ) use ( $cic_brand ): string {
	return (string) preg_replace_callback(
		'/(?<![\w\/.@-])cater(?:[ \t\-]+ice[ \t\-]*|ice[ \t\-]+)cream(?![\w\/@-]|\.com)/i',
		static function ( array $m ) use ( $cic_brand ): string {
			// "cater-ice-cream" (lower case, hyphens only) is a slug/identifier, not the public name.
			if ( strtolower( $m[0] ) === $m[0] && false === strpbrk( $m[0], " \t" ) ) {
				return $m[0];
			}
			return $cic_brand;
		},
		$text
	);
};
/** Short before → after excerpt around the first difference. */
$cic_excerpt = static function ( string $before, string $after ): array {
	$pos = strspn( $before ^ $after, "\0" );
	$from = max( 0, $pos - 40 );
	return array(
		'before' => ( $from ? '…' : '' ) . mb_substr( substr( $before, $from ), 0, 110 ),
		'after'  => ( $from ? '…' : '' ) . mb_substr( substr( $after, $from ), 0, 110 ),
	);
};
/** Recursive replace inside arrays/objects (string values only; keys and other types kept). */
$cic_deep = static function ( $value ) use ( &$cic_deep, $cic_replace ) {
	if ( is_string( $value ) ) {
		return $cic_replace( $value );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = $cic_deep( $v );
		}
	}
	return $value;
};
$cic_record = static function ( string $where, $before, $after ) use ( &$cic_changes, $cic_excerpt ): void {
	$b = is_string( $before ) ? $before : wp_json_encode( $before );
	$a = is_string( $after ) ? $after : wp_json_encode( $after );
	$cic_changes[] = array_merge( array( 'where' => $where ), $cic_excerpt( (string) $b, (string) $a ) );
};

WP_CLI::log( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' );

/* 1–3. Options. */
foreach ( array( 'blogname', 'blogdescription' ) as $cic_opt ) {
	$cic_before = (string) get_option( $cic_opt, '' );
	$cic_after  = $cic_replace( $cic_before );
	if ( $cic_after !== $cic_before ) {
		$cic_record( "option {$cic_opt}", $cic_before, $cic_after );
		$cic_dry || update_option( $cic_opt, $cic_after );
	}
}

$cic_core = get_option( 'cic_core_settings', null );
if ( is_array( $cic_core ) ) {
	$cic_new = $cic_core;
	if ( isset( $cic_new['business_name'] ) ) {
		$cic_new['business_name'] = $cic_replace( (string) $cic_new['business_name'] );
	}
	if ( isset( $cic_new['website_label'] ) && 'catericecream.com' === strtolower( trim( (string) $cic_new['website_label'] ) ) && $cic_brand !== $cic_new['website_label'] ) {
		$cic_new['website_label'] = $cic_brand;
	}
	foreach ( array( 'business_name', 'website_label' ) as $cic_k ) {
		if ( ( $cic_core[ $cic_k ] ?? null ) !== ( $cic_new[ $cic_k ] ?? null ) ) {
			$cic_record( "option cic_core_settings[{$cic_k}]", (string) $cic_core[ $cic_k ], (string) $cic_new[ $cic_k ] );
		}
	}
	if ( $cic_new !== $cic_core && ! $cic_dry ) {
		update_option( 'cic_core_settings', $cic_new );
	}
}

// Text settings: only these keys of the enquiry settings (never recipients or addresses).
$cic_enq = get_option( 'cic_enquiry_settings', null );
if ( is_array( $cic_enq ) ) {
	$cic_new = $cic_enq;
	foreach ( array( 'from_name', 'operator_subject', 'quote_ack_subject', 'quote_ack_body', 'contact_ack_subject', 'contact_ack_body' ) as $cic_k ) {
		if ( isset( $cic_new[ $cic_k ] ) && is_string( $cic_new[ $cic_k ] ) ) {
			$cic_new[ $cic_k ] = $cic_replace( $cic_new[ $cic_k ] );
			if ( $cic_new[ $cic_k ] !== $cic_enq[ $cic_k ] ) {
				$cic_record( "option cic_enquiry_settings[{$cic_k}]", $cic_enq[ $cic_k ], $cic_new[ $cic_k ] );
			}
		}
	}
	if ( $cic_new !== $cic_enq && ! $cic_dry ) {
		update_option( 'cic_enquiry_settings', $cic_new );
	}
}

global $wpdb;
$cic_widget_options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'widget\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( array_merge( array( 'cic_site_layout', 'wpseo_titles', 'wpseo_social' ), $cic_widget_options ) as $cic_opt ) {
	$cic_before = get_option( $cic_opt, null );
	if ( ! is_array( $cic_before ) ) {
		continue;
	}
	$cic_after = $cic_deep( $cic_before );
	if ( $cic_after !== $cic_before ) {
		array_walk_recursive(
			$cic_before,
			static function ( $v, $k ) use ( $cic_replace, $cic_record, $cic_opt ): void {
				if ( is_string( $v ) && $cic_replace( $v ) !== $v ) {
					$cic_record( "option {$cic_opt}[…{$k}]", $v, $cic_replace( $v ) );
				}
			}
		);
		$cic_dry || update_option( $cic_opt, $cic_after );
	}
}

/* 4–5. Posts, their text fields, and media. */
$cic_types = array( 'page', 'post', 'product', 'cic_event', 'cic_city_editorial', 'wp_block', 'nav_menu_item', 'attachment' );
$cic_meta_ok = static function ( string $key, string $type ): bool {
	if ( 'attachment' === $type ) {
		return '_wp_attachment_image_alt' === $key;
	}
	if ( 0 === strpos( $key, '_yoast_wpseo_' ) ) {
		return (bool) preg_match( '/_(title|metadesc|focuskw|opengraph-title|opengraph-description|twitter-title|twitter-description|bctitle)$/', $key );
	}
	return '_' !== $key[0];
};
$cic_ids = get_posts(
	array(
		'post_type'        => $cic_types,
		'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'inherit' ),
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	)
);
foreach ( $cic_ids as $cic_id ) {
	$cic_post = get_post( $cic_id );
	if ( ! $cic_post || ( 'inherit' === $cic_post->post_status && 'attachment' !== $cic_post->post_type ) ) {
		continue;
	}
	$cic_update = array();
	// Media titles are admin-only; content/excerpt are the description and caption.
	$cic_fields = 'attachment' === $cic_post->post_type ? array( 'post_content', 'post_excerpt' ) : array( 'post_title', 'post_content', 'post_excerpt' );
	foreach ( $cic_fields as $cic_f ) {
		$cic_after = $cic_replace( (string) $cic_post->$cic_f );
		if ( $cic_after !== (string) $cic_post->$cic_f ) {
			$cic_update[ $cic_f ] = $cic_after;
			$cic_record( "{$cic_post->post_type} #{$cic_id} {$cic_f}", (string) $cic_post->$cic_f, $cic_after );
		}
	}
	if ( $cic_update && ! $cic_dry ) {
		$cic_update['ID'] = $cic_id;
		wp_update_post( wp_slash( $cic_update ) );
	}
	foreach ( (array) get_post_meta( $cic_id ) as $cic_key => $cic_values ) {
		if ( ! $cic_meta_ok( (string) $cic_key, $cic_post->post_type ) || 1 !== count( $cic_values ) ) {
			continue;
		}
		$cic_before = maybe_unserialize( $cic_values[0] );
		$cic_after  = $cic_deep( $cic_before );
		if ( $cic_after !== $cic_before ) {
			$cic_record( "{$cic_post->post_type} #{$cic_id} meta {$cic_key}", $cic_before, $cic_after );
			$cic_dry || update_post_meta( $cic_id, $cic_key, wp_slash( $cic_after ) );
		}
	}
}

/* Terms (names and descriptions of public taxonomies). */
foreach ( get_terms( array( 'taxonomy' => array_values( get_taxonomies( array( 'public' => true ) ) ), 'hide_empty' => false ) ) as $cic_term ) {
	$cic_update = array();
	foreach ( array( 'name', 'description' ) as $cic_f ) {
		$cic_after = $cic_replace( (string) $cic_term->$cic_f );
		if ( $cic_after !== (string) $cic_term->$cic_f ) {
			$cic_update[ $cic_f ] = $cic_after;
			$cic_record( "term #{$cic_term->term_id} ({$cic_term->taxonomy}) {$cic_f}", (string) $cic_term->$cic_f, $cic_after );
		}
	}
	if ( $cic_update && ! $cic_dry ) {
		wp_update_term( $cic_term->term_id, $cic_term->taxonomy, $cic_update );
	}
}

/* Custom tables: report only (edit in wp-admin; writes go through CityStore / Pairings). */
foreach ( array( 'cic_city_content', 'cic_product_events' ) as $cic_table ) {
	$cic_t = $wpdb->prefix . $cic_table;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cic_t ) ) !== $cic_t ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		continue;
	}
	$cic_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$cic_t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$cic_rows = $wpdb->get_results( "SELECT * FROM {$cic_t}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	foreach ( $cic_rows as $cic_row ) {
		foreach ( $cic_cols as $cic_col ) {
			if ( is_string( $cic_row[ $cic_col ] ) && $cic_replace( $cic_row[ $cic_col ] ) !== $cic_row[ $cic_col ] ) {
				$cic_notes[] = "{$cic_table} row " . wp_json_encode( array_slice( $cic_row, 0, 2 ) ) . " column {$cic_col}: contains the old name; edit it in wp-admin.";
			}
		}
	}
}

foreach ( $cic_changes as $cic_c ) {
	WP_CLI::log( '- ' . $cic_c['where'] . "\n    before: " . $cic_c['before'] . "\n    after:  " . $cic_c['after'] );
}
foreach ( $cic_notes as $cic_n ) {
	WP_CLI::warning( $cic_n );
}
if ( '' !== $cic_report ) {
	file_put_contents( $cic_report, wp_json_encode( array( 'dry_run' => $cic_dry, 'changes' => $cic_changes, 'report_only' => $cic_notes ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	WP_CLI::log( 'Report written: ' . $cic_report );
}
if ( ! $cic_dry && $cic_changes ) {
	wp_cache_flush();
}
WP_CLI::success( sprintf( '%s %d change(s)%s.', $cic_dry ? 'Would make' : 'Made', count( $cic_changes ), $cic_changes ? '' : ' (already done)' ) );
