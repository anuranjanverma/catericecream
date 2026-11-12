<?php
/**
 * Plugin bootstrap: wires services. Each later phase registers its own service here
 * (Content, Design, Locations, Quotes, SEO, Integrations) instead of growing this file.
 *
 * @package CIC
 */

namespace CIC;

use CIC\Admin\Dependencies;
use CIC\Admin\SiteHealth;
use CIC\Database\Migrations;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		return self::$instance ??= new self();
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Dependencies() )->register();
		( new SiteHealth() )->register();
		( new Migrations() )->register();

		( new Design\Service() )->register();
		( new Content\Service() )->register();
		( new Locations\Service() )->register();
		( new Seo\Service() )->register();
		( new Enquiries\Service() )->register();
		( new Sheets\Service() )->register();
		( new Analytics\Service() )->register();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'cater-ice-cream-core', false, dirname( plugin_basename( CIC_CORE_FILE ) ) . '/languages' );
	}

	/**
	 * The core plugin does not touch order storage or checkout, so it is compatible with HPOS
	 * and the block cart/checkout (which stay disabled by the catalogue guard).
	 */
	public function declare_woocommerce_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CIC_CORE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CIC_CORE_FILE, true );
		}
	}

	public static function activate(): void {
		( new Migrations() )->maybe_migrate();
		update_option( Config::OPTION_ACTIVATED_VERSION, CIC_CORE_VERSION, false );
	}

	/**
	 * Deactivation keeps all data and settings; only scheduled work is removed.
	 */
	public static function deactivate(): void {
		Sheets\Service::unschedule();
	}
}
