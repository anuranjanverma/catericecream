<?php
/**
 * Header test helper (CLI, local only). Temporarily changes header settings and restores them.
 *
 *   wp eval-file dev/tests/header/state.php snapshot <file>
 *   wp eval-file dev/tests/header/state.php restore <file>
 *   wp eval-file dev/tests/header/state.php set <section> <key> <0|1>
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;
'local' === wp_get_environment_type() || WP_CLI::error( 'Local environment only.' );

$option = \CIC\Site\Layout::OPTION;
switch ( $args[0] ?? '' ) {
	case 'snapshot':
		file_put_contents( (string) $args[1], wp_json_encode( array( 'value' => get_option( $option, '__absent__' ) ) ) );
		echo 'ok';
		break;
	case 'restore':
		$data = json_decode( (string) file_get_contents( (string) $args[1] ), true );
		is_array( $data ) || WP_CLI::error( 'Unreadable snapshot.' );
		'__absent__' === $data['value'] ? delete_option( $option ) : update_option( $option, $data['value'] );
		echo 'restored';
		break;
	case 'set':
		$layout = \CIC\Site\Layout::get();
		unset( $layout['footer']['copyright_text'] );
		$layout[ (string) $args[1] ][ (string) $args[2] ] = '1' === (string) $args[3];
		update_option( $option, $layout );
		echo 'set';
		break;
	default:
		WP_CLI::error( 'Unknown action.' );
}
