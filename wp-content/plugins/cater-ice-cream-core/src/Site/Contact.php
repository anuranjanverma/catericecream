<?php
/**
 * Public business contact details and shared CTA labels. One source for every header, footer,
 * call panel and tel: link. The operator notification recipient is deliberately not exposed here.
 *
 * @package CIC
 */

namespace CIC\Site;

use CIC\Config;

defined( 'ABSPATH' ) || exit;

final class Contact {

	/** Public keys edited on the Header, Footer & Contact screen. */
	public const PUBLIC_KEYS = array( 'business_name', 'legal_entity', 'business_contact', 'business_phone', 'business_email', 'business_location', 'quote_label', 'quote_url', 'call_label', 'availability_note', 'website_label', 'social_instagram', 'social_facebook', 'social_tiktok', 'social_yelp' );

	public const SOCIAL = array(
		'social_instagram' => 'Instagram',
		'social_facebook'  => 'Facebook',
		'social_tiktok'    => 'TikTok',
		'social_yelp'      => 'Yelp',
	);

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$c      = Config::all();
		$social = array();
		foreach ( self::SOCIAL as $key => $label ) {
			if ( '' !== (string) $c[ $key ] ) {
				$social[] = array(
					'label' => $label,
					'url'   => (string) $c[ $key ],
				);
			}
		}
		return array(
			'business_name'     => (string) $c['business_name'],
			'legal_entity'      => (string) $c['legal_entity'],
			'contact_name'      => (string) $c['business_contact'],
			'location'          => (string) $c['business_location'],
			'phone'             => (string) $c['business_phone'],
			'phone_href'        => self::tel_href( (string) $c['business_phone'] ),
			'email'             => (string) $c['business_email'],
			'quote_label'       => (string) $c['quote_label'],
			'quote_url'         => self::absolute_url( (string) $c['quote_url'] ),
			'call_label'        => (string) $c['call_label'],
			'availability_note' => (string) $c['availability_note'],
			'website_label'     => (string) $c['website_label'],
			'social'            => $social,
		);
	}

	/** tel: link from a display number; North American 10-digit numbers get +1. */
	public static function tel_href( string $phone ): string {
		$digits = preg_replace( '/\D+/', '', $phone );
		if ( '' === $digits ) {
			return '';
		}
		if ( 10 === strlen( $digits ) ) {
			$digits = '1' . $digits;
		}
		return 'tel:+' . $digits;
	}

	/** Site-relative paths become absolute; external URLs are kept (already validated on save). */
	public static function absolute_url( string $url ): string {
		if ( '' === $url ) {
			return home_url( '/' );
		}
		return str_starts_with( $url, '/' ) ? home_url( $url ) : $url;
	}

	/**
	 * Validate the public contact fields. Private keys (recipient, timezone) are carried over unchanged.
	 *
	 * @param mixed    $input    Posted values.
	 * @param string[] $errors   Messages (by reference).
	 * @return array<string, mixed> Full option value.
	 */
	public static function sanitize( $input, array &$errors ): array {
		$input    = is_array( $input ) ? $input : array();
		$previous = get_option( Config::OPTION_SETTINGS, array() );
		$previous = is_array( $previous ) ? $previous : array();
		$defaults = Config::defaults();
		$clean    = $previous;

		foreach ( self::PUBLIC_KEYS as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$raw = trim( (string) $input[ $key ] );
			switch ( $key ) {
				case 'business_email':
					if ( '' !== $raw && ! is_email( $raw ) ) {
						$errors[] = __( 'Public email is not a valid address; the previous value was kept.', 'cater-ice-cream-core' );
						continue 2;
					}
					$value = sanitize_email( $raw );
					break;
				case 'business_phone':
					if ( '' !== $raw && ! preg_match( '/^[0-9+().\-\s]{7,25}$/', $raw ) ) {
						$errors[] = __( 'Public phone may contain only digits, spaces, +, -, ( ) and dots; the previous value was kept.', 'cater-ice-cream-core' );
						continue 2;
					}
					$value = $raw;
					break;
				case 'quote_url':
					$value = self::sanitize_link( $raw );
					if ( null === $value ) {
						$errors[] = __( 'Quote link must be a site path such as /get-a-quote/ or an https:// address; the previous value was kept.', 'cater-ice-cream-core' );
						continue 2;
					}
					break;
				case 'social_instagram':
				case 'social_facebook':
				case 'social_tiktok':
				case 'social_yelp':
					$value = '' === $raw ? '' : esc_url_raw( $raw, array( 'https' ) );
					if ( '' !== $raw && '' === $value ) {
						$errors[] = sprintf( __( '%s link must start with https://; the previous value was kept.', 'cater-ice-cream-core' ), self::SOCIAL[ $key ] );
						continue 2;
					}
					break;
				default:
					$value = mb_substr( sanitize_text_field( $raw ), 0, 200 );
			}
			if ( '' === $value && ! str_starts_with( $key, 'social_' ) && 'business_email' !== $key && 'business_phone' !== $key ) {
				$value = $defaults[ $key ];
			}
			$clean[ $key ] = $value;
		}
		return $clean;
	}

	/** Site path or https URL; null when not acceptable. */
	public static function sanitize_link( string $raw ): ?string {
		if ( '' === $raw ) {
			return '';
		}
		if ( preg_match( '#^/[A-Za-z0-9/_\-\#?=&.%]*$#', $raw ) && ! str_starts_with( $raw, '//' ) ) {
			return $raw;
		}
		$url = esc_url_raw( $raw, array( 'https' ) );
		return '' !== $url ? $url : null;
	}
}
