<?php
/**
 * "Your {city} plans." heading, introduction and three icon cards.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_p = $args['vm']['plans'];
if ( '' === $cic_p['heading'] ) {
	return;
}
echo cic_theme_section_open( 'cic-city', $args['section'], 'cic-section cic-city-plans' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<h2 class="cic-center"><?php echo $cic_p['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php if ( '' !== $cic_p['intro'] ) : ?>
			<p class="cic-center cic-section-intro cic-city-plans__intro"><?php echo esc_html( $cic_p['intro'] ); ?></p>
		<?php endif; ?>
		<ul class="cic-plan-cards">
			<?php foreach ( $cic_p['items'] as $cic_item ) : ?>
				<li class="cic-plan-card">
					<span class="cic-plan-card__icon"><?php echo cic_theme_icon( '' !== $cic_item['icon'] ? $cic_item['icon'] : 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<span class="cic-plan-card__text"><strong><?php echo esc_html( $cic_item['title'] ); ?></strong><?php if ( '' !== $cic_item['text'] ) : ?><span><?php echo esc_html( $cic_item['text'] ); ?></span><?php endif; ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
