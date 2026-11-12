<?php
/**
 * Google Sheets export service: settings, admin screen, WP-CLI, internal cron event and the
 * token-protected HTTP endpoint.
 *
 * Two schedules run the same idempotent export:
 * - a WP-Cron event every 5 minutes (fires on site traffic), and
 * - an external scheduler calling the endpoint or `wp cic sheets sync` (SiteGround cron),
 *   which is the reliable path when the site receives little traffic.
 * A new enquiry also queues an immediate single run, so the Sheet usually updates within a minute.
 *
 * @package CIC
 */

namespace CIC\Sheets;

defined( 'ABSPATH' ) || exit;

final class Service {

	public const SCHEDULE = 'cic_five_minutes';

	public function register(): void {
		( new Settings() )->register();
		( new Endpoint() )->register();

		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( Sync::HOOK, array( $this, 'run' ), 10, 1 );
		add_action( 'cic_enquiry_stored', array( $this, 'on_stored' ), 10, 2 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cic sheets', Cli::class );
		}
		if ( is_admin() ) {
			( new Admin\SheetsPage() )->register();
		}
	}

	/**
	 * @param array<string, array{interval: int, display: string}> $schedules Schedules.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function schedules( array $schedules ): array {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes (Cater Ice Cream)', 'cater-ice-cream-core' ),
		);
		return $schedules;
	}

	public function schedule(): void {
		if ( ! Settings::ready() ) {
			return;
		}
		if ( ! wp_next_scheduled( Sync::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, Sync::HOOK );
		}
	}

	/** Cron callback (periodic and one-off). */
	public function run( $source = '' ): void {
		if ( ! Settings::ready() ) {
			return;
		}
		$result = Sync::run();
		if ( is_wp_error( $result ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CIC Sheets: ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/** A submitted enquiry: queue a single run shortly after the request completes. */
	public function on_stored( $id, $type ): void {
		if ( ! Settings::ready() ) {
			return;
		}
		if ( ! wp_next_scheduled( Sync::HOOK, array( 'enquiry' ) ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, Sync::HOOK, array( 'enquiry' ) );
		}
	}

	/** Deactivation: remove the pending events (data and settings are kept). */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( Sync::HOOK );
		wp_clear_scheduled_hook( Sync::HOOK, array( 'enquiry' ) );
	}
}