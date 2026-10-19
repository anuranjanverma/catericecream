<?php
/**
 * Quote starter strip. The occasion is already known, so it is passed as a hidden value. Posted
 * (not in the URL) to the shared quote page, which is built in Phase 06.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
$cic_s  = $cic_vm['starter'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-starter-wrap' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container">
		<form class="cic-starter" method="post" action="<?php echo esc_url( cic_theme_contact()['quote_url'] ); ?>">
			<input type="hidden" name="event" value="<?php echo esc_attr( $cic_vm['key'] ); ?>">
			<div class="cic-starter__field">
				<span class="cic-icon-badge cic-icon-badge--plain"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<div><label class="cic-label" for="cic-starter-location"><?php echo esc_html( $cic_s['location'] ); ?></label><input class="cic-input" id="cic-starter-location" name="location" type="text" autocomplete="postal-code" placeholder="<?php esc_attr_e( 'City or ZIP code', 'cater-ice-cream' ); ?>"></div>
			</div>
			<div class="cic-starter__field">
				<span class="cic-icon-badge cic-icon-badge--plain"><?php echo cic_theme_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<div><label class="cic-label" for="cic-starter-date"><?php echo esc_html( $cic_s['date'] ); ?></label><input class="cic-input" id="cic-starter-date" name="event_date" type="date"></div>
			</div>
			<div class="cic-starter__field">
				<span class="cic-icon-badge cic-icon-badge--plain"><?php echo cic_theme_icon( 'people' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<div><label class="cic-label" for="cic-starter-guests"><?php echo esc_html( $cic_s['guests'] ); ?></label><input class="cic-input" id="cic-starter-guests" name="guests" type="number" min="1" inputmode="numeric" placeholder="<?php esc_attr_e( 'Approximate number', 'cater-ice-cream' ); ?>"></div>
			</div>
			<button type="submit" class="cic-button"><?php echo esc_html( $cic_s['cta'] ); ?> <span aria-hidden="true">→</span></button>
			<p class="cic-caption cic-starter__note"><?php echo esc_html( $cic_s['note'] ); ?></p>
		</form>
	</div>
</section>
