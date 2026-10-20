<?php
/**
 * Hero search / quote starter: Location, Event type, Date → the dedicated quote route.
 * GET with validated, non-personal context only (location key or ZIP, event key, date). The
 * browser helper (context.js) validates, keeps explicit selections in sessionStorage and prefills.
 * Selecting here does not reserve a date or confirm availability.
 *
 * Args: search (labels, events, action, cta, note, optional city_key: a fixed city that a typed
 * ZIP refines), id (unique prefix).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_s  = $args['search'];
$cic_id = $args['id'] ?? 'cic-search';
?>
<form class="cic-search" method="get" action="<?php echo esc_url( $cic_s['action'] ); ?>" data-cic-quote-form novalidate>
	<?php if ( '' !== ( $cic_s['city_key'] ?? '' ) ) : ?>
		<input type="hidden" name="location" value="<?php echo esc_attr( $cic_s['city_key'] ); ?>" data-cic-context="location" data-cic-fixed>
	<?php endif; ?>
	<div class="cic-search__field">
		<span class="cic-search__icon"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<label class="cic-search__label" for="<?php echo esc_attr( $cic_id ); ?>-location"><?php echo esc_html( $cic_s['location_label'] ); ?></label>
		<input class="cic-search__input" id="<?php echo esc_attr( $cic_id ); ?>-location" name="location" type="text" inputmode="text" autocomplete="postal-code" maxlength="60" placeholder="<?php echo esc_attr( $cic_s['location_ph'] ); ?>" aria-describedby="<?php echo esc_attr( $cic_id ); ?>-error" data-cic-context="location">
	</div>
	<div class="cic-search__field">
		<span class="cic-search__icon"><?php echo cic_theme_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<label class="cic-search__label" for="<?php echo esc_attr( $cic_id ); ?>-event"><?php echo esc_html( $cic_s['event_label'] ); ?></label>
		<select class="cic-search__input cic-search__select" id="<?php echo esc_attr( $cic_id ); ?>-event" name="event" data-cic-context="event">
			<option value=""><?php echo esc_html( $cic_s['event_ph'] ); ?></option>
			<?php foreach ( $cic_s['events'] as $cic_key => $cic_label ) : ?>
				<option value="<?php echo esc_attr( $cic_key ); ?>"><?php echo esc_html( $cic_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="cic-search__field">
		<span class="cic-search__icon"><?php echo cic_theme_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<label class="cic-search__label" for="<?php echo esc_attr( $cic_id ); ?>-date"><?php echo esc_html( $cic_s['date_label'] ); ?></label>
		<input class="cic-search__input" id="<?php echo esc_attr( $cic_id ); ?>-date" name="date" type="date" data-cic-context="date">
	</div>
	<button type="submit" class="cic-button cic-search__submit"><?php echo esc_html( $cic_s['cta'] ); ?> <span aria-hidden="true">→</span></button>
	<p class="cic-search__error" id="<?php echo esc_attr( $cic_id ); ?>-error" role="alert" hidden></p>
	<?php if ( '' !== $cic_s['note'] ) : ?>
		<p class="cic-caption cic-search__note"><?php echo esc_html( $cic_s['note'] ); ?></p>
	<?php endif; ?>
</form>
