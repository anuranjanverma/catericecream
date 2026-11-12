<?php
/**
 * The single notification owner for enquiries (CF7 Mail and Mail (2) are skipped for our forms).
 * Runs only after the enquiry is stored in Flamingo:
 * - operator notification to the configured recipient (Reply-To: the customer);
 * - customer acknowledgement (optional, rate-limited per recipient), never promising a booking;
 * - internal follow-up reminders.
 *
 * Each channel is claimed atomically (Store::claim_channel), so a retry, the scheduled job and an
 * admin "Retry" can overlap without sending twice. A failure keeps the saved enquiry, records a
 * non-personal error code and schedules a retry with Action Scheduler. Nothing personal is logged.
 *
 * @package CIC
 */

namespace CIC\Enquiries;

use CIC\Site\Contact;

defined( 'ABSPATH' ) || exit;

final class Notifier {

	public const RETRY_HOOK       = 'cic_enquiry_notify';
	public const MAINTENANCE_HOOK = 'cic_enquiry_maintenance';
	public const MAX_AUTO_RETRIES = 6;

	public function register(): void {
		add_action( self::RETRY_HOOK, array( self::class, 'retry_job' ) );
		add_action( self::MAINTENANCE_HOOK, array( self::class, 'maintenance' ) );
		add_action( 'action_scheduler_init', array( self::class, 'ensure_maintenance' ) );
	}

	public static function ensure_maintenance(): void {
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::MAINTENANCE_HOOK, array(), Workflow::GROUP ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, HOUR_IN_SECONDS, self::MAINTENANCE_HOOK, array(), Workflow::GROUP );
		}
	}

	/** Hourly: free interrupted sends, retry undelivered notifications, apply retention cleanup. */
	public static function maintenance(): void {
		Store::unstick();
		foreach ( Store::undelivered() as $row ) {
			if ( (int) $row['attempts'] < self::MAX_AUTO_RETRIES * 2 ) {
				self::send_all( (int) $row['flamingo_id'] );
			}
		}
		Store::cleanup();
		Drafts::cleanup(); // Only when a draft retention period is configured.
	}

	public static function retry_job( $id ): void {
		self::send_all( (int) $id );
	}

	/**
	 * Send whatever is still due for an enquiry. Returns the channel states afterwards.
	 *
	 * @return array{operator: string, customer: string}
	 */
	public static function send_all( int $id ): array {
		$row = Store::by_flamingo( $id );
		if ( ! $row || ! Workflow::is_enquiry( $id ) ) {
			return array( 'operator' => 'missing', 'customer' => 'missing' );
		}
		if ( in_array( $row['operator_status'], array( 'pending', 'failed' ), true ) ) {
			self::send( $id, 'operator' );
		}
		$row = Store::by_flamingo( $id );
		if ( in_array( $row['customer_status'], array( 'pending', 'failed' ), true ) ) {
			$email = self::customer_email( $id );
			if ( empty( Settings::value( 'customer_enabled' ) ) || '' === $email ) {
				Store::set_channel( $id, 'customer', 'skipped', empty( Settings::value( 'customer_enabled' ) ) ? 'disabled' : 'no_email' );
			} elseif ( 'pending' === $row['customer_status'] && ! Guard::allow_ack( $email ) ) {
				Store::set_channel( $id, 'customer', 'skipped', 'ack_limit' );
			} else {
				self::send( $id, 'customer' );
			}
		}
		$row = Store::by_flamingo( $id );
		if ( in_array( 'failed', array( $row['operator_status'], $row['customer_status'] ), true ) ) {
			self::schedule_retry( $id, (int) $row['attempts'] );
		}
		return array( 'operator' => (string) $row['operator_status'], 'customer' => (string) $row['customer_status'] );
	}

	private static function schedule_retry( int $id, int $attempts ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) || $attempts >= self::MAX_AUTO_RETRIES * 2 ) {
			return;
		}
		if ( ! as_has_scheduled_action( self::RETRY_HOOK, array( 'id' => $id ), Workflow::GROUP ) ) {
			as_schedule_single_action( time() + 5 * MINUTE_IN_SECONDS * max( 1, $attempts ), self::RETRY_HOOK, array( 'id' => $id ), Workflow::GROUP );
		}
	}

	/** Send one channel if it can be claimed. */
	public static function send( int $id, string $channel ): bool {
		if ( ! Store::claim_channel( $id, $channel ) ) {
			return false;
		}
		$mail = 'operator' === $channel ? self::operator_mail( $id ) : self::customer_mail( $id );
		if ( ! $mail ) {
			Store::set_channel( $id, $channel, 'skipped', 'no_recipient' );
			return false;
		}
		$ok = self::deliver( $mail['to'], $mail['subject'], $mail['body'], $mail['headers'], $code );
		Store::set_channel( $id, $channel, $ok ? 'sent' : 'failed', $ok ? '' : $code );
		return $ok;
	}

	/**
	 * wp_mail with the failure reason captured as a code only (the WP_Error data contains the
	 * message and recipients, which must not reach logs).
	 *
	 * @param array<int, string> $headers Headers.
	 */
	private static function deliver( string $to, string $subject, string $body, array $headers, ?string &$code = null ): bool {
		$code    = 'mail_failed';
		$capture = static function ( $error ) use ( &$code ): void {
			$code = $error instanceof \WP_Error ? (string) $error->get_error_code() : 'mail_failed';
		};
		add_action( 'wp_mail_failed', $capture );
		try {
			$ok = (bool) wp_mail( $to, $subject, $body, $headers );
		} catch ( \Throwable $e ) {
			$ok   = false;
			$code = 'exception';
		} finally {
			remove_action( 'wp_mail_failed', $capture );
		}
		return $ok;
	}

	/** @return array<int, string> */
	private static function from_headers(): array {
		$s = Settings::get();
		return '' !== $s['from_email'] ? array( 'From: ' . ( '' !== $s['from_name'] ? $s['from_name'] : get_bloginfo( 'name' ) ) . ' <' . $s['from_email'] . '>' ) : array();
	}

	/** @return array<string, mixed> Flamingo fields. */
	private static function fields( int $id ): array {
		$fields = array();
		foreach ( (array) get_post_meta( $id, '_fields', true ) as $name => $unused ) {
			$value           = get_post_meta( $id, '_field_' . $name, true );
			$fields[ $name ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
		return $fields;
	}

	private static function customer_email( int $id ): string {
		$email = (string) ( self::fields( $id )['your_email'] ?? '' );
		return is_email( $email ) ? $email : '';
	}

	/** Non-personal summary lines (used in acknowledgements and the operator mail). */
	public static function summary_lines( array $c ): array {
		$lines = array();
		if ( ! empty( $c['event_label'] ) ) {
			$lines[] = __( 'Event:', 'cater-ice-cream-core' ) . ' ' . $c['event_label'];
		}
		if ( ! empty( $c['date_label'] ) ) {
			$lines[] = __( 'Date:', 'cater-ice-cream-core' ) . ' ' . $c['date_label'];
		}
		if ( ! empty( $c['guests'] ) ) {
			$lines[] = __( 'Approx. guests:', 'cater-ice-cream-core' ) . ' ' . $c['guests'];
		}
		if ( ! empty( $c['location_label'] ) ) {
			$lines[] = __( 'Location:', 'cater-ice-cream-core' ) . ' ' . $c['location_label'];
		}
		if ( ! empty( $c['treat_labels'] ) ) {
			$lines[] = __( 'Treats:', 'cater-ice-cream-core' ) . ' ' . implode( ', ', (array) $c['treat_labels'] );
		}
		return $lines;
	}

	/** @return array{to: string, subject: string, body: string, headers: array<int, string>}|null */
	private static function operator_mail( int $id ): ?array {
		$to = (string) Settings::value( 'recipient' );
		if ( ! is_email( $to ) ) {
			return null;
		}
		$type   = (string) get_post_meta( $id, '_cic_type', true );
		$c      = Workflow::context( $id );
		$f      = self::fields( $id );
		$labels = array(
			'{type}'     => 'contact' === $type ? __( 'contact', 'cater-ice-cream-core' ) : __( 'quote', 'cater-ice-cream-core' ),
			'{id}'       => (string) $id,
			'{event}'    => (string) ( $c['event_label'] ?? ( $c['topic_label'] ?? '' ) ),
			'{location}' => (string) ( $c['location_label'] ?? '' ),
		);
		$subject = trim( strtr( (string) Settings::value( 'operator_subject' ), $labels ), " ·-" );
		$rows    = array();
		if ( 'contact' === $type ) {
			$rows[ __( 'Topic', 'cater-ice-cream-core' ) ]   = $c['topic_label'] ?? '';
			$rows[ __( 'Message', 'cater-ice-cream-core' ) ] = $f['message'] ?? '';
		} else {
			$coverage = (array) ( $c['coverage'] ?? array() );
			$rows     = array(
				__( 'Event', 'cater-ice-cream-core' )          => $c['event_label'] ?? '',
				__( 'Date', 'cater-ice-cream-core' )           => $c['date_label'] ?? '',
				__( 'Approx. guests', 'cater-ice-cream-core' ) => (string) ( $c['guests'] ?? '' ),
				__( 'Location', 'cater-ice-cream-core' )       => ( $c['location_label'] ?? '' ) . ( $coverage ? ' — ' . sprintf( /* translators: %s: status. */ __( 'coverage check: %s', 'cater-ice-cream-core' ), \CIC\Locations\Labels::coverage( (string) ( $coverage['status'] ?? 'unknown' ) ) ) . ( ! empty( $coverage['fixture'] ) ? ' ' . __( '(based on local TEST data, not approved coverage)', 'cater-ice-cream-core' ) : '' ) : '' ),
				__( 'Venue', 'cater-ice-cream-core' )          => $c['venue'] ?? '',
				__( 'Serving start', 'cater-ice-cream-core' )  => $c['serve_time'] ?? '',
				__( 'Duration', 'cater-ice-cream-core' )       => isset( $c['serve_duration'] ) && '' !== $c['serve_duration'] ? ( Validator::DURATIONS[ $c['serve_duration'] ] ?? '' ) : '',
				__( 'Treats', 'cater-ice-cream-core' )         => implode( ', ', (array) ( $c['treat_labels'] ?? array() ) ),
				__( 'Flavours', 'cater-ice-cream-core' )       => $f['flavours'] ?? '',
				__( 'Theme', 'cater-ice-cream-core' )          => $f['party_theme'] ?? '',
				__( 'Dietary', 'cater-ice-cream-core' )        => $f['dietary'] ?? '',
				__( 'Notes', 'cater-ice-cream-core' )          => $f['notes'] ?? '',
				__( 'Prefers', 'cater-ice-cream-core' )        => $c['contact_pref'] ?? '',
			);
		}
		$rows = array_merge(
			$rows,
			array(
				__( 'Name', 'cater-ice-cream-core' )  => $f['your_name'] ?? '',
				__( 'Email', 'cater-ice-cream-core' ) => $f['your_email'] ?? '',
				__( 'Phone', 'cater-ice-cream-core' ) => $f['phone'] ?? '',
			)
		);
		$body = sprintf( /* translators: %d: enquiry id. */ __( 'A new enquiry (#%d) was saved.', 'cater-ice-cream-core' ), $id ) . "\n\n";
		foreach ( $rows as $label => $value ) {
			if ( '' !== trim( (string) $value ) ) {
				$body .= $label . ': ' . $value . "\n";
			}
		}
		$body   .= "\n" . __( 'Open in WordPress:', 'cater-ice-cream-core' ) . ' ' . Admin\EnquiriesPage::detail_url( $id ) . "\n";
		$headers = array_merge( self::from_headers(), array( 'Content-Type: text/plain; charset=UTF-8' ) );
		$reply   = (string) ( $f['your_email'] ?? '' );
		if ( is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}
		return array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'headers' => $headers );
	}

	/** @return array{to: string, subject: string, body: string, headers: array<int, string>}|null */
	private static function customer_mail( int $id ): ?array {
		$to = self::customer_email( $id );
		if ( '' === $to ) {
			return null;
		}
		$type  = (string) get_post_meta( $id, '_cic_type', true );
		$s     = Settings::get();
		$f     = self::fields( $id );
		$c     = Workflow::context( $id );
		$vars  = array(
			'{name}'    => (string) ( $f['your_name'] ?? '' ),
			'{summary}' => implode( "\n", self::summary_lines( $c ) ),
			'{phone}'        => Contact::get()['phone'],
			'{contact_name}' => Contact::get()['contact_name'],
		);
		$key     = 'contact' === $type ? 'contact' : 'quote';
		$headers = array_merge( self::from_headers(), array( 'Content-Type: text/plain; charset=UTF-8' ) );
		if ( is_email( (string) ( $s['reply_to'] ?? '' ) ) ) {
			$headers[] = 'Reply-To: ' . $s['reply_to'];
		}
		return array(
			'to'      => $to,
			'subject' => strtr( (string) $s[ $key . '_ack_subject' ], $vars ),
			'body'    => strtr( (string) $s[ $key . '_ack_body' ], $vars ),
			'headers' => $headers,
		);
	}

	/** Internal follow-up reminder to the assignee, else the fallback, else the operator recipient. */
	public static function reminder( int $id ): bool {
		$assignee = (int) get_post_meta( $id, '_cic_assignee', true );
		$user     = $assignee ? get_userdata( $assignee ) : null;
		$to       = $user && is_email( $user->user_email ) ? $user->user_email : (string) Settings::value( 'reminder_fallback' );
		$to       = is_email( $to ) ? $to : (string) Settings::value( 'recipient' );
		if ( ! is_email( $to ) ) {
			return false;
		}
		$c    = Workflow::context( $id );
		$body = sprintf( /* translators: 1: id, 2: date. */ __( 'Follow-up reminder for enquiry #%1$d (follow-up date %2$s).', 'cater-ice-cream-core' ), $id, (string) get_post_meta( $id, '_cic_follow_up', true ) ) . "\n\n" . implode( "\n", self::summary_lines( $c ) ) . "\n\n" . Admin\EnquiriesPage::detail_url( $id ) . "\n";
		return self::deliver( $to, sprintf( /* translators: %d: id. */ __( 'Follow-up reminder: enquiry #%d', 'cater-ice-cream-core' ), $id ), $body, array_merge( self::from_headers(), array( 'Content-Type: text/plain; charset=UTF-8' ) ) );
	}
}
