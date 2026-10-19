<?php
/**
 * Phase 01 fixtures: priced test products used to prove the catalogue guard blocks purchasing
 * even for products WooCommerce would normally consider purchasable. Never seed data.
 *
 * Usage (WP-CLI only):
 *   wp eval-file dev/tests/phase01/fixtures.php create
 *   wp eval-file dev/tests/phase01/fixtures.php delete
 *
 * @package CIC\Tests
 */

if ( ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

const CIC_FIXTURE_META = '_cic_fixture';
const CIC_FIXTURE_TAG  = 'phase01';

/**
 * @return WC_Product[]
 */
function cic_fixture_products(): array {
	return wc_get_products(
		array(
			'limit'      => -1,
			'status'     => array( 'publish', 'draft', 'private' ),
			'meta_key'   => CIC_FIXTURE_META, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => CIC_FIXTURE_TAG, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
}

$mode = $args[0] ?? 'create';

if ( 'delete' === $mode ) {
	$count = 0;
	foreach ( cic_fixture_products() as $product ) {
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				wp_delete_post( $child_id, true );
			}
		}
		$product->delete( true );
		++$count;
	}
	WP_CLI::success( "Deleted {$count} fixture products." );
	return;
}

$existing = cic_fixture_products();
if ( $existing ) {
	$out = array();
	foreach ( $existing as $p ) {
		$out[ $p->get_type() ] = $p->get_id();
		if ( $p->is_type( 'variable' ) ) {
			$out['variation'] = $p->get_children()[0] ?? 0;
			$out['variable_slug'] = $p->get_slug();
		} else {
			$out['simple_slug'] = $p->get_slug();
		}
	}
	echo wp_json_encode( $out ), "\n";
	return;
}

// Simple product with a real non-zero TEST price and stock: purchasable without the guard.
$simple = new WC_Product_Simple();
$simple->set_name( '[Fixture] Phase 01 Simple Test Product' );
$simple->set_slug( 'cic-fixture-simple' );
$simple->set_status( 'publish' );
$simple->set_regular_price( '12.50' );
$simple->set_stock_status( 'instock' );
$simple->update_meta_data( CIC_FIXTURE_META, CIC_FIXTURE_TAG );
$simple_id = $simple->save();

// Variable product with two priced variations.
$attribute = new WC_Product_Attribute();
$attribute->set_name( 'Size' );
$attribute->set_options( array( 'Small', 'Large' ) );
$attribute->set_visible( true );
$attribute->set_variation( true );

$variable = new WC_Product_Variable();
$variable->set_name( '[Fixture] Phase 01 Variable Test Product' );
$variable->set_slug( 'cic-fixture-variable' );
$variable->set_status( 'publish' );
$variable->set_attributes( array( $attribute ) );
$variable->update_meta_data( CIC_FIXTURE_META, CIC_FIXTURE_TAG );
$variable_id = $variable->save();

$variation_ids = array();
foreach ( array( 'Small' => '8.00', 'Large' => '14.00' ) as $size => $price ) {
	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $variable_id );
	$variation->set_attributes( array( 'size' => $size ) );
	$variation->set_regular_price( $price );
	$variation->set_stock_status( 'instock' );
	$variation->update_meta_data( CIC_FIXTURE_META, CIC_FIXTURE_TAG );
	$variation_ids[] = $variation->save();
}
WC_Product_Variable::sync( $variable_id );

echo wp_json_encode(
	array(
		'simple'        => $simple_id,
		'simple_slug'   => 'cic-fixture-simple',
		'variable'      => $variable_id,
		'variable_slug' => 'cic-fixture-variable',
		'variation'     => $variation_ids[0],
	)
), "\n";
