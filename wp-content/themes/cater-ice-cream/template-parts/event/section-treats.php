<?php
/**
 * Featured treats for an event, from the pairing index (published treats only). The first two are
 * large brand cards; the rest are compact, followed by the "more treats" card.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_t     = $args['vm']['treats'];
$cic_large = array_slice( $cic_t['items'], 0, 2 );
$cic_small = array_slice( $cic_t['items'], 2 );
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-section' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
if ( 'birthday' === $args['vm']['layout'] ) {
	echo cic_theme_balloon( 'cic-balloon--t1', 'heading-accent' ) . cic_theme_confetti( 'cic-confetti--treats' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG.
} elseif ( 'graduation' === $args['vm']['layout'] ) {
	echo cic_theme_motif( 'stars', 'cic-treats__stars' ) . cic_theme_confetti( 'cic-confetti--treats' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG.
}
?>
	<div class="cic-container">
		<?php if ( '' !== $cic_t['heading'] ) : ?>
			<h2 class="cic-center"><?php echo $cic_t['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $cic_t['intro'] ) : ?>
			<p class="cic-center cic-section-intro"><?php echo esc_html( $cic_t['intro'] ); ?></p>
		<?php endif; ?>
		<ul class="cic-treat-feature">
			<?php foreach ( $cic_large as $cic_i => $cic_item ) : ?>
				<li class="cic-treat-feature__card cic-treat-feature__card--<?php echo (int) $cic_i + 1; ?>" data-cic-paired-product="<?php echo esc_attr( (string) $cic_item['product_id'] ); ?>">
					<div class="cic-treat-feature__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 767px) 50vw, 280px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
					<div class="cic-treat-feature__body">
						<h3 class="cic-treat-feature__title"><a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?></a></h3>
						<?php if ( '' !== $cic_item['intro'] ) : ?>
							<p><?php echo esc_html( $cic_item['intro'] ); ?></p>
						<?php endif; ?>
						<p class="cic-treat-feature__more" aria-hidden="true"><?php esc_html_e( 'Explore', 'cater-ice-cream' ); ?> →</p>
						<p class="screen-reader-text"><?php echo esc_html( $cic_item['headline'] . ' ' . $cic_item['description'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $cic_small || '' !== $cic_t['more_label'] ) : ?>
			<ul class="cic-treat-mini">
				<?php foreach ( $cic_small as $cic_item ) : ?>
					<li class="cic-treat-mini__item" data-cic-paired-product="<?php echo esc_attr( (string) $cic_item['product_id'] ); ?>">
						<?php echo cic_theme_image( $cic_item['image'], '72px', 'lazy', array( 'class' => 'cic-photo cic-treat-mini__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
						<a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?> <span aria-hidden="true">→</span></a>
					</li>
				<?php endforeach; ?>
				<?php if ( '' !== $cic_t['more_label'] ) : ?>
					<li class="cic-treat-mini__item">
						<?php echo cic_theme_image( $cic_t['more_image'], '72px', 'lazy', array( 'class' => 'cic-photo cic-treat-mini__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
						<a href="<?php echo esc_url( $cic_t['more_url'] ); ?>"><?php echo esc_html( $cic_t['more_label'] ); ?> <span aria-hidden="true">→</span></a>
					</li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
