<?php
/**
 * Dependency checks with admin notices. Missing dependencies never cause fatal errors;
 * features that need them stay inactive until they are installed.
 *
 * @package CIC
 */

namespace CIC\Admin;

defined( 'ABSPATH' ) || exit;

final class Dependencies {

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Free dependencies required by the agreed architecture, and the phase that first needs each.
	 *
	 * @return array<string, array{label: string, phase: string, active: bool}>
	 */
	public static function status(): array {
		return array(
			'woocommerce'    => array(
				'label'  => 'WooCommerce',
				'phase'  => '01',
				'active' => class_exists( 'WooCommerce' ),
			),
			'acf'            => array(
				'label'  => 'Advanced Custom Fields (free)',
				'phase'  => '03',
				'active' => class_exists( 'ACF' ),
			),
			'contact-form-7' => array(
				'label'  => 'Contact Form 7',
				'phase'  => '06',
				'active' => defined( 'WPCF7_VERSION' ),
			),
			'flamingo'       => array(
				'label'  => 'Flamingo',
				'phase'  => '06',
				'active' => defined( 'FLAMINGO_VERSION' ),
			),
			'catalog-guard'  => array(
				'label'  => 'cic-catalog-guard must-use plugin',
				'phase'  => '01',
				'active' => defined( 'CIC_CATALOG_GUARD_VERSION' ),
			),
		);
	}

	public static function is_active( string $key ): bool {
		$status = self::status();
		return ! empty( $status[ $key ]['active'] );
	}

	/**
	 * Shown on the Dashboard and Plugins screens to users who can manage plugins.
	 */
	public function render_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! current_user_can( 'activate_plugins' ) || ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}
		$missing = array_filter( self::status(), static fn( $dep ) => ! $dep['active'] );
		if ( ! $missing ) {
			return;
		}
		$items = '';
		foreach ( $missing as $dep ) {
			/* translators: 1: dependency name, 2: build phase number. */
			$items .= '<li>' . esc_html( sprintf( __( '%1$s (needed from phase %2$s)', 'cater-ice-cream-core' ), $dep['label'], $dep['phase'] ) ) . '</li>';
		}
		$required_now = array_filter( $missing, static fn( $dep ) => '01' === $dep['phase'] );
		$class        = $required_now ? 'notice-error' : 'notice-info';
		printf(
			'<div class="notice %1$s"><p><strong>%2$s</strong> %3$s</p><ul style="list-style:disc;padding-left:1.5em">%4$s</ul></div>',
			esc_attr( $class ),
			esc_html__( 'Cater Ice Cream Core:', 'cater-ice-cream-core' ),
			esc_html__( 'some dependencies are not active. Related features stay disabled until they are.', 'cater-ice-cream-core' ),
			$items // Escaped per item above.
		);
	}
}
