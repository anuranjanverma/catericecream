<?php
/**
 * Theme bootstrap. Keep this file small: each concern lives in inc/.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

define( 'CIC_THEME_VERSION', '0.2.0' );

require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/assets.php';
require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/content.php';
require_once get_template_directory() . '/inc/woocommerce.php';
