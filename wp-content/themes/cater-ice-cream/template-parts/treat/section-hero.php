<?php
/**
 * Treat hero: breadcrumb, eyebrow, emotional headline, intro, quote CTA, jump link, feeling chips, photo.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm   = $args['vm'];
$cic_hero = $cic_vm['hero'];
$cic_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' );
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-hero cic-surface-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<span class="cic-dots-decor" aria-hidden="true"></span>
	<div class="cic-container cic-container--wide cic-hero__grid">
		<div class="cic-hero__text">
			<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( get_the_title( wc_get_page_id( 'shop' ) ), $cic_shop ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( '' !== $cic_hero['eyebrow'] ) : ?>
				<p class="cic-eyebrow"><?php echo esc_html( $cic_hero['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="cic-hero__title"><?php echo $cic_hero['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered (em/strong only). ?></h1>
			<?php if ( '' !== $cic_hero['intro'] ) : ?>
				<p class="cic-lead"><?php echo esc_html( $cic_hero['intro'] ); ?></p>
			<?php endif; ?>
			<div class="cic-hero__actions">
				<?php echo cic_theme_arrow_link( $cic_vm['quote_url'], $cic_hero['cta'], 'cic-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</div>
			<?php if ( '' !== $cic_hero['jump'] ) : ?>
				<p class="cic-hero__jump"><a href="#occasions"><?php echo esc_html( $cic_hero['jump'] ); ?> <span aria-hidden="true">↓</span></a></p>
			<?php endif; ?>
			<?php if ( $cic_hero['chips'] ) : ?>
				<ul class="cic-chips" aria-label="<?php esc_attr_e( 'The feeling', 'cater-ice-cream' ); ?>">
					<?php foreach ( $cic_hero['chips'] as $cic_chip ) : ?>
						<li class="cic-chip"><?php echo cic_theme_icon( $cic_chip['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_chip['label'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php if ( $cic_hero['image'] ) : ?>
			<div class="cic-hero__media">
				<?php echo cic_theme_image( $cic_hero['image'], '(max-width: 899px) 100vw, 50vw', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
			</div>
		<?php endif; ?>
	</div>
</section>
