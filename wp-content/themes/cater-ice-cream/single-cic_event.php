<?php
/**
 * Single event. The composition comes from the event's layout setting; Birthday is the approved
 * slice composition (designs/04_birthday_final.png). Other layouts use the standard composition
 * with the same editable sections until Phase 07.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) {
	the_post();
	if ( class_exists( '\CIC\Content\ViewModel' ) ) {
		$cic_vm = \CIC\Content\ViewModel::event( get_the_ID() );
		?>
		<article class="cic-event cic-event--<?php echo esc_attr( $cic_vm['layout'] ); ?>" data-cic-event="<?php echo esc_attr( $cic_vm['key'] ); ?>">
			<?php
			foreach ( $cic_vm['sections'] as $cic_section ) {
				get_template_part( 'template-parts/event/section', $cic_section['key'], array( 'vm' => $cic_vm, 'section' => $cic_section ) );
			}
			?>
		</article>
		<?php
	} else {
		get_template_part( 'template-parts/content' );
	}
}
get_footer();
