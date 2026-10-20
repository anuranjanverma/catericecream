<?php
/**
 * Featured treats for Wedding / Anniversary / Corporate (05–07): two large brand cards (photo left,
 * name and "Explore" right) from this event's pairings, then a single text line linking the other
 * paired treats, the "more treats" label and "View all treats". All links are internal.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm    = $args['vm'];
$cic_t     = $cic_vm['treats'];
$cic_large = array_slice( $cic_t['items'], 0, 2 );
$cic_small = array_slice( $cic_t['items'], 2 );
$cic_wed   = 'wedding' === $cic_vm['layout'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-section cic-brands' . ( 'anniversary' === $cic_vm['layout'] ? ' cic-band' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--wide">
		<?php if ( '' !== $cic_t['eyebrow'] ) : ?>
			<p class="cic-brands__eyebrow"><span class="cic-swoosh" aria-hidden="true"></span><?php echo esc_html( $cic_t['eyebrow'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $cic_t['heading'] ) : ?>
			<h2 class="cic-brands__title<?php echo $cic_wed ? ' cic-ruled-title' : ''; ?><?php echo '' !== $cic_t['eyebrow'] ? ' cic-brands__title--left' : ''; ?>"><span><?php echo $cic_t['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></span></h2>
		<?php endif; ?>
		<?php if ( '' !== $cic_t['intro'] ) : ?>
			<p class="cic-center cic-section-intro"><?php echo esc_html( $cic_t['intro'] ); ?></p>
		<?php endif; ?>
		<ul class="cic-brand-pair">
			<?php foreach ( $cic_large as $cic_item ) : ?>
				<li class="cic-brand-pair__card" data-cic-paired-product="<?php echo esc_attr( (string) $cic_item['product_id'] ); ?>">
					<div class="cic-brand-pair__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 599px) 45vw, 300px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
					<div class="cic-brand-pair__body">
						<h3 class="cic-brand-pair__name"><a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?></a></h3>
						<p class="cic-brand-pair__more" aria-hidden="true"><?php echo esc_html( $cic_wed ? sprintf( /* translators: %s: treat. */ __( 'Explore %s', 'cater-ice-cream' ), $cic_item['title'] ) : __( 'Explore', 'cater-ice-cream' ) ); ?> →</p>
						<p class="screen-reader-text"><?php echo esc_html( $cic_item['headline'] . ' ' . $cic_item['description'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="cic-brand-links">
			<?php foreach ( $cic_small as $cic_item ) : ?>
				<a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?></a><span aria-hidden="true">·</span>
			<?php endforeach; ?>
			<?php if ( '' !== $cic_t['more_label'] ) : ?>
				<a href="<?php echo esc_url( $cic_t['more_url'] ); ?>"><?php echo esc_html( $cic_t['more_label'] ); ?></a>
			<?php endif; ?>
			<span class="cic-brand-links__rule" aria-hidden="true"></span>
			<a class="cic-brand-links__all" href="<?php echo esc_url( $cic_t['more_url'] ); ?>"><?php esc_html_e( 'View all treats', 'cater-ice-cream' ); ?> <span aria-hidden="true">→</span></a>
		</p>
	</div>
</section>
