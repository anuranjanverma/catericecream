<?php
/**
 * Wedding hero (05): full-bleed couple photo with an ivory text card on the left, champagne-gold
 * eyebrow rule, italic accent headline, interlocking-rings line motif and a fine gold wave.
 * Mobile (21): the photo first, then the card overlapping its lower edge.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm   = $args['vm'];
$cic_hero = $cic_vm['hero'];
$cic_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' );
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-wed-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<?php if ( ! empty( $cic_hero['images'][0] ) ) : ?>
		<div class="cic-wed-hero__photo"><?php echo cic_theme_image( $cic_hero['images'][0], '100vw', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
	<?php endif; ?>
	<div class="cic-container cic-container--wide cic-wed-hero__inner">
		<div class="cic-wed-hero__card">
			<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( get_post_type_object( 'cic_event' )->labels->name, (string) get_post_type_archive_link( 'cic_event' ) ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( '' !== $cic_hero['eyebrow'] ) : ?>
				<p class="cic-eyebrow cic-ruled-eyebrow"><?php echo esc_html( $cic_hero['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="cic-hero__title cic-wed-hero__title"><?php echo $cic_hero['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
			<?php if ( '' !== $cic_hero['intro'] ) : ?>
				<p class="cic-lead"><?php echo esc_html( $cic_hero['intro'] ); ?></p>
			<?php endif; ?>
			<div class="cic-hero__actions">
				<?php echo cic_theme_arrow_link( $cic_vm['quote_url'], $cic_hero['cta'], 'cic-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( '' !== $cic_hero['link_label'] ) : ?>
					<?php echo cic_theme_arrow_link( $cic_shop, $cic_hero['link_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php endif; ?>
			</div>
			<?php echo cic_theme_motif( 'rings', 'cic-wed-hero__rings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?>
		</div>
	</div>
	<?php echo cic_theme_motif( 'gold-wave', 'cic-wed-hero__wave' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?>
</section>
