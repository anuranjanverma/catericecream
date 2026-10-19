<?php
/**
 * Site Health check confirming the standalone catalogue guard is loaded and active.
 *
 * @package CIC
 */

namespace CIC\Admin;

defined( 'ABSPATH' ) || exit;

final class SiteHealth {

	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
	}

	/**
	 * @param array<string, mixed> $tests Registered tests.
	 * @return array<string, mixed>
	 */
	public function add_tests( array $tests ): array {
		$tests['direct']['cic_catalog_guard'] = array(
			'label' => __( 'Cater Ice Cream catalogue guard', 'cater-ice-cream-core' ),
			'test'  => array( $this, 'test_catalog_guard' ),
		);
		return $tests;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function test_catalog_guard(): array {
		$loaded = defined( 'CIC_CATALOG_GUARD_VERSION' );
		$active = $loaded && defined( 'CIC_CATALOG_MODE_ACTIVE' ) && CIC_CATALOG_MODE_ACTIVE;

		if ( $active ) {
			$status      = 'good';
			$label       = __( 'Catalogue mode is active: public purchasing is disabled', 'cater-ice-cream-core' );
			$description = __( 'The cic-catalog-guard must-use plugin is loaded and blocks cart, checkout and purchase requests on the server.', 'cater-ice-cream-core' );
		} elseif ( $loaded ) {
			$status      = 'recommended';
			$label       = __( 'Sales mode is enabled in wp-config.php', 'cater-ice-cream-core' );
			$description = __( 'CIC_COMMERCE_MODE is set to sales. Online selling requires a separately tested release.', 'cater-ice-cream-core' );
		} else {
			$status      = 'critical';
			$label       = __( 'The catalogue guard must-use plugin is missing', 'cater-ice-cream-core' );
			$description = __( 'wp-content/mu-plugins/cic-catalog-guard.php is not loaded, so public purchase paths may be open.', 'cater-ice-cream-core' );
		}

		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Cater Ice Cream', 'cater-ice-cream-core' ),
				'color' => 'critical' === $status ? 'red' : 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'test'        => 'cic_catalog_guard',
		);
	}
}
