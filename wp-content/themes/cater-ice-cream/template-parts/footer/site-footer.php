<?php
/**
 * Shared dynamic footer: logo, intro, supported column layouts, native menus, contact/social
 * links and the legal row. Settings: Cater Ice Cream → Header, Footer & Contact.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_footer  = cic_theme_layout()['footer'];
$cic_contact = cic_theme_contact();
$cic_layout  = (string) $cic_footer['layout'];
$cic_menu_1  = 'brand-contact' !== $cic_layout ? cic_theme_menu( 'footer', array( 'depth' => 1 ) ) : '';
$cic_menu_2  = 'brand-menus-contact' === $cic_layout ? cic_theme_menu( 'footer_secondary', array( 'depth' => 1 ) ) : '';
$cic_legal   = ! empty( $cic_footer['show_legal'] ) ? cic_theme_menu( 'legal', array( 'depth' => 1 ) ) : '';
$cic_social  = ! empty( $cic_footer['show_social'] ) ? $cic_contact['social'] : array();
// Privacy Policy: added to the legal row automatically once a policy page is PUBLISHED (core
// Settings → Privacy) and the legal menu does not already link to it. Drafts never show.
$cic_privacy = ! empty( $cic_footer['show_legal'] ) ? get_privacy_policy_url() : '';
if ( '' !== $cic_privacy && false === strpos( $cic_legal, esc_url( $cic_privacy ) ) ) {
	$cic_item  = '<li class="menu-item menu-item-privacy-policy">' . get_the_privacy_policy_link() . '</li>';
	$cic_legal = '' !== $cic_legal ? (string) preg_replace( '#</ul>\s*$#', $cic_item . '</ul>', $cic_legal, 1 ) : '<ul class="menu">' . $cic_item . '</ul>';
}
?>
<footer class="cic-footer" style="<?php echo esc_attr( '--cic-footer-logo-w:' . (int) $cic_footer['logo_width'] . 'px' ); ?>">
	<div class="cic-container cic-footer__grid cic-footer--<?php echo esc_attr( $cic_layout ); ?>">
		<div class="cic-footer__brand">
			<a class="cic-footer__logo<?php echo ! empty( $cic_footer['logo_plate'] ) ? ' has-plate' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php echo cic_theme_footer_logo( $cic_footer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</a>
			<?php if ( '' !== trim( (string) $cic_footer['intro'] ) ) : ?>
				<p class="cic-footer__intro"><?php echo esc_html( $cic_footer['intro'] ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( 'brand-contact' !== $cic_layout ) : ?>
			<nav class="cic-nav cic-footer__menu" aria-label="<?php esc_attr_e( 'Footer', 'cater-ice-cream' ); ?>">
				<?php echo $cic_menu_1; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core menu markup. ?>
			</nav>
		<?php endif; ?>
		<?php if ( 'brand-menus-contact' === $cic_layout ) : ?>
			<nav class="cic-nav cic-footer__menu" aria-label="<?php esc_attr_e( 'Footer (more)', 'cater-ice-cream' ); ?>">
				<?php echo $cic_menu_2; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core menu markup. ?>
			</nav>
		<?php endif; ?>

		<div class="cic-footer__contact">
			<?php if ( ! empty( $cic_footer['show_contact'] ) ) : ?>
				<?php if ( '' !== $cic_contact['website_label'] ) : ?>
					<p class="cic-footer__site"><?php echo esc_html( $cic_contact['website_label'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $cic_footer['show_phone'] ) && '' !== $cic_contact['phone_href'] ) : ?>
					<p><a href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo esc_html( $cic_contact['phone'] ); ?></a></p>
				<?php endif; ?>
				<?php if ( ! empty( $cic_footer['show_email'] ) && '' !== $cic_contact['email'] ) : ?>
					<p><a href="<?php echo esc_url( 'mailto:' . $cic_contact['email'] ); ?>"><?php echo esc_html( $cic_contact['email'] ); ?></a></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $cic_social ) : ?>
				<ul class="cic-inline-list" aria-label="<?php esc_attr_e( 'Social profiles', 'cater-ice-cream' ); ?>">
					<?php foreach ( $cic_social as $cic_profile ) : ?>
						<li><a href="<?php echo esc_url( $cic_profile['url'] ); ?>" rel="noopener"><?php echo esc_html( $cic_profile['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $cic_footer['show_legal'] ) ) : ?>
		<div class="cic-footer__legal">
			<div class="cic-container cic-footer__legal-inner">
				<p><?php echo esc_html( $cic_footer['copyright_text'] ); ?></p>
				<?php if ( '' !== $cic_legal ) : ?>
					<nav class="cic-nav" aria-label="<?php esc_attr_e( 'Legal', 'cater-ice-cream' ); ?>">
						<?php echo $cic_legal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core menu markup. ?>
					</nav>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</footer>
