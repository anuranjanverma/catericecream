<?php
/**
 * Home "how it works" (anchor target for the How It Works menu item).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_section       = $args['section'];
$cic_section['key'] = 'how-it-works';
echo cic_theme_section_open( 'cic-home', $cic_section, 'cic-section cic-home-process' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<?php get_template_part( 'template-parts/components/process', null, array( 'process' => $args['vm']['process'] ) ); ?>
	</div>
</section>
