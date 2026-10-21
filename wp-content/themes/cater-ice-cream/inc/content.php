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
		// Party popper for event-type (occasion) fields; date fields keep the calendar.
		'party'      => '<path d="M4 20.5l3.7-11 7.3 7.3z"/><path d="M6.1 14.2l3.7 3.7"/><path d="M12.6 8.6c1.1-1.7.8-3.4-.4-4.6"/><path d="M15.4 11.4c1.7-1.1 3.4-.8 4.6.4"/><path d="M14.6 5.6l1.6-1.6"/><circle cx="18.6" cy="6.9" r=".6"/><circle cx="11.3" cy="2.9" r=".6"/><circle cx="21" cy="14.6" r=".6"/>',
		'pin'        => '<path d="M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'chat'       => '<path d="M4 5.5h16v10H9l-5 4z"/>',
		'check'      => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16.5 9.5"/>',
		'dots'       => '<circle cx="5.5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="18.5" cy="12" r="1.4"/>',
		'clock'      => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'leaf'       => '<path d="M5 19c0-8 5-14 15-14 0 10-6 15-14 15"/><path d="M5 19c3-4 6-7 10-9"/>',
		'cupcake'    => '<path d="M6 12h12l-1.6 8H7.6z"/><path d="M9.5 12l.5 8M14.5 12l-.5 8"/><path d="M5.5 12a3 3 0 0 1 1.2-5.6A5.3 5.3 0 0 1 12 3.5a5.3 5.3 0 0 1 5.3 2.9 3 3 0 0 1 1.2 5.6"/><path d="M12 3.5V2"/>',
		'wand'       => '<path d="M4 20l10-10"/><path d="M15 3v3M13.5 4.5h3M19 7v2M18 8h2M18 13v2M17 14h2M10 4v1.5M9.25 4.75h1.5"/><path d="M12.5 11.5l2 2"/>',
		'home'       => '<path d="M3.5 11L12 4l8.5 7"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5.5h4V20"/>',
		'sundae'     => '<path d="M4 11h16a8 8 0 0 1-16 0z"/><path d="M9 19l-1 2h8l-1-2"/><path d="M7 11a5 5 0 0 1 10 0"/><circle cx="12" cy="4" r="1.5"/><path d="M12 5.5V6"/>',
		'phone'      => '<path d="M6.6 3.5h3l1.5 4-2 1.3a10 10 0 0 0 6.1 6.1l1.3-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z"/>',
		'chevron'    => '<path d="M9 6l6 6-6 6"/>',
		'banana'     => '<path d="M5 6c-1 6 2 12 9 13 3 .4 5-.6 6-2-6 0-10-4-11-11z"/><path d="M5 6l-.5-2"/>',
		'spoon'      => '<ellipse cx="15.5" cy="7.5" rx="3.5" ry="4.5" transform="rotate(40 15.5 7.5)"/><path d="M13 10.5L5 19"/>',
		'cup'        => '<path d="M6 9h12l-1.6 11H7.6z"/><path d="M5 9h14"/><path d="M8 9a4 4 0 0 1 8 0"/><path d="M12 5V3"/>',
		'handshake'  => '<path d="M2.5 11l3.5-3.5 3 1 3-2 3.5 1 3.5-1 2.5 4"/><path d="M6 7.5l5.5 7a1.6 1.6 0 0 0 2.4.2l3.6-3.2"/><path d="M9.5 14.5l2 2M12 13l2 2M2.5 11l4 4.5"/>',
		'building'   => '<rect x="5" y="3.5" width="10" height="17" rx="1"/><path d="M15 9h4v11.5h-4"/><path d="M8 7h1.5M10.5 7H12M8 10.5h1.5M10.5 10.5H12M8 14h1.5M10.5 14H12M9 20.5V17h2v3.5"/>',
		'rings'      => '<circle cx="9" cy="14" r="5"/><circle cx="15" cy="14" r="5"/><path d="M10.5 6.5L12 4l1.5 2.5"/>',
		'briefcase'  => '<rect x="3.5" y="7.5" width="17" height="12" rx="2"/><path d="M9 7.5V5.5h6v2"/><path d="M3.5 12.5h17M11 12.5v2h2v-2"/>',
		'cake'       => '<path d="M4.5 20.5h15V13a2 2 0 0 0-2-2h-11a2 2 0 0 0-2 2z"/><path d="M4.5 15.5c1.5 1.3 3 1.3 4.5 0s3-1.3 4.5 0 3 1.3 4.5 0"/><path d="M8 11V8.5M12 11V8.5M16 11V8.5"/><path d="M8 6.5v-1M12 6.5v-1M16 6.5v-1"/>',
		'clipboard'  => '<rect x="5" y="4.5" width="14" height="16.5" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M8.5 13l2.5 2.5 4.5-5"/>',
		'mail'       => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3.5 7l8.5 6.5L20.5 7"/>',
		'gift-cups'  => '<path d="M3.5 10.5h8L10 20H5z"/><path d="M12.5 10.5h8L19 20h-5z"/><path d="M4.5 10.5a3 3 0 0 1 6 0M13.5 10.5a3 3 0 0 1 6 0"/><path d="M8 5l1 -2M16 5l-1-2M12 6V3.5"/>',
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
		// Explicit "auto" stops core from promoting a second image to high priority.
		$attrs['loading']       = 'eager';
		$attrs['fetchpriority'] = 'auto';
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

/**
 * Decorative line motifs drawn as real SVG (never screenshot slices). Stroke uses currentColor,
 * so colour comes from the page preset (e.g. Wedding champagne gold via --cic-color-decor).
 * Motion (where any) is CSS-only and disabled for reduced-motion users.
 */
function cic_theme_motif( string $name, string $class = '' ): string {
	$motifs = array(
		'rings'       => array( '0 0 80 60', '<circle cx="30" cy="36" r="15"/><circle cx="48" cy="36" r="15"/><path d="M62 10c-3-5-10-3-9 3 1 4 9 8 9 8s8-4 9-8c1-6-6-8-9-3z"/><path d="M4 56c18-10 30-4 46-12" class="cic-motif__trail"/>' ),
		'gold-wave'   => array( '0 0 1440 40', '<path d="M0 26c160 12 300-18 470-8s300 22 470 6 340-26 500-6" class="cic-motif__trail"/>' ),
		'botanical'   => array( '0 0 120 200', '<path d="M60 196C58 140 64 90 92 8"/><path d="M62 150c-20-6-34-20-40-40 20 2 36 16 40 40zM66 116c18-8 30-24 32-44-18 6-30 22-32 44zM70 82c-18-6-28-20-30-38 18 4 28 18 30 38zM78 50c14-6 22-18 24-34-14 6-22 18-24 34z"/>' ),
		'hearts'      => array( '0 0 120 70', '<path d="M30 58C12 46 6 34 10 24c4-10 18-10 22 0 4-10 18-10 22 0 4 12-6 24-24 34z"/><path d="M70 50c-14-9-18-18-15-26 3-7 13-7 16 0 3-7 13-7 16 0 3 9-4 18-17 26z"/><path d="M86 30c10-6 20-6 30 2" class="cic-motif__trail"/>' ),
		'sparkle'     => array( '0 0 48 48', '<path d="M24 6l3 12 12 3-12 3-3 12-3-12-12-3 12-3z"/><path d="M40 6l1 4 4 1-4 1-1 4-1-4-4-1 4-1z"/>' ),
		'strokes'     => array( '0 0 48 48', '<path d="M10 40l8-10M20 44l4-14M30 42l-2-12"/>' ),
		'skyline'     => array( '0 0 120 48', '<path d="M2 44h116M10 44V30h8v14M22 44V22h10v22M36 44V12h8v32M48 44V26h12v18M64 44V18h9v26M78 44V30h8v14M92 44V24h10v20"/><path d="M106 44V30M106 30c-3-4-7-5-10-4M106 30c3-4 7-5 10-4M106 30c-1-5 0-9 3-11"/>' ),
		'coast'       => array( '0 0 120 48', '<path d="M2 44h116"/><path d="M4 34l18-14 14 10 16-16 20 18 14-8 18 10 12-6"/><path d="M14 44c6-4 12-4 18 0s12 4 18 0 12-4 18 0"/><path d="M98 34V20M98 20c-3-4-7-4-10-2M98 20c3-4 7-4 10-2"/>' ),
		'mission'     => array( '0 0 120 48', '<path d="M2 44h116"/><path d="M40 44V26h40v18M48 26v-8h24v8M56 18v-8l4-4 4 4v8"/><path d="M54 44V34a6 6 0 0 1 12 0v10M60 10V4"/><path d="M14 44V30M14 30c-3-4-7-4-10-2M14 30c3-4 7-4 10-2M102 44V30M102 30c-3-4-7-4-10-2M102 30c3-4 7-4 10-2"/>' ),
		'people-line' => array( '0 0 96 64', '<circle cx="30" cy="22" r="8"/><circle cx="66" cy="22" r="8"/><circle cx="48" cy="16" r="9"/><path d="M14 56c0-10 8-18 16-18M82 56c0-10-8-18-16-18M30 58c0-12 8-22 18-22s18 10 18 22"/>' ),
	);
	if ( ! isset( $motifs[ $name ] ) ) {
		return '';
	}
	return '<svg class="cic-motif cic-motif--' . esc_attr( $name ) . ' ' . esc_attr( $class ) . '" viewBox="' . esc_attr( $motifs[ $name ][0] ) . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $motifs[ $name ][1] . '</svg>';
}

/** "Your treat" / quote link for a record (explicit selection carried to the quote flow). */
function cic_theme_arrow_link( string $url, string $label, string $class = 'cic-text-link' ): string {
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' <span aria-hidden="true">→</span></a>';
}

/**
 * Non-personal source context of the current page for lead attribution (template type + record
 * key), recorded by context.js only when the visitor takes an explicit quote action.
 *
 * @return array{type: string, key: string}
 */
function cic_theme_source_page(): array {
	$key = static fn( int $id ): string => class_exists( '\CIC\Content\Catalog' ) ? \CIC\Content\Catalog::key( $id ) : '';
	if ( is_front_page() ) {
		return array( 'type' => 'home', 'key' => '' );
	}
	if ( is_singular( 'cic_event' ) ) {
		return array( 'type' => 'event', 'key' => $key( (int) get_queried_object_id() ) );
	}
	if ( is_singular( 'product' ) ) {
		return array( 'type' => 'treat', 'key' => $key( (int) get_queried_object_id() ) );
	}
	$route = class_exists( '\CIC\Locations\Routes' ) ? \CIC\Locations\Routes::current() : null;
	if ( $route && 'city' === $route['type'] ) {
		return array( 'type' => 'city', 'key' => (string) $route['city']['key'] );
	}
	if ( $route || is_page( 'service-areas' ) ) {
		return array( 'type' => 'hub', 'key' => '' );
	}
	if ( is_page( 'contact' ) ) {
		return array( 'type' => 'contact', 'key' => '' );
	}
	if ( is_page( 'get-a-quote' ) ) {
		return array( 'type' => 'quote', 'key' => '' );
	}
	if ( is_post_type_archive( array( 'product', 'cic_event' ) ) || ( function_exists( 'is_shop' ) && is_shop() ) ) {
		return array( 'type' => 'catalogue', 'key' => '' );
	}
	return array( 'type' => 'other', 'key' => '' );
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$home     = is_front_page() && 'page' === get_option( 'show_on_front' );
		$location = is_page( 'service-areas' ) || ( class_exists( '\CIC\Locations\Routes' ) && null !== \CIC\Locations\Routes::current() );
		if ( $home || $location || is_page( 'get-a-quote' ) || is_singular( array( 'product', 'cic_event' ) ) || is_post_type_archive( array( 'product', 'cic_event' ) ) || ( function_exists( 'is_shop' ) && is_shop() ) ) {
			wp_enqueue_style( 'cic-compositions', get_template_directory_uri() . '/assets/css/compositions.css', array( 'cic-main' ), cic_theme_asset_version( 'assets/css/compositions.css' ) );
		}
		$pages = is_post_type_archive( array( 'product', 'cic_event' ) ) || ( function_exists( 'is_shop' ) && is_shop() ) || is_page( array( 'about', 'contact' ) ) || is_page_template( 'template-policy.php' ) || is_404();
		if ( $pages ) {
			wp_enqueue_style( 'cic-compositions', get_template_directory_uri() . '/assets/css/compositions.css', array( 'cic-main' ), cic_theme_asset_version( 'assets/css/compositions.css' ) );
			wp_enqueue_style( 'cic-home', get_template_directory_uri() . '/assets/css/home.css', array( 'cic-compositions' ), cic_theme_asset_version( 'assets/css/home.css' ) );
		}
		if ( $pages || is_page( array( 'about', 'contact' ) ) ) {
			wp_enqueue_style( 'cic-locations', get_template_directory_uri() . '/assets/css/locations.css', array( 'cic-home' ), cic_theme_asset_version( 'assets/css/locations.css' ) );
		}
		if ( is_singular( array( 'product', 'cic_event' ) ) || $pages ) {
			wp_enqueue_style( 'cic-details', get_template_directory_uri() . '/assets/css/details.css', array( 'cic-compositions' ), cic_theme_asset_version( 'assets/css/details.css' ) );
		}
		if ( $pages ) {
			wp_enqueue_style( 'cic-pages', get_template_directory_uri() . '/assets/css/pages.css', array( 'cic-details' ), cic_theme_asset_version( 'assets/css/pages.css' ) );
		}
		if ( $home || $location || is_page( 'get-a-quote' ) ) {
			wp_enqueue_style( 'cic-home', get_template_directory_uri() . '/assets/css/home.css', array( 'cic-compositions' ), cic_theme_asset_version( 'assets/css/home.css' ) );
		}
		if ( $location ) {
			wp_enqueue_style( 'cic-locations', get_template_directory_uri() . '/assets/css/locations.css', array( 'cic-home' ), cic_theme_asset_version( 'assets/css/locations.css' ) );
		}
		// California map interactions (Service Areas hub/state, About, Contact).
		if ( is_page( array( 'service-areas', 'about', 'contact' ) ) || ( class_exists( '\CIC\Locations\Routes' ) && null !== \CIC\Locations\Routes::current() ) ) {
			wp_enqueue_script( 'cic-map', get_template_directory_uri() . '/assets/js/map.js', array(), cic_theme_asset_version( 'assets/js/map.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
		}
		// Shared enquiry forms (quote + contact): styles and progressive enhancement.
		$forms = is_page( array( 'get-a-quote', 'contact' ) );
		if ( $forms ) {
			wp_enqueue_style( 'cic-quote', get_template_directory_uri() . '/assets/css/quote.css', array( 'cic-main' ), cic_theme_asset_version( 'assets/css/quote.css' ) );
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
			$config['page']         = cic_theme_source_page();
			$config['coverageUrl']  = rest_url( 'cic/v1/coverage' );
			$config['locationsUrl'] = rest_url( 'cic/v1/locations' );
			$config['locationUrl']  = rest_url( 'cic/v1/location' );
			$config['datepicker']   = array(
				'placeholder' => __( 'MM/DD/YYYY', 'cater-ice-cream' ),
				'hint'        => __( 'Format MM/DD/YYYY. Press the Down arrow key to choose from a calendar.', 'cater-ice-cream' ),
				'dialog'      => __( 'Choose an event date', 'cater-ice-cream' ),
				'prevMonth'   => __( 'Previous month', 'cater-ice-cream' ),
				'nextMonth'   => __( 'Next month', 'cater-ice-cream' ),
				'prevYear'    => __( 'Previous year', 'cater-ice-cream' ),
				'nextYear'    => __( 'Next year', 'cater-ice-cream' ),
				'close'       => __( 'Close calendar', 'cater-ice-cream' ),
				'unavailable' => __( 'not available to choose', 'cater-ice-cream' ),
				'selected'    => __( 'selected', 'cater-ice-cream' ),
				'note'        => __( 'Choosing a date does not reserve it. Availability is confirmed with your quote.', 'cater-ice-cream' ),
			);
			wp_add_inline_script( 'cic-context', 'window.cicContext = ' . wp_json_encode( $config ) . ';', 'before' );
			if ( $forms ) {
				wp_enqueue_script( 'cic-quote', get_template_directory_uri() . '/assets/js/quote.js', array( 'cic-context' ), cic_theme_asset_version( 'assets/js/quote.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
				wp_add_inline_script(
					'cic-quote',
					'window.cicQuote = ' . wp_json_encode(
						array(
							'messages' => array(
								'summary'   => __( 'Please check these fields:', 'cater-ice-cream' ),
								'required'  => __( 'Please fill in this field.', 'cater-ice-cream' ),
								'event'     => __( 'Please choose what you are celebrating.', 'cater-ice-cream' ),
								'date'      => __( 'Please choose your event date, or tick “Date not set yet”.', 'cater-ice-cream' ),
								'dateRange' => __( 'Please choose a date from today onward (within three years).', 'cater-ice-cream' ),
								'guests'    => __( 'Please enter an approximate guest count (a whole number from 1).', 'cater-ice-cream' ),
								'location'  => __( 'Please enter your event city or 5-digit ZIP code.', 'cater-ice-cream' ),
								'treats'    => __( 'Please choose at least one treat, or “Not sure yet”.', 'cater-ice-cream' ),
								'email'     => __( 'Please enter a valid email address, for example name@example.com.', 'cater-ice-cream' ),
								'phone'     => __( 'Please add a phone number so we can call you, or choose email.', 'cater-ice-cream' ),
								'needCity'  => __( 'Venue city', 'cater-ice-cream' ),
								'needZip'   => __( 'Event ZIP code', 'cater-ice-cream' ),
								'sending'   => __( 'Sending…', 'cater-ice-cream' ),
								'network'   => __( 'We could not reach the server. Your answers are kept: please press Send again.', 'cater-ice-cream' ),
								'waiting'   => __( 'We are already saving this request. Please wait a moment.', 'cater-ice-cream' ),
								'unsureTip' => __( 'Not sure yet', 'cater-ice-cream' ),
								'tbc'       => __( 'Date to be confirmed', 'cater-ice-cream' ),
								'guestsFmt' => __( '%d guests', 'cater-ice-cream' ),
								'other'     => __( 'Other occasion', 'cater-ice-cream' ),
							),
						)
					) . ';',
					'before'
				);
			}
			// Date picker for every event-date field; runs after the context/quote prefills.
			wp_enqueue_script( 'cic-datepicker', get_template_directory_uri() . '/assets/js/datepicker.js', $forms ? array( 'cic-context', 'cic-quote' ) : array( 'cic-context' ), cic_theme_asset_version( 'assets/js/datepicker.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
		}
	}
);
