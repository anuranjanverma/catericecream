<?php
/**
 * City treats: the shared homepage treat composition (two brand cards + compact cards) with the
 * city heading and an "Explore all treats" card.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $args['vm']['treats']['items'] ) ) {
	return;
}
get_template_part( 'template-parts/home/section', 'treats', $args );
