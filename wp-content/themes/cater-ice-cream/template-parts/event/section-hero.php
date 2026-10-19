<?php
/**
 * Event hero. Birthday: centred multi-colour headline, balloons and confetti, three-photo collage.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm       = $args['vm'];
$cic_hero     = $cic_vm['hero'];
$cic_birthday = 'birthday' === $cic_vm['layout'];
$cic_events   = get_post_type_archive_link( 'cic_event' );
$cic_shop     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' );
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-hero cic-surface-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<?php if ( $cic_birthday ) : ?>
		<?php echo cic_theme_balloon( 'cic-balloon--l1', 'accent-2' ) . cic_theme_balloon( 'cic-balloon--l2', 'decor' ) . cic_theme_balloon( 'cic-balloon--r1', 'accent' ) . cic_theme_balloon( 'cic-balloon--r2', 'heading-accent' ) . cic_theme_confetti( 'cic-confetti--hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?>
	<?php endif; ?>
	<div class="cic-container cic-event-hero__intro">
		<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( get_post_type_object( 'cic_event' )->labels->name, (string) $cic_events ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<?php if ( '' !== $cic_hero['eyebrow'] ) : ?>
			<p class="cic-eyebrow<?php echo $cic_birthday ? ' screen-reader-text' : ''; ?>"><?php echo esc_html( $cic_hero['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h1 class="cic-hero__title"><?php echo $cic_hero['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered (em/strong only). ?></h1>
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
	<?php if ( $cic_hero['images'] ) : ?>
		<div class="cic-collage cic-collage--<?php echo (int) count( $cic_hero['images'] ); ?>">
			<?php foreach ( $cic_hero['images'] as $cic_i => $cic_img ) : ?>
				<div class="cic-collage__item cic-collage__item--<?php echo (int) $cic_i + 1; ?>">
					<?php echo cic_theme_image( $cic_img, 1 === $cic_i ? '(max-width: 767px) 100vw, 40vw' : '(max-width: 767px) 50vw, 28vw', 1 === $cic_i ? 'lcp' : 'eager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
