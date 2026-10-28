<?php
/**
 * Quote panel + FAQ (05–07). The panel is the shared non-personal quote starter (venue city/ZIP,
 * date, guests) with this occasion fixed; it hands over to the dedicated quote route and never
 * claims a submission. The full CF7 form with contact details is Phase 06.
 * - Wedding: framed panel with botanical line art, FAQ and service-area link below. Prom uses the
 *   same framed panel with starlight lines.
 * - Anniversary / Corporate: panel left, "Good to know" FAQ right (Corporate in a tinted card).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm  = $args['vm'];
$cic_q   = $cic_vm['quote'];
$cic_wed = in_array( $cic_vm['layout'], array( 'wedding', 'prom' ), true );
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-section cic-qpanel cic-qpanel--' . esc_attr( $cic_vm['layout'] ) . ( 'prom' === $cic_vm['layout'] ? ' cic-qpanel--wedding' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--wide cic-qpanel__grid">
		<div class="cic-qpanel__panel">
			<?php if ( $cic_wed ) : ?>
				<?php $cic_line = 'prom' === $cic_vm['layout'] ? 'starlight' : 'botanical'; ?>
				<?php echo cic_theme_motif( $cic_line, 'cic-qpanel__botanical cic-qpanel__botanical--l' ) . cic_theme_motif( $cic_line, 'cic-qpanel__botanical cic-qpanel__botanical--r' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?>
			<?php endif; ?>
			<h2 class="cic-qpanel__title"><?php echo $cic_q['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php
			get_template_part(
				'template-parts/components/quote-starter',
				null,
				array(
					'id'     => 'cic-event-quote',
					'fixed'  => array( 'event' => $cic_vm['key'] ),
					'fields' => array( 'location', 'date', 'guests' ),
					'labels' => array(
						'location' => $cic_q['starter']['location_label'],
						'date'     => 'wedding' === $cic_vm['layout'] ? __( 'Wedding date', 'cater-ice-cream' ) : $cic_q['starter']['date_label'],
						'guests'   => $cic_q['starter']['guests_label'],
					),
					'cta'    => $cic_q['cta'],
					'note'   => $cic_q['note'],
					'class'  => 'cic-qs--panel',
				)
			);
			?>
		</div>
		<?php if ( $cic_q['faqs'] || '' !== $cic_q['area_label'] ) : ?>
			<div class="cic-qpanel__faq">
				<?php if ( ! $cic_wed ) : ?>
					<h2 class="cic-qpanel__faq-title"><span class="cic-swoosh" aria-hidden="true"></span><?php echo $cic_q['faq_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<?php else : ?>
					<h2 class="screen-reader-text"><?php echo wp_strip_all_tags( $cic_q['faq_title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tags stripped from kses-filtered text. ?></h2>
				<?php endif; ?>
				<?php get_template_part( 'template-parts/event/faq-list', null, array( 'items' => $cic_q['faqs'] ) ); ?>
				<?php if ( '' !== $cic_q['area_label'] ) : ?>
					<p class="cic-qpanel__area"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo cic_theme_arrow_link( $cic_q['area_url'], $cic_q['area_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
