<?php
/**
 * Phase 02 browser-test fixtures. CLI only.
 *   wp eval-file dev/tests/phase02/fixtures.php create   → prints JSON with page IDs/URLs
 *   wp eval-file dev/tests/phase02/fixtures.php delete
 *   wp eval-file dev/tests/phase02/fixtures.php reset-override   (drops the element override on page A)
 *   wp eval-file dev/tests/phase02/fixtures.php restore-override
 *
 * @package CIC
 */

defined( 'WP_CLI' ) || exit;

$action = $args[0] ?? 'create';

$override_heading = '<!-- wp:heading {"className":"fx-override-heading","cicStyle":{"heading":"#1F6F8B","size_min":30,"size_max":50}} -->' . "\n" . '<h2 class="wp-block-heading fx-override-heading">Local override heading</h2>' . "\n" . '<!-- /wp:heading -->';
$plain_heading    = '<!-- wp:heading {"className":"fx-override-heading"} -->' . "\n" . '<h2 class="wp-block-heading fx-override-heading">Local override heading</h2>' . "\n" . '<!-- /wp:heading -->';

$content_a = implode(
	"\n\n",
	array(
		'<!-- wp:heading {"level":1,"className":"fx-h1"} -->' . "\n" . '<h1 class="wp-block-heading fx-h1">Inherited heading <em>with accent</em></h1>' . "\n" . '<!-- /wp:heading -->',
		$override_heading,
		'<!-- wp:heading {"className":"fx-neighbour-heading"} -->' . "\n" . '<h2 class="wp-block-heading fx-neighbour-heading">Neighbour heading</h2>' . "\n" . '<!-- /wp:heading -->',
		'<!-- wp:paragraph {"className":"fx-body"} -->' . "\n" . '<p class="fx-body">Body paragraph that inherits the global body font and text colour.</p>' . "\n" . '<!-- /wp:paragraph -->',
		'<!-- wp:buttons -->' . "\n" . '<div class="wp-block-buttons"><!-- wp:button {"className":"fx-button-inherit"} -->' . "\n" . '<div class="wp-block-button fx-button-inherit"><a class="wp-block-button__link wp-element-button" href="#top">Inherited button</a></div>' . "\n" . '<!-- /wp:button -->' . "\n\n" . '<!-- wp:button {"className":"fx-button-override","cicStyle":{"action":"#1F6F8B"}} -->' . "\n" . '<div class="wp-block-button fx-button-override"><a class="wp-block-button__link wp-element-button" href="#top">Override button</a></div>' . "\n" . '<!-- /wp:button --></div>' . "\n" . '<!-- /wp:buttons -->',
		'<!-- wp:group {"className":"fx-wedding-section","cicStyle":{"context":"wedding"}} -->' . "\n" . '<div class="wp-block-group fx-wedding-section"><!-- wp:heading {"className":"fx-wedding-heading"} -->' . "\n" . '<h2 class="wp-block-heading fx-wedding-heading">Wedding section <em>accent</em></h2>' . "\n" . '<!-- /wp:heading -->' . "\n\n" . '<!-- wp:buttons -->' . "\n" . '<div class="wp-block-buttons"><!-- wp:button {"className":"fx-wedding-button"} -->' . "\n" . '<div class="wp-block-button fx-wedding-button"><a class="wp-block-button__link wp-element-button" href="#top">Wedding section button</a></div>' . "\n" . '<!-- /wp:button --></div>' . "\n" . '<!-- /wp:buttons --></div>' . "\n" . '<!-- /wp:group -->',
	)
);

$content_b = '<!-- wp:heading {"level":1,"className":"fx-h1"} -->' . "\n" . '<h1 class="wp-block-heading fx-h1">Page override heading <em>with accent</em></h1>' . "\n" . '<!-- /wp:heading -->' . "\n\n" . '<!-- wp:paragraph -->' . "\n" . '<p>This page has a page-scope override for heading accent colour and H1 size.</p>' . "\n" . '<!-- /wp:paragraph -->';

$find = static function ( string $slug ): int {
	$posts = get_posts(
		array(
			'post_type'   => 'page',
			'name'        => $slug,
			'post_status' => 'any',
			'meta_key'    => '_cic_fixture', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => 'phase02', // phpcs:ignore WordPress.DB.SlowDBQuery
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	return $posts ? (int) $posts[0] : 0;
};

switch ( $action ) {
	case 'create':
		$a = $find( 'cic-phase02-overrides' );
		if ( ! $a ) {
			$a = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Phase 02 override fixture', 'post_name' => 'cic-phase02-overrides', 'post_content' => $content_a, 'meta_input' => array( '_cic_fixture' => 'phase02' ) ) ) );
		}
		$b = $find( 'cic-phase02-page-override' );
		if ( ! $b ) {
			$b = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Phase 02 page override fixture', 'post_name' => 'cic-phase02-page-override', 'post_content' => $content_b, 'meta_input' => array( '_cic_fixture' => 'phase02' ) ) ) );
			update_post_meta( $b, CIC\Design\Overrides::META, array( 'heading_accent' => '#2E7D32', 'h1_min' => 36, 'h1_max' => 72 ) );
		}
		echo wp_json_encode(
			array(
				'a'    => $a,
				'b'    => $b,
				'urlA' => get_permalink( $a ),
				'urlB' => get_permalink( $b ),
			)
		);
		break;
	case 'reset-override':
	case 'restore-override':
		$a       = $find( 'cic-phase02-overrides' );
		$content = get_post_field( 'post_content', $a );
		$content = 'reset-override' === $action ? str_replace( $override_heading, $plain_heading, $content ) : str_replace( $plain_heading, $override_heading, $content );
		wp_update_post( wp_slash( array( 'ID' => $a, 'post_content' => $content ) ) );
		WP_CLI::success( $action . ' on page ' . $a );
		break;
	case 'delete':
		$ids = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_cic_fixture', 'meta_value' => 'phase02', 'numberposts' => -1, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
		WP_CLI::success( count( $ids ) . ' fixture posts deleted.' );
		break;
}
