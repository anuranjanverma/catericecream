<?php
/**
 * Imports the authentic treat media (2026-10-24) and places it on the Dippin' Dots and DipN' Bananas
 * pages. Content/media step only: deploy the code first, upload the media package (built by
 * build-treat-media.cjs) to the server, then run this from the release package.
 *
 * Usage: wp eval-file dev/tools/content/treat-media-20261024.php [dry-run] [source=<media package dir>]
 *
 * - Stable identifiers: every attachment is found by its asset key in the _cic_asset_id meta (the
 *   image-pack convention); IDs are resolved on the destination site, never copied from local.
 * - Re-running never duplicates an attachment and changes nothing that is already in place.
 * - Package files are checked against SHA256SUMS before anything is imported.
 * - Existing content wins: the Dippin' Dots detail photo is replaced only while it is still the
 *   seeded AI image (or empty); the "Photos and videos" fields are filled only while empty. Anything
 *   an editor changed is reported and left alone.
 * - Captions and alt text come from treat-media-20261024.json and contain no URLs, names or dates.
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Content\Catalog;
use CIC\Content\Fields;
use CIC\Content\Media;

$cic_args = (array) ( $args ?? array() );
$cic_dry  = in_array( 'dry-run', $cic_args, true );
$cic_src  = 'C:/laragon/release/catericecream/media-20261024-treats';
foreach ( $cic_args as $cic_arg ) {
	if ( str_starts_with( (string) $cic_arg, 'source=' ) ) {
		$cic_src = rtrim( substr( (string) $cic_arg, 7 ), '/\\' );
	}
}
$cic_manifest = json_decode( (string) file_get_contents( __DIR__ . '/treat-media-20261024.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
is_array( $cic_manifest ) || WP_CLI::error( 'Unreadable manifest treat-media-20261024.json.' );
WP_CLI::log( ( $cic_dry ? 'DRY RUN: nothing will be changed. ' : '' ) . 'Media package: ' . $cic_src );

/* ---------------- 1. Package check ---------------- */
$cic_sums = array();
if ( is_readable( $cic_src . '/SHA256SUMS' ) ) {
	foreach ( file( $cic_src . '/SHA256SUMS', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $cic_line ) {
		if ( preg_match( '/^([0-9a-f]{64}) \*?(.+)$/', trim( $cic_line ), $cic_mm ) ) {
			$cic_sums[ $cic_mm[2] ] = $cic_mm[1];
		}
	}
}
$cic_report  = is_readable( $cic_src . '/build-report.json' ) ? (array) json_decode( (string) file_get_contents( $cic_src . '/build-report.json' ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$cic_display = array();
foreach ( $cic_report as $cic_row ) {
	$cic_display[ $cic_row['key'] ?? '' ] = (int) ( $cic_row['width'] ?? 0 ) . 'x' . (int) ( $cic_row['height'] ?? 0 );
}
$cic_problems = array();
foreach ( $cic_manifest['assets'] as $cic_key => $cic_a ) {
	$cic_file = $cic_src . '/' . $cic_a['file'];
	if ( Media::find( $cic_key ) ) {
		continue; // Already imported: the package file is not needed.
	}
	if ( ! is_readable( $cic_file ) ) {
		$cic_problems[] = "missing {$cic_a['file']} (asset {$cic_key})";
	} elseif ( ! isset( $cic_sums[ $cic_a['file'] ] ) ) {
		$cic_problems[] = "no checksum for {$cic_a['file']} in SHA256SUMS";
	} elseif ( hash_file( 'sha256', $cic_file ) !== $cic_sums[ $cic_a['file'] ] ) {
		$cic_problems[] = "checksum mismatch for {$cic_a['file']}";
	}
}
if ( $cic_problems ) {
	WP_CLI::error( "Media package incomplete; nothing changed:\n  " . implode( "\n  ", $cic_problems ) );
}

/* ---------------- 2. Attachments ---------------- */
$cic_ids = array();
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
// Posters first, so videos can point at them.
uasort( $cic_manifest['assets'], static fn( $a, $b ) => ( 'video' === $a['kind'] ) <=> ( 'video' === $b['kind'] ) );
foreach ( $cic_manifest['assets'] as $cic_key => $cic_a ) {
	$cic_found = Media::find( $cic_key );
	if ( $cic_found ) {
		$cic_ids[ $cic_key ] = $cic_found;
		WP_CLI::log( "Media: {$cic_key} already in the Media Library (#{$cic_found}); reused." );
		continue;
	}
	WP_CLI::log( "Media: import {$cic_a['file']} as \"{$cic_a['title']}\"." );
	if ( $cic_dry ) {
		$cic_ids[ $cic_key ] = 0;
		continue;
	}
	$cic_tmp = wp_tempnam( $cic_a['file'] );
	copy( $cic_src . '/' . $cic_a['file'], $cic_tmp );
	$cic_id = media_handle_sideload(
		array(
			'name'     => $cic_a['file'],
			'tmp_name' => $cic_tmp,
		),
		0,
		$cic_a['title'],
		array(
			'post_excerpt' => (string) ( $cic_a['caption'] ?? '' ),
			'post_content' => 'Authentic ' . ( 'video' === $cic_a['kind'] ? 'video' : ( 'poster' === $cic_a['kind'] ? 'video frame' : 'photo' ) ) . ' supplied by the owner (original file: ' . $cic_a['source'] . '). Captions describe only what is visible.',
		)
	);
	if ( is_wp_error( $cic_id ) ) {
		@unlink( $cic_tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		WP_CLI::error( "Import of {$cic_a['file']} failed: " . $cic_id->get_error_message() );
	}
	update_post_meta( $cic_id, Media::META, $cic_key );
	update_post_meta( $cic_id, '_cic_asset_source', $cic_a['source'] );
	update_post_meta( $cic_id, '_cic_asset_sha256', (string) ( $cic_sums[ $cic_a['file'] ] ?? '' ) );
	if ( isset( $cic_a['alt'] ) ) {
		update_post_meta( $cic_id, '_wp_attachment_image_alt', sanitize_text_field( $cic_a['alt'] ) );
	}
	if ( 'video' === $cic_a['kind'] ) {
		// Display size after the rotation metadata (WordPress stores the coded size).
		if ( ! empty( $cic_display[ $cic_key ] ) && '0x0' !== $cic_display[ $cic_key ] ) {
			update_post_meta( $cic_id, '_cic_video_display', $cic_display[ $cic_key ] );
		}
		if ( ! empty( $cic_ids[ $cic_a['poster'] ] ) ) {
			set_post_thumbnail( $cic_id, $cic_ids[ $cic_a['poster'] ] );
		}
	}
	$cic_ids[ $cic_key ] = (int) $cic_id;
	WP_CLI::log( "  → #{$cic_id}" );
}

/* ---------------- 3. Placements ---------------- */
$cic_set = static function ( int $post, string $name, $value ) use ( $cic_dry ): void {
	if ( ! $cic_dry ) {
		update_post_meta( $post, $name, $value );
		update_post_meta( $post, '_' . $name, 'field_' . $name ); // ACF field reference.
	}
};
foreach ( $cic_manifest['placements'] as $cic_treat => $cic_place ) {
	$cic_product = Catalog::find_by_key( 'product', $cic_treat );
	if ( ! $cic_product ) {
		WP_CLI::warning( "Placement: treat \"{$cic_treat}\" not found; skipped." );
		continue;
	}
	$cic_name = html_entity_decode( get_the_title( $cic_product ), ENT_QUOTES, 'UTF-8' );

	if ( isset( $cic_place['detail_image'] ) ) {
		$cic_want    = $cic_ids[ $cic_place['detail_image']['asset'] ] ?? 0;
		$cic_current = (int) get_post_meta( $cic_product, 'cic_detail_image', true );
		$cic_seeded  = Media::find( $cic_place['detail_image']['replaces_seeded'] );
		if ( $cic_want && $cic_current === $cic_want ) {
			WP_CLI::log( "{$cic_name}: detail photo already set (already done)." );
		} elseif ( 0 === $cic_current || ( $cic_seeded && $cic_current === $cic_seeded ) ) {
			WP_CLI::log( "{$cic_name}: detail photo → {$cic_place['detail_image']['asset']} (was " . ( $cic_current ? "the seeded {$cic_place['detail_image']['replaces_seeded']} #{$cic_current}" : 'empty' ) . ').' );
			$cic_set( $cic_product, 'cic_detail_image', $cic_want );
		} else {
			WP_CLI::warning( "{$cic_name}: the detail photo was changed by an editor (#{$cic_current}); left alone. Choose {$cic_place['detail_image']['asset']} in Treat page composition → Product detail if wanted." );
		}
	}

	if ( isset( $cic_place['media'] ) ) {
		$cic_items = $cic_place['media']['items'];
		$cic_rows  = array();
		foreach ( $cic_items as $cic_n => $cic_asset ) {
			$cic_a      = $cic_manifest['assets'][ $cic_asset ];
			$cic_slot   = $cic_n + 1;
			$cic_image  = 'video' === $cic_a['kind'] ? ( $cic_ids[ $cic_a['poster'] ] ?? 0 ) : ( $cic_ids[ $cic_asset ] ?? 0 );
			$cic_video  = 'video' === $cic_a['kind'] ? ( $cic_ids[ $cic_asset ] ?? 0 ) : 0;
			$cic_rows[] = array( "cic_media_{$cic_slot}_image" => $cic_image, "cic_media_{$cic_slot}_video" => $cic_video, "cic_media_{$cic_slot}_caption" => (string) $cic_a['caption'] );
		}
		$cic_wanted = array_merge( array( 'cic_media_heading' => $cic_place['media']['heading'], 'cic_media_intro' => $cic_place['media']['intro'] ), ...$cic_rows );
		$cic_used   = array();
		foreach ( array( 'cic_media_heading', 'cic_media_intro' ) as $cic_f ) {
			if ( '' !== (string) get_post_meta( $cic_product, $cic_f, true ) ) {
				$cic_used[ $cic_f ] = get_post_meta( $cic_product, $cic_f, true );
			}
		}
		for ( $cic_i = 1; $cic_i <= Fields::MEDIA_SLOTS; $cic_i++ ) {
			foreach ( array( 'image', 'video', 'caption' ) as $cic_part ) {
				$cic_v = get_post_meta( $cic_product, "cic_media_{$cic_i}_{$cic_part}", true );
				if ( '' !== (string) $cic_v && '0' !== (string) $cic_v ) {
					$cic_used[ "cic_media_{$cic_i}_{$cic_part}" ] = $cic_v;
				}
			}
		}
		$cic_target = array_map( 'strval', array_filter( $cic_wanted, static fn( $v ) => '' !== (string) $v && '0' !== (string) $v ) );
		$cic_have   = array_map( 'strval', $cic_used );
		ksort( $cic_target );
		ksort( $cic_have );
		if ( $cic_used && $cic_target === $cic_have ) {
			WP_CLI::log( "{$cic_name}: Photos and videos already filled with these items (already done)." );
		} elseif ( $cic_used ) {
			WP_CLI::warning( "{$cic_name}: Photos and videos already has other content (" . implode( ', ', array_keys( $cic_used ) ) . '); left alone.' );
		} else {
			WP_CLI::log( "{$cic_name}: fill Photos and videos (\"" . wp_strip_all_tags( $cic_place['media']['heading'] ) . '"): ' . implode( ', ', $cic_items ) . '.' );
			foreach ( $cic_wanted as $cic_f => $cic_v ) {
				if ( '' !== (string) $cic_v && '0' !== (string) $cic_v ) {
					$cic_set( $cic_product, $cic_f, $cic_v );
				}
			}
		}
	}
}

WP_CLI::success( $cic_dry ? 'Dry run finished: nothing changed.' : 'Done.' );
