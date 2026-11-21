<?php
/**
 * City page "Popular Event Venues" (above "Explore nearby areas"): this city's venue names.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_template_part(
	'template-parts/components/venue-list',
	null,
	array(
		'prefix'  => 'cic-city',
		'section' => $args['section'],
		'venues'  => $args['vm']['venues'],
	)
);
