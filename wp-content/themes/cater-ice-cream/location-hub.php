<?php
/**
 * State hub (/service-areas/{state}/): a virtual route resolved by CIC\Locations\Routes. Uses the
 * Service Areas Page content (state hubs inherit the hub) with a fuller county directory; a
 * ?county= view lists one county's cities with pagination (noindex, canonical to the hub URL).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_current = \CIC\Locations\Routes::current();
$cic_county  = isset( $_GET['county'] ) ? sanitize_title( wp_unslash( $_GET['county'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
$cic_page    = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cic_vm      = \CIC\Content\ViewModel::hub( \CIC\Content\Catalog::find_by_key( 'page', 'service-areas' ), $cic_current['state'], 'state', $cic_county, $cic_page );

get_header();
get_template_part( 'template-parts/location/hub', null, array( 'vm' => $cic_vm ) );
get_footer();
