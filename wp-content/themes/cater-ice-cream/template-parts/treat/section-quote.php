<?php
/**
 * Treat quote starter. The explicit treat selection travels to the shared quote flow (Phase 06
 * builds the form); this section offers the quote action and the public call option.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm      = $args['vm'];
$cic_q       = $cic_vm['quote'];
$cic_contact = cic_theme_contact();
$cic_shop    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' );
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section cic-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-quote-split">
		<div>
			<h2><?php echo $cic_q['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php if ( '' !== $cic_q['intro'] ) : ?>
				<p class="cic-lead"><?php echo esc_html( $cic_q['intro'] ); ?></p>
			<?php endif; ?>
			<p class="cic-your-treat"><span class="cic-icon-badge"><?php echo cic_theme_icon( 'cone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><span><span class="cic-caption"><?php esc_html_e( 'Your treat:', 'cater-ice-cream' ); ?></span> <strong><?php echo esc_html( $cic_vm['title'] ); ?></strong></span></p>
			<p><?php echo cic_theme_arrow_link( $cic_shop, __( 'Explore all treats', 'cater-ice-cream' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
		</div>
		<div class="cic-quote-card">
			<?php echo cic_theme_arrow_link( $cic_vm['quote_url'], $cic_q['cta'], 'cic-button cic-button--block' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<p class="cic-caption"><?php echo esc_html( $cic_q['note'] ); ?></p>
			<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
				<p class="cic-quote-card__call"><?php esc_html_e( 'Prefer to talk?', 'cater-ice-cream' ); ?> <a href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo esc_html( $cic_contact['phone'] ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
</section>
