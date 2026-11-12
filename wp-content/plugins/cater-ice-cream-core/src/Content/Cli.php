<?php
/**
 * WP-CLI commands for the catalogue.
 *
 * @package CIC
 */

namespace CIC\Content;

defined( 'ABSPATH' ) || exit;

final class Cli {

	/**
	 * Seeds the four treats, four events and their pairings without overwriting edits. Safe to re-run.
	 *
	 * ## OPTIONS
	 *
	 * [--skip-media]
	 * : Do not import the image masters.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cic seed-catalog
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public static function seed( array $args, array $assoc_args ): void {
		$result = ( new Seeder() )->run( empty( $assoc_args['skip-media'] ) );
		foreach ( $result['messages'] as $message ) {
			\WP_CLI::log( $message );
		}
		$r = $result['report'];
		\WP_CLI::success( sprintf( 'Records created: %d, fields filled: %d, fields kept: %d, pairings added: %d, media imported: %d.', $r['created'], $r['filled'], $r['kept'], $r['pairs'], $r['media'] ) );
	}

	/**
	 * Imports image masters placed on the given manifest pages (idempotent).
	 *
	 * ## OPTIONS
	 *
	 * --pages=<pages>
	 * : Comma-separated manifest page keys, e.g. dippin-dots,birthday.
	 *
	 * @param string[]              $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public static function import_media( array $args, array $assoc_args ): void {
		$pages = array_filter( array_map( 'trim', explode( ',', (string) ( $assoc_args['pages'] ?? '' ) ) ) );
		$new   = 0;
		foreach ( Media::assets_for_pages( $pages ) as $asset ) {
			$before = Media::find( $asset );
			$id     = Media::import( $asset );
			if ( is_wp_error( $id ) ) {
				\WP_CLI::warning( $id->get_error_message() );
				continue;
			}
			$new += $before ? 0 : 1;
			\WP_CLI::log( sprintf( '%-28s → attachment %d%s', $asset, $id, $before ? ' (reused)' : ' (imported)' ) );
		}
		\WP_CLI::success( sprintf( '%d new attachment(s).', $new ) );
	}
}
