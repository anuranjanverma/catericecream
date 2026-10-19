<?php
/**
 * Treat occasion pairings (from the shared pairing service; same copy as the event page).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_o = $args['vm']['occasions'];
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section cic-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container">
		<h2 class="cic-center"><?php echo $cic_o['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<ul class="cic-pairing-grid">
			<?php foreach ( $cic_o['items'] as $cic_item ) : ?>
				<li class="cic-card cic-pairing-card" data-cic-pairing-event="<?php echo esc_attr( (string) $cic_item['event_id'] ); ?>">
					<?php if ( $cic_item['image'] ) : ?>
						<div class="cic-pairing-card__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 767px) 100vw, 280px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
					<?php endif; ?>
					<div class="cic-card__body">
						<p class="cic-eyebrow cic-eyebrow--accent"><?php echo esc_html( $cic_item['event_title'] ); ?></p>
						<h3><?php echo esc_html( $cic_item['headline'] ); ?></h3>
						<p class="cic-pairing-card__text"><?php echo esc_html( $cic_item['description'] ); ?></p>
						<?php echo cic_theme_arrow_link( $cic_item['event_url'], $cic_item['link_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
