<?php
/**
 * Wires the Design System and shared header/footer settings, the admin menu and cache invalidation.
 *
 * @package CIC
 */

namespace CIC\Design;

use CIC\Config;
use CIC\Design\Admin\DesignPage;
use CIC\Site\Admin\LayoutPage;
use CIC\Site\Layout;

defined( 'ABSPATH' ) || exit;

final class Service {

	private DesignPage $design_page;
	private LayoutPage $layout_page;

	public function register(): void {
		( new ThemeJson() )->register();
		( new Stylesheet() )->register();
		( new Overrides() )->register();
		( new Preview() )->register();

		$this->design_page = new DesignPage();
		$this->design_page->register();
		$this->layout_page = new LayoutPage();
		$this->layout_page->register();

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_notices', array( $this, 'override_errors_notice' ) );

		foreach ( array( Settings::OPTION, Layout::OPTION, Config::OPTION_SETTINGS ) as $option ) {
			add_action( 'update_option_' . $option, array( $this, 'invalidate' ) );
			add_action( 'add_option_' . $option, array( $this, 'invalidate' ) );
			add_action( 'delete_option_' . $option, array( $this, 'invalidate' ) );
		}
		add_action( 'cic_design_page_overrides_saved', array( $this, 'invalidate_post' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cic', Cli::class );
		}
	}

	public function admin_menu(): void {
		add_menu_page(
			__( 'Cater Ice Cream', 'cater-ice-cream-core' ),
			__( 'Cater Ice Cream', 'cater-ice-cream-core' ),
			DesignPage::CAPABILITY,
			DesignPage::SLUG,
			array( $this->design_page, 'render' ),
			'dashicons-art',
			59
		);
		add_submenu_page( DesignPage::SLUG, __( 'Design System', 'cater-ice-cream-core' ), __( 'Design System', 'cater-ice-cream-core' ), DesignPage::CAPABILITY, DesignPage::SLUG, array( $this->design_page, 'render' ) );
		add_submenu_page( DesignPage::SLUG, __( 'Header, Footer & Contact', 'cater-ice-cream-core' ), __( 'Header, Footer & Contact', 'cater-ice-cream-core' ), LayoutPage::CAPABILITY, LayoutPage::SLUG, array( $this->layout_page, 'render' ) );
		add_submenu_page( DesignPage::SLUG, __( 'Style preview', 'cater-ice-cream-core' ), __( 'Style preview ↗', 'cater-ice-cream-core' ), 'edit_theme_options', Preview::url() );
	}

	/**
	 * A saved design, layout or contact setting must reach every template: regenerate the token
	 * stylesheet, clear theme.json caches and ask any installed page cache to purge.
	 */
	public function invalidate(): void {
		static $bumped = false;
		Settings::flush_cache();
		Stylesheet::reset();
		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}
		if ( ! $bumped ) {
			$bumped = true;
			update_option( Settings::VERSION, Settings::version() + 1, true );
		}
		self::purge_page_caches();
		do_action( 'cic_design_changed' );
	}

	public function invalidate_post( int $post_id ): void {
		clean_post_cache( $post_id );
		self::purge_page_caches( $post_id );
	}

	/**
	 * Purges known page/CDN cache layers when present. Hosting is not decided yet, so each call is
	 * guarded; other layers can hook cic_purge_page_caches.
	 */
	public static function purge_page_caches( int $post_id = 0 ): void {
		if ( $post_id ) {
			do_action( 'litespeed_purge_post', $post_id );
		} else {
			do_action( 'litespeed_purge_all' );
		}
		if ( function_exists( 'rocket_clean_domain' ) && ! $post_id ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) && ! $post_id ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) && ! $post_id ) {
			wp_cache_clear_cache();
		}
		if ( class_exists( '\SiteGround_Optimizer\Supercacher\Supercacher' ) && ! $post_id ) {
			\SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();
		}
		do_action( 'cic_purge_page_caches', $post_id );
	}

	public function override_errors_notice(): void {
		$errors = get_transient( 'cic_override_errors_' . get_current_user_id() );
		if ( ! is_array( $errors ) || ! $errors ) {
			return;
		}
		delete_transient( 'cic_override_errors_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Some design overrides were not saved:', 'cater-ice-cream-core' ) . '</p><ul class="ul-disc">';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';
	}
}
