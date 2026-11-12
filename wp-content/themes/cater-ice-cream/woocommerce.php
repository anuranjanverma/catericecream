<?php
/**
 * WooCommerce wrapper. Single products use the treat composition; the /treats/ archive uses the
 * catalogue list. Purchase UI is never rendered (and the catalogue guard blocks purchasing anyway).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
if ( is_singular( 'product' ) && class_exists( '\CIC\Content\ViewModel' ) ) {
	while ( have_posts() ) {
		the_post();
		get_template_part( 'template-parts/treat/single', null, array( 'vm' => \CIC\Content\ViewModel::treat( get_the_ID() ) ) );
	}
} elseif ( ( is_shop() || is_post_type_archive( 'product' ) ) && class_exists( '\CIC\Content\ViewModel' ) ) {
	get_template_part( 'template-parts/treat/archive' );
} else {
	woocommerce_content();
}
get_footer();
