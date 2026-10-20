<?php
/**
 * Treat quote starter (09/10 split composition): heading, introduction and the "Your treat" pill
 * on the left; the shared non-personal quote starter (location, event type, date) with this treat
 * fixed on the right. It hands over to the dedicated quote route; nothing is submitted here and no
 * success is shown. The full CF7 form arrives in Phase 06.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm   = $args['vm'];
$cic_q    = $cic_vm['quote'];
$cic_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' );
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section cic-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-quote-split">
		<div>
			<h2><?php echo $cic_q['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php if ( '' !== $cic_q['intro'] ) : ?>
				<p class="cic-lead"><?php echo esc_html( $cic_q['intro'] ); ?></p>
			<?php endif; ?>
			<?php get_template_part( 'template-parts/treat/your-treat', null, array( 'title' => $cic_vm['title'] ) ); ?>
			<p><?php echo cic_theme_arrow_link( $cic_shop, __( 'Explore all treats', 'cater-ice-cream' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
		</div>
		<div class="cic-quote-card">
			<?php
			get_template_part(
				'template-parts/components/quote-starter',
				null,
				array(
					'id'     => 'cic-treat-quote',
					'fixed'  => array( 'treat' => $cic_vm['key'] ),
					'fields' => array( 'location', 'event', 'date' ),
					'labels' => array( 'location' => __( 'Location', 'cater-ice-cream' ), 'date' => __( 'Date', 'cater-ice-cream' ) ),
					'events' => $cic_q['events'],
					'cta'    => $cic_q['cta'],
					'note'   => $cic_q['note'],
					'call'   => true,
				)
			);
			?>
		</div>
	</div>
</section>
