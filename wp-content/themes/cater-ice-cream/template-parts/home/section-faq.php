<?php
/**
 * Home FAQ: heading with a decorative cone on the left, accordion on the right.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_f = $args['vm']['faq'];
echo cic_theme_section_open( 'cic-home', $args['section'], 'cic-section cic-home-faq' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home cic-home-faq__grid">
		<div class="cic-home-faq__intro">
			<h2 class="cic-home-faq__title"><span class="cic-swoosh" aria-hidden="true"></span><?php echo $cic_f['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<span class="cic-home-faq__cone" aria-hidden="true"><?php echo cic_theme_icon( 'cone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		</div>
		<?php get_template_part( 'template-parts/event/faq-list', null, array( 'items' => $cic_f['items'] ) ); ?>
	</div>
</section>
