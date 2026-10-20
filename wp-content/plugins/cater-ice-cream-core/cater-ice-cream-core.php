<?php
/**
 * Plugin Name:       Cater Ice Cream Core
 * Description:       Business logic for Cater Ice Cream: events, product metadata, design settings, locations and enquiry workflow. Purchase prevention lives separately in the cic-catalog-guard must-use plugin.
 * Version:           0.5.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Web Karma
 * License:           GPL-2.0-or-later
 * Text Domain:       cater-ice-cream-core
 *
 * @package CIC
 */

defined( 'ABSPATH' ) || exit;

define( 'CIC_CORE_VERSION', '0.5.0' );
define( 'CIC_CORE_FILE', __FILE__ );
define( 'CIC_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CIC_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once CIC_CORE_DIR . 'src/Autoloader.php';
CIC\Autoloader::register( 'CIC\\', CIC_CORE_DIR . 'src/' );

register_activation_hook( __FILE__, array( CIC\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( CIC\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( CIC\Plugin::instance(), 'boot' ) );
