<?php
/**
 * Wrapper for WooCommerce archive and product templates. Treat compositions arrive in later phases.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
woocommerce_content();
get_footer();
