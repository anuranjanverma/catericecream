<?php
/**
 * Plum call panel with a photo (Pasadena mid-page panel, Service Areas "Not sure about your area?").
 * The phone number and tel link come from the global contact settings only.
 *
 * Args: heading (kses-filtered HTML), text, image, note, quote_label, quote_url, phone_first.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_contact = cic_theme_contact();
?>
<div class="cic-loc-call">
	<?php if ( ! empty( $args['image'] ) ) : ?>
		<div class="cic-loc-call__media"><?php echo cic_theme_image( (int) $args['image'], '(max-width: 899px) 100vw, 480px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
	<?php endif; ?>
	<div class="cic-loc-call__body">
		<h2 class="cic-loc-call__title"><?php echo $args['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php if ( '' !== ( $args['text'] ?? '' ) ) : ?>
			<p class="cic-loc-call__text"><?php echo esc_html( $args['text'] ); ?></p>
		<?php endif; ?>
		<div class="cic-loc-call__actions">
			<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
				<a class="cic-button cic-loc-call__phone" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
			<?php endif; ?>
			<a class="cic-button cic-button--secondary" href="<?php echo esc_url( $args['quote_url'] ); ?>"><?php echo esc_html( $args['quote_label'] ); ?> <span aria-hidden="true">→</span></a>
		</div>
		<?php if ( '' !== ( $args['note'] ?? '' ) ) : ?>
			<p class="cic-loc-call__note"><?php echo esc_html( $args['note'] ); ?></p>
		<?php endif; ?>
	</div>
	<span class="cic-loc-call__spark" aria-hidden="true"><span></span><span></span><span></span></span>
</div>
