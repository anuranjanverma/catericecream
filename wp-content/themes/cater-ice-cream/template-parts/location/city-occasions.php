<?php
/**
 * "Find the feeling for your {city} celebration." four occasion cards (photo beside text).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_o = $args['vm']['occasions'];
if ( '' === $cic_o['heading'] || ! $cic_o['items'] ) {
	return;
}
echo cic_theme_section_open( 'cic-city', $args['section'], 'cic-section cic-band cic-city-occasions' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<h2 class="cic-center"><?php echo $cic_o['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php get_template_part( 'template-parts/components/occasion-cards', null, array( 'items' => $cic_o['items'], 'variant' => 'row' ) ); ?>
	</div>
</section>
