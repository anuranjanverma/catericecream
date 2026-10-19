<?php
/**
 * Snapshot / restore the Phase 02 options around the browser test. CLI only.
 *   wp eval-file dev/tests/phase02/options.php snapshot <file>
 *   wp eval-file dev/tests/phase02/options.php restore <file>
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

$file    = (string) ( $args[1] ?? '' );
$options = array( 'cic_design_settings', 'cic_site_layout', 'cic_core_settings' );

if ( 'snapshot' === ( $args[0] ?? '' ) ) {
	$data = array();
	foreach ( $options as $opt ) {
		$data[ $opt ] = get_option( $opt, '__absent__' );
	}
	file_put_contents( $file, wp_json_encode( $data ) );
	WP_CLI::success( 'Snapshot written.' );
} elseif ( 'restore' === ( $args[0] ?? '' ) ) {
	$data = json_decode( (string) file_get_contents( $file ), true );
	foreach ( $options as $opt ) {
		if ( ! array_key_exists( $opt, (array) $data ) ) {
			continue;
		}
		if ( '__absent__' === $data[ $opt ] ) {
			delete_option( $opt );
		} else {
			update_option( $opt, $data[ $opt ] );
		}
	}
	WP_CLI::success( 'Options restored.' );
}
