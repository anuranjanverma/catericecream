<?php
/**
 * Treat feelings: ordered qualities with icon, title and explanation.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_f = $args['vm']['feelings'];
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container">
		<?php if ( '' !== $cic_f['heading'] ) : ?>
			<h2 class="cic-center"><?php echo $cic_f['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php endif; ?>
		<ul class="cic-qualities">
			<?php foreach ( $cic_f['items'] as $cic_item ) : ?>
				<li class="cic-quality">
					<span class="cic-icon-badge"><?php echo cic_theme_icon( $cic_item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<div>
						<h3 class="cic-quality__title"><?php echo esc_html( $cic_item['title'] ); ?></h3>
						<p><?php echo esc_html( $cic_item['text'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( '' !== $cic_f['note'] ) : ?>
			<p class="cic-rule-note"><span><?php echo esc_html( $cic_f['note'] ); ?></span></p>
		<?php endif; ?>
	</div>
</section>
