<?php
/**
 * Quote starter strip (Birthday). The occasion is fixed; location, date and guests are validated,
 * non-personal choices carried to the dedicated quote route by the context helper (context.js),
 * which also blocks a confirmed outside-area location. The full CF7 form is Phase 06.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
$cic_s  = $cic_vm['starter'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-starter-wrap' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container">
		<form class="cic-starter" method="get" action="<?php echo esc_url( cic_theme_contact()['quote_url'] ); ?>" data-cic-quote-form novalidate>
			<input type="hidden" name="event" value="<?php echo esc_attr( $cic_vm['key'] ); ?>" data-cic-context="event" data-cic-fixed>
			<div class="cic-starter__field">
				<span class="cic-icon-badge cic-icon-badge--plain"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<div><label class="cic-label" for="cic-starter-location"><?php echo esc_html( $cic_s['location'] ); ?></label><input class="cic-input" id="cic-starter-location" name="location" type="text" maxlength="60" autocomplete="off" placeholder="<?php esc_attr_e( 'City or ZIP code', 'cater-ice-cream' ); ?>" data-cic-context="location"></div>
			</div>
			<div class="cic-starter__field">
				<span class="cic-icon-badge cic-icon-badge--plain"><?php echo cic_theme_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<div><label class="cic-label" for="cic-starter-date"><?php echo esc_html( $cic_s['date'] ); ?></label><input class="cic-input" id="cic-starter-date" name="date" type="date" data-cic-context="date"></div>
			</div>
			<div class="cic-starter__field">
				<span class="cic-icon-badge cic-icon-badge--plain"><?php echo cic_theme_icon( 'people' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<div><label class="cic-label" for="cic-starter-guests"><?php echo esc_html( $cic_s['guests'] ); ?></label><input class="cic-input" id="cic-starter-guests" name="guests" type="number" min="1" max="10000" inputmode="numeric" placeholder="<?php esc_attr_e( 'Approximate number', 'cater-ice-cream' ); ?>" data-cic-context="guests"></div>
			</div>
			<button type="submit" class="cic-button"><?php echo esc_html( $cic_s['cta'] ); ?> <span aria-hidden="true">→</span></button>
			<p class="cic-search__error" id="cic-starter-error" role="alert" hidden></p>
			<p class="cic-caption cic-starter__note"><?php echo esc_html( $cic_s['note'] ); ?></p>
		</form>
	</div>
</section>
