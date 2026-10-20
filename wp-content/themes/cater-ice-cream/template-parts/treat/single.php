<?php
/**
 * Treat page composition (approved references: 09 Dippin' Dots and 10 DipN' Bananas share the
 * split composition; 11 Classic Ice Cream and 12 Frozen Yogurt have their own). Sections follow the
 * composition's approved order (CIC\Content\Fields::TREAT_LAYOUT_SECTIONS); variants below swap
 * the sections whose layout differs.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm       = $args['vm'];
$cic_variants = array(
	'classic' => array( 'hero' => 'hero-classic', 'occasions' => 'occasions-rows', 'quote' => 'quote-faq' ),
	'yogurt'  => array( 'hero' => 'hero-yogurt', 'occasions' => 'occasions-cards', 'quote' => 'quote-trio' ),
);
$cic_map      = $cic_variants[ $cic_vm['layout'] ] ?? array();
?>
<article class="cic-treat cic-treat--<?php echo esc_attr( $cic_vm['layout'] ); ?>" data-cic-treat="<?php echo esc_attr( $cic_vm['key'] ); ?>">
	<?php
	foreach ( $cic_vm['sections'] as $cic_section ) {
		get_template_part( 'template-parts/treat/section', $cic_map[ $cic_section['key'] ] ?? $cic_section['key'], array( 'vm' => $cic_vm, 'section' => $cic_section ) );
	}
	?>
</article>
