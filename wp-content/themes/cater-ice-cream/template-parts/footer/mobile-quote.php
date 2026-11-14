<?php
/**
 * Floating mobile actions (below 600 px; main.css §10d, main.js): a round call button on the left
 * (the central business phone, tel: link) and the "Inquiry" pill on the right, which opens the Get a
 * Quote page (owner request, 2026-11-13). Plain links that work without JavaScript; the script only
 * hides the group while the menu drawer or another dialog is open or a form field has focus. Not
 * printed on the Get a Quote page (see cic_theme_mobile_cta_url()).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_cta_url = cic_theme_mobile_cta_url();
if ( '' === $cic_cta_url ) {
	return;
}
$cic_mobile  = cic_theme_layout()['mobile'];
$cic_contact = cic_theme_contact();
$cic_label   = trim( (string) ( $cic_mobile['sticky_label'] ?? '' ) );
$cic_label   = '' !== $cic_label ? $cic_label : (string) $cic_contact['quote_label'];
$cic_call    = ! empty( $cic_mobile['sticky_call'] ) && '' !== (string) $cic_contact['phone_href'];
?>
<div class="cic-mobile-cta<?php echo $cic_call ? ' has-call' : ''; ?>" data-cic-mobile-cta>
	<?php if ( $cic_call ) : ?>
		<a class="cic-mobile-cta__call" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><span aria-hidden="true"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><span class="screen-reader-text"><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></span></a>
	<?php endif; ?>
	<a class="cic-button cic-mobile-cta__button" href="<?php echo esc_url( $cic_cta_url ); ?>"><?php echo esc_html( $cic_label ); ?></a>
</div>
