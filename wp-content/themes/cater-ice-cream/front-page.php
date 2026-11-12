<?php
/**
 * Homepage (approved references: designs/01_home_desktop.png, designs/02_home_mobile.png).
 * Content comes from the "Homepage composition" fields on the static front page; treats and
 * events come from published catalogue records. Sections follow the editor-controlled order.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
if ( class_exists( '\CIC\Content\ViewModel' ) && 'page' === get_option( 'show_on_front' ) && is_front_page() ) {
	$cic_vm = \CIC\Content\ViewModel::home( (int) get_option( 'page_on_front' ) );
	echo '<div class="cic-home">';
	foreach ( $cic_vm['sections'] as $cic_section ) {
		get_template_part( 'template-parts/home/section', $cic_section['key'], array( 'vm' => $cic_vm, 'section' => $cic_section ) );
	}
	echo '</div>';
} else {
	get_template_part( 'index' );
}
get_footer();
