<?php
/**
 * Shared header (three rows + mobile navigation) and footer settings. Applies to every template:
 * pages, products, events, archives and virtual city routes. Menus stay native WordPress menus.
 *
 * @package CIC
 */

namespace CIC\Site;

use CIC\Design\Schema;

defined( 'ABSPATH' ) || exit;

final class Layout {

	public const OPTION = 'cic_site_layout';

	/**
	 * Field definitions: type bool | text | color_ref | int (min/max) | image | choice.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function schema(): array {
		return array(
			'top'    => array(
				'enabled'      => array( 'type' => 'bool', 'default' => true, 'label' => 'Show the top row' ),
				'text'         => array( 'type' => 'text', 'default' => 'Southern California ice cream catering', 'label' => 'Announcement text', 'max' => 160, 'help' => 'Placeholder copy until final text is supplied. Do not add prices or minimum-order wording.' ),
				'show_phone'   => array( 'type' => 'bool', 'default' => false, 'label' => 'Show phone link' ),
				'show_email'   => array( 'type' => 'bool', 'default' => false, 'label' => 'Show email link' ),
				'show_social'  => array( 'type' => 'bool', 'default' => false, 'label' => 'Show social links (only profiles with a saved URL appear)' ),
				'surface'      => array( 'type' => 'color_ref', 'default' => 'text_primary', 'label' => 'Background colour' ),
				'text_color'   => array( 'type' => 'color_ref', 'default' => 'on_action', 'label' => 'Text colour' ),
				'show_desktop' => array( 'type' => 'bool', 'default' => true, 'label' => 'Visible on desktop' ),
				'show_mobile'  => array( 'type' => 'bool', 'default' => true, 'label' => 'Visible on mobile' ),
			),
			'main'   => array(
				'logo_id'           => array( 'type' => 'image', 'default' => 0, 'label' => 'Desktop logo', 'help' => 'Leave empty to use the approved original logo bundled with the theme.' ),
				'logo_mobile_id'    => array( 'type' => 'image', 'default' => 0, 'label' => 'Mobile logo', 'help' => 'Optional; falls back to the desktop logo.' ),
				'logo_width'        => array( 'type' => 'int', 'default' => 190, 'min' => 120, 'max' => 320, 'label' => 'Desktop logo width (px)' ),
				'logo_width_mobile' => array( 'type' => 'int', 'default' => 136, 'min' => 96, 'max' => 220, 'label' => 'Mobile logo width (px)' ),
				'show_call'         => array( 'type' => 'bool', 'default' => true, 'label' => 'Desktop: phone number beside the quote button', 'help' => 'Desktop only (1024 px and wider). Shows the public phone from Business contact. Phones and tablets keep the call action in the menu drawer.' ),
				'show_quote'        => array( 'type' => 'bool', 'default' => true, 'label' => 'Show quote button' ),
				'sticky'            => array( 'type' => 'bool', 'default' => true, 'label' => 'Sticky header while scrolling' ),
				'compact'           => array( 'type' => 'bool', 'default' => true, 'label' => 'Desktop: compact the sticky header while scrolling down', 'help' => 'Desktop only (1024 px and wider) and only when the header is sticky. Scrolling down hides the top row and shortens the main row; scrolling up or returning to the top restores the full header. Mobile and tablet headers never change.' ),
				'logo_width_compact' => array( 'type' => 'int', 'default' => 140, 'min' => 96, 'max' => 260, 'label' => 'Desktop logo width while compact (px)', 'help' => 'Used only while the desktop header is compact. The full header keeps the desktop logo width above.' ),
			),
			'bottom' => array(
				'enabled'      => array( 'type' => 'bool', 'default' => false, 'label' => 'Show the bottom row', 'help' => 'Shows the "Header bottom row" menu (Appearance → Menus).' ),
				'surface'      => array( 'type' => 'color_ref', 'default' => 'surface_soft', 'label' => 'Background colour' ),
				'text_color'   => array( 'type' => 'color_ref', 'default' => 'text_primary', 'label' => 'Text colour' ),
				'show_desktop' => array( 'type' => 'bool', 'default' => true, 'label' => 'Visible on desktop' ),
				'show_mobile'  => array( 'type' => 'bool', 'default' => false, 'label' => 'Visible on mobile' ),
			),
			'mobile' => array(
				'show_call'      => array( 'type' => 'bool', 'default' => true, 'label' => 'Call action in the menu drawer' ),
				'show_quote'     => array( 'type' => 'bool', 'default' => true, 'label' => 'Quote button in the menu drawer' ),
				'show_secondary' => array( 'type' => 'bool', 'default' => true, 'label' => 'Include the bottom-row menu in the drawer' ),
				'show_email'     => array( 'type' => 'bool', 'default' => false, 'label' => 'Email link in the menu drawer' ),
				'sticky_quote'   => array( 'type' => 'bool', 'default' => true, 'label' => 'Phones: floating Inquiry button at the bottom of the screen', 'help' => 'Below 600 px only, on every page except Get a Quote. Links to the Get a Quote page; hidden while the menu is open or a form field is being filled in.' ),
				'sticky_label'   => array( 'type' => 'text', 'default' => 'Inquiry', 'label' => 'Phones: floating button text', 'max' => 24, 'help' => 'Short text for the floating button (e.g. Inquiry). Empty uses the quote button text from Business contact.' ),
				'sticky_call'    => array( 'type' => 'bool', 'default' => true, 'label' => 'Phones: round call button on the left', 'help' => 'Calls the business phone from Header, Footer & Contact. Hidden when no phone number is set.' ),
			),
			'footer' => array(
				'logo_id'      => array( 'type' => 'image', 'default' => 0, 'label' => 'Footer logo', 'help' => 'Leave empty to use the approved original logo on a light plate. A reversed (white) logo can be uploaded when an approved one exists.' ),
				'logo_width'   => array( 'type' => 'int', 'default' => 168, 'min' => 110, 'max' => 260, 'label' => 'Footer logo width (px)' ),
				'logo_plate'   => array( 'type' => 'bool', 'default' => true, 'label' => 'Show the logo on a light plate' ),
				'intro'        => array( 'type' => 'text', 'default' => '', 'label' => 'Short intro text', 'max' => 240 ),
				'layout'       => array(
					'type'    => 'choice',
					'default' => 'brand-menu-contact',
					'label'   => 'Column layout',
					'choices' => array(
						'brand-menu-contact'  => 'Logo · footer menu · contact (approved layout)',
						'brand-menus-contact' => 'Logo · two menus · contact',
						'brand-contact'       => 'Logo · contact',
					),
				),
				'show_contact' => array( 'type' => 'bool', 'default' => true, 'label' => 'Show contact column' ),
				'show_phone'   => array( 'type' => 'bool', 'default' => true, 'label' => 'Contact column: phone' ),
				'show_email'   => array( 'type' => 'bool', 'default' => false, 'label' => 'Contact column: email' ),
				'show_social'  => array( 'type' => 'bool', 'default' => true, 'label' => 'Show social links (only profiles with a saved URL appear)' ),
				'show_legal'   => array( 'type' => 'bool', 'default' => true, 'label' => 'Show the legal row (copyright and policy links)' ),
				'copyright'    => array( 'type' => 'text', 'default' => '© {year} CaterIceCream.com · Frosty Yummy LLC', 'label' => 'Copyright line', 'max' => 200, 'help' => '{year} is replaced with the current year.' ),
			),
			// Prefooter "Cities We Serve" (2026-10-22): published, indexable production cities ranked by their
			// served ZIP codes (DatabaseRepository::served_city_ranking()); never a hand-picked list.
			'cities' => array(
				'enabled'    => array( 'type' => 'bool', 'default' => true, 'label' => 'Show "Cities We Serve" above the footer', 'help' => 'Shown on every public page except Get a Quote. Cities are chosen automatically: published, set to Index, approved (production) data and at least one served ZIP code, ordered by how many served ZIP codes they have. The counts are never shown.' ),
				'heading'    => array( 'type' => 'text', 'default' => 'Cities We Serve', 'label' => 'Heading', 'max' => 80 ),
				'count'      => array( 'type' => 'int', 'default' => 20, 'min' => 4, 'max' => 60, 'label' => 'Number of cities' ),
				'link_label' => array( 'type' => 'text', 'default' => 'View all service areas', 'label' => '"View all" link label (links to the Service Areas page)', 'max' => 80 ),
			),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function defaults(): array {
		$out = array();
		foreach ( self::schema() as $section => $fields ) {
			foreach ( $fields as $key => $def ) {
				$out[ $section ][ $key ] = $def['default'];
			}
		}
		return $out;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get(): array {
		$saved    = get_option( self::OPTION, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$settings = self::defaults();
		foreach ( $settings as $section => $values ) {
			if ( isset( $saved[ $section ] ) && is_array( $saved[ $section ] ) ) {
				$settings[ $section ] = array_merge( $values, array_intersect_key( $saved[ $section ], $values ) );
			}
		}
		$settings['footer']['copyright_text'] = str_replace( '{year}', wp_date( 'Y' ), (string) $settings['footer']['copyright'] );
		return $settings;
	}

	/**
	 * @param mixed    $input  Posted values.
	 * @param string[] $errors Messages (by reference).
	 * @return array<string, array<string, mixed>>
	 */
	public static function sanitize( $input, array &$errors ): array {
		$input = is_array( $input ) ? $input : array();
		$prev  = self::get();
		$clean = array();
		foreach ( self::schema() as $section => $fields ) {
			$given = isset( $input[ $section ] ) && is_array( $input[ $section ] ) ? $input[ $section ] : array();
			foreach ( $fields as $key => $def ) {
				$raw = $given[ $key ] ?? null;
				switch ( $def['type'] ) {
					case 'bool':
						$clean[ $section ][ $key ] = ! empty( $raw );
						break;
					case 'text':
						$clean[ $section ][ $key ] = mb_substr( sanitize_text_field( (string) $raw ), 0, (int) $def['max'] );
						break;
					case 'color_ref':
						$raw = (string) $raw;
						if ( '' === $raw ) {
							$clean[ $section ][ $key ] = $def['default'];
						} elseif ( isset( Schema::colors()[ $raw ] ) ) {
							$clean[ $section ][ $key ] = $raw;
						} else {
							$errors[]                  = sprintf( '%s: unknown colour token; previous value kept.', $def['label'] );
							$clean[ $section ][ $key ] = $prev[ $section ][ $key ];
						}
						break;
					case 'int':
						$raw = trim( (string) $raw );
						if ( '' === $raw ) {
							$clean[ $section ][ $key ] = $def['default'];
						} elseif ( preg_match( '/^\d+$/', $raw ) && (int) $raw >= $def['min'] && (int) $raw <= $def['max'] ) {
							$clean[ $section ][ $key ] = (int) $raw;
						} else {
							$errors[]                  = sprintf( '%s must be %d–%d; previous value kept.', $def['label'], $def['min'], $def['max'] );
							$clean[ $section ][ $key ] = $prev[ $section ][ $key ];
						}
						break;
					case 'image':
						$id = absint( $raw );
						if ( $id && ! wp_attachment_is_image( $id ) ) {
							$errors[] = sprintf( '%s must be an image from the Media Library; previous value kept.', $def['label'] );
							$id       = (int) $prev[ $section ][ $key ];
						}
						$clean[ $section ][ $key ] = $id;
						break;
					case 'choice':
						$raw                       = (string) $raw;
						$clean[ $section ][ $key ] = isset( $def['choices'][ $raw ] ) ? $raw : $def['default'];
						break;
				}
			}
		}
		return $clean;
	}
}
