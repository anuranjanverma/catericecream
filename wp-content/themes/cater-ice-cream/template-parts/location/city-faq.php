<?php
/**
 * Planning FAQ (left) and the city quote panel (right). Until the shared CF7 quote form arrives
 * (Phase 06) the panel collects only non-personal choices (event type, date, guests, event ZIP;
 * venue city fixed to this city) and carries them to the quote route. No names, emails, phones or
 * free text are collected or stored here.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
$cic_f  = $cic_vm['faq'];
$cic_q  = $cic_vm['quote'];
$cic_id = 'cic-city-quote';
echo cic_theme_section_open( 'cic-city', $args['section'], 'cic-section cic-city-faq' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home cic-city-faq__grid">
		<?php if ( $cic_f['items'] ) : ?>
			<div class="cic-city-faq__list">
				<h2 class="cic-city-faq__title"><?php echo $cic_f['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<?php get_template_part( 'template-parts/event/faq-list', null, array( 'items' => $cic_f['items'] ) ); ?>
			</div>
		<?php endif; ?>
		<div class="cic-card cic-city-quote">
			<h2 class="cic-city-quote__title"><?php echo $cic_q['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<form class="cic-city-quote__form" method="get" action="<?php echo esc_url( $cic_q['action'] ); ?>" data-cic-quote-form novalidate>
				<input type="hidden" name="location" value="<?php echo esc_attr( $cic_vm['city']['key'] ); ?>" data-cic-context="location" data-cic-fixed>
				<p class="cic-field">
					<label for="<?php echo esc_attr( $cic_id ); ?>-event"><?php esc_html_e( 'Event type', 'cater-ice-cream' ); ?></label>
					<select class="cic-input" id="<?php echo esc_attr( $cic_id ); ?>-event" name="event" data-cic-context="event">
						<option value=""><?php esc_html_e( 'Select event', 'cater-ice-cream' ); ?></option>
						<?php foreach ( $cic_q['events'] as $cic_key => $cic_label ) : ?>
							<option value="<?php echo esc_attr( $cic_key ); ?>"><?php echo esc_html( $cic_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="cic-field">
					<label for="<?php echo esc_attr( $cic_id ); ?>-date"><?php esc_html_e( 'Event date', 'cater-ice-cream' ); ?></label>
					<input class="cic-input" id="<?php echo esc_attr( $cic_id ); ?>-date" name="date" type="date" data-cic-context="date">
				</p>
				<p class="cic-field">
					<label for="<?php echo esc_attr( $cic_id ); ?>-guests"><?php esc_html_e( 'Estimated guests', 'cater-ice-cream' ); ?></label>
					<input class="cic-input" id="<?php echo esc_attr( $cic_id ); ?>-guests" name="guests" type="number" min="1" max="10000" inputmode="numeric" placeholder="<?php esc_attr_e( 'e.g. 60', 'cater-ice-cream' ); ?>" data-cic-context="guests">
				</p>
				<p class="cic-field">
					<label for="<?php echo esc_attr( $cic_id ); ?>-venue"><?php esc_html_e( 'Venue city', 'cater-ice-cream' ); ?></label>
					<input class="cic-input" id="<?php echo esc_attr( $cic_id ); ?>-venue" type="text" value="<?php echo esc_attr( $cic_vm['city']['name'] ); ?>" readonly>
				</p>
				<p class="cic-field cic-field--wide">
					<label for="<?php echo esc_attr( $cic_id ); ?>-zip"><?php esc_html_e( 'Event ZIP code', 'cater-ice-cream' ); ?></label>
					<input class="cic-input" id="<?php echo esc_attr( $cic_id ); ?>-zip" name="zip" type="text" inputmode="numeric" maxlength="10" autocomplete="postal-code" placeholder="<?php esc_attr_e( 'Enter ZIP code', 'cater-ice-cream' ); ?>" data-cic-context="location" data-cic-zip>
				</p>
				<button type="submit" class="cic-button cic-city-quote__submit"><?php esc_html_e( 'Request my event quote', 'cater-ice-cream' ); ?> <span aria-hidden="true">→</span></button>
				<p class="cic-search__error" id="<?php echo esc_attr( $cic_id ); ?>-error" role="alert" hidden></p>
				<?php if ( '' !== $cic_q['note'] ) : ?>
					<p class="cic-caption cic-city-quote__note"><?php echo esc_html( $cic_q['note'] ); ?></p>
				<?php endif; ?>
			</form>
		</div>
	</div>
</section>
