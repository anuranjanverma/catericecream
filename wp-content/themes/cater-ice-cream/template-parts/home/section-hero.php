<?php
/**
 * Home hero: eyebrow, two-line headline, intro, photo collage (main + two insets) and the hero search.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm   = $args['vm'];
$cic_hero = $cic_vm['hero'];
$cic_imgs = $cic_hero['images'];
// Longest headline line in characters: lets home.css size the desktop headline so each line fits its
// column (the headline is editable copy; longer words must not push the intro under the search card).
$cic_chars = 0;
if ( preg_match_all( '#<span class="cic-line">(.*?)</span>#s', (string) $cic_hero['headline'], $cic_m ) ) {
	foreach ( $cic_m[1] as $cic_l ) {
		$cic_chars = max( $cic_chars, mb_strlen( html_entity_decode( wp_strip_all_tags( $cic_l ), ENT_QUOTES, 'UTF-8' ) ) );
	}
}
echo cic_theme_section_open( 'cic-home', $args['section'], 'cic-home-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--wide cic-home-hero__grid">
		<div class="cic-home-hero__text">
			<?php if ( '' !== $cic_hero['eyebrow'] ) : ?>
				<p class="cic-eyebrow"><?php echo esc_html( $cic_hero['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="cic-home-hero__title"<?php echo $cic_chars ? ' style="--cic-hero-chars:' . (int) $cic_chars . '"' : ''; ?>><span class="cic-swoosh" aria-hidden="true"></span><?php echo $cic_hero['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
			<?php if ( '' !== $cic_hero['intro'] ) : ?>
				<p class="cic-lead"><?php echo esc_html( $cic_hero['intro'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="cic-home-hero__media">
			<span class="cic-home-hero__blob" aria-hidden="true"></span>
			<span class="cic-home-hero__ring" aria-hidden="true"></span>
			<span class="cic-swoosh cic-swoosh--light cic-home-hero__spark" aria-hidden="true"></span>
			<?php if ( $cic_imgs[0] ) : ?>
				<div class="cic-home-hero__main"><?php echo cic_theme_image( $cic_imgs[0], '(max-width: 899px) 100vw, 560px', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
			<?php if ( $cic_imgs[1] ) : ?>
				<div class="cic-home-hero__inset cic-home-hero__inset--1"><?php echo cic_theme_image( $cic_imgs[1], '(max-width: 899px) 40vw, 200px', 'eager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
			<?php if ( $cic_imgs[2] ) : ?>
				<div class="cic-home-hero__inset cic-home-hero__inset--2"><?php echo cic_theme_image( $cic_imgs[2], '(max-width: 899px) 40vw, 220px', 'eager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
		</div>
	</div>
	<div class="cic-container cic-home-hero__search">
		<?php get_template_part( 'template-parts/components/hero-search', null, array( 'search' => $cic_vm['search'], 'id' => 'cic-home-search' ) ); ?>
	</div>
</section>
