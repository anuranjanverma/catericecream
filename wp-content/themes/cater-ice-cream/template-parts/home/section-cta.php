<?php
/**
 * Closing call panel (shared .cic-call-panel component; colours from the Design System).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_c       = $args['vm']['cta'];
$cic_contact = cic_theme_contact();
echo cic_theme_section_open( 'cic-home', $args['section'], 'cic-call-panel cic-home-cta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-call-panel__inner">
		<h2 class="cic-call-panel__title"><span class="cic-swoosh cic-swoosh--light" aria-hidden="true"></span><?php echo $cic_c['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<div class="cic-call-panel__actions">
			<a class="cic-button cic-button--inverse" href="<?php echo esc_url( $cic_c['url'] ); ?>" data-cic-quote-link><?php echo esc_html( $cic_c['label'] ); ?> <span aria-hidden="true">→</span></a>
			<?php if ( $cic_c['phone'] && '' !== $cic_contact['phone_href'] ) : ?>
				<a class="cic-call-panel__phone" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
