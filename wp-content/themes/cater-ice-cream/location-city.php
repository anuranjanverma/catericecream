<?php
/**
 * Virtual city page (/service-areas/{state}/{city}/; approved reference designs/13_city_pasadena.png),
 * resolved by CIC\Locations\Routes from the location repository. City content records override the
 * city template on the Service Areas page. No city × event × treat pages exist.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_current = \CIC\Locations\Routes::current();
$cic_vm      = \CIC\Content\ViewModel::city( $cic_current['city'], (int) $cic_current['preview'] );
$cic_parts   = array( 'hero', 'plans', 'treats', 'call', 'occasions', 'check', 'faq', 'nearby' );

get_header();
echo '<div class="cic-home cic-city" data-cic-city="' . esc_attr( $cic_vm['city']['key'] ) . '">';
if ( $cic_vm['preview'] ) {
	echo '<p class="cic-loc-notice cic-loc-notice--preview" role="status">' . esc_html__( 'Preview: this is how the city page will look. It is not public until the city is published, and stays out of search engines until its content is reviewed.', 'cater-ice-cream' ) . '</p>';
}
if ( $cic_vm['city']['fixture'] ) {
	echo '<p class="cic-loc-notice" role="note">' . esc_html__( 'Local test data: this city and its coverage come from development fixtures, not approved service areas.', 'cater-ice-cream' ) . '</p>';
}
foreach ( $cic_parts as $cic_part ) {
	get_template_part( 'template-parts/location/city', $cic_part, array( 'vm' => $cic_vm, 'section' => array( 'key' => 'city-' . $cic_part, 'preset' => '' ) ) );
}
echo '</div>';
get_footer();
