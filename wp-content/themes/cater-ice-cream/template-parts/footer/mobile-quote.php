<?php
/**
 * Fixed "Get a Quote" button for phones (below 600 px; main.css §10d, main.js). A plain link that
 * works without JavaScript; the script only hides it while the menu drawer or another dialog is open
 * or a form field has focus. Not printed on the Get a Quote page (see cic_theme_mobile_cta_url()).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_cta_url = cic_theme_mobile_cta_url();
if ( '' === $cic_cta_url ) {
	return;
}
?>
<div class="cic-mobile-cta" data-cic-mobile-cta>
	<a class="cic-button cic-mobile-cta__button" href="<?php echo esc_url( $cic_cta_url ); ?>"><?php echo esc_html( cic_theme_contact()['quote_label'] ); ?> <span aria-hidden="true">→</span></a>
</div>
