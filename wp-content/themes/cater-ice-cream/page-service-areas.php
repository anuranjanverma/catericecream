<?php
/**
 * Service Areas hub Page (/service-areas/; approved reference designs/15_service_areas.png and
 * the Service Areas column of 20_mobile_supporting.png). Content comes from the "Service Areas
 * composition" fields; county directories, map points and coverage come from the location
 * repository adapter.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
if ( class_exists( '\CIC\Content\ViewModel' ) ) {
	get_template_part( 'template-parts/location/hub', null, array( 'vm' => \CIC\Content\ViewModel::hub( (int) get_queried_object_id(), 'CA', 'hub' ) ) );
} else {
	get_template_part( 'index' );
}
get_footer();
