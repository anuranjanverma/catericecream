<?php
/**
 * Planning steps band (05–07): centred heading and three numbered steps in a row with fine
 * dividers. Wedding uses outlined champagne numerals on blush; Anniversary/Corporate use filled
 * soft-pink numerals on lavender (colours from the page preset).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_p = $args['vm']['planning'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-section cic-plan-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--wide">
		<?php if ( '' !== $cic_p['heading'] ) : ?>
			<h2 class="cic-center cic-plan-band__title"><?php echo $cic_p['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $cic_p['intro'] ) : ?>
			<p class="cic-center cic-section-intro"><?php echo esc_html( $cic_p['intro'] ); ?></p>
		<?php endif; ?>
		<ol class="cic-plan-band__steps">
			<?php foreach ( $cic_p['steps'] as $cic_i => $cic_step ) : ?>
				<li class="cic-plan-band__step">
					<span class="cic-plan-band__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $cic_i + 1 ) ); ?></span>
					<span class="cic-plan-band__text">
						<strong><?php echo esc_html( $cic_step['title'] ); ?></strong>
						<?php if ( '' !== $cic_step['text'] ) : ?>
							<span><?php echo esc_html( $cic_step['text'] ); ?></span>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
