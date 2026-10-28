<?php
/**
 * Concise image alt text (owner QA request, 2026-10-26).
 *
 * The image-pack attachments were imported with their image-generation prompts as alt text
 * ("Appetizing macro product photograph … NOT candy balls, NOT cereal …"). This replaces those with
 * the short descriptions in alt-text-20261026.json, one per asset id.
 *
 * - Attachments are found by asset id (_cic_asset_id, the Media importer's key), or else by a file
 *   name equal to the asset id (e.g. events-treat-trio.webp). Never by attachment ID.
 * - An alt text is replaced only when it is generated text: identical to the pack's alt/description,
 *   longer than 125 characters, or containing prompt wording (NOT / TINY / MANY, "photograph",
 *   "photorealistic", "macro", "still life", "no logos", …). Anything else is an editor's own
 *   description and is kept (reported).
 * - Media library only: titles, captions, files and image placements are untouched.
 *
 * Usage: wp eval-file dev/tools/content/alt-text-20261026.php [dry-run]
 * (The pack's original alt/description per asset is stored in the JSON file, so the image pack
 * itself is not needed on the server.)
 *
 * Safe to re-run: a second run reports every asset as already done.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

$cic_args = (array) ( $args ?? array() );
$cic_dry  = in_array( 'dry-run', $cic_args, true );
$cic_data = json_decode( (string) file_get_contents( __DIR__ . '/alt-text-20261026.json' ), true );
$cic_map  = (array) ( $cic_data['alt'] ?? array() );
$cic_pack = (array) ( $cic_data['generated'] ?? array() );
if ( ! $cic_map ) {
	WP_CLI::error( 'alt-text-20261026.json is missing or empty.' );
}

$cic_generated = static function ( string $alt, string $key ) use ( $cic_pack ): bool {
	if ( '' === $alt ) {
		return false;
	}
	foreach ( (array) ( $cic_pack[ $key ] ?? array() ) as $prompt ) {
		// The pack text itself, or an earlier shortened copy of it (a prefix of the prompt).
		if ( '' !== $prompt && ( $alt === $prompt || ( mb_strlen( $alt ) >= 40 && 0 === strpos( $prompt, rtrim( $alt, ' .,' ) ) ) ) ) {
			return true;
		}
	}
	return mb_strlen( $alt ) > 125
		|| (bool) preg_match( '/\b(NOT|TINY|MANY)\b/', $alt )
		|| (bool) preg_match( '/photorealistic|photograph|macro|still life|no (labels|logos|lettering)|studio background|bokeh|\d-\d ?mm/i', $alt );
};
$cic_find = static function ( string $key ): int {
	$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_cic_asset_id', 'meta_value' => $key, 'numberposts' => 1, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $ids ) {
		return (int) $ids[0];
	}
	global $wpdb;
	$like = '%/' . $wpdb->esc_like( $key ) . '.webp';
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'attachment' WHERE m.meta_key = '_wp_attached_file' AND ( m.meta_value LIKE %s OR m.meta_value = %s ) ORDER BY m.post_id LIMIT 1", $like, $key . '.webp' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};

WP_CLI::log( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' );
$cic_n = array( 'changed' => 0, 'done' => 0, 'kept' => 0, 'missing' => 0 );
foreach ( $cic_map as $cic_key => $cic_new ) {
	$cic_id = $cic_find( (string) $cic_key );
	if ( ! $cic_id ) {
		++$cic_n['missing'];
		WP_CLI::log( "- {$cic_key}: not in this Media Library (skipped)" );
		continue;
	}
	$cic_old = trim( (string) get_post_meta( $cic_id, '_wp_attachment_image_alt', true ) );
	if ( $cic_old === $cic_new ) {
		++$cic_n['done'];
		continue;
	}
	if ( '' !== $cic_old && ! $cic_generated( $cic_old, (string) $cic_key ) ) {
		++$cic_n['kept'];
		WP_CLI::log( "- {$cic_key} (#{$cic_id}): kept the existing description: {$cic_old}" );
		continue;
	}
	++$cic_n['changed'];
	WP_CLI::log( "- {$cic_key} (#{$cic_id})\n    before: " . ( '' !== $cic_old ? mb_substr( $cic_old, 0, 140 ) . ( mb_strlen( $cic_old ) > 140 ? '…' : '' ) : '(empty)' ) . "\n    after:  {$cic_new}" );
	if ( ! $cic_dry ) {
		update_post_meta( $cic_id, '_wp_attachment_image_alt', wp_slash( $cic_new ) );
	}
}
WP_CLI::success( sprintf( '%s %d, already done %d, kept (editor text) %d, not found %d.', $cic_dry ? 'Would change' : 'Changed', $cic_n['changed'], $cic_n['done'], $cic_n['kept'], $cic_n['missing'] ) );
