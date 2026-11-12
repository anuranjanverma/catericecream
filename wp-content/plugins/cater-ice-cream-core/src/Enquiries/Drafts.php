<?php
/**
 * Saved progress for the two-step quote form (2026-10-23). One inquiry record from Step 1 to the
 * optional details after submission, stored where every inquiry lives: a Flamingo message.
 *
 * Lifecycle (meta _cic_stage on the flamingo_inbound record; missing = submitted, so historical
 * inquiries are unchanged):
 * - Nothing is stored while the visitor types. Step 1 "Continue" posts the Step 1 values to
 *   POST cic/v1/quote-draft; only when they pass server validation (and the spam guard) is the
 *   draft created: a Flamingo message with _cic_stage = incomplete. Creation is idempotent per
 *   client key (a row in {prefix}cic_submissions with form "draft", both notification channels
 *   "skipped" and no Flamingo id, so it can never trigger or be mistaken for a notification).
 * - Autosave (POST cic/v1/quote-draft/save) sends a full snapshot of the allowlisted fields with a
 *   monotonic revision. A per-draft MySQL lock serialises saves and the final submission; a save
 *   whose revision is not newer than the stored one is ignored, so an old request can never
 *   overwrite newer values. Saves never write the stage, the workflow status or the assignee.
 * - The final submission still travels through Contact Form 7 (Pipeline). For the visitor's own
 *   incomplete draft the pipeline completes that SAME message with the submitted values (exactly
 *   the arguments CF7 would store), marks it submitted, raises the revision to the maximum (late
 *   saves are ignored) and then notifies once through the existing store-then-notify path.
 * - After submission only notes, party theme and dietary details may be saved, for OPTIONAL_TTL,
 *   without touching status, assignee, notifications or the stage.
 *
 * Access: an unpredictable token in an HttpOnly, SameSite=Strict cookie (only its HMAC is stored
 * on the draft); the client key from Step 1 (kept only in page memory) is accepted as a fallback
 * when cookies are blocked. Numeric ids never authorise anything. Requests must be same-origin
 * and carry the X-CIC-Draft header. No tokens or personal data go into URLs, analytics or logs.
 *
 * @package CIC
 */

namespace CIC\Enquiries;

defined( 'ABSPATH' ) || exit;

final class Drafts {

	public const COOKIE       = 'cic_qd';
	public const COOKIE_TTL   = 7 * DAY_IN_SECONDS;
	public const OPTIONAL_TTL = DAY_IN_SECONDS;
	public const MAX_REV      = '9999999999999999';
	public const CREATE_LIMIT = 10;  // Drafts created per hashed IP per Guard::WINDOW.
	public const SAVE_LIMIT   = 600; // Saves per draft per hour.

	/** Fields a draft may hold before submission (Steps 1 and 2), with length limits. */
	public const MAIN_FIELDS = array(
		'your_name'       => 100,
		'your_email'      => 190,
		'phone'           => 25,
		'contact_pref'    => 10,
		'treats'          => 'list',
		'treats_unsure'   => 'flag',
		'event'           => 60,
		'event_other'     => 80,
		'event_date'      => 10,
		'date_unknown'    => 'flag',
		'guests'          => 5,
		'location'        => 60,
		'location_detail' => 60,
		'venue'           => 200,
		'serve_time'      => 5,
		'serve_duration'  => 10,
	);

	/** Optional details accepted after submission. */
	public const OPTIONAL_FIELDS = array(
		'notes'       => 1500,
		'party_theme' => 500,
		'dietary'     => 500,
	);

	/** Step 1 fields: validated before a draft may be created. */
	public const STEP1_FIELDS = array( 'your_name', 'your_email', 'phone', 'contact_pref', 'treats' );

	/** The draft authorised for this request (set by resolve()). @var array<string, mixed>|null */
	private static ?array $resolved = null;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'cic/v1',
			'/quote-draft',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_continue' ),
				'permission_callback' => array( self::class, 'same_origin' ),
			)
		);
		register_rest_route(
			'cic/v1',
			'/quote-draft/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_save' ),
				'permission_callback' => array( self::class, 'same_origin' ),
			)
		);
	}

	/** Same-origin browser requests from our script only (custom header forces a CORS preflight). */
	public static function same_origin( \WP_REST_Request $request ): bool {
		if ( '1' !== (string) $request->get_header( 'x_cic_draft' ) ) {
			return false;
		}
		$host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$origin = (string) $request->get_header( 'origin' );
		if ( '' !== $origin && (string) wp_parse_url( $origin, PHP_URL_HOST ) !== $host ) {
			return false;
		}
		$site = (string) $request->get_header( 'sec_fetch_site' );
		return '' === $site || in_array( $site, array( 'same-origin', 'none' ), true );
	}

	/* ------------------------------------------------------------------ *
	 * REST
	 * ------------------------------------------------------------------ */

	/**
	 * Step 1 "Continue": create the draft (first time) or update the visitor's own draft.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_continue( \WP_REST_Request $request ) {
		$body = (array) $request->get_json_params();
		$key  = (string) ( $body['key'] ?? '' );
		$rev  = (string) ( $body['rev'] ?? '' );
		if ( ! Store::valid_key( $key ) || ! self::valid_rev( $rev ) ) {
			return self::error( 'bad_request', 400 );
		}
		$fields = self::clean( (array) ( $body['fields'] ?? array() ), self::MAIN_FIELDS );
		$check  = Validator::quote( $fields );
		$errors = array_intersect_key( $check['errors'], array_flip( self::STEP1_FIELDS ) );
		if ( $errors ) {
			return new \WP_REST_Response( array( 'ok' => false, 'code' => 'invalid', 'errors' => $errors ), 422 );
		}

		$own = self::resolve( $key );
		if ( $own && 'incomplete' === $own['stage'] ) {
			$saved = self::save_main( (int) $own['id'], $fields, $rev );
			if ( 'locked' === $saved ) {
				return self::error( 'busy', 409 );
			}
			if ( 'submitted' !== $saved ) {
				return new \WP_REST_Response( array( 'ok' => true, 'rev' => $rev ), 200 );
			}
			// Submitted meanwhile: this Continue starts a new inquiry (a new event), below.
		}

		// A new draft: spam guard first (honeypot, signed render time, per-IP draft limit).
		$guard = array( 'cic_website' => (string) ( $body['cic_website'] ?? '' ), 'cic_ts' => (string) ( $body['cic_ts'] ?? '' ) );
		if ( '' !== trim( $guard['cic_website'] ) || ! Guard::token_time( $guard['cic_ts'] ) || time() - Guard::token_time( $guard['cic_ts'] ) < Guard::MIN_SECONDS ) {
			return self::error( 'rejected', 403 );
		}
		if ( Guard::count( 'draft', Guard::client_hash() ) >= self::CREATE_LIMIT ) {
			return self::error( 'rate_limit', 429 );
		}

		$claim = self::claim_key( $key );
		if ( 'processing' === $claim['state'] ) {
			return self::error( 'busy', 409 );
		}
		if ( 'stored' === $claim['state'] ) {
			// A retry of a creation that already succeeded (e.g. the response was lost): hand the same
			// draft back with a fresh token. The key is a secret only this browser holds.
			$id = (int) $claim['draft'];
			if ( ! self::is_draft( $id ) || 'incomplete' !== self::stage( $id ) ) {
				return self::error( 'gone', 410 );
			}
			self::issue_token( $id );
			self::save_main( $id, $fields, $rev );
			return new \WP_REST_Response( array( 'ok' => true, 'rev' => $rev ), 200 );
		}

		$id = self::create( $fields, $check['data'], Workflow::clean_source( (string) ( $body['source'] ?? '' ) ), $rev );
		if ( ! $id ) {
			self::release_key( $key );
			return self::error( 'storage', 500 );
		}
		self::finish_key( $key, $id );
		Guard::hit( 'draft', Guard::client_hash(), Guard::WINDOW );
		return new \WP_REST_Response( array( 'ok' => true, 'rev' => $rev ), 201 );
	}

	/**
	 * Autosave: Steps 1–2 while incomplete; optional details for a while after submission.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_save( \WP_REST_Request $request ) {
		$body = (array) $request->get_json_params();
		$rev  = (string) ( $body['rev'] ?? '' );
		if ( ! self::valid_rev( $rev ) ) {
			return self::error( 'bad_request', 400 );
		}
		$own = self::resolve( (string) ( $body['key'] ?? '' ) );
		if ( ! $own ) {
			return self::error( 'no_draft', 403 );
		}
		$id     = (int) $own['id'];
		$bucket = 'dsave' . $id;
		if ( Guard::count( $bucket, 'x' ) >= self::SAVE_LIMIT ) {
			return self::error( 'rate_limit', 429 );
		}
		Guard::hit( $bucket, 'x', HOUR_IN_SECONDS );
		$raw = (array) ( $body['fields'] ?? array() );
		if ( 'incomplete' === $own['stage'] ) {
			$state = self::save_main( $id, self::clean( $raw, self::MAIN_FIELDS ), $rev );
			if ( 'locked' === $state ) {
				return self::error( 'busy', 409 );
			}
			return new \WP_REST_Response( array( 'ok' => true, 'applied' => 'applied' === $state, 'stage' => 'submitted' === $state ? 'submitted' : 'incomplete' ), 200 );
		}
		if ( ! self::optional_open( $id ) ) {
			return self::error( 'closed', 410 );
		}
		$ok = self::save_optional( $id, self::clean( $raw, self::OPTIONAL_FIELDS ), $rev );
		return new \WP_REST_Response( array( 'ok' => true, 'applied' => $ok, 'stage' => 'submitted' ), 200 );
	}

	private static function error( string $code, int $status ): \WP_REST_Response {
		return new \WP_REST_Response( array( 'ok' => false, 'code' => $code ), $status );
	}

	/* ------------------------------------------------------------------ *
	 * Access
	 * ------------------------------------------------------------------ */

	private static function hmac( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	public static function is_draft( int $id ): bool {
		return Workflow::is_enquiry( $id ) && 'publish' === get_post_status( $id ) && 'quote' === get_post_meta( $id, '_cic_type', true );
	}

	/** Stage of an inquiry: incomplete | submitted (missing meta = submitted, historical records). */
	public static function stage( int $id ): string {
		return 'incomplete' === get_post_meta( $id, '_cic_stage', true ) ? 'incomplete' : 'submitted';
	}

	/**
	 * The draft this browser may access: the cookie token, else the Step 1 client key (page memory
	 * only; cookies blocked). Null when neither proves access.
	 *
	 * @return array{id: int, stage: string}|null
	 */
	public static function resolve( string $key = '' ): ?array {
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( preg_match( '/^(\d{1,20})\.([A-Za-z0-9_-]{43})$/', $raw, $m ) ) {
			$id   = (int) $m[1];
			$hash = (string) get_post_meta( $id, '_cic_draft_token', true );
			if ( '' !== $hash && self::is_draft( $id ) && hash_equals( $hash, self::hmac( $m[2] ) ) ) {
				return self::$resolved = array( 'id' => $id, 'stage' => self::stage( $id ) );
			}
		}
		if ( Store::valid_key( $key ) ) {
			$row = Store::by_key( $key );
			$sum = $row && 'draft' === $row['form'] && 'stored' === $row['status'] ? json_decode( (string) $row['summary'], true ) : null;
			$id  = is_array( $sum ) ? (int) ( $sum['draft'] ?? 0 ) : 0;
			if ( $id && self::is_draft( $id ) ) {
				return self::$resolved = array( 'id' => $id, 'stage' => self::stage( $id ) );
			}
		}
		return null;
	}

	/** New random token for a draft: stored as an HMAC, sent once in the HttpOnly cookie. */
	private static function issue_token( int $id ): void {
		$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		update_post_meta( $id, '_cic_draft_token', self::hmac( $token ) );
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				$id . '.' . $token,
				array(
					'expires'  => time() + self::COOKIE_TTL,
					'path'     => '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Strict',
				)
			);
		}
	}

	/** Optional details may be added for OPTIONAL_TTL after submission. */
	public static function optional_open( int $id ): bool {
		$at = (int) get_post_meta( $id, '_cic_submitted_at', true );
		return 'submitted' === self::stage( $id ) && $at > 0 && time() - $at <= self::OPTIONAL_TTL;
	}

	/* ------------------------------------------------------------------ *
	 * Idempotent creation (client key)
	 * ------------------------------------------------------------------ */

	/** @return array{state: string, draft: int} */
	private static function claim_key( string $key ): array {
		global $wpdb;
		$t   = Store::table();
		$now = current_time( 'mysql', true );
		$new = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (form, key_hash, status, summary, operator_status, customer_status, created_at, updated_at) VALUES ('draft', %s, 'processing', '', 'skipped', 'skipped', %s, %s)", Store::hash( $key ), $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 === (int) $new ) {
			return array( 'state' => 'claimed', 'draft' => 0 );
		}
		$row = Store::by_key( $key );
		if ( ! $row || 'draft' !== $row['form'] ) {
			return array( 'state' => 'processing', 'draft' => 0 );
		}
		$sum = json_decode( (string) $row['summary'], true );
		return array( 'state' => (string) $row['status'], 'draft' => is_array( $sum ) ? (int) ( $sum['draft'] ?? 0 ) : 0 );
	}

	private static function finish_key( string $key, int $id ): void {
		global $wpdb;
		$wpdb->update( Store::table(), array( 'status' => 'stored', 'summary' => wp_json_encode( array( 'draft' => $id ) ), 'updated_at' => current_time( 'mysql', true ) ), array( 'key_hash' => Store::hash( $key ), 'form' => 'draft' ) );
	}

	private static function release_key( string $key ): void {
		global $wpdb;
		$wpdb->delete( Store::table(), array( 'key_hash' => Store::hash( $key ), 'form' => 'draft', 'status' => 'processing' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Writing
	 * ------------------------------------------------------------------ */

	/**
	 * Allowlisted, length-limited values (strings; treats a list of keys; flags "1" or "").
	 *
	 * @param array<string, mixed>      $raw   Request values.
	 * @param array<string, int|string> $allow Field => limit | 'list' | 'flag'.
	 * @return array<string, mixed>
	 */
	public static function clean( array $raw, array $allow ): array {
		$out = array();
		foreach ( $allow as $name => $limit ) {
			if ( ! array_key_exists( $name, $raw ) ) {
				continue;
			}
			$v = $raw[ $name ];
			if ( 'list' === $limit ) {
				$v            = is_array( $v ) ? $v : array();
				$out[ $name ] = array_slice( array_values( array_filter( array_map( static fn( $x ) => substr( sanitize_key( (string) $x ), 0, 60 ), $v ) ) ), 0, 20 );
			} elseif ( 'flag' === $limit ) {
				$out[ $name ] = ! empty( $v ) && '0' !== (string) $v ? '1' : '';
			} else {
				$v            = is_scalar( $v ) ? (string) $v : '';
				$v            = isset( self::OPTIONAL_FIELDS[ $name ] ) ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
				$out[ $name ] = mb_substr( trim( $v ), 0, (int) $limit );
			}
		}
		return $out;
	}

	private static function lock( int $id ): bool {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $wpdb->prefix . 'cic_draft_' . $id ) );
	}

	private static function unlock( int $id ): void {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $wpdb->prefix . 'cic_draft_' . $id ) );
	}

	public static function valid_rev( string $rev ): bool {
		return (bool) preg_match( '/^\d{1,16}$/', $rev );
	}

	/** Atomically move a revision meta forward; false when the stored one is not older. */
	private static function claim_rev( int $id, string $meta, string $rev ): bool {
		global $wpdb;
		$done = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = %s AND CAST(meta_value AS UNSIGNED) < CAST(%s AS UNSIGNED)", $rev, $id, $meta, $rev ) );
		wp_cache_delete( $id, 'post_meta' );
		return 1 === $done;
	}

	/** Flamingo channel slug of the quote form (as CF7 stores it). */
	private static function channel(): string {
		$form = Forms::id( 'quote' );
		$meta = $form ? (array) get_post_meta( $form, '_flamingo', true ) : array();
		$term = ! empty( $meta['channel'] ) ? get_term( (int) $meta['channel'] ) : null;
		return $term && ! is_wp_error( $term ) ? (string) $term->slug : 'contact-form-7';
	}

	/**
	 * Create the draft (Step 1 accepted).
	 *
	 * @param array<string, mixed> $fields Clean values.
	 * @param array<string, mixed> $data   Validator output.
	 * @param array<string, mixed> $source Source context.
	 */
	private static function create( array $fields, array $data, array $source, string $rev ): int {
		if ( ! class_exists( 'Flamingo_Inbound_Message' ) ) {
			return 0;
		}
		$name  = (string) ( $fields['your_name'] ?? '' );
		$email = (string) ( $fields['your_email'] ?? '' );
		try {
			$msg = \Flamingo_Inbound_Message::add(
				array(
					'channel'    => self::channel(),
					'status'     => 'incomplete',
					'subject'    => __( 'Quote request (not yet submitted)', 'cater-ice-cream-core' ),
					'from'       => trim( sprintf( '%s <%s>', $name, $email ) ),
					'from_name'  => $name,
					'from_email' => $email,
					'fields'     => $fields,
					'meta'       => array(),
				)
			);
		} catch ( \Throwable $e ) {
			return 0;
		}
		$id = (int) $msg->id();
		if ( ! $id ) {
			return 0;
		}
		update_post_meta( $id, '_cic_stage', 'incomplete' );
		Workflow::init( $id, $data, $source );
		self::set_context( $id, $fields );
		update_post_meta( $id, '_cic_draft_rev', $rev );
		update_post_meta( $id, '_cic_optional_rev', '0' );
		update_post_meta( $id, '_cic_activity', time() );
		self::issue_token( $id );
		do_action( 'cic_draft_created', $id );
		return $id;
	}

	/** Non-personal context from the valid parts of a (partial) draft. */
	private static function set_context( int $id, array $fields ): void {
		$check = Validator::quote( $fields );
		$data  = $check['data'];
		$drop  = array(
			'event'           => array( 'event', 'event_label', 'event_other' ),
			'event_other'     => array( 'event_other' ),
			'event_date'      => array( 'date', 'date_label', 'date_unknown' ),
			'guests'          => array( 'guests' ),
			'location'        => array( 'location', 'location_label', 'location_detail', 'coverage' ),
			'location_detail' => array( 'location', 'location_label', 'location_detail', 'coverage' ),
			'venue'           => array( 'venue' ),
			'serve_time'      => array( 'serve_time' ),
			'serve_duration'  => array( 'serve_duration' ),
			'treats'          => array( 'treats', 'treat_labels', 'treats_unsure' ),
			'contact_pref'    => array( 'contact_pref' ),
		);
		foreach ( array_keys( $check['errors'] ) as $field ) {
			foreach ( $drop[ $field ] ?? array() as $k ) {
				unset( $data[ $k ] );
			}
		}
		if ( empty( $fields['event_date'] ) && empty( $fields['date_unknown'] ) ) {
			unset( $data['date'], $data['date_label'], $data['date_unknown'] );
		}
		Workflow::set_context( $id, $data );
	}

	/**
	 * Apply a snapshot of Steps 1–2 to an incomplete draft (newer revision only).
	 *
	 * @return string applied | stale (an older or repeated request: ignored) | submitted | locked
	 */
	private static function save_main( int $id, array $fields, string $rev ): string {
		if ( ! self::lock( $id ) ) {
			return 'locked';
		}
		try {
			if ( 'incomplete' !== self::stage( $id ) ) {
				return 'submitted'; // Never reopen or overwrite a submitted inquiry.
			}
			if ( ! self::claim_rev( $id, '_cic_draft_rev', $rev ) ) {
				return 'stale';
			}
			$msg          = new \Flamingo_Inbound_Message( $id );
			$msg->fields  = array_merge( (array) $msg->fields, $fields );
			$name         = (string) ( $msg->fields['your_name'] ?? '' );
			$email        = (string) ( $msg->fields['your_email'] ?? '' );
			$msg->from_name  = $name;
			$msg->from_email = $email;
			$msg->from       = trim( sprintf( '%s <%s>', $name, $email ) );
			$msg->save();
			self::set_context( $id, self::fields( $id ) );
			update_post_meta( $id, '_cic_activity', time() );
			return 'applied';
		} finally {
			self::unlock( $id );
		}
	}

	/** Optional details after submission: only these fields; status, assignee and mail untouched. */
	private static function save_optional( int $id, array $fields, string $rev ): bool {
		if ( ! $fields || ! self::lock( $id ) ) {
			return false;
		}
		try {
			if ( ! self::claim_rev( $id, '_cic_optional_rev', $rev ) ) {
				return false;
			}
			$list = (array) get_post_meta( $id, '_fields', true );
			foreach ( $fields as $name => $value ) {
				update_post_meta( $id, '_field_' . $name, $value );
				$list[ $name ] = null;
			}
			update_post_meta( $id, '_fields', $list );
			update_post_meta( $id, '_cic_activity', time() );
			update_post_meta( $id, '_cic_optional_at', time() );
			return true;
		} finally {
			self::unlock( $id );
		}
	}

	/** Current Flamingo field values of an inquiry. */
	public static function fields( int $id ): array {
		$out = array();
		foreach ( array_keys( (array) get_post_meta( $id, '_fields', true ) ) as $name ) {
			$out[ $name ] = get_post_meta( $id, '_field_' . $name, true );
		}
		return $out;
	}

	/**
	 * Final submission (called by Pipeline for the visitor's own incomplete draft): store the
	 * submitted values on the SAME Flamingo message, exactly as CF7's Flamingo module would build
	 * them, and mark it submitted. Returns the id, or 0 when the draft cannot be completed (it was
	 * submitted already, or the lock timed out): the pipeline then stores a new message as usual.
	 *
	 * @param \WPCF7_ContactForm $form       Form.
	 * @param \WPCF7_Submission  $submission Submission.
	 */
	public static function complete( int $id, $form, $submission ): int {
		if ( ! class_exists( 'Flamingo_Inbound_Message' ) || ! self::lock( $id ) ) {
			return 0;
		}
		try {
			if ( 'incomplete' !== self::stage( $id ) ) {
				return 0;
			}
			$posted = array_filter(
				(array) $submission->get_posted_data(),
				static fn( $name ) => ! $form->scan_form_tags( array( 'name' => $name, 'feature' => 'do-not-store' ) ),
				ARRAY_FILTER_USE_KEY
			);
			$email   = function_exists( 'wpcf7_flamingo_get_value' ) ? (string) wpcf7_flamingo_get_value( 'email', $form ) : '';
			$name    = function_exists( 'wpcf7_flamingo_get_value' ) ? (string) wpcf7_flamingo_get_value( 'name', $form ) : '';
			$subject = function_exists( 'wpcf7_flamingo_get_value' ) ? (string) wpcf7_flamingo_get_value( 'subject', $form ) : '';
			$meta    = array();
			foreach ( array( 'serial_number', 'url', 'date', 'time', 'post_id', 'post_name', 'post_title', 'post_url', 'post_author', 'post_author_email', 'site_title', 'site_description', 'site_url', 'site_admin_email', 'user_login', 'user_email', 'user_display_name' ) as $smt ) {
				$tag          = '_' . $smt;
				$meta[ $smt ] = apply_filters( 'wpcf7_special_mail_tags', null, $tag, false, new \WPCF7_MailTag( '[' . $tag . ']', $tag, '' ) );
			}
			$msg                    = new \Flamingo_Inbound_Message( $id );
			$msg->channel           = self::channel();
			$msg->submission_status = 'mail_sent';
			$msg->subject           = $subject;
			$msg->from              = trim( sprintf( '%s <%s>', $name, $email ) );
			$msg->from_name         = $name;
			$msg->from_email        = $email;
			$msg->fields            = $posted;
			$msg->meta              = $meta;
			$msg->consent           = $submission->collect_consent();
			$msg->spam              = false;
			$msg->save();
			// Stale draft-only values (e.g. a cleared field) must not survive next to the submission.
			foreach ( array_diff( array_keys( self::MAIN_FIELDS ), array_keys( $posted ) ) as $gone ) {
				delete_post_meta( $id, '_field_' . $gone );
			}
			$now = current_time( 'mysql' );
			wp_update_post( array( 'ID' => $id, 'post_date' => $now, 'post_date_gmt' => get_gmt_from_date( $now ) ) );
			update_post_meta( $id, '_hash', $submission->get_posted_data_hash() );
			if ( class_exists( 'Flamingo_Contact' ) && is_email( $email ) ) {
				\Flamingo_Contact::add( array( 'email' => $email, 'name' => $name, 'last_contacted' => $now ) );
			}
			update_post_meta( $id, '_cic_draft_rev', self::MAX_REV );
			update_post_meta( $id, '_cic_stage', 'submitted' );
			update_post_meta( $id, '_cic_submitted_at', time() );
			update_post_meta( $id, '_cic_activity', time() );
			return $id;
		} finally {
			self::unlock( $id );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Confirmation summary ("Your event at a glance")
	 * ------------------------------------------------------------------ */

	/**
	 * Summary for the visitor who submitted it (contact, event and treats; labels resolved by the
	 * server). Only ever returned to the authorised browser, never put in URLs or storage.
	 *
	 * @param array<string, mixed> $d Validated, normalised submission.
	 * @return array<string, string>
	 */
	public static function glance( array $d ): array {
		$time = (string) ( $d['serve_time'] ?? '' );
		return array_filter(
			array(
				'name'     => (string) ( $d['name'] ?? '' ),
				'email'    => (string) ( $d['email'] ?? '' ),
				'phone'    => (string) ( $d['phone'] ?? '' ),
				'contact'  => 'phone' === ( $d['contact_pref'] ?? '' ) ? __( 'Phone', 'cater-ice-cream-core' ) : __( 'Email', 'cater-ice-cream-core' ),
				'event'    => (string) ( $d['event_label'] ?? '' ),
				'date'     => (string) ( $d['date_label'] ?? '' ),
				'guests'   => ! empty( $d['guests'] ) ? sprintf( /* translators: %d: guests. */ _n( '%d guest', '%d guests', (int) $d['guests'], 'cater-ice-cream-core' ), (int) $d['guests'] ) : '',
				'location' => trim( (string) ( $d['location_label'] ?? '' ) . ( ! empty( $d['location_detail'] ) ? ' · ' . $d['location_detail'] : '' ) ),
				'venue'    => (string) ( $d['venue'] ?? '' ),
				'serving'  => trim( ( '' !== $time ? wp_date( 'g:i a', strtotime( '2000-01-01 ' . $time . ' UTC' ), new \DateTimeZone( 'UTC' ) ) : '' ) . ( ! empty( $d['serve_duration'] ) ? ( '' !== $time ? ' · ' : '' ) . ( Validator::DURATIONS[ $d['serve_duration'] ] ?? '' ) : '' ) ),
				'treats'   => implode( ', ', (array) ( $d['treat_labels'] ?? array() ) ),
			),
			static fn( $v ) => '' !== $v
		);
	}

	/**
	 * Summary for a confirmation page reload: only for the browser that holds this inquiry's draft
	 * access, and only while optional details are still open.
	 *
	 * @return array{glance: array<string, string>, optional: array<string, string>, open: bool}|null
	 */
	public static function for_receipt( int $flamingo_id ): ?array {
		$own = self::resolve();
		if ( ! $own || (int) $own['id'] !== $flamingo_id || 'submitted' !== $own['stage'] || ! self::optional_open( $flamingo_id ) ) {
			return null;
		}
		$check = Validator::quote( self::fields( $flamingo_id ) );
		$opt   = array();
		foreach ( array_keys( self::OPTIONAL_FIELDS ) as $k ) {
			$opt[ $k ] = (string) get_post_meta( $flamingo_id, '_field_' . $k, true );
		}
		return array( 'glance' => self::glance( $check['data'] ), 'optional' => $opt, 'open' => true );
	}

	/* ------------------------------------------------------------------ *
	 * Retention
	 * ------------------------------------------------------------------ */

	/**
	 * Delete incomplete drafts older than the configured retention (Enquiry settings). Nothing is
	 * deleted while no retention period is set (owner decision pending). Submitted inquiries are
	 * never touched here.
	 */
	public static function cleanup(): int {
		$days = (int) Settings::value( 'draft_retention_days' );
		if ( $days < 1 ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'   => Workflow::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => 200,
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => '_cic_stage', 'value' => 'incomplete' ),
					array( 'key' => '_cic_activity', 'value' => time() - $days * DAY_IN_SECONDS, 'compare' => '<', 'type' => 'NUMERIC' ),
				),
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
		return count( $ids );
	}
}
