<?php
/**
 * Thin storage/notification adapter around CF7 (BUILD_SPECIFICATION §13), using supported hooks.
 *
 * Why: in the installed versions (CF7 6.2.2 + Flamingo 2.6.4) CF7 sends mail first and Flamingo
 * stores afterwards on wpcf7_submit, after the visitor's result is fixed. That cannot guarantee
 * "saved before notified", nor turn a storage failure into a failed response. So for our two forms:
 *
 *  1. wpcf7_validate      every field validated on the server (Validator); expired form token →
 *                          "press Send again" with a fresh token, values kept.
 *  2. wpcf7_spam          honeypot / token / timing / per-IP limit (Guard). Spam is not stored.
 *  3. wpcf7_before_send_mail (valid, non-spam only):
 *       a. atomic claim of the submission key (Store) — a retry of a stored key replays the same
 *          receipt; a concurrent duplicate is told to wait; neither stores or notifies again;
 *       b. store once through CF7's own Flamingo integration (wpcf7_flamingo_submit) and capture
 *          the Flamingo message id (wpcf7_after_flamingo);
 *       c. no id → release the key, abort: the visitor sees an error, nothing is acknowledged;
 *       d. workflow meta on the Flamingo message, receipt, then notifications (Notifier).
 *  4. wpcf7_skip_mail     CF7 mail is skipped: the core plugin is the single mail owner.
 *  5. wpcf7_flamingo_submit_if blocks Flamingo's second (post-mail) storage for our forms.
 *  6. wpcf7_feedback_response adds {cic: accepted, receipt, summary} for the JavaScript; the
 *     no-JS path redirects to the confirmation page (receipt only, never personal data).
 *
 * @package CIC
 */

namespace CIC\Enquiries;

defined( 'ABSPATH' ) || exit;

final class Pipeline {

	private static bool $storing     = false;
	private static int $flamingo_id  = 0;

	public function register(): void {
		add_filter( 'wpcf7_posted_data', array( $this, 'strip_guard' ) );
		add_filter( 'wpcf7_validate', array( $this, 'validate' ), 20, 2 );
		add_filter( 'wpcf7_spam', array( $this, 'spam' ), 20, 2 );
		add_action( 'wpcf7_before_send_mail', array( $this, 'store' ), 5, 3 );
		add_filter( 'wpcf7_skip_mail', array( $this, 'skip_mail' ), 20, 2 );
		add_filter( 'wpcf7_flamingo_submit_if', array( $this, 'flamingo_gate' ), 20 );
		add_filter( 'wpcf7_flamingo_inbound_message_parameters', array( $this, 'minimise' ), 20 );
		add_action( 'wpcf7_after_flamingo', array( $this, 'capture' ) );
		add_filter( 'wpcf7_feedback_response', array( $this, 'feedback' ), 20, 2 );
		add_action( 'wpcf7_submit', array( $this, 'redirect_nonajax' ), 99, 2 );
	}

	/**
	 * Keep the guard inputs (honeypot, token, submission key, source) out of CF7's posted data, so
	 * they are never stored in Flamingo. The pipeline reads them from the request directly.
	 *
	 * @param array<string, mixed> $data Posted data.
	 * @return array<string, mixed>
	 */
	public function strip_guard( $data ) {
		if ( is_array( $data ) && '' !== Forms::current_type() ) {
			unset( $data['cic_website'], $data['cic_ts'], $data['cic_key'], $data['cic_source'], $data['cic_guard'] );
		}
		return $data;
	}

	/** Raw POST, unslashed (CF7 already read the same request). */
	private static function post(): array {
		return wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- CF7 submission; validated below.
	}

	/**
	 * @return array{data: array<string, mixed>, errors: array<string, string>}
	 */
	private static function check( string $type ): array {
		$post = self::post();
		return 'contact' === $type ? Validator::contact( $post ) : Validator::quote( $post );
	}

	/**
	 * @param \WPCF7_Validation $result Result.
	 * @param array             $tags   Form tags.
	 */
	public function validate( $result, $tags ) {
		$type = Forms::current_type();
		if ( '' === $type ) {
			return $result;
		}
		$byname = array();
		foreach ( (array) $tags as $tag ) {
			if ( ! empty( $tag->name ) ) {
				$byname[ $tag->name ] = $tag;
			}
		}
		$check = self::check( $type );
		foreach ( $check['errors'] as $field => $message ) {
			$target = $byname[ $field ] ?? ( $byname['cic_guard'] ?? null );
			if ( $target ) {
				$result->invalidate( $target, $message );
			}
		}
		$post = self::post();
		if ( Guard::expired( (string) ( $post['cic_ts'] ?? '' ) ) && isset( $byname['cic_guard'] ) ) {
			$result->invalidate( $byname['cic_guard'], __( 'This form was open for a long time. Your answers are kept: please press Send again.', 'cater-ice-cream-core' ) );
		}
		$key = (string) ( $post['cic_key'] ?? '' );
		if ( ! Store::valid_key( $key ) && isset( $byname['cic_guard'] ) ) {
			$result->invalidate( $byname['cic_guard'], __( 'Please reload the page and try again.', 'cater-ice-cream-core' ) );
		}
		return $result;
	}

	public function spam( $spam, $submission = null ) {
		if ( $spam || '' === Forms::current_type() ) {
			return $spam;
		}
		$reason = Guard::spam_reason( self::post() );
		if ( '' !== $reason && $submission && method_exists( $submission, 'add_spam_log' ) ) {
			$submission->add_spam_log( array( 'agent' => 'cic-guard', 'reason' => $reason ) );
		}
		return '' !== $reason;
	}

	public function skip_mail( $skip, $form ) {
		return ( is_object( $form ) && '' !== Forms::type_of( (int) $form->id() ) ) ? true : $skip;
	}

	/** Only our own storage call may store our forms (blocks Flamingo's post-mail copy and spam). */
	public function flamingo_gate( $cases ) {
		return ( '' !== Forms::current_type() && ! self::$storing ) ? array() : $cases;
	}

	/** Store the accepted status and leave out the IP address and user agent. */
	public function minimise( $args ) {
		if ( self::$storing && is_array( $args ) ) {
			$args['status'] = 'mail_sent';
			if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
				unset( $args['meta']['remote_ip'], $args['meta']['user_agent'] );
			}
		}
		return $args;
	}

	public function capture( $result ): void {
		if ( self::$storing && is_array( $result ) ) {
			self::$flamingo_id = (int) ( $result['flamingo_inbound_id'] ?? 0 );
		}
	}

	/**
	 * @param \WPCF7_ContactForm $form       Form.
	 * @param bool               $abort      Abort flag (by reference).
	 * @param \WPCF7_Submission  $submission Submission.
	 */
	public function store( $form, &$abort, $submission ): void {
		$type = Forms::type_of( (int) $form->id() );
		if ( '' === $type || $abort ) {
			return;
		}
		$post = self::post();
		$key  = (string) ( $post['cic_key'] ?? '' );
		if ( ! Forms::available() || ! function_exists( 'wpcf7_flamingo_submit' ) ) {
			$abort = true;
			$submission->set_response( __( 'Sorry, we could not save your request right now. Nothing was sent. Please try again later or call us.', 'cater-ice-cream-core' ) );
			$submission->add_result_props( array( 'cic' => array( 'accepted' => false, 'error' => 'unavailable' ) ) );
			return;
		}

		$claim = Store::claim( $type, $key );
		if ( 'stored' === $claim['state'] ) {
			// A retry of a request we already saved: same receipt, nothing stored or sent again.
			$abort = true;
			$submission->set_response( Forms::type_of( (int) $form->id() ) === 'contact' ? __( 'Your message is received.', 'cater-ice-cream-core' ) : __( 'Your quote request is received.', 'cater-ice-cream-core' ) );
			$submission->add_result_props( array( 'cic' => self::payload( $type, $claim['row'], true ) ) );
			return;
		}
		if ( 'claimed' !== $claim['state'] ) {
			$abort = true;
			$submission->set_response( __( 'We are already saving this request. Please wait a moment.', 'cater-ice-cream-core' ) );
			$submission->add_result_props( array( 'cic' => array( 'accepted' => false, 'pending' => true ) ) );
			return;
		}

		$check             = self::check( $type );
		self::$flamingo_id = 0;
		self::$storing     = true;
		try {
			wpcf7_flamingo_submit( $form, array( 'status' => 'mail_sent' ) );
		} catch ( \Throwable $e ) {
			self::$flamingo_id = 0;
		} finally {
			self::$storing = false;
		}
		$id = self::$flamingo_id;
		if ( ! $id || 'flamingo_inbound' !== get_post_type( $id ) ) {
			Store::release( $key );
			$abort = true;
			$submission->set_response( __( 'Sorry, we could not save your request. Nothing was sent. Please try again, or call us.', 'cater-ice-cream-core' ) );
			$submission->add_result_props( array( 'cic' => array( 'accepted' => false, 'error' => 'storage' ) ) );
			return;
		}

		Workflow::init( $id, $check['data'], Workflow::clean_source( (string) ( $post['cic_source'] ?? '' ) ) );
		$summary = self::summary( $check['data'] );
		Store::complete( $key, $id, $summary );
		Guard::accepted();
		do_action( 'cic_enquiry_stored', $id, $type );
		Notifier::send_all( $id );
		$submission->add_result_props( array( 'cic' => self::payload( $type, Store::by_key( $key ), false ) ) );
	}

	/**
	 * Non-personal confirmation summary (labels only).
	 *
	 * @param array<string, mixed> $data Normalised submission.
	 * @return array<string, mixed>
	 */
	public static function summary( array $data ): array {
		if ( 'contact' === $data['type'] ) {
			return array( 'topic' => (string) ( $data['topic_label'] ?? '' ) );
		}
		$city = (string) ( $data['location_label'] ?? '' );
		return array(
			'event'    => (string) ( $data['event_label'] ?? '' ),
			'location' => $city,
			'treats'   => array_values( (array) ( $data['treat_labels'] ?? array() ) ),
			'date'     => (string) ( $data['date_label'] ?? '' ),
			'guests'   => (int) ( $data['guests'] ?? 0 ),
			'keys'     => array(
				'event' => (string) ( $data['event'] ?? '' ),
				'treat' => implode( ',', (array) ( $data['treats'] ?? array() ) ),
				'cov'   => (string) ( $data['coverage']['status'] ?? '' ),
			),
		);
	}

	/**
	 * @param array<string, mixed>|null $row Store row.
	 * @return array<string, mixed>
	 */
	private static function payload( string $type, ?array $row, bool $duplicate ): array {
		$summary = $row ? json_decode( (string) $row['summary'], true ) : array();
		return array(
			'accepted'  => (bool) $row,
			'duplicate' => $duplicate,
			'form'      => $type,
			'receipt'   => $row ? (string) $row['receipt'] : '',
			'summary'   => is_array( $summary ) ? $summary : array(),
		);
	}

	/**
	 * @param array<string, mixed> $response REST response.
	 * @param array<string, mixed> $result   Submission result.
	 * @return array<string, mixed>
	 */
	public function feedback( $response, $result ) {
		if ( '' === Forms::type_of( (int) ( $result['contact_form_id'] ?? 0 ) ) ) {
			return $response;
		}
		$response['cic']          = isset( $result['cic'] ) && is_array( $result['cic'] ) ? $result['cic'] : array( 'accepted' => false );
		$response['cic']['token'] = Guard::token();
		if ( ! empty( $response['cic']['accepted'] ) ) {
			$response['cic']['key'] = Forms::new_key();
		}
		return $response;
	}

	/** No-JS: after an accepted (or replayed) submission go to the confirmation page. */
	public function redirect_nonajax( $form, $result ): void {
		$type = is_object( $form ) ? Forms::type_of( (int) $form->id() ) : '';
		if ( '' === $type || \WPCF7_Submission::is_restful() || empty( $result['cic']['accepted'] ) || empty( $result['cic']['receipt'] ) ) {
			return;
		}
		wp_safe_redirect( add_query_arg( 'contact' === $type ? 'sent' : 'received', $result['cic']['receipt'], Forms::page_url( $type ) ) . '#confirmation', 303 );
		exit;
	}
}
