<?php
/**
 * Wedding wide photo band (05): reception photo framed by botanical corners, then a caption
 * between fine champagne rules.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_g = $args['vm']['gallery'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-gallery' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-gallery__photo">
		<?php echo cic_theme_image( $cic_g['image'], '100vw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
		<?php echo cic_theme_motif( 'botanical', 'cic-gallery__botanical cic-gallery__botanical--l' ) . cic_theme_motif( 'botanical', 'cic-gallery__botanical cic-gallery__botanical--r' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?>
	</div>
	<?php if ( '' !== $cic_g['caption'] ) : ?>
		<p class="cic-container cic-ruled-caption"><span><?php echo esc_html( $cic_g['caption'] ); ?></span></p>
	<?php endif; ?>
</section>
