<?php
/**
 * Uninstall intentionally retains all Cater Ice Cream data (settings, schema version and, in
 * later phases, events, location tables and enquiry workflow metadata). Removing business data
 * requires a separate, deliberate and backed-up operation, never a plugin uninstall.
 *
 * @package CIC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
