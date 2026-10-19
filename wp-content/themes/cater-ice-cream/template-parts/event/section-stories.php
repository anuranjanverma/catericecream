<?php
/**
 * Occasion-specific stories (Birthday: little birthdays / milestone birthdays).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_s = $args['vm']['stories'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-section cic-band cic-scallop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container">
		<?php if ( '' !== $cic_s['heading'] ) : ?>
			<h2 class="cic-center"><?php echo $cic_s['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php endif; ?>
		<ul class="cic-story-grid">
			<?php foreach ( $cic_s['items'] as $cic_item ) : ?>
				<li class="cic-card cic-story-card">
					<div class="cic-story-card__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 767px) 100vw, 300px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
					<div class="cic-card__body">
						<h3 class="cic-story-card__title"><?php echo $cic_item['title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h3>
						<p><?php echo esc_html( $cic_item['text'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
