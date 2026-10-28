<?php
/**
 * Enquiries (Phase 06): CF7 forms, the store-then-notify pipeline, workflow, reminders, admin,
 * privacy and CLI. CF7 is the only submission transport and Flamingo the only enquiry store;
 * there is no cic_lead post type and no CIC submission endpoint. Saved quote progress (Drafts)
 * uses two draft-only REST routes that never submit: the final submission is always CF7.
 *
 * @package CIC
 */

namespace CIC\Enquiries;

defined( 'ABSPATH' ) || exit;

final class Service {

	public function register(): void {
		( new Forms() )->register();
		( new Pipeline() )->register();
		( new Drafts() )->register();
		( new Workflow() )->register();
		( new Notifier() )->register();
		( new Privacy() )->register();
		( new Settings() )->register();
		( new Roles() )->register();
		if ( is_admin() ) {
			( new Admin\EnquiriesPage() )->register();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cic enquiries', Cli::class );
		}
	}
}
