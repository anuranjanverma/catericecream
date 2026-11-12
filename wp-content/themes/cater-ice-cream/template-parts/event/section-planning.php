<?php
/**
 * Planning prompts: photo, heading, introduction and three numbered steps.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_p = $args['vm']['planning'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-section cic-tint' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-plan">
		<?php if ( $cic_p['image'] ) : ?>
			<div class="cic-plan__media"><?php echo cic_theme_image( $cic_p['image'], '(max-width: 899px) 100vw, 480px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
		<?php endif; ?>
		<div class="cic-plan__body">
			<span class="cic-plan__icon"><?php echo cic_theme_icon( 'hand-heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<?php if ( '' !== $cic_p['heading'] ) : ?>
				<h2><?php echo $cic_p['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $cic_p['intro'] ) : ?>
				<p><?php echo esc_html( $cic_p['intro'] ); ?></p>
			<?php endif; ?>
			<ol class="cic-steps">
				<?php foreach ( $cic_p['steps'] as $cic_i => $cic_step ) : ?>
					<li class="cic-step cic-step--<?php echo (int) $cic_i + 1; ?>">
						<span class="cic-step__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $cic_i + 1 ) ); ?></span>
						<div>
							<h3 class="cic-step__title"><?php echo esc_html( $cic_step['title'] ); ?></h3>
							<p><?php echo esc_html( $cic_step['text'] ); ?></p>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</div>
</section>
