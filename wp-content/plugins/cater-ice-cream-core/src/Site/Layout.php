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
				'logo_width'        => array( 'type' => 'int', 'default' => 250, 'min' => 120, 'max' => 320, 'label' => 'Desktop logo width (px)' ),
				'logo_width_mobile' => array( 'type' => 'int', 'default' => 168, 'min' => 96, 'max' => 220, 'label' => 'Mobile logo width (px)' ),
				'show_call'         => array( 'type' => 'bool', 'default' => false, 'label' => 'Show call action' ),
				'show_quote'        => array( 'type' => 'bool', 'default' => true, 'label' => 'Show quote button' ),
				'sticky'            => array( 'type' => 'bool', 'default' => true, 'label' => 'Sticky header while scrolling' ),
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
				'copyright'    => array( 'type' => 'text', 'default' => '© {year} Cater Ice Cream · Frosty Yummy LLC', 'label' => 'Copyright line', 'max' => 200, 'help' => '{year} is replaced with the current year.' ),
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
