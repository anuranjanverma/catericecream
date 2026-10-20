<?php
/**
 * "Explore nearby areas" strip: other published cities in the same county (nearest first) and a
 * link to all service areas. Never invents areas.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
echo cic_theme_section_open( 'cic-city', $args['section'], 'cic-city-nearby' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home cic-city-nearby__inner">
		<?php if ( $cic_vm['nearby'] ) : ?>
			<p class="cic-city-nearby__list">
				<strong><?php esc_html_e( 'Explore nearby areas:', 'cater-ice-cream' ); ?></strong>
				<?php foreach ( $cic_vm['nearby'] as $cic_i => $cic_city ) : ?>
					<?php echo 0 < $cic_i ? '<span aria-hidden="true">·</span>' : ''; ?>
					<a href="<?php echo esc_url( $cic_city['url'] ); ?>"><?php echo esc_html( $cic_city['name'] ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
		<a class="cic-text-link cic-city-nearby__all" href="<?php echo esc_url( $cic_vm['hub_url'] ); ?>"><?php esc_html_e( 'All service areas', 'cater-ice-cream' ); ?> <span aria-hidden="true">→</span></a>
	</div>
</section>
