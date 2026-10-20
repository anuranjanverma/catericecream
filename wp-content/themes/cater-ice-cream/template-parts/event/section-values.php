<?php
/**
 * Three value points under the hero (06 Anniversary, 07 Corporate): icon in a tinted circle,
 * title and a short line, separated by fine dividers.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_items = $args['vm']['values'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-ev-values' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container">
		<ul class="cic-ev-values__list">
			<?php foreach ( $cic_items as $cic_item ) : ?>
				<li class="cic-ev-value">
					<span class="cic-ev-value__icon"><?php echo cic_theme_icon( '' !== $cic_item['icon'] ? $cic_item['icon'] : 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<span class="cic-ev-value__text">
						<strong class="cic-ev-value__title"><?php echo esc_html( $cic_item['title'] ); ?></strong>
						<?php if ( '' !== $cic_item['text'] ) : ?>
							<span><?php echo esc_html( $cic_item['text'] ); ?></span>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
