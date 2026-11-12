<?php
/**
 * Home events band: one card per published event, in the central event order (each event's Order
 * field). Six events: two rows of three on desktop.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_e = $args['vm']['events'];
echo cic_theme_section_open( 'cic-home', $args['section'], 'cic-section cic-band cic-wave' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<?php if ( '' !== $cic_e['heading'] ) : ?>
			<h2 class="cic-center cic-home-events__title"><span class="cic-swoosh" aria-hidden="true"></span><?php echo $cic_e['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php endif; ?>
		<ul class="cic-event-cards cic-event-cards--<?php echo (int) count( $cic_e['items'] ); ?>">
			<?php foreach ( $cic_e['items'] as $cic_item ) : ?>
				<li class="cic-card cic-event-card" data-cic-event="<?php echo esc_attr( $cic_item['key'] ); ?>">
					<div class="cic-event-card__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 767px) 100vw, (max-width: 1099px) 50vw, 300px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
					<div class="cic-card__body">
						<h3 class="cic-event-card__title"><a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?></a></h3>
						<?php if ( '' !== $cic_item['excerpt'] ) : ?>
							<p><?php echo esc_html( $cic_item['excerpt'] ); ?></p>
						<?php endif; ?>
						<p class="cic-event-card__more" aria-hidden="true"><?php esc_html_e( 'Explore', 'cater-ice-cream' ); ?> →</p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
