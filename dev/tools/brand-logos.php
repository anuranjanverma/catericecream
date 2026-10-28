<?php
/**
 * Client-supplied finished logos (2026-10-22): logo-main.webp for the header only and
 * logo-transparent.webp (white artwork, transparent background) for the footer only.
 *
 * Usage: wp eval-file dev/tools/brand-logos.php
 *
 * Copies each file into the Media Library (uploads), so the site never depends on the handoff
 * folder, tags it (_cic_brand_asset = logo-header / logo-footer) and assigns it through the central
 * Header, Footer & Contact settings. The footer's light plate is switched off so the white artwork
 * keeps its transparency on the dark footer. Displayed sizes stay as approved (the theme fits the
 * artwork into the approved 2:1 logo box). The files are imported as supplied: never redrawn,
 * recoloured or resized. Safe to re-run (existing tagged attachments are reused). Favicon, emails
 * and other images are not touched.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Site\Layout;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$cic_dir   = ABSPATH . 'handoff/Cater_Ice_Cream_WebP_Assets/logos/';
$cic_logos = array(
	'logo-header' => array( 'file' => 'logo-main.webp', 'title' => 'Cater Ice Cream logo (header)' ),
	'logo-footer' => array( 'file' => 'logo-transparent.webp', 'title' => 'Cater Ice Cream logo, white (footer)' ),
);
$cic_ids = array();
foreach ( $cic_logos as $tag => $logo ) {
	$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_cic_brand_asset', 'meta_value' => $tag, 'numberposts' => 1, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $found ) {
		$cic_ids[ $tag ] = (int) $found[0];
		WP_CLI::log( "{$tag}: already in the Media Library (#{$found[0]})" );
		continue;
	}
	$source = $cic_dir . $logo['file'];
	if ( ! is_readable( $source ) ) {
		WP_CLI::error( "Missing {$source}" );
	}
	$tmp = wp_tempnam( $logo['file'] );
	copy( $source, $tmp );
	$id = media_handle_sideload( array( 'name' => $logo['file'], 'tmp_name' => $tmp ), 0, $logo['title'] );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( $id, '_cic_brand_asset', $tag );
	update_post_meta( $id, '_wp_attachment_image_alt', 'Cater Ice Cream' );
	$cic_ids[ $tag ] = (int) $id;
	$meta            = wp_get_attachment_metadata( $id );
	WP_CLI::log( sprintf( '%s: imported as #%d (%s, %dx%d)', $tag, $id, wp_basename( (string) get_attached_file( $id ) ), $meta['width'] ?? 0, $meta['height'] ?? 0 ) );
}

$cic_saved = get_option( Layout::OPTION, array() );
$cic_saved = is_array( $cic_saved ) ? $cic_saved : array();
$cic_saved['main']                 = array_merge( (array) ( $cic_saved['main'] ?? array() ), array( 'logo_id' => $cic_ids['logo-header'] ) );
$cic_saved['footer']               = array_merge( (array) ( $cic_saved['footer'] ?? array() ), array( 'logo_id' => $cic_ids['logo-footer'], 'logo_plate' => false ) );
update_option( Layout::OPTION, $cic_saved );
$cic_now = Layout::get();
WP_CLI::success( sprintf( 'Header logo #%d (mobile falls back to it), footer logo #%d, footer plate %s; widths %d/%d/%d px (desktop/mobile/compact), footer %d px.', $cic_now['main']['logo_id'], $cic_now['footer']['logo_id'], $cic_now['footer']['logo_plate'] ? 'on' : 'off', $cic_now['main']['logo_width'], $cic_now['main']['logo_width_mobile'], $cic_now['main']['logo_width_compact'], $cic_now['footer']['logo_width'] ) );
