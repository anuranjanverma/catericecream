<?php
/**
 * "Planning in {city}? Let's talk treats." call panel (full-width plum band with photo).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_c = $args['vm']['call'];
if ( '' === $cic_c['heading'] ) {
	return;
}
$cic_contact = cic_theme_contact();
echo cic_theme_section_open( 'cic-city', $args['section'], 'cic-city-call' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
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
				'note'        => $cic_c['note'],
				'quote_label' => __( 'Prefer a quote?', 'cater-ice-cream' ),
				'quote_url'   => \CIC\Quote\Context::url( array( 'location' => $args['vm']['city']['key'] ) ),
			)
		);
		?>
	</div>
</section>
