<?php
/**
 * Lead workflow attached to the Flamingo message (BUILD_SPECIFICATION §13). All workflow data is
 * post meta on the flamingo_inbound record, so it references the Flamingo id and is removed with
 * the message (erasure/retention follow Flamingo):
 *   _cic_type        quote | contact
 *   _cic_context     non-personal normalised selections (event, date, guests, location + coverage,
 *                    treats, durations, topic) with server-resolved labels
 *   _cic_source      first/latest permitted source context (template + key)
 *   _cic_status      new | contacted | quote_sent | booked | lost | spam
 *   _cic_assignee    user id with manage_cic_enquiries
 *   _cic_follow_up   next follow-up date (Y-m-d, America/Los_Angeles)
 *   _cic_notes       private notes [{time, user, text}]
 *
 * Reminders: internal only, one Action Scheduler job per enquiry (unscheduled before rescheduling,
 * so never duplicated), only when reminders are configured, cancelled for closed/spam/trashed/
 * deleted messages. The job re-checks everything when it runs.
 *
 * @package CIC
 */

namespace CIC\Enquiries;

defined( 'ABSPATH' ) || exit;

final class Workflow {

	public const POST_TYPE = 'flamingo_inbound';
	public const HOOK      = 'cic_enquiry_reminder';
	public const GROUP     = 'cic-enquiries';

	/** @return array<string, string> */
	public static function statuses(): array {
		return array(
			'new'        => __( 'New', 'cater-ice-cream-core' ),
			'contacted'  => __( 'Contacted', 'cater-ice-cream-core' ),
			'quote_sent' => __( 'Quote sent', 'cater-ice-cream-core' ),
			'booked'     => __( 'Booked', 'cater-ice-cream-core' ),
			'lost'       => __( 'Lost', 'cater-ice-cream-core' ),
			'spam'       => __( 'Spam', 'cater-ice-cream-core' ),
		);
	}

	public const CLOSED = array( 'booked', 'lost', 'spam' );

	public const SOURCE_TYPES = array( 'home', 'event', 'treat', 'city', 'hub', 'contact', 'quote', 'catalogue', 'other' );

	public function register(): void {
		add_action( self::HOOK, array( self::class, 'run_reminder' ) );
		add_action( 'wp_trash_post', array( self::class, 'on_remove' ) );
		add_action( 'before_delete_post', array( self::class, 'on_remove' ) );
		add_action( 'transition_post_status', array( self::class, 'on_status' ), 10, 3 );
	}

	public static function is_enquiry( int $id ): bool {
		return $id > 0 && self::POST_TYPE === get_post_type( $id ) && '' !== (string) get_post_meta( $id, '_cic_type', true );
	}

	/**
	 * Initial workflow metadata for a newly stored enquiry.
	 *
	 * @param array<string, mixed> $data   Validated, normalised submission.
	 * @param array<string, mixed> $source Validated source context.
	 */
	public static function init( int $id, array $data, array $source ): void {
		$keep = array( 'event', 'event_label', 'event_other', 'date', 'date_unknown', 'date_label', 'guests', 'location', 'location_label', 'location_detail', 'coverage', 'venue', 'serve_time', 'serve_duration', 'treats', 'treat_labels', 'treats_unsure', 'contact_pref', 'topic', 'topic_label' );
		update_post_meta( $id, '_cic_type', $data['type'] );
		update_post_meta( $id, '_cic_context', wp_slash( wp_json_encode( array_intersect_key( $data, array_flip( $keep ) ) ) ) );
		update_post_meta( $id, '_cic_source', wp_slash( wp_json_encode( $source ) ) );
		update_post_meta( $id, '_cic_status', 'new' );
	}

	/** @return array<string, mixed> */
	public static function context( int $id ): array {
		$c = json_decode( (string) get_post_meta( $id, '_cic_context', true ), true );
		return is_array( $c ) ? $c : array();
	}

	/** @return array<string, mixed> */
	public static function source( int $id ): array {
		$c = json_decode( (string) get_post_meta( $id, '_cic_source', true ), true );
		return is_array( $c ) ? $c : array();
	}

	/**
	 * Validate the browser-supplied source context (allowlisted template types + keys only).
	 *
	 * @return array<string, array{type: string, key: string}>
	 */
	public static function clean_source( string $raw ): array {
		$in  = json_decode( $raw, true );
		$out = array();
		foreach ( array( 'first', 'latest' ) as $slot ) {
			$s = is_array( $in ) && isset( $in[ $slot ] ) && is_array( $in[ $slot ] ) ? $in[ $slot ] : null;
			if ( $s && in_array( $s['type'] ?? '', self::SOURCE_TYPES, true ) ) {
				$out[ $slot ] = array( 'type' => (string) $s['type'], 'key' => substr( sanitize_key( (string) ( $s['key'] ?? '' ) ), 0, 60 ) );
			}
		}
		return $out;
	}

	public static function status( int $id ): string {
		$s = (string) get_post_meta( $id, '_cic_status', true );
		return isset( self::statuses()[ $s ] ) ? $s : 'new';
	}

	/** @return true|\WP_Error */
	public static function set_status( int $id, string $status ) {
		if ( ! isset( self::statuses()[ $status ] ) ) {
			return new \WP_Error( 'cic_status', __( 'Unknown status.', 'cater-ice-cream-core' ) );
		}
		update_post_meta( $id, '_cic_status', $status );
		self::schedule( $id );
		return true;
	}

	/** @return true|\WP_Error */
	public static function set_assignee( int $id, int $user_id ) {
		if ( $user_id && ! user_can( $user_id, Roles::CAPABILITY ) ) {
			return new \WP_Error( 'cic_assignee', __( 'That person cannot manage enquiries.', 'cater-ice-cream-core' ) );
		}
		$user_id ? update_post_meta( $id, '_cic_assignee', $user_id ) : delete_post_meta( $id, '_cic_assignee' );
		self::schedule( $id );
		return true;
	}

	/** @return true|\WP_Error */
	public static function set_follow_up( int $id, string $date ) {
		if ( '' === $date ) {
			delete_post_meta( $id, '_cic_follow_up' );
			self::schedule( $id );
			return true;
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return new \WP_Error( 'cic_follow_up', __( 'Enter the follow-up date as a valid date.', 'cater-ice-cream-core' ) );
		}
		update_post_meta( $id, '_cic_follow_up', $date );
		self::schedule( $id );
		return true;
	}

	/** @return true|\WP_Error */
	public static function add_note( int $id, string $text ) {
		$text = mb_substr( sanitize_textarea_field( $text ), 0, 2000 );
		if ( '' === $text ) {
			return new \WP_Error( 'cic_note', __( 'Write a note first.', 'cater-ice-cream-core' ) );
		}
		$notes   = self::notes( $id );
		$notes[] = array( 'time' => gmdate( 'Y-m-d H:i:s' ), 'user' => get_current_user_id(), 'text' => $text );
		update_post_meta( $id, '_cic_notes', wp_slash( wp_json_encode( array_slice( $notes, -200 ) ) ) );
		return true;
	}

	/** @return array<int, array{time: string, user: int, text: string}> */
	public static function notes( int $id ): array {
		$n = json_decode( (string) get_post_meta( $id, '_cic_notes', true ), true );
		return is_array( $n ) ? $n : array();
	}

	/* ------------------------------------------------------------------ *
	 * Reminders
	 * ------------------------------------------------------------------ */

	public static function scheduler(): bool {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_unschedule_all_actions' );
	}

	/** When the reminder for an enquiry should run (0 = none). */
	public static function reminder_time( int $id ): int {
		$date = (string) get_post_meta( $id, '_cic_follow_up', true );
		if ( ! Settings::reminders_ready() || '' === $date || in_array( self::status( $id ), self::CLOSED, true ) || 'publish' !== get_post_status( $id ) ) {
			return 0;
		}
		$s  = Settings::get();
		$tz = new \DateTimeZone( \CIC\Quote\Context::TIMEZONE );
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $s['reminder_time'], $tz );
		if ( ! $dt ) {
			return 0;
		}
		$at = $dt->modify( '-' . (int) $s['reminder_days'] . ' days' )->getTimestamp();
		return $at > time() ? $at : 0;
	}

	/** (Re)plan the single reminder job for an enquiry; cancels it when not applicable. */
	public static function schedule( int $id ): void {
		if ( ! self::scheduler() ) {
			return;
		}
		as_unschedule_all_actions( self::HOOK, array( 'id' => $id ), self::GROUP );
		$at = self::reminder_time( $id );
		if ( $at ) {
			as_schedule_single_action( $at, self::HOOK, array( 'id' => $id ), self::GROUP );
		}
	}

	public static function cancel( int $id ): void {
		if ( self::scheduler() ) {
			as_unschedule_all_actions( self::HOOK, array( 'id' => $id ), self::GROUP );
		}
	}

	/** Settings changed: re-plan every open enquiry that has a follow-up date. */
	public static function reschedule_all(): void {
		$ids = get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids', 'meta_key' => '_cic_follow_up' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $ids as $id ) {
			self::schedule( (int) $id );
		}
	}

	/** Reminder job: re-check the enquiry, then email the assignee (or the fallback) once. */
	public static function run_reminder( $id ): void {
		$id = (int) $id;
		if ( ! self::is_enquiry( $id ) || ! Settings::reminders_ready() || in_array( self::status( $id ), self::CLOSED, true ) || 'publish' !== get_post_status( $id ) ) {
			return;
		}
		$current = (string) get_post_meta( $id, '_cic_follow_up', true );
		if ( '' === $current || get_post_meta( $id, '_cic_reminded', true ) === $current ) {
			return; // No follow-up date any more, or already reminded for this date.
		}
		update_post_meta( $id, '_cic_reminded', $current );
		Notifier::reminder( $id );
	}

	public static function on_remove( $post_id ): void {
		if ( self::POST_TYPE === get_post_type( (int) $post_id ) ) {
			self::cancel( (int) $post_id );
		}
	}

	/** Flamingo "Spam" moves a message to its spam status: mirror it and cancel reminders. */
	public static function on_status( $new, $old, $post ): void {
		if ( $post instanceof \WP_Post && self::POST_TYPE === $post->post_type && $new !== $old && 'publish' !== $new && self::is_enquiry( (int) $post->ID ) ) {
			if ( 'flamingo-spam' === $new ) {
				update_post_meta( $post->ID, '_cic_status', 'spam' );
			}
			self::cancel( (int) $post->ID );
		}
	}
}
