<?php
/**
 * Enquiries admin (BUILD_SPECIFICATION §13, §15). Requires manage_cic_enquiries (administrators
 * and the Enquiry manager role); every action is nonce-protected.
 * - List of Flamingo messages from the quote/contact forms with status, assignee, follow-up and
 *   notification state; filters; safe CSV export (formula-protected, filtered, spam excluded).
 * - Detail: the saved enquiry, coverage (fixture-labelled), source, status, assignee, next
 *   follow-up, private notes, notification state with Retry, and reminder state.
 * Settings (mail recipient, templates, forms, reminders) are for administrators only.
 * No public REST, search, sitemap or export route exists for enquiries.
 *
 * @package CIC
 */

namespace CIC\Enquiries\Admin;

use CIC\Enquiries\Notifier;
use CIC\Enquiries\Roles;
use CIC\Enquiries\Settings;
use CIC\Enquiries\Store;
use CIC\Enquiries\Validator;
use CIC\Enquiries\Workflow;

defined( 'ABSPATH' ) || exit;

final class EnquiriesPage {

	public const SLUG = 'cic-enquiries';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_cic_enquiry_update', array( $this, 'update' ) );
		add_action( 'admin_post_cic_enquiry_retry', array( $this, 'retry' ) );
		add_action( 'admin_post_cic_enquiry_export', array( $this, 'export' ) );
	}

	public function menu(): void {
		$count = self::count_new();
		$badge = $count ? ' <span class="awaiting-mod">' . (int) $count . '</span>' : '';
		add_menu_page( __( 'Enquiries', 'cater-ice-cream-core' ), __( 'Enquiries', 'cater-ice-cream-core' ) . $badge, Roles::CAPABILITY, self::SLUG, array( $this, 'render' ), 'dashicons-email-alt', 25 );
		add_submenu_page( self::SLUG, __( 'All enquiries', 'cater-ice-cream-core' ), __( 'All enquiries', 'cater-ice-cream-core' ), Roles::CAPABILITY, self::SLUG, array( $this, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Enquiry settings', 'cater-ice-cream-core' ), __( 'Settings', 'cater-ice-cream-core' ), 'manage_options', Settings::PAGE, array( new Settings(), 'render' ) );
	}

	public static function detail_url( int $id ): string {
		return add_query_arg( array( 'page' => self::SLUG, 'enquiry' => $id ), admin_url( 'admin.php' ) );
	}

	private static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	private static function deny(): void {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage enquiries.', 'cater-ice-cream-core' ), '', array( 'response' => 403 ) );
	}

	private static function count_new(): int {
		if ( ! current_user_can( Roles::CAPABILITY ) ) {
			return 0;
		}
		$q = new \WP_Query( array( 'post_type' => Workflow::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_cic_status', 'value' => 'new' ) ), 'no_found_rows' => false ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		return (int) $q->found_posts;
	}

	/**
	 * Query args for the current filters.
	 *
	 * @param array<string, mixed> $f Filters.
	 * @return array<string, mixed>
	 */
	private static function query_args( array $f, int $per = 30, int $paged = 1 ): array {
		$meta = array( array( 'key' => '_cic_type', 'compare' => 'EXISTS' ) );
		if ( '' !== $f['status'] ) {
			$meta[] = array( 'key' => '_cic_status', 'value' => $f['status'] );
		} else {
			$meta[] = array( 'key' => '_cic_status', 'value' => 'spam', 'compare' => '!=' );
		}
		if ( '' !== $f['type'] ) {
			$meta[] = array( 'key' => '_cic_type', 'value' => $f['type'] );
		}
		if ( $f['assignee'] > 0 ) {
			$meta[] = array( 'key' => '_cic_assignee', 'value' => $f['assignee'] );
		}
		$args = array(
			'post_type'      => Workflow::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		if ( '' !== $f['s'] ) {
			$args['s'] = $f['s'];
		}
		return $args;
	}

	/** @return array<string, mixed> */
	private static function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		$type   = isset( $_REQUEST['type'] ) ? sanitize_key( wp_unslash( $_REQUEST['type'] ) ) : '';
		$f      = array(
			'status'   => isset( Workflow::statuses()[ $status ] ) ? $status : '',
			'type'     => in_array( $type, array( 'quote', 'contact' ), true ) ? $type : '',
			'assignee' => isset( $_REQUEST['assignee'] ) ? absint( $_REQUEST['assignee'] ) : 0,
			's'        => isset( $_REQUEST['s'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ), 0, 80 ) : '',
		);
		// phpcs:enable
		return $f;
	}

	private static function styles(): void {
		echo '<style>.cic-pill{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;background:#f0f0f1;color:#3c434a}.cic-pill--good{background:#edfaef;color:#00450c}.cic-pill--warn{background:#fcf9e8;color:#614200}.cic-pill--bad{background:#fcf0f1;color:#8a2424}.cic-enq-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:16px}@media(max-width:960px){.cic-enq-grid{grid-template-columns:1fr}}.cic-box{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:4px 16px 16px;margin-bottom:16px}.cic-box th{width:34%;text-align:left;vertical-align:top;padding:6px 8px 6px 0}.cic-box td{padding:6px 0;white-space:pre-wrap}.cic-notes li{border-left:3px solid #72aee6;padding:4px 8px;background:#f6f7f7;white-space:pre-wrap}.cic-badge-fx{display:inline-block;padding:0 6px;border-radius:3px;background:#fcf0c3;color:#5c4400;font-size:11px;font-weight:600}</style>';
	}

	private static function notify_pill( ?array $row ): string {
		if ( ! $row ) {
			return '<span class="cic-pill">—</span>';
		}
		$parts = array();
		foreach ( array( 'operator' => __( 'Team', 'cater-ice-cream-core' ), 'customer' => __( 'Customer', 'cater-ice-cream-core' ) ) as $ch => $label ) {
			$st      = (string) $row[ $ch . '_status' ];
			$tone    = array( 'sent' => 'good', 'failed' => 'bad', 'pending' => 'warn', 'sending' => 'warn' )[ $st ] ?? 'neutral';
			$parts[] = '<span class="cic-pill cic-pill--' . esc_attr( $tone ) . '" data-channel="' . esc_attr( $ch ) . '" data-state="' . esc_attr( $st ) . '">' . esc_html( $label . ': ' . $st ) . '</span>';
		}
		return implode( ' ', $parts );
	}

	private static function received( int $id ): string {
		return wp_date( 'M j, Y g:i a', (int) get_post_time( 'U', true, $id ), new \DateTimeZone( 'America/Los_Angeles' ) );
	}

	private static function field( int $id, string $name ): string {
		$v = get_post_meta( $id, '_field_' . $name, true );
		return is_array( $v ) ? implode( ', ', $v ) : (string) $v;
	}

	public function render(): void {
		if ( ! current_user_can( Roles::CAPABILITY ) ) {
			self::deny();
		}
		echo '<div class="wrap">';
		self::styles();
		$id = isset( $_GET['enquiry'] ) ? absint( $_GET['enquiry'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id ) {
			$this->render_detail( $id );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	private function notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( isset( $_GET['cic_msg'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['cic_msg'] ) ) ) . '</p></div>';
		}
		if ( isset( $_GET['cic_err'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['cic_err'] ) ) ) . '</p></div>';
		}
		// phpcs:enable
		if ( ! \CIC\Enquiries\Forms::available() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Contact Form 7 or Flamingo is not active, so the website forms are switched off and show call/email options instead. No enquiry is accepted without being saved.', 'cater-ice-cream-core' ) . '</p></div>';
		}
	}

	private function render_list(): void {
		$f     = self::filters();
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$q     = new \WP_Query( self::query_args( $f, 30, $paged ) );
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Enquiries', 'cater-ice-cream-core' ) . '</h1> ';
		$export = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'cic_enquiry_export' ), array_filter( $f ) ), admin_url( 'admin-post.php' ) ), 'cic_enquiry_export' );
		echo '<a class="page-title-action" id="cic-export" href="' . esc_url( $export ) . '">' . esc_html__( 'Export CSV', 'cater-ice-cream-core' ) . '</a><hr class="wp-header-end">';
		$this->notices();
		echo '<p class="description">' . esc_html__( 'Every website enquiry is saved in Flamingo before any email is sent. A request is not a booking until you confirm it with the customer.', 'cater-ice-cream-core' ) . '</p>';
		echo '<form method="get" style="margin:1em 0"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		echo '<label class="screen-reader-text" for="cic-enq-status">' . esc_html__( 'Status', 'cater-ice-cream-core' ) . '</label><select id="cic-enq-status" name="status"><option value="">' . esc_html__( 'All open and closed (not spam)', 'cater-ice-cream-core' ) . '</option>';
		foreach ( Workflow::statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['status'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <label class="screen-reader-text" for="cic-enq-type">' . esc_html__( 'Type', 'cater-ice-cream-core' ) . '</label><select id="cic-enq-type" name="type"><option value="">' . esc_html__( 'Quotes and messages', 'cater-ice-cream-core' ) . '</option><option value="quote"' . selected( $f['type'], 'quote', false ) . '>' . esc_html__( 'Quote requests', 'cater-ice-cream-core' ) . '</option><option value="contact"' . selected( $f['type'], 'contact', false ) . '>' . esc_html__( 'Contact messages', 'cater-ice-cream-core' ) . '</option></select> ';
		echo '<label class="screen-reader-text" for="cic-enq-assignee">' . esc_html__( 'Assignee', 'cater-ice-cream-core' ) . '</label><select id="cic-enq-assignee" name="assignee"><option value="0">' . esc_html__( 'Anyone', 'cater-ice-cream-core' ) . '</option>';
		foreach ( Roles::assignees() as $u ) {
			echo '<option value="' . (int) $u->ID . '"' . selected( $f['assignee'], (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
		}
		echo '</select> <label class="screen-reader-text" for="cic-enq-s">' . esc_html__( 'Search', 'cater-ice-cream-core' ) . '</label><input type="search" id="cic-enq-s" name="s" value="' . esc_attr( $f['s'] ) . '" placeholder="' . esc_attr__( 'Search', 'cater-ice-cream-core' ) . '"> <button class="button">' . esc_html__( 'Filter', 'cater-ice-cream-core' ) . '</button></form>';

		echo '<table class="widefat striped" id="cic-enquiry-list"><thead><tr>';
		foreach ( array( __( 'Enquiry', 'cater-ice-cream-core' ), __( 'Received (Pacific)', 'cater-ice-cream-core' ), __( 'Event / topic', 'cater-ice-cream-core' ), __( 'Location', 'cater-ice-cream-core' ), __( 'Status', 'cater-ice-cream-core' ), __( 'Assignee', 'cater-ice-cream-core' ), __( 'Next follow-up', 'cater-ice-cream-core' ), __( 'Notifications', 'cater-ice-cream-core' ) ) as $h ) {
			echo '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $q->posts as $p ) {
			$id   = (int) $p->ID;
			$c    = Workflow::context( $id );
			$type = (string) get_post_meta( $id, '_cic_type', true );
			$as   = (int) get_post_meta( $id, '_cic_assignee', true );
			$user = $as ? get_userdata( $as ) : null;
			echo '<tr data-enquiry="' . (int) $id . '"><td><a class="row-title" href="' . esc_url( self::detail_url( $id ) ) . '">#' . (int) $id . ' ' . esc_html( self::field( $id, 'your_name' ) ) . '</a><br><span class="description">' . esc_html( 'contact' === $type ? __( 'Contact message', 'cater-ice-cream-core' ) : __( 'Quote request', 'cater-ice-cream-core' ) ) . '</span></td>';
			echo '<td>' . esc_html( self::received( $id ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $c['event_label'] ?? ( $c['topic_label'] ?? '' ) ) ) . ( ! empty( $c['date_label'] ) ? '<br><span class="description">' . esc_html( $c['date_label'] ) . '</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) ( $c['location_label'] ?? '' ) ) . ( ! empty( $c['coverage']['fixture'] ) ? ' <span class="cic-badge-fx">' . esc_html__( 'TEST DATA', 'cater-ice-cream-core' ) . '</span>' : '' ) . '</td>';
			echo '<td data-status="' . esc_attr( Workflow::status( $id ) ) . '">' . esc_html( Workflow::statuses()[ Workflow::status( $id ) ] ) . '</td>';
			echo '<td>' . esc_html( $user ? $user->display_name : '—' ) . '</td>';
			echo '<td>' . esc_html( (string) get_post_meta( $id, '_cic_follow_up', true ) ?: '—' ) . '</td>';
			echo '<td>' . self::notify_pill( Store::by_flamingo( $id ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped pills.
		}
		if ( ! $q->posts ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No enquiries match these filters.', 'cater-ice-cream-core' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $q->max_num_pages > 1 ) {
			echo '<p>' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => (int) $q->max_num_pages ) ) ) . '</p>';
		}
	}

	private function render_detail( int $id ): void {
		if ( ! Workflow::is_enquiry( $id ) || 'publish' !== get_post_status( $id ) ) {
			echo '<h1>' . esc_html__( 'Enquiry not found', 'cater-ice-cream-core' ) . '</h1><p><a href="' . esc_url( self::url() ) . '">← ' . esc_html__( 'All enquiries', 'cater-ice-cream-core' ) . '</a></p>';
			return;
		}
		$type = (string) get_post_meta( $id, '_cic_type', true );
		$c    = Workflow::context( $id );
		$row  = Store::by_flamingo( $id );
		echo '<h1>' . esc_html( sprintf( /* translators: 1: id, 2: type. */ __( 'Enquiry #%1$d · %2$s', 'cater-ice-cream-core' ), $id, 'contact' === $type ? __( 'Contact message', 'cater-ice-cream-core' ) : __( 'Quote request', 'cater-ice-cream-core' ) ) ) . '</h1>';
		echo '<p><a href="' . esc_url( self::url() ) . '">← ' . esc_html__( 'All enquiries', 'cater-ice-cream-core' ) . '</a></p>';
		$this->notices();
		echo '<div class="cic-enq-grid"><div>';

		$rows = array( __( 'Received (Pacific)', 'cater-ice-cream-core' ) => self::received( $id ) );
		if ( 'contact' === $type ) {
			$rows[ __( 'Topic', 'cater-ice-cream-core' ) ]   = (string) ( $c['topic_label'] ?? '' );
			$rows[ __( 'Message', 'cater-ice-cream-core' ) ] = self::field( $id, 'message' );
		} else {
			$cov  = (array) ( $c['coverage'] ?? array() );
			$rows = array_merge(
				$rows,
				array(
					__( 'Event', 'cater-ice-cream-core' )          => (string) ( $c['event_label'] ?? '' ),
					__( 'Date', 'cater-ice-cream-core' )           => (string) ( $c['date_label'] ?? '' ),
					__( 'Approx. guests', 'cater-ice-cream-core' ) => (string) ( $c['guests'] ?? '' ),
					__( 'Location', 'cater-ice-cream-core' )       => (string) ( $c['location_label'] ?? '' ),
					__( 'Coverage check', 'cater-ice-cream-core' ) => $cov ? \CIC\Locations\Labels::coverage( (string) ( $cov['status'] ?? 'unknown' ) ) . ' (' . (string) ( $cov['reason'] ?? '' ) . ')' : '',
					__( 'Venue', 'cater-ice-cream-core' )          => (string) ( $c['venue'] ?? '' ),
					__( 'Serving start', 'cater-ice-cream-core' )  => (string) ( $c['serve_time'] ?? '' ),
					__( 'Duration', 'cater-ice-cream-core' )       => ! empty( $c['serve_duration'] ) ? ( Validator::DURATIONS[ $c['serve_duration'] ] ?? '' ) : '',
					__( 'Treats', 'cater-ice-cream-core' )         => implode( ', ', (array) ( $c['treat_labels'] ?? array() ) ),
					__( 'Flavours', 'cater-ice-cream-core' )       => self::field( $id, 'flavours' ),
					__( 'Theme or feeling', 'cater-ice-cream-core' ) => self::field( $id, 'party_theme' ),
					__( 'Dietary', 'cater-ice-cream-core' )        => self::field( $id, 'dietary' ),
					__( 'Additional notes', 'cater-ice-cream-core' ) => self::field( $id, 'notes' ),
				)
			);
		}
		echo '<div class="cic-box"><h2>' . esc_html__( 'Request', 'cater-ice-cream-core' ) . '</h2><table id="cic-enquiry-request">';
		foreach ( $rows as $label => $value ) {
			if ( '' !== trim( $value ) ) {
				echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
			}
		}
		echo '</table>';
		if ( ! empty( $c['coverage']['fixture'] ) ) {
			echo '<p><span class="cic-badge-fx">' . esc_html__( 'TEST DATA', 'cater-ice-cream-core' ) . '</span> ' . esc_html__( 'The coverage check used local fixture data, not approved production coverage. Confirm service manually.', 'cater-ice-cream-core' ) . '</p>';
		}
		echo '</div>';

		echo '<div class="cic-box"><h2>' . esc_html__( 'Customer', 'cater-ice-cream-core' ) . '</h2><table>';
		foreach ( array( __( 'Name', 'cater-ice-cream-core' ) => self::field( $id, 'your_name' ), __( 'Email', 'cater-ice-cream-core' ) => self::field( $id, 'your_email' ), __( 'Phone', 'cater-ice-cream-core' ) => self::field( $id, 'phone' ), __( 'Prefers', 'cater-ice-cream-core' ) => (string) ( $c['contact_pref'] ?? 'email' ) ) as $label => $value ) {
			if ( '' !== $value ) {
				echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
			}
		}
		echo '</table></div>';

		$src = Workflow::source( $id );
		if ( $src ) {
			echo '<div class="cic-box"><h2>' . esc_html__( 'Source', 'cater-ice-cream-core' ) . '</h2><table>';
			foreach ( $src as $slot => $s ) {
				echo '<tr><th scope="row">' . esc_html( 'first' === $slot ? __( 'First page', 'cater-ice-cream-core' ) : __( 'Latest page', 'cater-ice-cream-core' ) ) . '</th><td>' . esc_html( $s['type'] . ( '' !== $s['key'] ? ' · ' . $s['key'] : '' ) ) . '</td></tr>';
			}
			echo '</table></div>';
		}

		$notes = Workflow::notes( $id );
		echo '<div class="cic-box"><h2>' . esc_html__( 'Private notes', 'cater-ice-cream-core' ) . '</h2>';
		if ( $notes ) {
			echo '<ul class="cic-notes" id="cic-notes">';
			foreach ( array_reverse( $notes ) as $n ) {
				$u = get_userdata( (int) $n['user'] );
				echo '<li><strong>' . esc_html( $u ? $u->display_name : '—' ) . '</strong> · ' . esc_html( wp_date( 'M j, Y g:i a', strtotime( $n['time'] . ' UTC' ), new \DateTimeZone( 'America/Los_Angeles' ) ) ) . "\n" . esc_html( $n['text'] ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'No notes yet. Notes are internal and never sent to the customer.', 'cater-ice-cream-core' ) . '</p>';
		}
		echo '</div></div><div>';

		// Workflow form.
		$status = Workflow::status( $id );
		$as     = (int) get_post_meta( $id, '_cic_assignee', true );
		echo '<div class="cic-box"><h2>' . esc_html__( 'Workflow', 'cater-ice-cream-core' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="cic-workflow"><input type="hidden" name="action" value="cic_enquiry_update"><input type="hidden" name="enquiry" value="' . (int) $id . '">';
		wp_nonce_field( 'cic_enquiry_' . $id );
		echo '<p><label for="cic-wf-status"><strong>' . esc_html__( 'Status', 'cater-ice-cream-core' ) . '</strong></label><br><select id="cic-wf-status" name="status">';
		foreach ( Workflow::statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $status, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></p><p><label for="cic-wf-assignee"><strong>' . esc_html__( 'Assignee', 'cater-ice-cream-core' ) . '</strong></label><br><select id="cic-wf-assignee" name="assignee"><option value="0">' . esc_html__( 'Unassigned', 'cater-ice-cream-core' ) . '</option>';
		foreach ( Roles::assignees() as $u ) {
			echo '<option value="' . (int) $u->ID . '"' . selected( $as, (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
		}
		echo '</select></p><p><label for="cic-wf-follow"><strong>' . esc_html__( 'Next follow-up', 'cater-ice-cream-core' ) . '</strong></label><br><input type="date" id="cic-wf-follow" name="follow_up" value="' . esc_attr( (string) get_post_meta( $id, '_cic_follow_up', true ) ) . '"></p>';
		echo '<p><label for="cic-wf-note"><strong>' . esc_html__( 'Add a private note', 'cater-ice-cream-core' ) . '</strong></label><br><textarea id="cic-wf-note" name="note" rows="4" class="large-text"></textarea></p>';
		submit_button( __( 'Save', 'cater-ice-cream-core' ), 'primary', 'submit', false );
		echo '</form>';
		$at = Workflow::scheduler() && function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( Workflow::HOOK, array( 'id' => $id ), Workflow::GROUP ) : false;
		echo '<p class="description" id="cic-reminder-state">' . esc_html(
			! Settings::reminders_ready()
				? __( 'Reminders: off (timing not configured yet).', 'cater-ice-cream-core' )
				: ( $at ? sprintf( /* translators: %s: time. */ __( 'Reminder scheduled for %s (Pacific).', 'cater-ice-cream-core' ), wp_date( 'M j, Y g:i a', (int) $at, new \DateTimeZone( 'America/Los_Angeles' ) ) ) : __( 'No reminder scheduled.', 'cater-ice-cream-core' ) )
		) . '</p></div>';

		echo '<div class="cic-box" id="cic-notifications"><h2>' . esc_html__( 'Notifications', 'cater-ice-cream-core' ) . '</h2><p>' . self::notify_pill( $row ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped pills.
		if ( $row && '' !== $row['last_error'] ) {
			echo '<p class="description">' . esc_html( sprintf( /* translators: %s: error code. */ __( 'Last error: %s', 'cater-ice-cream-core' ), $row['last_error'] ) ) . '</p>';
		}
		if ( $row && ( in_array( $row['operator_status'], array( 'failed', 'pending' ), true ) || in_array( $row['customer_status'], array( 'failed', 'pending' ), true ) ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cic_enquiry_retry"><input type="hidden" name="enquiry" value="' . (int) $id . '">';
			wp_nonce_field( 'cic_enquiry_retry_' . $id );
			submit_button( __( 'Retry notifications', 'cater-ice-cream-core' ), 'secondary', 'cic-retry', false );
			echo '</form><p class="description">' . esc_html__( 'The enquiry is saved either way. Retrying sends only what has not been sent.', 'cater-ice-cream-core' ) . '</p>';
		}
		echo '</div>';
		if ( current_user_can( 'edit_users' ) ) {
			echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'flamingo_inbound', 'post' => $id, 'action' => 'edit' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Open the Flamingo record', 'cater-ice-cream-core' ) . '</a></p>';
		}
		echo '</div></div>';
	}

	private static function enquiry_from_post( string $nonce_prefix ): int {
		$id = isset( $_POST['enquiry'] ) ? absint( $_POST['enquiry'] ) : 0;
		if ( ! current_user_can( Roles::CAPABILITY ) || ! check_admin_referer( $nonce_prefix . $id ) || ! Workflow::is_enquiry( $id ) ) {
			self::deny();
		}
		return $id;
	}

	public function update(): void {
		$id     = self::enquiry_from_post( 'cic_enquiry_' );
		$errors = array();
		foreach (
			array(
				Workflow::set_status( $id, isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'new' ),
				Workflow::set_assignee( $id, isset( $_POST['assignee'] ) ? absint( $_POST['assignee'] ) : 0 ),
				Workflow::set_follow_up( $id, isset( $_POST['follow_up'] ) ? sanitize_text_field( wp_unslash( $_POST['follow_up'] ) ) : '' ),
			) as $r
		) {
			if ( is_wp_error( $r ) ) {
				$errors[] = $r->get_error_message();
			}
		}
		$note = isset( $_POST['note'] ) ? (string) wp_unslash( $_POST['note'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in add_note.
		if ( '' !== trim( $note ) ) {
			$r = Workflow::add_note( $id, $note );
			if ( is_wp_error( $r ) ) {
				$errors[] = $r->get_error_message();
			}
		}
		$args = array( 'enquiry' => $id );
		$args[ $errors ? 'cic_err' : 'cic_msg' ] = rawurlencode( $errors ? implode( ' ', $errors ) : __( 'Enquiry updated.', 'cater-ice-cream-core' ) );
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	public function retry(): void {
		$id     = self::enquiry_from_post( 'cic_enquiry_retry_' );
		$result = Notifier::send_all( $id );
		/* translators: 1: team state, 2: customer state. */
		$msg = sprintf( __( 'Notifications: team %1$s, customer %2$s.', 'cater-ice-cream-core' ), $result['operator'], $result['customer'] );
		wp_safe_redirect( self::url( array( 'enquiry' => $id, ( in_array( 'failed', $result, true ) ? 'cic_err' : 'cic_msg' ) => rawurlencode( $msg ) ) ) );
		exit;
	}

	/** Spreadsheet formula protection: values starting with = + - @ tab or CR are prefixed. */
	public static function csv_cell( $value ): string {
		$value = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
	}

	public function export(): void {
		if ( ! current_user_can( Roles::CAPABILITY ) || ! check_admin_referer( 'cic_enquiry_export' ) ) {
			self::deny();
		}
		$f    = self::filters();
		$q    = new \WP_Query( array_merge( self::query_args( $f, 2000, 1 ), array( 'no_found_rows' => true ) ) );
		$head = array( 'id', 'received_pacific', 'type', 'status', 'assignee', 'next_follow_up', 'event', 'date', 'guests', 'location', 'coverage', 'treats', 'topic', 'name', 'email', 'phone', 'contact_pref', 'team_notification', 'customer_notification' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="enquiries-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, $head, ',', '"', '' );
		foreach ( $q->posts as $p ) {
			$id   = (int) $p->ID;
			$c    = Workflow::context( $id );
			$as   = get_userdata( (int) get_post_meta( $id, '_cic_assignee', true ) );
			$row  = Store::by_flamingo( $id );
			$line = array( $id, self::received( $id ), get_post_meta( $id, '_cic_type', true ), Workflow::status( $id ), $as ? $as->display_name : '', get_post_meta( $id, '_cic_follow_up', true ), $c['event_label'] ?? '', $c['date_label'] ?? '', $c['guests'] ?? '', $c['location_label'] ?? '', ( $c['coverage']['status'] ?? '' ) . ( ! empty( $c['coverage']['fixture'] ) ? ' (test data)' : '' ), implode( ', ', (array) ( $c['treat_labels'] ?? array() ) ), $c['topic_label'] ?? '', self::field( $id, 'your_name' ), self::field( $id, 'your_email' ), self::field( $id, 'phone' ), $c['contact_pref'] ?? '', $row['operator_status'] ?? '', $row['customer_status'] ?? '' );
			fputcsv( $out, array_map( array( self::class, 'csv_cell' ), $line ), ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
