<?php
/**
 * Split event hero (06 Anniversary, 07 Corporate): text on the left; a large photo with an organic
 * edge and a framed inset on the right. Anniversary adds a hand-drawn double-heart line and a
 * sparkle; Corporate keeps a clean professional look with soft raspberry strokes; Graduation adds a
 * graduation-cap line motif, small stars and a little confetti.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm     = $args['vm'];
$cic_hero   = $cic_vm['hero'];
$cic_imgs   = $cic_hero['images'];
$cic_shop   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' );
$cic_hearts = 'anniversary' === $cic_vm['layout'];
$cic_grad   = 'graduation' === $cic_vm['layout'];
$cic_motif  = $cic_hearts ? cic_theme_motif( 'hearts', 'cic-split-hero__hearts' ) : ( $cic_grad ? cic_theme_motif( 'grad-cap', 'cic-split-hero__cap' ) : '' );
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-split-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--wide cic-split-hero__grid">
		<div class="cic-split-hero__text">
			<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( get_post_type_object( 'cic_event' )->labels->name, (string) get_post_type_archive_link( 'cic_event' ) ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( '' !== $cic_hero['eyebrow'] ) : ?>
				<p class="cic-eyebrow cic-split-hero__eyebrow"><span class="cic-swoosh" aria-hidden="true"></span><?php echo esc_html( $cic_hero['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="cic-hero__title cic-split-hero__title"><?php echo $cic_hero['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?><?php echo $cic_motif; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?></h1>
			<?php if ( '' !== $cic_hero['intro'] ) : ?>
				<p class="cic-lead"><?php echo esc_html( $cic_hero['intro'] ); ?></p>
			<?php endif; ?>
			<div class="cic-hero__actions">
				<?php echo cic_theme_arrow_link( $cic_vm['quote_url'], $cic_hero['cta'], 'cic-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( '' !== $cic_hero['link_label'] ) : ?>
					<?php echo cic_theme_arrow_link( $cic_shop, $cic_hero['link_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php endif; ?>
			</div>
		</div>
		<div class="cic-split-hero__media">
			<?php if ( ! empty( $cic_imgs[0] ) ) : ?>
				<div class="cic-split-hero__main"><?php echo cic_theme_image( $cic_imgs[0], '(max-width: 899px) 100vw, 55vw', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $cic_imgs[1] ) ) : ?>
				<div class="cic-split-hero__inset"><?php echo cic_theme_image( $cic_imgs[1], '(max-width: 899px) 45vw, 300px', 'eager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
			<?php
			if ( $cic_grad ) {
				echo cic_theme_motif( 'stars', 'cic-split-hero__stars' ) . cic_theme_confetti( 'cic-confetti--grad' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG.
			} else {
				echo $cic_hearts ? cic_theme_motif( 'sparkle', 'cic-split-hero__sparkle' ) : cic_theme_motif( 'strokes', 'cic-split-hero__strokes' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG.
			}
			?>
		</div>
	</div>
</section>
