<?php
/**
 * Treat theme inspiration: styling ideas with palette swatches from Design System tokens.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_t = $args['vm']['themes'];
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container">
		<?php if ( '' !== $cic_t['eyebrow'] ) : ?>
			<p class="cic-eyebrow cic-center"><?php echo esc_html( $cic_t['eyebrow'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $cic_t['heading'] ) : ?>
			<h2 class="cic-center"><?php echo $cic_t['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php endif; ?>
		<ul class="cic-theme-grid">
			<?php foreach ( $cic_t['items'] as $cic_item ) : ?>
				<li class="cic-theme-card">
					<div class="cic-theme-card__media">
						<?php echo cic_theme_image( $cic_item['image'], '(max-width: 767px) 100vw, 360px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
						<?php if ( $cic_item['palette'] ) : ?>
							<span class="cic-swatches" aria-hidden="true">
								<?php foreach ( array_slice( $cic_item['palette'], 0, 3 ) as $cic_token ) : ?>
									<span class="cic-swatch-dot" style="background-color:<?php echo esc_attr( cic_theme_color( $cic_token ) ); ?>"></span>
								<?php endforeach; ?>
							</span>
						<?php endif; ?>
					</div>
					<h3 class="cic-theme-card__title"><?php echo esc_html( $cic_item['title'] ); ?></h3>
					<p><?php echo esc_html( $cic_item['text'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( '' !== $cic_t['note'] ) : ?>
			<p class="cic-caption cic-center"><?php echo esc_html( $cic_t['note'] ); ?></p>
		<?php endif; ?>
	</div>
</section>
