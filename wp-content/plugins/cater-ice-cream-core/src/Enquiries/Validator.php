<?php
/**
 * Server-side validation and normalisation of quote and contact submissions (BUILD_SPECIFICATION
 * §12–13). Every value is checked here regardless of the browser: published event/treat keys,
 * LA-local dates (no UTC shifting), numeric ranges, contact preference, text lengths, and the
 * location through the shared LocationRepository + Coverage rules:
 *
 * - invalid → correct the field; outside_area → blocked (cannot submit for that location);
 * - unknown / manual_review (shared ZIP, partly served city) → ask for the missing half (the venue
 *   city for a ZIP, or the event ZIP for a city). Once given, the request is accepted for the team
 *   to confirm; coverage is recorded with the enquiry, including whether it came from fixtures.
 *
 * Labels are resolved on the server. Field definitions are provisional pending owner review.
 *
 * @package CIC
 */

namespace CIC\Enquiries;

use CIC\Content\Catalog;
use CIC\Locations\Coverage;
use CIC\Locations\Service as Locations;
use CIC\Quote\Context;

defined( 'ABSPATH' ) || exit;

final class Validator {

	public const DURATIONS = array(
		'1h'     => 'Up to 1 hour',
		'2h'     => '1–2 hours',
		'3h'     => '2–3 hours',
		'4h'     => '3–4 hours',
		'4plus'  => 'More than 4 hours',
		'unsure' => 'Not sure yet',
	);

	public const TOPICS = array(
		'general' => 'General question',
		'event'   => 'Planning an event',
		'treats'  => 'Treats and options',
		'areas'   => 'Service areas',
		'other'   => 'Something else',
	);

	public const CONTACT_PREFS = array( 'email', 'phone' );

	/** Maximum lengths of free-text fields. */
	public const LIMITS = array(
		'event_other' => 80,
		'venue'       => 200,
		'flavours'    => 500,
		'party_theme' => 500,
		'dietary'     => 500,
		'your_name'   => 100,
		'your_email'  => 190,
		'notes'       => 1500,
		'message'     => 3000,
	);

	/**
	 * @param array<string, mixed> $in Posted values (strings / arrays).
	 * @return array{data: array<string, mixed>, errors: array<string, string>}
	 */
	public static function quote( array $in ): array {
		$errors = array();
		$data   = array( 'type' => 'quote' );
		$s      = static fn( string $k ): string => trim( (string) ( is_array( $in[ $k ] ?? '' ) ? implode( ',', $in[ $k ] ) : ( $in[ $k ] ?? '' ) ) );

		// Step 1: event.
		$event  = sanitize_key( $s( 'event' ) );
		$events = array_map( array( self::class, 'plain' ), array_column( Catalog::selectable_events(), 'label', 'key' ) );
		if ( 'other' === $event ) {
			$data['event']       = 'other';
			$data['event_label'] = __( 'Other occasion', 'cater-ice-cream-core' );
		} elseif ( isset( $events[ $event ] ) ) {
			$data['event']       = $event;
			$data['event_label'] = (string) $events[ $event ];
		} else {
			$errors['event'] = __( 'Please choose what you are celebrating.', 'cater-ice-cream-core' );
		}
		$data['event_other'] = self::text( $s( 'event_other' ), 'event_other', $errors );
		if ( 'other' === ( $data['event'] ?? '' ) && '' !== $data['event_other'] ) {
			$data['event_label'] = $data['event_other'];
		}

		$data['date_unknown'] = '' !== $s( 'date_unknown' );
		$raw_date             = $s( 'event_date' );
		$data['date']         = '';
		if ( ! $data['date_unknown'] ) {
			$date = Context::date( $raw_date );
			if ( '' === $raw_date ) {
				$errors['event_date'] = __( 'Please choose your event date, or tick “Date not set yet”.', 'cater-ice-cream-core' );
			} elseif ( '' === $date ) {
				$errors['event_date'] = __( 'Please choose a date from today onward (within three years).', 'cater-ice-cream-core' );
			} else {
				$data['date'] = $date;
			}
		}
		$data['date_label'] = '' !== $data['date'] ? self::date_label( $data['date'] ) : __( 'Date to be confirmed', 'cater-ice-cream-core' );

		$guests = $s( 'guests' );
		if ( preg_match( '/^\d{1,5}$/', $guests ) && (int) $guests >= 1 && (int) $guests <= 10000 ) {
			$data['guests'] = (int) $guests;
		} else {
			$errors['guests'] = __( 'Please enter an approximate guest count (a whole number from 1).', 'cater-ice-cream-core' );
		}

		$location = self::location( $s( 'location' ), $s( 'location_detail' ) );
		if ( '' !== $location['error_field'] ) {
			$errors[ $location['error_field'] ] = $location['message'];
		}
		$data['location']        = $location['key'];
		$data['location_label']  = $location['label'];
		$data['location_detail'] = $location['detail'];
		$data['coverage']        = $location['coverage'];

		$data['venue'] = self::text( $s( 'venue' ), 'venue', $errors );
		$time          = $s( 'serve_time' );
		if ( '' !== $time && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			$errors['serve_time'] = __( 'Please choose a valid serving start time.', 'cater-ice-cream-core' );
			$time                 = '';
		}
		$data['serve_time'] = $time;
		$duration           = sanitize_key( $s( 'serve_duration' ) );
		if ( '' !== $duration && ! isset( self::DURATIONS[ $duration ] ) ) {
			$errors['serve_duration'] = __( 'Please choose a serving duration from the list.', 'cater-ice-cream-core' );
			$duration                 = '';
		}
		$data['serve_duration'] = $duration;

		// Step 2: treats (multiple published treats OR "Not sure yet", never both).
		$products  = array_map( array( self::class, 'plain' ), array_column( Catalog::selectable_products(), 'title', 'key' ) );
		$raw       = $in['treats'] ?? array();
		$raw       = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$treats    = array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', $raw ) ) ) ) );
		$unknown   = array_diff( $treats, array_keys( $products ) );
		$unsure    = '' !== $s( 'treats_unsure' );
		$data['treats_unsure'] = $unsure;
		$data['treats']        = array_values( array_intersect( $treats, array_keys( $products ) ) );
		if ( $unknown ) {
			$errors['treats'] = __( 'One of the selected treats is no longer available. Please choose again.', 'cater-ice-cream-core' );
		} elseif ( $unsure && $data['treats'] ) {
			$errors['treats'] = __( 'Choose treats or “Not sure yet”, not both.', 'cater-ice-cream-core' );
		} elseif ( ! $unsure && ! $data['treats'] ) {
			$errors['treats'] = __( 'Please choose at least one treat, or “Not sure yet”.', 'cater-ice-cream-core' );
		}
		$data['treat_labels'] = $unsure ? array( __( 'Not sure yet', 'cater-ice-cream-core' ) ) : array_map( static fn( $k ) => (string) $products[ $k ], $data['treats'] );
		foreach ( array( 'flavours', 'party_theme', 'dietary' ) as $k ) {
			$data[ $k ] = self::text( $s( $k ), $k, $errors, true );
		}

		// Step 3: contact.
		self::contact_fields( $in, $data, $errors );
		$data['notes'] = self::text( $s( 'notes' ), 'notes', $errors, true );
		return array( 'data' => $data, 'errors' => $errors );
	}

	/**
	 * @param array<string, mixed> $in Posted values.
	 * @return array{data: array<string, mixed>, errors: array<string, string>}
	 */
	public static function contact( array $in ): array {
		$errors = array();
		$data   = array( 'type' => 'contact' );
		$topic  = sanitize_key( (string) ( is_array( $in['topic'] ?? '' ) ? reset( $in['topic'] ) : ( $in['topic'] ?? '' ) ) );
		if ( isset( self::TOPICS[ $topic ] ) ) {
			$data['topic']       = $topic;
			$data['topic_label'] = self::TOPICS[ $topic ];
		} else {
			$errors['topic'] = __( 'Please choose a topic.', 'cater-ice-cream-core' );
		}
		self::contact_fields( $in, $data, $errors, false );
		$data['message'] = self::text( trim( (string) ( $in['message'] ?? '' ) ), 'message', $errors, true );
		if ( '' === $data['message'] && ! isset( $errors['message'] ) ) {
			$errors['message'] = __( 'Please write your message.', 'cater-ice-cream-core' );
		}
		return array( 'data' => $data, 'errors' => $errors );
	}

	/**
	 * Name, email, phone and (quote only) contact preference.
	 *
	 * @param array<string, mixed>  $in     Posted values.
	 * @param array<string, mixed>  $data   Normalised data (by reference).
	 * @param array<string, string> $errors Errors (by reference).
	 */
	private static function contact_fields( array $in, array &$data, array &$errors, bool $with_pref = true ): void {
		$name = self::text( trim( (string) ( $in['your_name'] ?? '' ) ), 'your_name', $errors );
		if ( '' === $name && ! isset( $errors['your_name'] ) ) {
			$errors['your_name'] = __( 'Please enter your name.', 'cater-ice-cream-core' );
		}
		$data['name'] = $name;
		$email        = trim( (string) ( $in['your_email'] ?? '' ) );
		if ( '' === $email ) {
			$errors['your_email'] = __( 'Please enter your email address.', 'cater-ice-cream-core' );
		} elseif ( strlen( $email ) > self::LIMITS['your_email'] || ! is_email( $email ) ) {
			$errors['your_email'] = __( 'Please enter a valid email address, for example name@example.com.', 'cater-ice-cream-core' );
		}
		$data['email'] = isset( $errors['your_email'] ) ? '' : sanitize_email( $email );
		$pref          = 'email';
		if ( $with_pref ) {
			$raw  = $in['contact_pref'] ?? 'email';
			$pref = sanitize_key( (string) ( is_array( $raw ) ? reset( $raw ) : $raw ) );
			if ( ! in_array( $pref, self::CONTACT_PREFS, true ) ) {
				$errors['contact_pref'] = __( 'Please choose how you would like us to contact you.', 'cater-ice-cream-core' );
				$pref                   = 'email';
			}
		}
		$data['contact_pref'] = $pref;
		$phone                = self::phone( trim( (string) ( $in['phone'] ?? '' ) ) );
		if ( false === $phone ) {
			$errors['phone'] = __( 'Please enter a valid US phone number, for example (818) 555-0100.', 'cater-ice-cream-core' );
			$phone           = '';
		} elseif ( '' === $phone && 'phone' === $pref ) {
			$errors['phone'] = __( 'Please add a phone number so we can call you, or choose email.', 'cater-ice-cream-core' );
		}
		$data['phone'] = $phone;
	}

	/** Normalised US phone "(818) 555-0100", '' when empty, false when invalid. */
	public static function phone( string $raw ) {
		if ( '' === $raw ) {
			return '';
		}
		if ( strlen( $raw ) > 25 || ! preg_match( '/^[\d\s().+-]+$/', $raw ) ) {
			return false;
		}
		$digits = preg_replace( '/\D/', '', $raw );
		if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}
		if ( 10 !== strlen( $digits ) || in_array( $digits[0], array( '0', '1' ), true ) ) {
			return false;
		}
		return sprintf( '(%s) %s-%s', substr( $digits, 0, 3 ), substr( $digits, 3, 3 ), substr( $digits, 6 ) );
	}

	/**
	 * Bounded single/multi-line text.
	 *
	 * @param array<string, string> $errors Errors (by reference).
	 */
	private static function text( string $value, string $field, array &$errors, bool $multiline = false ): string {
		$value = $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		if ( mb_strlen( $value ) > self::LIMITS[ $field ] ) {
			/* translators: %d: maximum characters. */
			$errors[ $field ] = sprintf( __( 'Please shorten this to %d characters or fewer.', 'cater-ice-cream-core' ), self::LIMITS[ $field ] );
			return mb_substr( $value, 0, self::LIMITS[ $field ] );
		}
		return $value;
	}

	/** Plain-text label (WordPress titles are texturized with HTML entities). */
	public static function plain( string $title ): string {
		return html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/** User-facing date: MM/DD/YYYY, built from the calendar date itself (no timezone conversion). */
	public static function date_label( string $date ): string {
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( Context::TIMEZONE ) );
		return $dt ? $dt->format( 'm/d/Y' ) : $date;
	}

	/**
	 * Location + optional clarification through the shared coverage rules.
	 *
	 * @return array{key: string, label: string, detail: string, coverage: array<string, mixed>, error_field: string, message: string}
	 */
	public static function location( string $raw, string $detail_raw ): array {
		$out = array( 'key' => '', 'label' => '', 'detail' => '', 'coverage' => array(), 'error_field' => '', 'message' => '' );
		$key = Context::location( $raw );
		if ( '' === $raw ) {
			return array_merge( $out, array( 'error_field' => 'location', 'message' => __( 'Please enter your event city or 5-digit ZIP code.', 'cater-ice-cream-core' ) ) );
		}
		if ( '' === $key ) {
			return array_merge( $out, array( 'error_field' => 'location', 'message' => Coverage::message( 'invalid', 'malformed' ) ) );
		}
		$is_zip = (bool) preg_match( '/^\d{5}$/', $key );
		$detail = '';
		if ( '' !== trim( $detail_raw ) ) {
			$d = Context::location( $detail_raw );
			if ( $is_zip && ( '' === $d || preg_match( '/^\d{5}$/', $d ) ) ) {
				return array_merge( $out, array( 'key' => $key, 'error_field' => 'location_detail', 'message' => __( 'Please enter the venue’s city name.', 'cater-ice-cream-core' ) ) );
			}
			if ( ! $is_zip && ! preg_match( '/^\d{5}$/', $d ) ) {
				return array_merge( $out, array( 'key' => $key, 'error_field' => 'location_detail', 'message' => __( 'Please enter the event’s 5-digit ZIP code.', 'cater-ice-cream-core' ) ) );
			}
			$detail = $is_zip ? sanitize_text_field( $detail_raw ) : $d;
		}
		$repo = Locations::repository();
		if ( $is_zip ) {
			$result = $repo->evaluate_coverage( $key, $detail );
		} elseif ( '' !== $detail ) {
			$result = $repo->evaluate_coverage( $detail, $raw );
		} else {
			$result = $repo->evaluate_coverage( $raw );
		}
		$status = (string) ( $result['status'] ?? 'unknown' );
		$city   = $is_zip ? null : $repo->find_city( $key );
		// Known cities use the repository label; an unknown city keeps the visitor's own wording (never a
		// label rebuilt from the slug).
		$label = $is_zip ? $key : ( $city ? (string) $city['label'] : sanitize_text_field( $raw ) );
		if ( '' !== $detail ) {
			$label = $is_zip ? $detail . ' ' . $key : $label . ' ' . $detail;
		}
		$out = array_merge(
			$out,
			array(
				'key'      => $key,
				'label'    => $label,
				'detail'   => $detail,
				'coverage' => array(
					'status'  => $status,
					'reason'  => (string) ( $result['reason'] ?? '' ),
					'fixture' => ! empty( $result['fixture'] ),
				),
			)
		);
		if ( 'invalid' === $status ) {
			return array_merge( $out, array( 'error_field' => 'location', 'message' => (string) $result['message'] ) );
		}
		if ( 'outside_area' === $status ) {
			return array_merge( $out, array( 'error_field' => 'location', 'message' => (string) $result['message'] ) );
		}
		if ( in_array( $status, array( 'manual_review', 'unknown' ), true ) && '' === $detail ) {
			$message = $is_zip
				? __( 'Please add the venue’s city as well, so our team can confirm service for this ZIP code.', 'cater-ice-cream-core' )
				: __( 'Please add the event’s 5-digit ZIP code as well, so our team can confirm service for your exact location.', 'cater-ice-cream-core' );
			return array_merge( $out, array( 'error_field' => 'location_detail', 'message' => $message ) );
		}
		return $out;
	}
}
