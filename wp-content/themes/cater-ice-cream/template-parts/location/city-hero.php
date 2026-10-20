<?php
/**
 * City hero: breadcrumb, pin eyebrow, headline, subheading, intro; main photo with two framed
 * insets; the full-width quote starter (event ZIP, event type, date) carrying this city.
 * Mobile order: text, search, photo (as on the homepage).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
$cic_h  = $cic_vm['hero'];
$cic_s  = $cic_vm['search'];
echo cic_theme_section_open( 'cic-city', $args['section'], 'cic-home-hero cic-city-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-home-hero__grid cic-city-hero__grid">
		<div class="cic-home-hero__text">
			<?php echo cic_theme_breadcrumbs( $cic_vm['breadcrumbs'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( '' !== $cic_h['eyebrow'] ) : ?>
				<p class="cic-eyebrow cic-loc-eyebrow"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_h['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="cic-home-hero__title cic-city-hero__title"><?php echo $cic_h['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
			<?php if ( '' !== $cic_h['subtitle'] ) : ?>
				<p class="cic-city-hero__subtitle"><?php echo esc_html( $cic_h['subtitle'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $cic_h['intro'] ) : ?>
				<p class="cic-city-hero__intro"><?php echo esc_html( $cic_h['intro'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="cic-home-hero__media">
			<span class="cic-home-hero__blob" aria-hidden="true"></span>
			<?php if ( $cic_h['images'][0] ) : ?>
				<div class="cic-home-hero__main cic-city-hero__main"><?php echo cic_theme_image( $cic_h['images'][0], '(max-width: 899px) 100vw, 560px', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
			<?php foreach ( array( 1, 2 ) as $cic_n ) : ?>
				<?php if ( $cic_h['images'][ $cic_n ] ) : ?>
					<div class="cic-home-hero__inset cic-home-hero__inset--<?php echo (int) $cic_n; ?>"><?php echo cic_theme_image( $cic_h['images'][ $cic_n ], '200px', 'eager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="cic-container cic-home-hero__search cic-city-hero__search">
		<?php get_template_part( 'template-parts/components/hero-search', null, array( 'search' => $cic_s, 'id' => 'cic-city-search' ) ); ?>
	</div>
</section>
