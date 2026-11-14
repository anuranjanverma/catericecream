<?php
/**
 * Keyword URL slugs of 2026-11-12 (SEO review, handoff/cater-ice-cream/CIC_URL Optimization.csv):
 *
 *   1. Renames the ten treat (WooCommerce product) and event (cic_event) slugs, e.g.
 *      /treats/dippin-dots/ → /treats/dippin-dots-catering/,
 *      /events/birthday/    → /events/birthday-party-ice-cream-catering/.
 *      Records are found by their stable cic_key (never by ID or slug), so this works on any copy.
 *      WordPress keeps the old slug (_wp_old_slug) and answers the old address with a 301 to the
 *      new one. A slug that is neither the old nor the new one (changed in wp-admin) is reported and
 *      kept.
 *   2. Custom links in every menu that point to an old address on this site get the new address
 *      (menu ordering of events matches menu paths against permalinks, so this matters).
 *   3. Links in post content (href only, relative or on this site's host) are updated through
 *      wp_update_post, so revisions keep the previous text.
 *
 * Singular event names (owner, 2026-11-13): wedding, prom and corporate now end on singular slugs
 * (wedding-…, prom-…, corporate-event-…). Each record accepts several old slugs: the original one
 * (live) and the plural one used locally on 2026-11-12; every old address 301s in one hop.
 *
 * Location (/service-areas/...) URLs are NOT changed by this tool.
 *
 * Usage: wp eval-file dev/tools/content/url-slugs-20261112.php [dry-run]
 * Safe to re-run: a second run reports "already done" everywhere. WordPress APIs only.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

$cic_args = (array) ( $args ?? array() );
$cic_dry  = in_array( 'dry-run', $cic_args, true );

/** cic_key => array( post type, URL base, old slug(s), new slug ). */
$cic_map = array(
	'dippin-dots'       => array( 'product', 'treats', 'dippin-dots', 'dippin-dots-catering' ),
	'dipn-bananas'      => array( 'product', 'treats', 'dipn-bananas', 'dipn-bananas-catering' ),
	'classic-ice-cream' => array( 'product', 'treats', 'classic-ice-cream', 'classic-ice-cream-catering' ),
	'frozen-yogurt'     => array( 'product', 'treats', 'frozen-yogurt', 'frozen-yogurt-catering' ),
	'birthday'          => array( 'cic_event', 'events', 'birthday', 'birthday-party-ice-cream-catering' ),
	'wedding'           => array( 'cic_event', 'events', array( 'wedding', 'weddings-ice-cream-catering' ), 'wedding-ice-cream-catering' ),
	'graduation'        => array( 'cic_event', 'events', 'graduation-parties', 'graduation-party-ice-cream-catering' ),
	'prom'              => array( 'cic_event', 'events', array( 'proms', 'proms-ice-cream-catering' ), 'prom-ice-cream-catering' ),
	'corporate'         => array( 'cic_event', 'events', array( 'corporate', 'corporate-events-ice-cream-catering' ), 'corporate-event-ice-cream-catering' ),
	'anniversary'       => array( 'cic_event', 'events', 'anniversary', 'anniversary-ice-cream-catering' ),
);

WP_CLI::log( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' );

/* 1. Slugs. Old path => new path, only for records that are (or will be) on the new slug. */
$cic_paths = array();
foreach ( $cic_map as $cic_key => $cic_row ) {
	list( $cic_type, $cic_base, $cic_olds, $cic_new ) = $cic_row;
	$cic_olds = (array) $cic_olds;
	$cic_id = \CIC\Content\Catalog::find_by_key( $cic_type, $cic_key );
	if ( ! $cic_id ) {
		WP_CLI::warning( "Slug: no {$cic_type} with key \"{$cic_key}\"; skipped." );
		continue;
	}
	$cic_slug = (string) get_post_field( 'post_name', $cic_id );
	if ( $cic_new === $cic_slug ) {
		WP_CLI::log( "Slug: #{$cic_id} {$cic_key} is already \"{$cic_new}\" (already done)." );
	} elseif ( in_array( $cic_slug, $cic_olds, true ) ) {
		$cic_old = $cic_slug;
		$cic_clash = get_page_by_path( $cic_new, OBJECT, $cic_type );
		if ( $cic_clash && (int) $cic_clash->ID !== $cic_id ) {
			WP_CLI::warning( "Slug: \"{$cic_new}\" is already used by #{$cic_clash->ID}; #{$cic_id} kept as \"{$cic_old}\"." );
			continue;
		}
		WP_CLI::log( "Slug: #{$cic_id} /{$cic_base}/{$cic_old}/ → /{$cic_base}/{$cic_new}/" );
		if ( ! $cic_dry ) {
			if ( 'product' === $cic_type && function_exists( 'wc_get_product' ) && wc_get_product( $cic_id ) ) {
				$cic_product = wc_get_product( $cic_id );
				$cic_product->set_slug( $cic_new );
				$cic_product->save();
			} else {
				wp_update_post( array( 'ID' => $cic_id, 'post_name' => $cic_new ) );
			}
			clean_post_cache( $cic_id );
			if ( get_post_field( 'post_name', $cic_id ) !== $cic_new ) {
				WP_CLI::warning( "Slug: #{$cic_id} could not be renamed (now \"" . get_post_field( 'post_name', $cic_id ) . '").' );
				continue;
			}
			// Core records the old slug on publish-to-publish updates; make sure the 301 source exists.
			if ( ! in_array( $cic_old, (array) get_post_meta( $cic_id, '_wp_old_slug' ), true ) ) {
				add_post_meta( $cic_id, '_wp_old_slug', $cic_old );
			}
		}
	} else {
		WP_CLI::warning( "Slug: #{$cic_id} {$cic_key} is \"{$cic_slug}\" (changed in wp-admin); kept. Links to its old addresses are left alone." );
		continue;
	}
	foreach ( $cic_olds as $cic_from ) {
		$cic_paths[ "/{$cic_base}/{$cic_from}/" ] = "/{$cic_base}/{$cic_new}/";
	}
}

$cic_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

/* 2. Custom menu links (all menus). */
$cic_menu_hits = 0;
foreach ( wp_get_nav_menus() as $cic_menu ) {
	foreach ( (array) wp_get_nav_menu_items( $cic_menu->term_id, array( 'post_status' => 'any' ) ) as $cic_item ) {
		if ( 'custom' !== $cic_item->type ) {
			continue;
		}
		$cic_url  = (string) $cic_item->url;
		$cic_uh   = (string) wp_parse_url( $cic_url, PHP_URL_HOST );
		$cic_path = '/' . trim( (string) wp_parse_url( $cic_url, PHP_URL_PATH ), '/' ) . '/';
		if ( ( '' !== $cic_uh && $cic_uh !== $cic_host ) || ! isset( $cic_paths[ $cic_path ] ) ) {
			continue;
		}
		$cic_new_url = (string) preg_replace( '#' . preg_quote( rtrim( $cic_path, '/' ), '#' ) . '/?#', $cic_paths[ $cic_path ], $cic_url, 1 );
		++$cic_menu_hits;
		WP_CLI::log( "Menu \"{$cic_menu->name}\": item #{$cic_item->ID} \"{$cic_item->title}\" {$cic_url} → {$cic_new_url}" );
		if ( ! $cic_dry ) {
			update_post_meta( (int) $cic_item->ID, '_menu_item_url', esc_url_raw( $cic_new_url ) );
		}
	}
}
if ( ! $cic_menu_hits ) {
	WP_CLI::log( 'Menus: no custom links to old addresses (already done).' );
}

/* 3. Links in post content. */
$cic_post_hits = 0;
if ( $cic_paths ) {
	$cic_host_re = '(?:https?:)?//' . preg_quote( $cic_host, '#' );
	$cic_types   = array_diff( get_post_types(), array( 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'wp_global_styles' ) );
	$cic_posts   = get_posts(
		array(
			'post_type'        => array_values( $cic_types ),
			'post_status'      => 'any',
			'numberposts'      => -1,
			'suppress_filters' => true,
		)
	);
	foreach ( $cic_posts as $cic_post ) {
		$cic_content = (string) $cic_post->post_content;
		$cic_changed = $cic_content;
		foreach ( $cic_paths as $cic_from => $cic_to ) {
			$cic_changed = (string) preg_replace(
				'#(href=(["\'])(?:' . $cic_host_re . ')?)' . preg_quote( rtrim( $cic_from, '/' ), '#' ) . '/?(?=[\#?"\'])#',
				'$1' . $cic_to,
				$cic_changed
			);
		}
		if ( $cic_changed === $cic_content ) {
			continue;
		}
		++$cic_post_hits;
		WP_CLI::log( "Content: #{$cic_post->ID} {$cic_post->post_type} ({$cic_post->post_status}) \"{$cic_post->post_title}\": links updated." );
		if ( ! $cic_dry ) {
			wp_update_post( array( 'ID' => $cic_post->ID, 'post_content' => wp_slash( $cic_changed ) ) );
		}
	}
}
if ( ! $cic_post_hits ) {
	WP_CLI::log( 'Content: no links to old addresses (already done).' );
}

WP_CLI::success( $cic_dry ? 'Dry run finished: nothing changed.' : 'Done. Old addresses answer with a 301 (WordPress old-slug redirect).' );
