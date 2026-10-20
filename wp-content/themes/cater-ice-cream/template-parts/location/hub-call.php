<?php
/**
 * "Not sure about your area?" call panel.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_c = $args['vm']['call'];
echo cic_theme_section_open( 'cic-areas', $args['section'], 'cic-areas-call' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<?php
		get_template_part(
			'template-parts/components/call-panel',
			null,
			array(
				'heading'     => $cic_c['heading'],
				'text'        => $cic_c['text'],
				'image'       => $cic_c['image'],
				'quote_label' => $cic_c['quote_label'],
				'quote_url'   => $cic_c['quote_url'],
			)
		);
		?>
	</div>
</section>
