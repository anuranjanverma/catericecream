<?php
/**
 * Story with photo.
 * - Wedding (05 "The sweet moment"): arched photo + tilted inset with botanical line art, text and
 *   three icon points with fine dividers.
 * - Anniversary (06) / Corporate (07): wide photo bleeding to the left edge, heading, text and a
 *   three-item checklist with icon circles; a heart or people line motif.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm  = $args['vm'];
$cic_f   = $cic_vm['feature'];
$cic_wed = 'wedding' === $cic_vm['layout'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-section cic-feature cic-feature--' . ( $cic_wed ? 'wedding' : 'split' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--wide cic-feature__grid">
		<div class="cic-feature__media">
			<?php if ( $cic_wed ) : ?>
				<?php echo cic_theme_motif( 'botanical', 'cic-feature__botanical cic-feature__botanical--l' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?>
			<?php endif; ?>
			<?php if ( $cic_f['image'] ) : ?>
				<div class="cic-feature__photo"><?php echo cic_theme_image( $cic_f['image'], '(max-width: 899px) 100vw, 50vw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
			<?php if ( $cic_wed && $cic_f['inset'] ) : ?>
				<div class="cic-feature__inset"><?php echo cic_theme_image( $cic_f['inset'], '(max-width: 899px) 50vw, 240px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
		</div>
		<div class="cic-feature__body">
			<?php if ( '' !== $cic_f['eyebrow'] ) : ?>
				<p class="cic-eyebrow cic-ruled-eyebrow"><?php echo esc_html( $cic_f['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="cic-feature__title"><?php echo $cic_f['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php if ( '' !== $cic_f['text'] ) : ?>
				<p class="cic-feature__text"><?php echo esc_html( $cic_f['text'] ); ?></p>
			<?php endif; ?>
			<?php if ( $cic_f['points'] ) : ?>
				<ul class="cic-feature__points cic-feature__points--<?php echo $cic_wed ? 'columns' : 'list'; ?>">
					<?php foreach ( $cic_f['points'] as $cic_point ) : ?>
						<li>
							<span class="cic-feature__icon"><?php echo cic_theme_icon( '' !== $cic_point['icon'] ? $cic_point['icon'] : 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span class="cic-feature__point">
								<strong><?php echo esc_html( $cic_point['title'] ); ?></strong>
								<?php if ( '' !== $cic_point['text'] ) : ?>
									<span><?php echo esc_html( $cic_point['text'] ); ?></span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! $cic_wed ) : ?>
				<?php echo cic_theme_motif( 'anniversary' === $cic_vm['layout'] ? 'hearts' : 'people-line', 'cic-feature__motif' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?>
			<?php endif; ?>
		</div>
	</div>
</section>
