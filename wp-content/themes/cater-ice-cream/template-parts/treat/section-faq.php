<?php
/**
 * Treat FAQs (09/10): heading, optional introduction and the original mascot on the left; native
 * details/summary accordion on the right.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_q = $args['vm']['faq'];
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section cic-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-faq-split">
		<div class="cic-faq-split__intro">
			<h2><?php echo $cic_q['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php if ( '' !== $cic_q['intro'] ) : ?>
				<p><?php echo esc_html( $cic_q['intro'] ); ?></p>
				<span class="cic-faq-split__mascot"><?php echo cic_theme_mascot( 96 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<?php endif; ?>
		</div>
		<?php get_template_part( 'template-parts/event/faq-list', null, array( 'items' => $cic_q['items'] ) ); ?>
	</div>
</section>
