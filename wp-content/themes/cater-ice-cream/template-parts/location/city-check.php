<?php
/**
 * "Let's check your event location." ZIP check (the venue city travels with the request so shared
 * ZIPs can be disambiguated) plus what we confirm; an illustrative location preview on the right.
 * The preview is decorative (labelled), not a boundary or coverage map.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
$cic_c  = $cic_vm['check'];
if ( '' === $cic_c['heading'] ) {
	return;
}
echo cic_theme_section_open( 'cic-city', $args['section'], 'cic-section cic-city-check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home cic-city-check__grid">
		<div class="cic-city-check__text" id="check-your-area">
			<h2 class="cic-city-check__title"><?php echo $cic_c['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php if ( '' !== $cic_c['intro'] ) : ?>
				<p><?php echo esc_html( $cic_c['intro'] ); ?></p>
			<?php endif; ?>
			<?php
			get_template_part(
				'template-parts/components/area-check',
				null,
				array(
					'id'          => 'cic-city-zip',
					/* translators: %s: city name. */
					'placeholder' => sprintf( __( 'Enter your %s event ZIP code', 'cater-ice-cream' ), $cic_vm['city']['name'] ),
					'label'       => __( 'Event ZIP code', 'cater-ice-cream' ),
					'button'      => __( 'Check my location', 'cater-ice-cream' ),
					'endpoint'    => $cic_c['endpoint'],
					'city'        => $cic_vm['city']['key'],
				)
			);
			?>
			<ul class="cic-city-check__needs">
				<li><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Venue address & access', 'cater-ice-cream' ); ?></li>
				<li><?php echo cic_theme_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Your preferred date', 'cater-ice-cream' ); ?></li>
				<li><?php echo cic_theme_icon( 'people' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Estimated guest count', 'cater-ice-cream' ); ?></li>
			</ul>
		</div>
		<figure class="cic-city-map">
			<svg viewBox="0 0 320 140" aria-hidden="true" focusable="false">
				<rect class="cic-city-map__ground" width="320" height="140" rx="14"/>
				<path class="cic-city-map__park" d="M196 92c14-6 34-4 46 6s12 30-6 34-46 2-52-14 0-20 12-26z"/>
				<path class="cic-city-map__park" d="M30 18c12-4 26 0 30 10s-6 20-20 20-22-6-22-16 2-10 12-14z"/>
				<g class="cic-city-map__roads">
					<path d="M0 40h320M0 86h320M0 118h320M60 0v140M128 0v140M210 0v140M272 0v140"/>
					<path d="M0 132L120 0M150 140L300 0" />
				</g>
				<g class="cic-city-map__streets">
					<path d="M0 20h320M0 62h320M0 102h320M30 0v140M94 0v140M168 0v140M240 0v140M300 0v140"/>
				</g>
				<path class="cic-city-map__pin" d="M168 72c-9-11-14-17-14-23a14 14 0 0 1 28 0c0 6-5 12-14 23z"/>
				<circle class="cic-city-map__pin-dot" cx="168" cy="49" r="5"/>
			</svg>
			<span class="cic-city-map__label"><?php echo esc_html( $cic_vm['city']['name'] ); ?></span>
			<figcaption class="cic-caption"><?php esc_html_e( 'Illustrative location preview', 'cater-ice-cream' ); ?></figcaption>
		</figure>
	</div>
</section>
