<?php
/**
 * Google Analytics 4 settings of 2026-10-25 (settings only; the code ships separately):
 *
 *   1. Pre-flight duplicate check: lists active plugins that add their own Google tag (Site Kit,
 *      GTM4WP, MonsterInsights, header-script plugins, ...). They are reported, never changed.
 *   2. Adds the Analytics settings (Cater Ice Cream → Analytics, option cic_analytics) with
 *      "enabled" and the approved Measurement ID G-NXRRDRV5C2, ONLY when the option does not exist
 *      yet. Saved settings (another ID, or switched off) are reported and kept, unless the argument
 *      replace-id is given, which replaces only the Measurement ID and keeps on/off.
 *
 * Usage: wp eval-file dev/tools/content/analytics-20261025.php [dry-run] [replace-id]
 *
 * The option alone never makes a site send data: the tag also needs the production environment
 * on catericecream.com / www.catericecream.com and skips administrators (CIC\Analytics\Service).
 * Safe to re-run: a second run reports "already done". WordPress APIs only (any table prefix).
 *
 * @package CIC
 */

defined( 'WP_CLI' ) && WP_CLI || exit;

use CIC\Analytics\Service;
use CIC\Analytics\Settings;

$cic_args    = (array) ( $args ?? array() );
$cic_dry     = in_array( 'dry-run', $cic_args, true );
$cic_replace = in_array( 'replace-id', $cic_args, true );
$cic_id      = 'G-NXRRDRV5C2';

if ( ! class_exists( Settings::class ) ) {
	WP_CLI::error( 'The Analytics code is not deployed (CIC\Analytics\Settings is missing). Deploy the code first.' );
}

WP_CLI::log( $cic_dry ? 'DRY RUN: nothing will be changed.' : 'Applying changes.' );

/* 1. Other Google tags. */
$cic_others = Settings::other_tags();
if ( $cic_others ) {
	WP_CLI::warning( 'Active plugins that can add their own Google tag: ' . implode( ', ', $cic_others ) . '. Check that ' . $cic_id . ' is not configured there as well (installed only once).' );
} else {
	WP_CLI::log( 'Duplicate check: no active plugin that adds its own Google tag.' );
}

/* 2. Settings. */
$cic_saved = get_option( Settings::OPTION, null );
if ( null === $cic_saved ) {
	WP_CLI::log( 'Settings: not set yet → add enabled = yes, Measurement ID = ' . $cic_id . '.' );
	if ( ! $cic_dry ) {
		add_option( Settings::OPTION, array( 'enabled' => 1, 'measurement_id' => $cic_id ) );
	}
} else {
	$cic_now = Settings::get();
	WP_CLI::log( 'Settings: already saved (enabled = ' . ( empty( $cic_now['enabled'] ) ? 'no' : 'yes' ) . ', Measurement ID = ' . ( '' !== $cic_now['measurement_id'] ? $cic_now['measurement_id'] : '(empty)' ) . ').' );
	if ( $cic_id === $cic_now['measurement_id'] ) {
		WP_CLI::log( 'Settings: Measurement ID already ' . $cic_id . ' (already done).' );
	} elseif ( $cic_replace ) {
		WP_CLI::log( 'Settings: replace-id given → Measurement ID becomes ' . $cic_id . '; on/off is kept.' );
		if ( ! $cic_dry ) {
			update_option( Settings::OPTION, array_merge( $cic_now, array( 'measurement_id' => $cic_id ) ) );
		}
	} else {
		WP_CLI::warning( 'Settings: a different Measurement ID is saved; kept. Re-run with replace-id to replace it.' );
	}
	if ( empty( $cic_now['enabled'] ) ) {
		WP_CLI::log( 'Settings: Analytics is switched off on this site; kept (switch it on in Cater Ice Cream → Analytics).' );
	}
}

/* Result on this site. */
$cic_status = Service::status( false );
WP_CLI::log( 'Status here' . ( $cic_dry ? ' (before changes)' : '' ) . ': ' . $cic_status['label'] );
WP_CLI::success( $cic_dry ? 'Dry run finished.' : 'Done.' );
