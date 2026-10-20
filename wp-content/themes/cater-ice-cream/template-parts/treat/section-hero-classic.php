<?php
/**
 * Classic Ice Cream hero (11): a centred cream card (eyebrow, headline, intro, CTA, jump link)
 * between two people photos, with the scoops photo rising from the bottom and soft heart/cone
 * line motifs. Mobile: card first, then the scoops, then the two photos side by side.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm   = $args['vm'];
$cic_hero = $cic_vm['hero'];
$cic_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' );
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-classic-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-classic-hero__stage">
		<?php if ( $cic_hero['image_2'] ) : ?>
			<div class="cic-classic-hero__side cic-classic-hero__side--l"><?php echo cic_theme_image( $cic_hero['image_2'], '(max-width: 899px) 50vw, 34vw', 'eager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
		<?php endif; ?>
		<?php if ( $cic_hero['image_3'] ) : ?>
			<div class="cic-classic-hero__side cic-classic-hero__side--r"><?php echo cic_theme_image( $cic_hero['image_3'], '(max-width: 899px) 50vw, 34vw', 'eager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
		<?php endif; ?>
		<div class="cic-classic-hero__card">
			<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( get_the_title( wc_get_page_id( 'shop' ) ), $cic_shop ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( '' !== $cic_hero['eyebrow'] ) : ?>
				<p class="cic-eyebrow"><?php echo esc_html( $cic_hero['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="cic-hero__title"><?php echo $cic_hero['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
			<?php if ( '' !== $cic_hero['intro'] ) : ?>
				<p class="cic-classic-hero__intro"><?php echo esc_html( $cic_hero['intro'] ); ?></p>
			<?php endif; ?>
			<div class="cic-hero__actions">
				<?php echo cic_theme_arrow_link( $cic_vm['quote_url'], $cic_hero['cta'], 'cic-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</div>
			<?php if ( '' !== $cic_hero['jump'] ) : ?>
				<p class="cic-hero__jump"><a href="#occasions"><?php echo esc_html( $cic_hero['jump'] ); ?> <span aria-hidden="true">↓</span></a></p>
			<?php endif; ?>
			<?php echo cic_theme_motif( 'hearts', 'cic-classic-hero__heart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?>
		</div>
		<?php if ( $cic_hero['image'] ) : ?>
			<div class="cic-classic-hero__scoops"><?php echo cic_theme_image( $cic_hero['image'], '(max-width: 899px) 100vw, 46vw', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
		<?php endif; ?>
	</div>
</section>
