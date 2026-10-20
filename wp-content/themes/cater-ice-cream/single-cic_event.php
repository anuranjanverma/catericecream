<?php
/**
 * Single event. Each composition (designs/04 Birthday, 05 Wedding, 06 Anniversary, 07 Corporate)
 * has its own approved sections and order (CIC\Content\Fields::EVENT_LAYOUT_SECTIONS) and its own
 * section variants where the references differ, so the pages are not one reordered template.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

/** Section variants per composition; anything not listed uses section-{key}.php (Birthday's). */
$cic_variants = array(
	'wedding'     => array( 'hero' => 'hero-wedding', 'treats' => 'treats-brands', 'planning' => 'planning-band', 'quote' => 'quote-panel' ),
	'anniversary' => array( 'hero' => 'hero-split', 'treats' => 'treats-brands', 'planning' => 'planning-band', 'quote' => 'quote-panel' ),
	'corporate'   => array( 'hero' => 'hero-split', 'treats' => 'treats-brands', 'planning' => 'planning-band', 'quote' => 'quote-panel' ),
	'standard'    => array( 'hero' => 'hero-split', 'treats' => 'treats-brands', 'planning' => 'planning-band', 'quote' => 'quote-panel' ),
);

get_header();
while ( have_posts() ) {
	the_post();
	if ( class_exists( '\CIC\Content\ViewModel' ) ) {
		$cic_vm  = \CIC\Content\ViewModel::event( get_the_ID() );
		$cic_map = $cic_variants[ $cic_vm['layout'] ] ?? array();
		?>
		<article class="cic-event cic-event--<?php echo esc_attr( $cic_vm['layout'] ); ?>" data-cic-event="<?php echo esc_attr( $cic_vm['key'] ); ?>">
			<?php
			foreach ( $cic_vm['sections'] as $cic_section ) {
				get_template_part( 'template-parts/event/section', $cic_map[ $cic_section['key'] ] ?? $cic_section['key'], array( 'vm' => $cic_vm, 'section' => $cic_section ) );
			}
			?>
		</article>
		<?php
	} else {
		get_template_part( 'template-parts/content' );
	}
}
get_footer();
