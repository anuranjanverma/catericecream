<?php
/**
 * Service Areas / state hub composition: sections in the editor-controlled order.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
echo '<div class="cic-home cic-areas">';
foreach ( $cic_vm['sections'] as $cic_section ) {
	get_template_part( 'template-parts/location/hub', str_replace( 'areas_', '', $cic_section['key'] ), array( 'vm' => $cic_vm, 'section' => $cic_section ) );
}
echo '</div>';
