<?php
/**
 * Home value points (icon + label, separated by dividers).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

echo cic_theme_section_open( 'cic-home', $args['section'], 'cic-home-values' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container">
		<ul class="cic-values">
			<?php foreach ( $args['vm']['values'] as $cic_value ) : ?>
				<li class="cic-value"><span class="cic-value__icon"><?php echo cic_theme_icon( $cic_value['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php echo esc_html( $cic_value['label'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
