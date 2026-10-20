<?php
/**
 * WP-CLI: wp cic enquiries <install|status|retry|maintenance>
 *
 * @package CIC
 */

namespace CIC\Enquiries;

defined( 'ABSPATH' ) || exit;

final class Cli {

	/**
	 * Create the CF7 quote and contact forms if missing (existing forms are kept).
	 */
	public function install(): void {
		Forms::install();
		\WP_CLI::success( sprintf( 'Quote form #%d, contact form #%d.', Forms::id( 'quote' ), Forms::id( 'contact' ) ) );
	}

	/**
	 * Dependency, form, notification and reminder status (no personal data).
	 */
	public function status(): void {
		$s = Settings::get();
		\WP_CLI::log( 'CF7: ' . ( defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : 'missing' ) . ' · Flamingo: ' . ( defined( 'FLAMINGO_VERSION' ) ? FLAMINGO_VERSION : 'missing' ) . ' · forms available: ' . ( Forms::available() ? 'yes' : 'no' ) );
		\WP_CLI::log( sprintf( 'Quote form #%d, contact form #%d. Reminders: %s. Scheduler: %s.', Forms::id( 'quote' ), Forms::id( 'contact' ), Settings::reminders_ready() ? 'on' : 'off', Workflow::scheduler() ? 'Action Scheduler' : 'missing' ) );
		\WP_CLI::log( sprintf( 'Undelivered notifications: %d. Customer acknowledgements: %s.', count( Store::undelivered( 500 ) ), empty( $s['customer_enabled'] ) ? 'off' : 'on' ) );
	}

	/**
	 * Retry undelivered notifications for one enquiry, or all.
	 *
	 * ## OPTIONS
	 * [<id>]
	 * : Flamingo message id.
	 */
	public function retry( array $args ): void {
		$ids = isset( $args[0] ) ? array( (int) $args[0] ) : array_map( static fn( $r ) => (int) $r['flamingo_id'], Store::undelivered( 500 ) );
		foreach ( $ids as $id ) {
			$r = Notifier::send_all( $id );
			\WP_CLI::log( sprintf( '#%d team %s, customer %s', $id, $r['operator'], $r['customer'] ) );
		}
		\WP_CLI::success( count( $ids ) . ' processed.' );
	}

	/**
	 * Run the hourly maintenance now (unstick, retry, retention cleanup).
	 */
	public function maintenance(): void {
		Notifier::maintenance();
		\WP_CLI::success( 'Maintenance done.' );
	}
}
