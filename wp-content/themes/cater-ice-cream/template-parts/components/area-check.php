<?php
/**
 * Area check: city or ZIP → POST /cic/v1/coverage (shared coverage rules, never a date
 * reservation). Typing offers limited, debounced autocomplete from GET /cic/v1/locations.
 * Results: covered (link to the city page / quote), outside_area (the exact error; quote blocked),
 * manual_review or unknown (asks for ZIP and venue city), invalid (correct the input).
 *
 * Args: id, placeholder, button, endpoint, hint, label, city (city key for shared ZIPs), class.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_id = $args['id'] ?? 'cic-area';
?>
<form class="cic-area-check <?php echo esc_attr( $args['class'] ?? '' ); ?>" method="get" action="<?php echo esc_url( cic_theme_contact()['quote_url'] ); ?>" data-cic-area-check data-endpoint="<?php echo esc_url( $args['endpoint'] ); ?>"<?php echo '' !== ( $args['city'] ?? '' ) ? ' data-city="' . esc_attr( $args['city'] ) . '"' : ''; ?> novalidate>
	<label class="screen-reader-text" for="<?php echo esc_attr( $cic_id ); ?>"><?php echo esc_html( $args['label'] ?? __( 'City or ZIP code', 'cater-ice-cream' ) ); ?></label>
	<div class="cic-area-check__field">
		<span class="cic-search__icon"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<input class="cic-input" id="<?php echo esc_attr( $cic_id ); ?>" name="location" type="text" maxlength="60" autocomplete="off" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" data-cic-context="location" data-cic-autocomplete>
	</div>
	<button type="submit" class="cic-button"><?php echo esc_html( $args['button'] ); ?> <span aria-hidden="true">→</span></button>
	<p class="cic-area-check__result" role="status" aria-live="polite" hidden></p>
	<?php if ( '' !== ( $args['hint'] ?? '' ) ) : ?>
		<p class="cic-area-check__hint"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $args['hint'] ); ?></p>
	<?php endif; ?>
</form>
