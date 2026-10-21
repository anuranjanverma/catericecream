<?php
/**
 * Shared header: independently editable top, main and bottom rows, plus the mobile drawer.
 * Settings: Cater Ice Cream → Header, Footer & Contact. Menus: Appearance → Menus.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_layout  = cic_theme_layout();
$cic_contact = cic_theme_contact();
$cic_top     = $cic_layout['top'];
$cic_main    = $cic_layout['main'];
$cic_bottom  = $cic_layout['bottom'];
$cic_mobile  = $cic_layout['mobile'];

$cic_top_links = array();
if ( ! empty( $cic_top['enabled'] ) ) {
	if ( ! empty( $cic_top['show_phone'] ) && '' !== $cic_contact['phone_href'] ) {
		$cic_top_links[] = array( $cic_contact['phone_href'], $cic_contact['phone'] );
	}
	if ( ! empty( $cic_top['show_email'] ) && '' !== $cic_contact['email'] ) {
		$cic_top_links[] = array( 'mailto:' . $cic_contact['email'], $cic_contact['email'] );
	}
	if ( ! empty( $cic_top['show_social'] ) ) {
		foreach ( $cic_contact['social'] as $cic_profile ) {
			$cic_top_links[] = array( $cic_profile['url'], $cic_profile['label'] );
		}
	}
}
$cic_has_top    = ! empty( $cic_top['enabled'] ) && ( '' !== trim( (string) ( $cic_top['text'] ?? '' ) ) || $cic_top_links );
$cic_has_bottom = ! empty( $cic_bottom['enabled'] ) && has_nav_menu( 'secondary' );
$cic_primary    = cic_theme_menu(
	'primary',
	array(
		'depth'      => 2,
		'menu_class' => 'menu',
		'menu_id'    => 'cic-primary-menu',
	)
);
?>
<header class="cic-header<?php echo ! empty( $cic_main['sticky'] ) ? ' is-sticky' : ''; ?>" data-cic-header<?php echo ! empty( $cic_main['sticky'] ) && ! empty( $cic_main['compact'] ) ? ' data-cic-compact' : ''; ?> style="<?php echo esc_attr( '--cic-logo-w:' . (int) $cic_main['logo_width'] . 'px;--cic-logo-w-mobile:' . (int) $cic_main['logo_width_mobile'] . 'px;--cic-logo-w-compact:' . (int) ( $cic_main['logo_width_compact'] ?? 140 ) . 'px' ); ?>">
	<?php if ( $cic_has_top ) : ?>
		<div class="cic-header__row cic-header__top <?php echo esc_attr( cic_theme_row_classes( $cic_top ) ); ?>" style="<?php echo esc_attr( '--cic-row-bg:' . cic_theme_color( $cic_top['surface'] ) . ';--cic-row-fg:' . cic_theme_color( $cic_top['text_color'] ) ); ?>">
			<div class="cic-container cic-header__top-inner">
				<?php if ( '' !== trim( (string) $cic_top['text'] ) ) : ?>
					<p class="cic-header__announce"><?php echo esc_html( $cic_top['text'] ); ?></p>
				<?php endif; ?>
				<?php if ( $cic_top_links ) : ?>
					<ul class="cic-inline-list">
						<?php foreach ( $cic_top_links as $cic_link ) : ?>
							<li><a href="<?php echo esc_url( $cic_link[0] ); ?>"><?php echo esc_html( $cic_link[1] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="cic-header__main">
		<div class="cic-container cic-container--wide cic-header__main-inner">
			<a class="cic-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php echo cic_theme_header_logo( $cic_main ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			</a>
			<?php if ( '' !== $cic_primary ) : ?>
				<nav class="cic-nav cic-nav--primary" aria-label="<?php esc_attr_e( 'Primary', 'cater-ice-cream' ); ?>" data-cic-nav>
					<?php echo $cic_primary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core menu markup. ?>
				</nav>
			<?php endif; ?>
			<div class="cic-header__actions">
				<?php if ( ! empty( $cic_main['show_call'] ) && '' !== $cic_contact['phone_href'] ) : ?>
					<a class="cic-header__call" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $cic_main['show_quote'] ) ) : ?>
					<a class="cic-button cic-header__quote" href="<?php echo esc_url( $cic_contact['quote_url'] ); ?>"><?php echo esc_html( $cic_contact['quote_label'] ); ?> <span aria-hidden="true">→</span></a>
				<?php endif; ?>
				<button type="button" class="cic-menu-toggle" aria-expanded="false" aria-controls="cic-drawer" data-cic-drawer-open>
					<span class="cic-menu-toggle__bars" aria-hidden="true"></span>
					<span class="cic-menu-toggle__label"><?php esc_html_e( 'Menu', 'cater-ice-cream' ); ?></span>
				</button>
			</div>
		</div>
	</div>

	<?php if ( $cic_has_bottom ) : ?>
		<div class="cic-header__row cic-header__bottom <?php echo esc_attr( cic_theme_row_classes( $cic_bottom ) ); ?>" style="<?php echo esc_attr( '--cic-row-bg:' . cic_theme_color( $cic_bottom['surface'] ) . ';--cic-row-fg:' . cic_theme_color( $cic_bottom['text_color'] ) ); ?>">
			<div class="cic-container cic-header__bottom-inner">
				<nav class="cic-nav" aria-label="<?php esc_attr_e( 'Secondary', 'cater-ice-cream' ); ?>">
					<?php echo cic_theme_menu( 'secondary', array( 'depth' => 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core menu markup. ?>
				</nav>
			</div>
		</div>
	<?php endif; ?>
</header>

<div class="cic-drawer" id="cic-drawer" role="dialog" aria-modal="true" aria-labelledby="cic-drawer-title" hidden data-cic-drawer>
	<div class="cic-drawer__backdrop" data-cic-drawer-close></div>
	<div class="cic-drawer__panel">
		<div class="cic-drawer__head">
			<p class="cic-drawer__title" id="cic-drawer-title"><?php esc_html_e( 'Menu', 'cater-ice-cream' ); ?></p>
			<button type="button" class="cic-drawer__close" data-cic-drawer-close aria-label="<?php esc_attr_e( 'Close menu', 'cater-ice-cream' ); ?>"></button>
		</div>
		<?php if ( '' !== $cic_primary ) : ?>
			<nav class="cic-nav cic-drawer__nav" aria-label="<?php esc_attr_e( 'Primary', 'cater-ice-cream' ); ?>" data-cic-nav>
				<?php echo cic_theme_menu( 'primary', array( 'depth' => 2, 'menu_class' => 'menu', 'menu_id' => 'cic-drawer-menu' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core menu markup. ?>
			</nav>
		<?php endif; ?>
		<?php if ( ! empty( $cic_mobile['show_secondary'] ) && has_nav_menu( 'secondary' ) ) : ?>
			<nav class="cic-nav cic-drawer__nav" aria-label="<?php esc_attr_e( 'Secondary', 'cater-ice-cream' ); ?>">
				<?php echo cic_theme_menu( 'secondary', array( 'depth' => 1, 'menu_class' => 'menu', 'menu_id' => 'cic-drawer-secondary' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core menu markup. ?>
			</nav>
		<?php endif; ?>
		<div class="cic-drawer__actions">
			<?php if ( ! empty( $cic_mobile['show_quote'] ) ) : ?>
				<a class="cic-button" href="<?php echo esc_url( $cic_contact['quote_url'] ); ?>"><?php echo esc_html( $cic_contact['quote_label'] ); ?> <span aria-hidden="true">→</span></a>
			<?php endif; ?>
			<?php if ( ! empty( $cic_mobile['show_call'] ) && '' !== $cic_contact['phone_href'] ) : ?>
				<a class="cic-button cic-button--secondary" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $cic_mobile['show_email'] ) && '' !== $cic_contact['email'] ) : ?>
				<a class="cic-drawer__contact" href="<?php echo esc_url( 'mailto:' . $cic_contact['email'] ); ?>"><?php echo esc_html( $cic_contact['email'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</div>
