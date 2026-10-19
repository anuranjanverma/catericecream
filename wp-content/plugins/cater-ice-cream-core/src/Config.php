<?php
/**
 * Central configuration defaults. Saved values (from later admin screens) are merged over
 * these defaults at read time; reading never writes to the database.
 *
 * @package CIC
 */

namespace CIC;

defined( 'ABSPATH' ) || exit;

final class Config {

	public const OPTION_SETTINGS          = 'cic_core_settings';
	public const OPTION_SCHEMA_VERSION    = 'cic_core_schema_version';
	public const OPTION_ACTIVATED_VERSION = 'cic_core_activated_version';

	/**
	 * Spec-confirmed starting values (BUILD_SPECIFICATION §1, §13). All are admin-editable later.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'business_name'      => 'Cater Ice Cream',
			'legal_entity'       => 'Frosty Yummy LLC',
			'business_contact'   => 'Yan Kriv',
			'business_phone'     => '(818) 726-9700',
			'business_email'     => 'ceo.frostyyummy@gmail.com',
			'business_location'  => 'Los Angeles, California',
			// Notification recipient, not a sender or login identity. Local/staging mail is captured.
			'operator_recipient' => 'anuranjan.verma25@gmail.com',
			'event_timezone'     => 'America/Los_Angeles',
			// Public CTA labels and recurring text (Phase 02). Edited on Header, Footer & Contact.
			'quote_label'        => 'Get a Quote',
			'quote_url'          => '/get-a-quote/',
			'call_label'         => 'Call',
			'availability_note'  => 'Your date and service availability will be confirmed with your quote.',
			'website_label'      => 'catericecream.com',
			// Social profiles stay empty until real URLs are supplied; empty ones are never displayed.
			'social_instagram'   => '',
			'social_facebook'    => '',
			'social_tiktok'      => '',
			'social_yelp'        => '',
		);
	}

	/**
	 * Defaults merged with saved values.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION_SETTINGS, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? array_intersect_key( $saved, self::defaults() ) : array() );
	}

	/**
	 * @return mixed
	 */
	public static function get( string $key ) {
		$saved    = get_option( self::OPTION_SETTINGS, array() );
		$settings = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		return $settings[ $key ] ?? null;
	}
}
