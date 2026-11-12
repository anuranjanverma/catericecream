<?php
/**
 * Branded 404 (approved layout designs/19_policy_and_404.png). WordPress sends the real 404
 * status before this template renders (unknown routes, drafts, unknown city slugs). The page is
 * never indexed (core adds noindex on 404s).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
$cic_contact = cic_theme_contact();
$cic_shop    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' );
?>
<div class="cic-pages cic-404-page">
	<section class="cic-404">
		<div class="cic-container cic-container--wide cic-404__grid">
			<span class="cic-404__art"><?php echo cic_theme_mascot( 360, array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			<div class="cic-404__text">
				<p class="cic-404__code" aria-hidden="true">404</p>
				<h1 class="cic-404__title"><?php esc_html_e( 'This scoop went missing.', 'cater-ice-cream' ); ?></h1>
				<p class="cic-lead"><?php esc_html_e( "The page you're looking for may have moved. Let's help you find your next sweet moment.", 'cater-ice-cream' ); ?></p>
				<div class="cic-hero__actions">
					<a class="cic-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to homepage', 'cater-ice-cream' ); ?> <span aria-hidden="true">→</span></a>
					<a class="cic-button cic-button--secondary" href="<?php echo esc_url( $cic_shop ); ?>"><?php esc_html_e( 'Explore our treats', 'cater-ice-cream' ); ?></a>
				</div>
				<p class="cic-404__quote"><a class="cic-text-link" href="<?php echo esc_url( $cic_contact['quote_url'] ); ?>"><?php esc_html_e( 'Planning an event? Get a quote', 'cater-ice-cream' ); ?> <span aria-hidden="true">→</span></a></p>
			</div>
		</div>
	</section>
</div>
<?php
get_footer();
