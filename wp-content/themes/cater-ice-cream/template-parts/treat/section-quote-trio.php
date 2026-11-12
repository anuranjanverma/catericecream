<?php
/**
 * Frozen Yogurt closing area (12): three columns: heading, introduction and the "Your treat"
 * pill; the shared non-personal quote starter (location, event type, date) with this treat fixed;
 * and the "Good to know" FAQ. No submission or success state here (Phase 06).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
$cic_q  = $cic_vm['quote'];
$cic_f  = $cic_vm['faq'];
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section cic-treat-close cic-treat-close--trio' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--wide cic-treat-close__grid">
		<div class="cic-treat-close__intro">
			<h2 class="cic-treat-close__title"><?php echo $cic_q['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php if ( '' !== $cic_q['intro'] ) : ?>
				<p><?php echo esc_html( $cic_q['intro'] ); ?></p>
			<?php endif; ?>
			<?php get_template_part( 'template-parts/treat/your-treat', null, array( 'title' => $cic_vm['title'] ) ); ?>
		</div>
		<div class="cic-treat-close__form">
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
					'class'  => 'cic-qs--stack',
				)
			);
			?>
		</div>
		<?php if ( $cic_f['items'] ) : ?>
			<div class="cic-treat-close__faq">
				<h2 class="cic-treat-close__faq-title"><?php echo $cic_f['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<?php get_template_part( 'template-parts/event/faq-list', null, array( 'items' => $cic_f['items'] ) ); ?>
			</div>
		<?php endif; ?>
	</div>
</section>
