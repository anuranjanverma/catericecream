<?php
/**
 * Helpers for treat and event compositions: icons, images, breadcrumbs, section wrappers and
 * decorative SVGs. Data comes from the core plugin's CIC\Content\ViewModel.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline line icon (decorative; hidden from assistive technology). Stroke uses currentColor.
 */
function cic_theme_icon( string $name ): string {
	$paths = array(
		'sparkle'    => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 15l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"/>',
		'heart'      => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
		'people'     => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M15 14.2c.6-.2 1.3-.3 2-.3 2.8 0 4.5 1.9 4.5 4.6"/>',
		'star'       => '<path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.8z"/>',
		'sun'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/>',
		'beads'      => '<circle cx="7" cy="8" r="2"/><circle cx="12" cy="6" r="2"/><circle cx="17" cy="8" r="2"/><circle cx="9" cy="13" r="2"/><circle cx="15" cy="13" r="2"/><circle cx="12" cy="18" r="2"/>',
		'cone'       => '<path d="M7 10a5 5 0 0 1 10 0"/><path d="M6.5 10h11L12 21z"/><path d="M9 14l5 3M15 13.5l-5 3"/>',
		'person'     => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.9 3.1-6.5 7-6.5s7 2.6 7 6.5"/>',
		'gift'       => '<rect x="4" y="9" width="16" height="11" rx="1.5"/><path d="M3 9h18M12 9v11"/><path d="M12 9c-1.5-3-5-3.5-5-1s3.5 1 5 1c1.5 0 5 1.5 5-1s-3.5-2-5 1"/>',
		'hand-heart' => '<path d="M3 15h3l4 2h4.5a1.5 1.5 0 0 0 0-3H11"/><path d="M6 15l4.5-3.5c1-.7 2.2-.6 3 .2L18 15l3-1.5"/><path d="M15 3.5c1-1 2.6-.9 3.4.1.9 1 .7 2.5-.3 3.4L15 9.8 11.9 7c-1-.9-1.2-2.4-.3-3.4.8-1 2.4-1.1 3.4-.1z"/>',
		'calendar'   => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
		'pin'        => '<path d="M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'chat'       => '<path d="M4 5.5h16v10H9l-5 4z"/>',
		'leaf'       => '<path d="M5 19c0-8 5-14 15-14 0 10-6 15-14 15"/><path d="M5 19c3-4 6-7 10-9"/>',
		'cupcake'    => '<path d="M6 12h12l-1.6 8H7.6z"/><path d="M9.5 12l.5 8M14.5 12l-.5 8"/><path d="M5.5 12a3 3 0 0 1 1.2-5.6A5.3 5.3 0 0 1 12 3.5a5.3 5.3 0 0 1 5.3 2.9 3 3 0 0 1 1.2 5.6"/><path d="M12 3.5V2"/>',
		'wand'       => '<path d="M4 20l10-10"/><path d="M15 3v3M13.5 4.5h3M19 7v2M18 8h2M18 13v2M17 14h2M10 4v1.5M9.25 4.75h1.5"/><path d="M12.5 11.5l2 2"/>',
		'home'       => '<path d="M3.5 11L12 4l8.5 7"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5.5h4V20"/>',
		'sundae'     => '<path d="M4 11h16a8 8 0 0 1-16 0z"/><path d="M9 19l-1 2h8l-1-2"/><path d="M7 11a5 5 0 0 1 10 0"/><circle cx="12" cy="4" r="1.5"/><path d="M12 5.5V6"/>',
		'phone'      => '<path d="M6.6 3.5h3l1.5 4-2 1.3a10 10 0 0 0 6.1 6.1l1.3-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z"/>',
		'chevron'    => '<path d="M9 6l6 6-6 6"/>',
		'arrow'      => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'arrow-down' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="cic-icon cic-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Responsive attachment image. Hero images pass $priority (eager, and fetchpriority only for LCP).
 *
 * @param array<string, string> $attrs Extra attributes.
 */
function cic_theme_image( int $id, string $sizes, string $priority = 'lazy', array $attrs = array() ): string {
	if ( ! $id ) {
		return '';
	}
	$attrs = array_merge( array( 'sizes' => $sizes, 'class' => 'cic-photo', 'decoding' => 'async' ), $attrs );
	if ( 'lcp' === $priority ) {
		$attrs['loading']       = 'eager';
		$attrs['fetchpriority'] = 'high';
	} elseif ( 'eager' === $priority ) {
		$attrs['loading'] = 'eager';
	} else {
		$attrs['loading'] = 'lazy';
	}
	return wp_get_attachment_image( $id, 'full', false, $attrs );
}

/**
 * Breadcrumb trail.
 *
 * @param array<int, array{0: string, 1: string}> $trail Label, URL (empty URL = current page).
 */
function cic_theme_breadcrumbs( array $trail ): string {
	$items = '';
	foreach ( $trail as $i => $crumb ) {
		$items .= '' === $crumb[1]
			? '<li><span aria-current="page">' . esc_html( $crumb[0] ) . '</span></li>'
			: '<li><a href="' . esc_url( $crumb[1] ) . '">' . esc_html( $crumb[0] ) . '</a></li>';
	}
	return '<nav class="cic-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'cater-ice-cream' ) . '"><ol>' . $items . '</ol></nav>';
}

/**
 * Opening tag for a composition section, applying an optional per-section preset.
 *
 * @param array{key: string, preset: string} $section Section from the view model.
 */
function cic_theme_section_open( string $prefix, array $section, string $extra = '', string $tag = 'section' ): string {
	$classes = array( 'cic-comp', $prefix . '__' . $section['key'] );
	if ( '' !== $section['preset'] ) {
		$classes[] = 'cic-ctx';
		$classes[] = 'cic-context-' . $section['preset'];
	}
	if ( '' !== $extra ) {
		$classes[] = $extra;
	}
	return '<' . $tag . ' class="' . esc_attr( implode( ' ', $classes ) ) . '" id="' . esc_attr( $section['key'] ) . '">';
}

/**
 * Balloon decoration (decorative SVG, colour from a semantic role).
 */
function cic_theme_balloon( string $class, string $role ): string {
	return '<svg class="cic-balloon ' . esc_attr( $class ) . '" style="--cic-balloon:var(--cic-color-' . esc_attr( $role ) . ')" viewBox="0 0 60 130" aria-hidden="true" focusable="false"><path d="M30 4C15 4 6 16 6 30c0 17 13 31 24 34 11-3 24-17 24-34C54 16 45 4 30 4z" fill="var(--cic-balloon)"/><path d="M26 64l4 6 4-6z" fill="var(--cic-balloon)"/><path d="M30 70c-6 12 8 20 0 32s6 18 2 26" fill="none" stroke="var(--cic-balloon)" stroke-width="1.5"/><ellipse cx="20" cy="22" rx="5" ry="9" fill="var(--wp--preset--color--surface-base)" opacity=".35"/></svg>';
}

/**
 * Confetti pieces (decorative). Positions are fixed per slot so the layout stays stable.
 */
function cic_theme_confetti( string $class = '' ): string {
	$pieces = '';
	for ( $i = 1; $i <= 10; $i++ ) {
		$pieces .= '<i class="cic-confetti__piece cic-confetti__piece--' . $i . '"></i>';
	}
	return '<span class="cic-confetti ' . esc_attr( $class ) . '" aria-hidden="true">' . $pieces . '</span>';
}

/** "Your treat" / quote link for a record (explicit selection carried to the quote flow). */
function cic_theme_arrow_link( string $url, string $label, string $class = 'cic-text-link' ): string {
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' <span aria-hidden="true">→</span></a>';
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$home     = is_front_page() && 'page' === get_option( 'show_on_front' );
		$location = is_page( 'service-areas' ) || ( class_exists( '\CIC\Locations\Routes' ) && null !== \CIC\Locations\Routes::current() );
		if ( $home || $location || is_page( 'get-a-quote' ) || is_singular( array( 'product', 'cic_event' ) ) || is_post_type_archive( array( 'product', 'cic_event' ) ) || ( function_exists( 'is_shop' ) && is_shop() ) ) {
			wp_enqueue_style( 'cic-compositions', get_template_directory_uri() . '/assets/css/compositions.css', array( 'cic-main' ), cic_theme_asset_version( 'assets/css/compositions.css' ) );
		}
		if ( $home || $location || is_page( 'get-a-quote' ) ) {
			wp_enqueue_style( 'cic-home', get_template_directory_uri() . '/assets/css/home.css', array( 'cic-compositions' ), cic_theme_asset_version( 'assets/css/home.css' ) );
		}
		if ( $location ) {
			wp_enqueue_style( 'cic-locations', get_template_directory_uri() . '/assets/css/locations.css', array( 'cic-home' ), cic_theme_asset_version( 'assets/css/locations.css' ) );
		}
		// Quote context helper on every page: explicit selections travel to the quote route.
		if ( class_exists( '\CIC\Quote\Context' ) ) {
			wp_enqueue_script( 'cic-context', get_template_directory_uri() . '/assets/js/context.js', array(), cic_theme_asset_version( 'assets/js/context.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
			$config             = \CIC\Quote\Context::client_config();
			$config['messages'] = array(
				'invalid'  => __( 'Please check the highlighted field: use a 5-digit ZIP code or a city name, and a date from today onward.', 'cater-ice-cream' ),
				'location' => __( 'Please enter a 5-digit ZIP code or a city name.', 'cater-ice-cream' ),
				'continue' => __( 'Continue to your quote', 'cater-ice-cream' ),
				'offline'  => __( "We couldn't check right now. Include your ZIP code in your quote request and we'll confirm service.", 'cater-ice-cream' ),
				'cityPage' => __( 'See the city page', 'cater-ice-cream' ),
				'fixture'  => __( 'Local test data, not approved coverage.', 'cater-ice-cream' ),
			);
			$config['coverageUrl']  = rest_url( 'cic/v1/coverage' );
			$config['locationsUrl'] = rest_url( 'cic/v1/locations' );
			wp_add_inline_script( 'cic-context', 'window.cicContext = ' . wp_json_encode( $config ) . ';', 'before' );
		}
	}
);
