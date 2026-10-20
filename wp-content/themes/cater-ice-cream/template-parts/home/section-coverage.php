<?php
/**
 * Home service-area block: intro, area check, county directory and an illustrative map.
 *
 * The check and the county lists go through the location repository adapter (POST /cic/v1/coverage,
 * published cities linked to their virtual city pages). The owner's city code is pending; until it
 * is connected the provisional repository answers from reviewed data only (local fixtures are
 * labelled and ignored outside local/development). The map is an illustration, not a boundary.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_c = $args['vm']['coverage'];
echo cic_theme_section_open( 'cic-home', $args['section'], 'cic-section cic-band cic-home-coverage' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home cic-coverage">
		<div class="cic-coverage__text">
			<h2 class="cic-coverage__title"><?php echo $cic_c['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php if ( '' !== $cic_c['intro'] ) : ?>
				<p><?php echo esc_html( $cic_c['intro'] ); ?></p>
			<?php endif; ?>
			<form class="cic-area-check" method="get" action="<?php echo esc_url( cic_theme_contact()['quote_url'] ); ?>" data-cic-area-check data-endpoint="<?php echo esc_url( $cic_c['endpoint'] ); ?>" novalidate>
				<label class="screen-reader-text" for="cic-area-location"><?php esc_html_e( 'City or ZIP code', 'cater-ice-cream' ); ?></label>
				<div class="cic-area-check__field">
					<span class="cic-search__icon"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<input class="cic-input" id="cic-area-location" name="location" type="text" maxlength="60" autocomplete="postal-code" placeholder="<?php echo esc_attr( $cic_c['placeholder'] ); ?>" data-cic-context="location">
				</div>
				<button type="submit" class="cic-button"><?php echo esc_html( $cic_c['button'] ); ?></button>
				<p class="cic-area-check__result" role="status" aria-live="polite" hidden></p>
			</form>
			<?php if ( $cic_c['counties'] ) : ?>
				<ul class="cic-county-list">
					<?php foreach ( $cic_c['counties'] as $cic_county ) : ?>
						<li class="cic-county">
							<?php if ( $cic_county['cities'] ) : ?>
								<details class="cic-county__toggle" data-cic-county>
									<summary><h3 class="cic-county__name"><?php echo esc_html( $cic_county['name'] ); ?></h3></summary>
									<ul class="cic-county__cities">
										<?php foreach ( $cic_county['cities'] as $cic_city ) : ?>
											<li>
												<?php if ( '' !== $cic_city['url'] ) : ?>
													<a href="<?php echo esc_url( $cic_city['url'] ); ?>"><?php echo esc_html( $cic_city['name'] ); ?></a>
												<?php else : ?>
													<?php echo esc_html( $cic_city['name'] ); ?>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</details>
							<?php else : ?>
								<h3 class="cic-county__name"><?php echo esc_html( $cic_county['name'] ); ?></h3>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( '' !== $cic_c['note'] ) : ?>
				<p class="cic-caption"><?php echo esc_html( $cic_c['note'] ); ?></p>
			<?php endif; ?>
		</div>
		<figure class="cic-coverage__map">
			<svg viewBox="0 0 300 300" role="img" aria-labelledby="cic-map-caption">
				<path class="cic-map__state" d="M11.2 6.0 L128.8 6.0 L128.8 96.0 L280.0 216.0 L277.2 237.0 L282.8 276.0 L277.2 285.0 L210.0 291.0 L204.4 273.0 L184.8 255.0 L170.8 246.0 L151.2 241.5 L137.2 234.0 L112.0 229.5 L112.0 210.0 L92.4 195.0 L75.6 168.0 L61.6 150.0 L58.8 132.0 L44.8 117.0 L25.2 96.0 L22.4 72.0 L5.6 54.0 L14.0 36.0 Z"/>
				<path class="cic-map__region" d="M112 229.5 L137.2 234 L151.2 241.5 L170.8 246 L184.8 255 L196 252 L190 236 L160 226 L128 220 Z"/>
				<g class="cic-map__pins">
					<circle cx="137.2" cy="233.4" r="4.5"/><circle cx="150.4" cy="237.6" r="4.5"/><circle cx="178.1" cy="244.5" r="4.5"/>
				</g>
				<line class="cic-map__leader" x1="160" y1="232" x2="205" y2="120"/>
				<g class="cic-map__inset">
					<circle cx="230" cy="95" r="62"/>
					<text x="186" y="78">Santa Barbara</text>
					<text x="214" y="98">Ventura</text>
					<text x="226" y="124">Los Angeles</text>
					<circle class="cic-map__pin" cx="198" cy="86" r="5"/><circle class="cic-map__pin" cx="220" cy="106" r="5"/><circle class="cic-map__pin" cx="244" cy="132" r="5"/>
				</g>
			</svg>
			<?php if ( '' !== $cic_c['caption'] ) : ?>
				<figcaption id="cic-map-caption" class="cic-caption"><?php echo esc_html( $cic_c['caption'] ); ?></figcaption>
			<?php endif; ?>
		</figure>
	</div>
</section>
