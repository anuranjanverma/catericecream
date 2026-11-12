<?php
/**
 * Google Analytics 4 settings (Cater Ice Cream → Analytics, administrators only; Settings API with
 * nonce + sanitisation). Stored per environment in the database; the Measurement ID is never
 * hardcoded. The live value arrives through the reviewed migration
 * dev/tools/content/analytics-20261025.php, which only adds the option when it is missing.
 *
 * Saving "enabled" never makes a local or staging site send data: Service::status() also requires
 * the production environment and an approved production host, and skips administrators.
 *
 * @package CIC
 */

namespace CIC\Analytics;

use CIC\Design\Admin\DesignPage;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION     = 'cic_analytics';
	public const PAGE       = 'cic-analytics';
	public const CAPABILITY = 'manage_options';

	/** GA4 web stream IDs: "G-" plus letters and digits. */
	public const ID_PATTERN = '/^G-[A-Z0-9]{4,16}$/';

	/**
	 * Plugins that add a Google tag themselves (directory => name). One of them active next to this
	 * integration risks a second install of the property; the settings page and the migration warn.
	 */
	private const TAG_PLUGINS = array(
		'google-site-kit'                                => 'Site Kit by Google',
		'duracelltomi-google-tag-manager'                => 'GTM4WP (Google Tag Manager for WordPress)',
		'google-analytics-for-wordpress'                 => 'MonsterInsights',
		'google-analytics-premium'                       => 'MonsterInsights Pro',
		'google-analytics-dashboard-for-wp'              => 'ExactMetrics',
		'ga-google-analytics'                            => 'GA Google Analytics',
		'woocommerce-google-analytics-integration'       => 'Google Analytics for WooCommerce',
		'google-listings-and-ads'                        => 'Google for WooCommerce',
		'insert-headers-and-footers'                     => 'WPCode (header scripts)',
		'header-footer-code-manager'                     => 'Header Footer Code Manager',
		'head-footer-code'                               => 'Head & Footer Code',
		'wp-headers-and-footers'                         => 'Insert Headers And Footers',
		'gtm-kit'                                        => 'GTM Kit',
	);

	/** @return array{enabled: int, measurement_id: string} */
	public static function defaults(): array {
		return array(
			'enabled'        => 0,
			'measurement_id' => '',
		);
	}

	/** @return array{enabled: int, measurement_id: string} */
	public static function get(): array {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/** The saved Measurement ID when it is valid, otherwise ''. */
	public static function measurement_id(): string {
		$id = (string) self::get()['measurement_id'];
		return preg_match( self::ID_PATTERN, $id ) ? $id : '';
	}

	/**
	 * Active plugins that add their own Google tag (names), for the duplicate-install warning.
	 *
	 * @return string[]
	 */
	public static function other_tags(): array {
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$found = array();
		foreach ( $active as $basename ) {
			$dir = strtok( (string) $basename, '/' );
			if ( isset( self::TAG_PLUGINS[ $dir ] ) ) {
				$found[] = self::TAG_PLUGINS[ $dir ];
			}
		}
		return array_values( array_unique( $found ) );
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		// After the "Cater Ice Cream" menu exists (Design\Service, priority 10).
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
	}

	public function register_setting(): void {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	public function admin_menu(): void {
		add_submenu_page( DesignPage::SLUG, __( 'Analytics', 'cater-ice-cream-core' ), __( 'Analytics', 'cater-ice-cream-core' ), self::CAPABILITY, self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * @param mixed $input Raw settings.
	 * @return array{enabled: int, measurement_id: string}
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$old   = self::get();
		$out   = array(
			'enabled'        => empty( $input['enabled'] ) ? 0 : 1,
			'measurement_id' => (string) $old['measurement_id'],
		);
		// Validated against the strict pattern as typed (stripping tags first could turn junk into '').
		$id = strtoupper( trim( (string) ( $input['measurement_id'] ?? '' ) ) );
		if ( '' === $id || preg_match( self::ID_PATTERN, $id ) ) {
			$out['measurement_id'] = $id;
		} else {
			add_settings_error( self::OPTION, 'cic_measurement_id', __( 'Measurement ID: enter a GA4 ID such as G-XXXXXXXXXX. The previous value was kept.', 'cater-ice-cream-core' ) );
		}
		if ( $out['enabled'] && '' === $out['measurement_id'] ) {
			add_settings_error( self::OPTION, 'cic_measurement_missing', __( 'Analytics stays off until a Measurement ID is saved.', 'cater-ice-cream-core' ), 'warning' );
		}
		return $out;
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change analytics settings.', 'cater-ice-cream-core' ), '', array( 'response' => 403 ) );
		}
		$s      = self::get();
		$name   = static fn( string $k ): string => self::OPTION . '[' . $k . ']';
		$status = Service::status( false );
		$others = self::other_tags();

		echo '<div class="wrap"><h1>' . esc_html__( 'Analytics', 'cater-ice-cream-core' ) . '</h1>';
		settings_errors( self::OPTION );
		if ( $others ) {
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %s: plugin names. */
					__( 'These active plugins can add their own Google tag: %s. Make sure this Google Analytics property is installed only once: either leave it out of those plugins or switch it off here.', 'cater-ice-cream-core' ),
					implode( ', ', $others )
				)
			) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Adds the Google Analytics 4 tag (gtag.js) to the website once, in the page head. It only runs on the live site (catericecream.com and www.catericecream.com in the production environment) and never for logged-in administrators. Local, .test and staging sites never send data. No names, emails, phone numbers, addresses, form contents or saved-progress keys are sent, and no form events are added here.', 'cater-ice-cream-core' ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::PAGE );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Google Analytics', 'cater-ice-cream-core' ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name( 'enabled' ) ) . '" value="1"' . checked( ! empty( $s['enabled'] ), true, false ) . '> ' . esc_html__( 'Enable Google Analytics on the live site', 'cater-ice-cream-core' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="cic-ga-id">' . esc_html__( 'Measurement ID', 'cater-ice-cream-core' ) . '</label></th><td><input class="regular-text code" type="text" id="cic-ga-id" name="' . esc_attr( $name( 'measurement_id' ) ) . '" value="' . esc_attr( (string) $s['measurement_id'] ) . '" placeholder="G-XXXXXXXXXX" autocomplete="off" spellcheck="false"><p class="description">' . esc_html__( 'From Google Analytics → Admin → Data streams → Web stream details.', 'cater-ice-cream-core' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Status on this site', 'cater-ice-cream-core' ) . '</th><td><p>' . esc_html( $status['label'] ) . '</p><p class="description">' . esc_html(
			sprintf(
				/* translators: 1: environment type, 2: site address host. */
				__( 'Environment: %1$s. Site address: %2$s. Your own visits are never counted while you are logged in as an administrator.', 'cater-ice-cream-core' ),
				wp_get_environment_type(),
				(string) wp_parse_url( home_url(), PHP_URL_HOST )
			)
		) . '</p></td></tr>';
		echo '</table>';
		submit_button();
		echo '</form></div>';
	}
}
