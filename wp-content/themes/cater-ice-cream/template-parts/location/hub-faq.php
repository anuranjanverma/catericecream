<?php
/**
 * "A few location questions." heading with a decorative sundae (left), accordion (right).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_f = $args['vm']['faq'];
echo cic_theme_section_open( 'cic-areas', $args['section'], 'cic-section cic-home-faq cic-areas-faq' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home cic-home-faq__grid">
		<div class="cic-home-faq__intro">
			<h2 class="cic-home-faq__title"><?php echo $cic_f['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<span class="cic-home-faq__cone cic-areas-faq__sundae" aria-hidden="true"><?php echo cic_theme_icon( 'sundae' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		</div>
		<?php get_template_part( 'template-parts/event/faq-list', null, array( 'items' => $cic_f['items'] ) ); ?>
	</div>
</section>
