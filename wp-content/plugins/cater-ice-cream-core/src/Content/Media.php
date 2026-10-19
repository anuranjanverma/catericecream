<?php
/**
 * Idempotent importer for the supplied WebP masters (handoff/Cater_Ice_Cream_WebP_Assets).
 * Each asset ID is imported once; later runs reuse the attachment (found by _cic_asset_id meta)
 * and never overwrite attachment edits. Only the master file is imported; WordPress generates
 * responsive sizes. Alt text comes from the manifest; provenance goes into the description.
 *
 * @package CIC
 */

namespace CIC\Content;

defined( 'ABSPATH' ) || exit;

final class Media {

	public const META = '_cic_asset_id';
	public const MAP  = 'cic_media_map';

	public static function pack_dir(): string {
		return (string) apply_filters( 'cic_asset_pack_dir', ABSPATH . 'handoff/Cater_Ice_Cream_WebP_Assets' );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function manifest(): ?array {
		static $manifest = null;
		if ( null === $manifest ) {
			$file     = self::pack_dir() . '/ASSET_MANIFEST.json';
			$manifest = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		}
		return is_array( $manifest ) ? $manifest : null;
	}

	/** Existing attachment for an asset ID, or 0. */
	public static function find( string $asset_id ): int {
		$ids = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'meta_key'    => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => $asset_id, // phpcs:ignore WordPress.DB.SlowDBQuery
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/** Attachment ID for an asset (0 when it has not been imported). Does not import. */
	public static function id( string $asset_id ): int {
		$map = (array) get_option( self::MAP, array() );
		if ( ! empty( $map[ $asset_id ] ) && 'attachment' === get_post_type( (int) $map[ $asset_id ] ) ) {
			return (int) $map[ $asset_id ];
		}
		return self::find( $asset_id );
	}

	/**
	 * Import one asset master, or return the existing attachment.
	 *
	 * @return int|\WP_Error Attachment ID.
	 */
	public static function import( string $asset_id ) {
		$existing = self::find( $asset_id );
		if ( $existing ) {
			self::remember( $asset_id, $existing );
			return $existing;
		}
		$manifest = self::manifest();
		$asset    = $manifest['assets'][ $asset_id ] ?? null;
		if ( ! $asset ) {
			return new \WP_Error( 'cic_unknown_asset', sprintf( 'Unknown asset "%s" (not in ASSET_MANIFEST.json).', $asset_id ) );
		}
		$source = self::pack_dir() . '/' . $asset['master'];
		if ( ! is_readable( $source ) || 'webp' !== strtolower( pathinfo( $source, PATHINFO_EXTENSION ) ) ) {
			return new \WP_Error( 'cic_missing_master', sprintf( 'Master file missing for "%s".', $asset_id ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( basename( $source ) );
		copy( $source, $tmp );
		$name = $asset_id . '.webp';
		$id   = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			0,
			ucwords( str_replace( '-', ' ', $asset_id ) ),
			array(
				'post_content' => 'Provenance: ' . (string) ( $asset['provenance'] ?? 'AI recreation from the approved mockups.' ) . ' Not a photograph of actual Cater Ice Cream customers, staff, equipment or venues; client review required before launch.',
			)
		);
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return $id;
		}
		update_post_meta( $id, self::META, $asset_id );
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) ( $asset['alt'] ?? '' ) ) );
		update_post_meta( $id, '_cic_asset_source', $asset['master'] );
		self::remember( $asset_id, $id );
		return (int) $id;
	}

	private static function remember( string $asset_id, int $attachment_id ): void {
		$map              = (array) get_option( self::MAP, array() );
		$map[ $asset_id ] = $attachment_id;
		update_option( self::MAP, $map, false );
	}

	/**
	 * Asset IDs placed on the given manifest pages.
	 *
	 * @param string[] $pages Manifest page keys.
	 * @return string[]
	 */
	public static function assets_for_pages( array $pages ): array {
		$manifest = self::manifest();
		$ids      = array();
		foreach ( $pages as $page ) {
			foreach ( (array) ( $manifest['pages'][ $page ]['placements'] ?? array() ) as $placement ) {
				$ids[] = (string) $placement['asset_id'];
			}
		}
		return array_values( array_unique( $ids ) );
	}
}
