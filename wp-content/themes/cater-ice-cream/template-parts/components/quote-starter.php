<?php
/**
 * Shared quote starter for event and treat pages. Collects only validated, non-personal choices
 * (location, date, guests or event type) and carries them, with the page's fixed occasion or
 * treat, to the dedicated quote route through the context helper (assets/js/context.js). It is not
 * a second form system: nothing is submitted or stored here and no success is shown. The full
 * CF7 quote flow arrives in Phase 06. A confirmed outside-area location blocks continuing.
 *
 * Args: id, fixed (context key => value), fields (location|date|guests|event), labels, events,
 * cta, note, call (bool), class.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_id      = $args['id'];
$cic_fields  = $args['fields'] ?? array( 'location', 'date', 'guests' );
$cic_labels  = array_merge(
	array(
		'location' => __( 'City or ZIP code', 'cater-ice-cream' ),
		'date'     => __( 'Event date', 'cater-ice-cream' ),
		'guests'   => __( 'Estimated guests', 'cater-ice-cream' ),
		'event'    => __( 'Event type', 'cater-ice-cream' ),
	),
	(array) ( $args['labels'] ?? array() )
);
$cic_icons   = array( 'location' => 'pin', 'date' => 'calendar', 'guests' => 'people', 'event' => 'sparkle' );
$cic_contact = cic_theme_contact();
?>
<form class="cic-qs <?php echo esc_attr( $args['class'] ?? '' ); ?>" method="get" action="<?php echo esc_url( $cic_contact['quote_url'] ); ?>" data-cic-quote-form novalidate>
	<?php foreach ( (array) ( $args['fixed'] ?? array() ) as $cic_key => $cic_value ) : ?>
		<input type="hidden" name="<?php echo esc_attr( $cic_key ); ?>" value="<?php echo esc_attr( $cic_value ); ?>" data-cic-context="<?php echo esc_attr( $cic_key ); ?>" data-cic-fixed>
	<?php endforeach; ?>
	<div class="cic-qs__fields cic-qs__fields--<?php echo (int) count( $cic_fields ); ?>">
		<?php foreach ( $cic_fields as $cic_field ) : ?>
			<p class="cic-qs__field cic-qs__field--<?php echo esc_attr( $cic_field ); ?>">
				<label class="cic-label" for="<?php echo esc_attr( $cic_id . '-' . $cic_field ); ?>"><?php echo esc_html( $cic_labels[ $cic_field ] ); ?></label>
				<span class="cic-qs__control">
					<span class="cic-qs__icon"><?php echo cic_theme_icon( $cic_icons[ $cic_field ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<?php if ( 'event' === $cic_field ) : ?>
						<select class="cic-input" id="<?php echo esc_attr( $cic_id . '-event' ); ?>" name="event" data-cic-context="event">
							<option value=""><?php esc_html_e( 'Select occasion', 'cater-ice-cream' ); ?></option>
							<?php foreach ( (array) ( $args['events'] ?? array() ) as $cic_key => $cic_label ) : ?>
								<option value="<?php echo esc_attr( $cic_key ); ?>"><?php echo esc_html( $cic_label ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php elseif ( 'date' === $cic_field ) : ?>
						<input class="cic-input" id="<?php echo esc_attr( $cic_id . '-date' ); ?>" name="date" type="date" data-cic-context="date">
					<?php elseif ( 'guests' === $cic_field ) : ?>
						<input class="cic-input" id="<?php echo esc_attr( $cic_id . '-guests' ); ?>" name="guests" type="number" min="1" max="10000" inputmode="numeric" placeholder="<?php esc_attr_e( 'Approximate number', 'cater-ice-cream' ); ?>" data-cic-context="guests">
					<?php else : ?>
						<input class="cic-input" id="<?php echo esc_attr( $cic_id . '-location' ); ?>" name="location" type="text" maxlength="60" autocomplete="off" placeholder="<?php esc_attr_e( 'City or ZIP code', 'cater-ice-cream' ); ?>" data-cic-context="location">
					<?php endif; ?>
				</span>
			</p>
		<?php endforeach; ?>
	</div>
	<button type="submit" class="cic-button cic-qs__submit"><?php echo esc_html( $args['cta'] ); ?> <span aria-hidden="true">→</span></button>
	<p class="cic-search__error" id="<?php echo esc_attr( $cic_id ); ?>-error" role="alert" hidden></p>
	<?php if ( '' !== ( $args['note'] ?? '' ) ) : ?>
		<p class="cic-caption cic-qs__note"><?php echo esc_html( $args['note'] ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $args['call'] ) && '' !== $cic_contact['phone_href'] ) : ?>
		<p class="cic-qs__call"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><a href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php esc_html_e( 'Prefer to talk?', 'cater-ice-cream' ); ?> <?php echo esc_html( $cic_contact['phone'] ); ?></a></p>
	<?php endif; ?>
</form>
