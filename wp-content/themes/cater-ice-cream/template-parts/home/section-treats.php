<?php
/**
 * Home treats: two large brand cards, then compact cards (remaining treats + "more treats").
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_t     = $args['vm']['treats'];
$cic_large = array_slice( $cic_t['items'], 0, 2 );
$cic_small = array_slice( $cic_t['items'], 2 );
echo cic_theme_section_open( 'cic-home', $args['section'], 'cic-section cic-home-treats' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<?php if ( '' !== $cic_t['heading'] ) : ?>
			<h2 class="cic-center"><?php echo $cic_t['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $cic_t['intro'] ) : ?>
			<p class="cic-center cic-section-intro"><?php echo esc_html( $cic_t['intro'] ); ?></p>
		<?php endif; ?>
		<ul class="cic-brand-cards">
			<?php foreach ( $cic_large as $cic_i => $cic_item ) : ?>
				<li class="cic-brand-card cic-brand-card--<?php echo (int) $cic_i + 1; ?>" data-cic-product="<?php echo esc_attr( $cic_item['key'] ); ?>">
					<div class="cic-brand-card__body">
						<h3 class="cic-brand-card__title"><a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?></a></h3>
						<?php if ( '' !== $cic_item['tagline'] ) : ?>
							<p class="cic-brand-card__tagline"><?php echo esc_html( $cic_item['tagline'] ); ?></p>
						<?php endif; ?>
						<p class="cic-brand-card__link" aria-hidden="true"><?php echo esc_html( $cic_item['link'] ); ?> →</p>
					</div>
					<div class="cic-brand-card__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 767px) 55vw, 300px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $cic_small || '' !== $cic_t['more_label'] ) : ?>
			<ul class="cic-mini-cards">
				<?php foreach ( $cic_small as $cic_item ) : ?>
					<li class="cic-mini-card" data-cic-product="<?php echo esc_attr( $cic_item['key'] ); ?>">
						<div class="cic-mini-card__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 767px) 45vw, 180px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
						<a class="cic-mini-card__link" href="<?php echo esc_url( $cic_item['url'] ); ?>"><span><?php echo esc_html( $cic_item['title'] ); ?></span> <span class="cic-mini-card__arrow" aria-hidden="true">→</span></a>
					</li>
				<?php endforeach; ?>
				<?php if ( '' !== $cic_t['more_label'] ) : ?>
					<li class="cic-mini-card">
						<div class="cic-mini-card__media"><?php echo cic_theme_image( $cic_t['more_image'], '(max-width: 767px) 45vw, 180px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
						<a class="cic-mini-card__link" href="<?php echo esc_url( $cic_t['more_url'] ); ?>"><span><?php echo esc_html( $cic_t['more_label'] ); ?></span> <span class="cic-mini-card__arrow" aria-hidden="true">→</span></a>
					</li>
				<?php endif; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
