<?php
/**
 * Snapshot / restore what the Phase 04 browser test edits. CLI only.
 *   wp eval-file dev/tests/phase04/state.php snapshot <file>
 *   wp eval-file dev/tests/phase04/state.php restore <file>
 *   wp eval-file dev/tests/phase04/state.php front
 *   wp eval-file dev/tests/phase04/state.php set-top-text <text>
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

$front   = (int) get_option( 'page_on_front' );
$options = array( 'cic_site_layout', 'cic_core_settings' );
$meta    = array( 'cic_home_eyebrow', 'cic_treats_heading', 'cic_home_image_main', 'cic_search_cta', '_cic_seeded_fields' );
$action  = $args[0] ?? '';
$file    = (string) ( $args[1] ?? '' );

if ( 'front' === $action ) {
	echo (int) $front;
} elseif ( 'snapshot' === $action ) {
	$data = array( 'options' => array(), 'meta' => array(), 'modified' => get_post_field( 'post_modified', $front ) );
	foreach ( $options as $opt ) {
		$data['options'][ $opt ] = get_option( $opt, '__absent__' );
	}
	foreach ( $meta as $key ) {
		$data['meta'][ $key ] = metadata_exists( 'post', $front, $key ) ? get_post_meta( $front, $key, true ) : '__absent__';
	}
	file_put_contents( $file, wp_json_encode( $data ) );
	WP_CLI::success( 'Snapshot written.' );
} elseif ( 'restore' === $action ) {
	$data = json_decode( (string) file_get_contents( $file ), true );
	foreach ( (array) $data['options'] as $opt => $value ) {
		'__absent__' === $value ? delete_option( $opt ) : update_option( $opt, $value );
	}
	foreach ( (array) $data['meta'] as $key => $value ) {
		'__absent__' === $value ? delete_post_meta( $front, $key ) : update_post_meta( $front, $key, $value );
	}
	WP_CLI::success( 'Restored.' );
} elseif ( 'set-top-text' === $action ) {
	$layout                = CIC\Site\Layout::get();
	$layout['top']['text'] = (string) ( $args[1] ?? '' );
	unset( $layout['footer']['copyright_text'] );
	update_option( 'cic_site_layout', $layout );
	WP_CLI::success( 'Top row text set.' );
}
