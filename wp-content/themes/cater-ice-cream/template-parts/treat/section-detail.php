<?php
/**
 * Treat detail: photo, "what it is" (WooCommerce description), texture/options, points, ingredient note.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_d = $args['vm']['detail'];
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-split' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-split__media">
		<?php echo cic_theme_image( $cic_d['image'], '(max-width: 899px) 100vw, 50vw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
	</div>
	<div class="cic-split__body cic-surface-alt">
		<h2><?php echo $cic_d['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<div class="cic-prose"><?php echo wp_kses_post( wpautop( $cic_d['body'] ) ); ?></div>
		<?php foreach ( array( $cic_d['texture'], $cic_d['options'] ) as $cic_para ) : ?>
			<?php if ( '' !== $cic_para ) : ?>
				<p><?php echo esc_html( $cic_para ); ?></p>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php if ( $cic_d['points'] ) : ?>
			<ul class="cic-points">
				<?php foreach ( $cic_d['points'] as $cic_point ) : ?>
					<li><span class="cic-icon-badge cic-icon-badge--sm"><?php echo cic_theme_icon( $cic_point['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php echo esc_html( $cic_point['label'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( '' !== $cic_d['ingredient'] ) : ?>
			<p class="cic-caption"><?php echo esc_html( $cic_d['ingredient'] ); ?></p>
		<?php endif; ?>
	</div>
</section>
