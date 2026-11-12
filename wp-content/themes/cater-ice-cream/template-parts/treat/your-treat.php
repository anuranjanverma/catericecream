<?php
/**
 * "Your treat:" pill with the approved original mascot (09–12).
 *
 * Args: title.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="cic-your-treat"><span class="cic-your-treat__mascot"><?php echo cic_theme_mascot( 56 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span><span><span class="cic-caption"><?php esc_html_e( 'Your treat:', 'cater-ice-cream' ); ?></span> <strong><?php echo esc_html( $args['title'] ); ?></strong></span></p>
