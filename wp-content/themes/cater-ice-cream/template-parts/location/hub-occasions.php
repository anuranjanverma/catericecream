<?php
/**
 * "Your location. Your kind of celebration." four occasion cards.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_o = $args['vm']['occasions'];
echo cic_theme_section_open( 'cic-areas', $args['section'], 'cic-section cic-areas-occasions' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<h2 class="cic-center"><?php echo $cic_o['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php if ( '' !== $cic_o['intro'] ) : ?>
			<p class="cic-center cic-section-intro cic-areas-occasions__intro"><?php echo esc_html( $cic_o['intro'] ); ?></p>
		<?php endif; ?>
		<?php get_template_part( 'template-parts/components/occasion-cards', null, array( 'items' => $cic_o['items'], 'variant' => 'grid' ) ); ?>
	</div>
</section>
