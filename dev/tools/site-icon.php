<?php
/**
 * Site Icon (favicon) from the approved mascot. CLI only:
 *   wp eval-file dev/tools/site-icon.php            create once (skips if a Site Icon is set)
 *   wp eval-file dev/tools/site-icon.php --force    replace the current Site Icon
 *
 * - Crops the dome, face and tray of the installed approved mascot
 *   (themes/cater-ice-cream/assets/brand/mascot-original.webp, x 70–570, y 14–364: no wordmark, no
 *   hand) onto a 512×512 white square, saved as assets/brand/site-icon-512.png (PNG, because
 *   favicons and touch icons need broadly supported formats; the WebP rule covers photographs).
 *   No new artwork: pixels are only cropped, scaled and placed on the background.
 * - Registers it the way Settings → General → Site Icon does: a normal media item (the source)
 *   plus a "site-icon" copy with WordPress's icon sizes (32, 180, 192, 270, 512). WordPress then
 *   prints the icon links itself and serves /favicon.ico. It stays replaceable in the admin.
 *
 * @package CIC\Tools
 */

defined( 'WP_CLI' ) || exit;

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';

$force   = in_array( '--force', $args, true ) || in_array( 'force', $args, true );
$current = (int) get_option( 'site_icon' );
if ( $current && ! $force ) {
	WP_CLI::success( "Site Icon already set (attachment #{$current}). Use --force to replace it." );
	return;
}

// 1. Crop the approved mascot (theme copy of the handoff file).
$src  = get_template_directory() . '/assets/brand/mascot-original.webp';
$dest = get_template_directory() . '/assets/brand/site-icon-512.png';
$im   = imagecreatefromwebp( $src );
if ( ! $im ) {
	WP_CLI::error( 'Cannot read the mascot artwork.' );
}
$crop   = array( 'x' => 70, 'y' => 14, 'w' => 500, 'h' => 350 );
$size   = 512;
$inner  = 464; // 24 px margin each side keeps the artwork clear of rounded icon masks.
$scale  = $inner / $crop['w'];
$out_w  = (int) round( $crop['w'] * $scale );
$out_h  = (int) round( $crop['h'] * $scale );
$canvas = imagecreatetruecolor( $size, $size );
imagealphablending( $canvas, true );
imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, 255, 255, 255 ) );
imagecopyresampled( $canvas, $im, (int) ( ( $size - $out_w ) / 2 ), (int) ( ( $size - $out_h ) / 2 ), $crop['x'], $crop['y'], $out_w, $out_h, $crop['w'], $crop['h'] );
imagepng( $canvas, $dest, 9 );
WP_CLI::log( 'Wrote ' . str_replace( ABSPATH, '', $dest ) . " ({$size}×{$size})." );

// 2. Source media item (kept in the library so the icon can be re-cropped or replaced later).
$upload = wp_upload_bits( 'cater-site-icon.png', null, (string) file_get_contents( $dest ) );
if ( ! empty( $upload['error'] ) ) {
	WP_CLI::error( 'Upload failed.' );
}
$parent = wp_insert_attachment(
	array(
		'post_title'     => 'Cater Ice Cream site icon (mascot crop)',
		'post_content'   => 'Crop of the approved mascot (mascot-original.webp) for the Site Icon. No new artwork.',
		'post_mime_type' => 'image/png',
		'post_status'    => 'inherit',
	),
	$upload['file']
);
wp_update_attachment_metadata( $parent, wp_generate_attachment_metadata( $parent, $upload['file'] ) );
update_post_meta( $parent, '_wp_attachment_image_alt', '' );
update_post_meta( $parent, '_cic_brand_asset', 'site-icon-source' );

// 3. The Site Icon copy with WordPress's icon sizes (same steps as the admin crop flow).
$info    = pathinfo( $upload['file'] );
$cropped = $info['dirname'] . '/cropped-' . $info['basename'];
copy( $upload['file'], $cropped );
$wp_site_icon = new WP_Site_Icon();
$attachment   = wp_copy_parent_attachment_properties( $cropped, $parent, 'site-icon' );
add_filter( 'intermediate_image_sizes_advanced', array( $wp_site_icon, 'additional_sizes' ) );
$icon = $wp_site_icon->insert_attachment( $attachment, $cropped );
remove_filter( 'intermediate_image_sizes_advanced', array( $wp_site_icon, 'additional_sizes' ) );
update_post_meta( $icon, '_cic_brand_asset', 'site-icon' ); // Not an image-pack master, so not _cic_asset_id.
update_option( 'site_icon', $icon );

$sizes = array_keys( (array) ( wp_get_attachment_metadata( $icon )['sizes'] ?? array() ) );
WP_CLI::success( "Site Icon set: attachment #{$icon} (source #{$parent}); sizes: " . implode( ', ', $sizes ) );
