<?php
/**
 * "What we need to check your location": icon steps joined by chevrons (row on desktop,
 * compact four-up grid on mobile).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_s = $args['vm']['steps'];
echo cic_theme_section_open( 'cic-areas', $args['section'], 'cic-section cic-areas-steps' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<h2 class="cic-center"><?php echo $cic_s['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php if ( '' !== $cic_s['intro'] ) : ?>
			<p class="cic-center cic-section-intro"><?php echo esc_html( $cic_s['intro'] ); ?></p>
		<?php endif; ?>
		<ol class="cic-loc-steps">
			<?php foreach ( $cic_s['items'] as $cic_step ) : ?>
				<li class="cic-loc-step">
					<span class="cic-loc-step__icon"><?php echo cic_theme_icon( '' !== $cic_step['icon'] ? $cic_step['icon'] : 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<span class="cic-loc-step__text"><strong><?php echo esc_html( $cic_step['title'] ); ?></strong><?php if ( '' !== $cic_step['text'] ) : ?><span><?php echo esc_html( $cic_step['text'] ); ?></span><?php endif; ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
