<?php
/**
 * "Let's start with where you're celebrating." quote starter (location, date, event type) that
 * carries only validated, non-personal choices into the quote route. A confirmed outside-area
 * location blocks continuing (context.js asks the shared coverage service first).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_s = $args['vm']['start'];
echo cic_theme_section_open( 'cic-areas', $args['section'], 'cic-section cic-areas-start' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<div class="cic-areas-start__panel">
			<h2 class="cic-areas-start__title"><?php echo $cic_s['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php if ( '' !== $cic_s['intro'] ) : ?>
				<p class="cic-areas-start__intro"><?php echo esc_html( $cic_s['intro'] ); ?></p>
			<?php endif; ?>
			<?php get_template_part( 'template-parts/components/hero-search', null, array( 'search' => $cic_s['search'], 'id' => 'cic-areas-start' ) ); ?>
			<span class="cic-areas-start__spark" aria-hidden="true"><span></span><span></span><span></span></span>
		</div>
	</div>
</section>
