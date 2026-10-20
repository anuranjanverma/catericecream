<?php
/**
 * Template helpers for the shared header and footer. Settings come from the core plugin; when it is
 * inactive the theme falls back to a minimal header and footer instead of failing.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

/**
 * Header/footer settings (core plugin) or minimal fallbacks.
 *
 * @return array<string, array<string, mixed>>
 */
function cic_theme_layout(): array {
	if ( class_exists( '\CIC\Site\Layout' ) ) {
		return \CIC\Site\Layout::get();
	}
	return array(
		'top'    => array( 'enabled' => false ),
		'main'   => array(
			'logo_id'           => 0,
			'logo_mobile_id'    => 0,
			'logo_width'        => 190,
			'logo_width_mobile' => 136,
			'show_call'         => false,
			'show_quote'        => true,
			'sticky'            => true,
		),
		'bottom' => array( 'enabled' => false ),
		'mobile' => array(
			'show_call'      => false,
			'show_quote'     => true,
			'show_secondary' => false,
			'show_email'     => false,
		),
		'footer' => array(
			'logo_id'        => 0,
			'logo_width'     => 168,
			'logo_plate'     => true,
			'intro'          => '',
			'layout'         => 'brand-menu-contact',
			'show_contact'   => false,
			'show_phone'     => false,
			'show_email'     => false,
			'show_social'    => false,
			'show_legal'     => true,
			'copyright_text' => '© ' . wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ),
		),
	);
}

/**
 * Public contact details (core plugin) or minimal fallbacks.
 *
 * @return array<string, mixed>
 */
function cic_theme_contact(): array {
	if ( class_exists( '\CIC\Site\Contact' ) ) {
		return \CIC\Site\Contact::get();
	}
	return array(
		'business_name'     => get_bloginfo( 'name' ),
		'legal_entity'      => '',
		'phone'             => '',
		'phone_href'        => '',
		'email'             => '',
		'quote_label'       => __( 'Get a Quote', 'cater-ice-cream' ),
		'quote_url'         => home_url( '/get-a-quote/' ),
		'call_label'        => __( 'Call', 'cater-ice-cream' ),
		'availability_note' => '',
		'website_label'     => '',
		'social'            => array(),
	);
}

/**
 * CSS value for a colour token key, e.g. text_primary → var(--wp--preset--color--text-primary).
 */
function cic_theme_color( string $token ): string {
	if ( class_exists( '\CIC\Design\Schema' ) && isset( \CIC\Design\Schema::colors()[ $token ] ) ) {
		return 'var(--wp--preset--color--' . \CIC\Design\Schema::colors()[ $token ]['slug'] . ')';
	}
	return 'var(--wp--preset--color--' . sanitize_html_class( str_replace( '_', '-', $token ) ) . ')';
}

/**
 * Visibility classes for a header row.
 *
 * @param array<string, mixed> $row Row settings.
 */
function cic_theme_row_classes( array $row ): string {
	$classes = array();
	if ( empty( $row['show_desktop'] ) ) {
		$classes[] = 'cic-hide-desktop';
	}
	if ( empty( $row['show_mobile'] ) ) {
		$classes[] = 'cic-hide-mobile';
	}
	return implode( ' ', $classes );
}

/**
 * The approved original logo bundled with the theme (BUILD_SPECIFICATION §5: artwork unchanged).
 *
 * @param array<string, mixed> $attrs Extra attributes.
 */
function cic_theme_bundled_logo( int $width, array $attrs = array() ): string {
	$base   = get_template_directory_uri() . '/assets/brand/logo-original-';
	$srcset = $base . '384.webp 384w, ' . $base . '576.webp 576w, ' . $base . '768.webp 768w';
	$attrs  = array_merge(
		array(
			'src'      => $base . '576.webp',
			'srcset'   => $srcset,
			'sizes'    => $width . 'px',
			'width'    => $width,
			'height'   => (int) round( $width / 2 ),
			'alt'      => cic_theme_contact()['business_name'],
			'decoding' => 'async',
		),
		$attrs
	);
	$html = '<img';
	foreach ( $attrs as $name => $value ) {
		$html .= ' ' . esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
	}
	return $html . '>';
}

/**
 * Header logo: optional Media Library desktop/mobile logos, else the bundled original.
 *
 * @param array<string, mixed> $main Main row settings.
 */
function cic_theme_header_logo( array $main ): string {
	$name   = cic_theme_contact()['business_name'];
	$width  = (int) $main['logo_width'];
	$mobile = (int) $main['logo_mobile_id'];
	if ( ! empty( $main['logo_id'] ) && wp_attachment_is_image( (int) $main['logo_id'] ) ) {
		$img = wp_get_attachment_image(
			(int) $main['logo_id'],
			'medium_large',
			false,
			array(
				'alt'           => $name,
				'sizes'   => $width . 'px',
				'loading' => 'eager',
			)
		);
	} else {
		$img = cic_theme_bundled_logo(
			$width,
			array( 'loading' => 'eager' )
		);
	}
	if ( $mobile && wp_attachment_is_image( $mobile ) ) {
		$src = wp_get_attachment_image_srcset( $mobile, 'medium_large' );
		$src = $src ? $src : wp_get_attachment_image_url( $mobile, 'medium_large' );
		$img = '<picture><source media="(max-width: 1023.98px)" srcset="' . esc_attr( (string) $src ) . '">' . $img . '</picture>';
	}
	return $img;
}

/**
 * Footer logo.
 *
 * @param array<string, mixed> $footer Footer settings.
 */
function cic_theme_footer_logo( array $footer ): string {
	$width = (int) $footer['logo_width'];
	if ( ! empty( $footer['logo_id'] ) && wp_attachment_is_image( (int) $footer['logo_id'] ) ) {
		return wp_get_attachment_image(
			(int) $footer['logo_id'],
			'medium_large',
			false,
			array(
				'alt'     => cic_theme_contact()['business_name'],
				'sizes'   => $width . 'px',
				'loading' => 'lazy',
			)
		);
	}
	return cic_theme_bundled_logo( $width, array( 'loading' => 'lazy' ) );
}

/**
 * A native menu for a location, or nothing when none is assigned.
 *
 * @param array<string, mixed> $args Extra wp_nav_menu() arguments.
 */
function cic_theme_menu( string $location, array $args = array() ): string {
	if ( ! has_nav_menu( $location ) ) {
		return '';
	}
	return (string) wp_nav_menu(
		array_merge(
			array(
				'theme_location' => $location,
				'container'      => false,
				'echo'           => false,
				'fallback_cb'    => false,
			),
			$args
		)
	);
}
