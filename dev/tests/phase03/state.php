<?php
/**
 * Snapshot / restore the content the Phase 03 admin-edit browser test touches. CLI only.
 *   wp eval-file dev/tests/phase03/state.php snapshot <file>
 *   wp eval-file dev/tests/phase03/state.php restore <file>
 *   wp eval-file dev/tests/phase03/state.php ids
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

use CIC\Content\Catalog;
use CIC\Content\Pairings;

$dd       = Catalog::find_by_key( 'product', 'dippin-dots' );
$birthday = Catalog::find_by_key( 'cic_event', 'birthday' );
$keys     = array(
	$dd       => array( 'cic_hero_eyebrow', 'cic_feel_heading', '_cic_seeded_fields' ),
	$birthday => array( 'cic_story_heading', '_cic_seeded_fields' ),
);
$action   = $args[0] ?? '';
$file     = (string) ( $args[1] ?? '' );

if ( 'ids' === $action ) {
	echo wp_json_encode( array( 'dd' => $dd, 'birthday' => $birthday ) );
} elseif ( 'snapshot' === $action ) {
	$data = array( 'meta' => array(), 'pairs' => Pairings::for_product( $dd ), 'excerpt' => get_post_field( 'post_excerpt', $birthday ) );
	foreach ( $keys as $id => $list ) {
		foreach ( $list as $key ) {
			$data['meta'][ $id ][ $key ] = metadata_exists( 'post', $id, $key ) ? get_post_meta( $id, $key, true ) : '__absent__';
		}
	}
	file_put_contents( $file, wp_json_encode( $data ) );
	WP_CLI::success( 'Snapshot written.' );
} elseif ( 'restore' === $action ) {
	$data = json_decode( (string) file_get_contents( $file ), true );
	foreach ( (array) $data['meta'] as $id => $list ) {
		foreach ( $list as $key => $value ) {
			if ( '__absent__' === $value ) {
				delete_post_meta( (int) $id, $key );
			} else {
				update_post_meta( (int) $id, $key, $value );
			}
		}
	}
	Pairings::replace_for_product( $dd, (array) $data['pairs'] );
	wp_update_post( array( 'ID' => $birthday, 'post_excerpt' => (string) $data['excerpt'] ) );
	WP_CLI::success( 'Content restored.' );
}
