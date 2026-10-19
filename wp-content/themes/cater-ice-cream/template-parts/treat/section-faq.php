<?php
/**
 * Treat FAQs (native details/summary accordion).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_q = $args['vm']['faq'];
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section cic-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-faq-split">
		<h2><?php echo $cic_q['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php get_template_part( 'template-parts/event/faq-list', null, array( 'items' => $cic_q['items'] ) ); ?>
	</div>
</section>
