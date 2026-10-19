<?php
/**
 * Treat page composition (approved reference: designs/09_treat_dippin_dots.png).
 * Sections render in the editor-controlled order; empty or hidden sections are skipped.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
?>
<article class="cic-treat" data-cic-treat="<?php echo esc_attr( $cic_vm['key'] ); ?>">
	<?php
	foreach ( $cic_vm['sections'] as $cic_section ) {
		get_template_part( 'template-parts/treat/section', $cic_section['key'], array( 'vm' => $cic_vm, 'section' => $cic_section ) );
	}
	?>
</article>
