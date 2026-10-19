<?php
/**
 * Quote panel and FAQ. The shared quote form is built in Phase 06; until then the panel offers
 * the quote action with the occasion selected, plus the public call option.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm      = $args['vm'];
$cic_q       = $cic_vm['quote'];
$cic_contact = cic_theme_contact();
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-section' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-quote-faq">
		<div class="cic-card cic-quote-panel">
			<?php if ( 'birthday' === $cic_vm['layout'] ) : ?>
				<span class="cic-quote-panel__icon"><?php echo cic_theme_icon( 'gift' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<?php endif; ?>
			<h2><?php echo $cic_q['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php echo cic_theme_arrow_link( $cic_vm['quote_url'], $cic_q['cta'], 'cic-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<p class="cic-caption"><?php echo esc_html( $cic_q['note'] ); ?></p>
			<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
				<p><?php esc_html_e( 'Prefer to talk?', 'cater-ice-cream' ); ?> <a href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo esc_html( $cic_contact['phone'] ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php if ( $cic_q['faqs'] || '' !== $cic_q['area_label'] ) : ?>
			<div class="cic-faq-panel">
				<h2 class="cic-h3"><?php echo $cic_q['faq_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<?php get_template_part( 'template-parts/event/faq-list', null, array( 'items' => $cic_q['faqs'] ) ); ?>
				<?php if ( '' !== $cic_q['area_label'] ) : ?>
					<p class="cic-faq-panel__area"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo cic_theme_arrow_link( $cic_q['area_url'], $cic_q['area_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
