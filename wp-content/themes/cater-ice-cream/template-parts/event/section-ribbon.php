<?php
/**
 * Wedding ribbon (05): three short phrases between fine champagne rules.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_items = $args['vm']['ribbon'];
echo cic_theme_section_open( 'cic-event', $args['section'], 'cic-ribbon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-ribbon__inner">
		<ul class="cic-ribbon__list">
			<?php foreach ( $cic_items as $cic_item ) : ?>
				<li><?php echo esc_html( $cic_item ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
