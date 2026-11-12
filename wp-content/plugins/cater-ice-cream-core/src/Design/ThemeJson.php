<?php
/**
 * Feeds resolved Design System tokens into the theme.json pipeline (wp_theme_json_data_theme), so
 * WordPress itself prints the same presets and custom properties on the frontend and in the editor.
 * Also closes the competing sources: user Global Styles and the Font Library.
 *
 * @package CIC
 */

namespace CIC\Design;

defined( 'ABSPATH' ) || exit;

final class ThemeJson {

	public const THEME = 'cater-ice-cream';

	public function register(): void {
		add_filter( 'wp_theme_json_data_theme', array( $this, 'filter_theme_data' ) );
		add_filter( 'wp_theme_json_data_user', array( $this, 'filter_user_data' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'filter_editor_settings' ) );
	}

	public static function is_active_theme(): bool {
		return self::THEME === get_template();
	}

	/**
	 * @param \WP_Theme_JSON_Data $theme_json Theme origin data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function filter_theme_data( $theme_json ) {
		if ( ! self::is_active_theme() ) {
			return $theme_json;
		}
		return $theme_json->update_with( self::theme_json_settings( Settings::resolved() ) );
	}

	/**
	 * The Design System screen is the only editable source of global styles. Saved user Global Styles
	 * (from the Site Editor) are ignored while this theme is active, so they cannot silently compete.
	 *
	 * @param \WP_Theme_JSON_Data $user_json User origin data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function filter_user_data( $user_json ) {
		if ( ! self::is_active_theme() || ! class_exists( '\WP_Theme_JSON_Data' ) ) {
			return $user_json;
		}
		return new \WP_Theme_JSON_Data( array( 'version' => 3 ), 'custom' );
	}

	/**
	 * @param array<string, mixed> $settings Block editor settings.
	 * @return array<string, mixed>
	 */
	public function filter_editor_settings( $settings ) {
		if ( self::is_active_theme() ) {
			$settings['fontLibraryEnabled'] = false;
		}
		return $settings;
	}

	/**
	 * theme.json fragment generated from tokens. Keys mirror the theme's theme.json exactly.
	 *
	 * @param array<string, mixed> $tokens Resolved tokens.
	 * @return array<string, mixed>
	 */
	public static function theme_json_settings( array $tokens ): array {
		$palette = array();
		foreach ( Schema::colors() as $key => $def ) {
			$palette[] = array(
				'slug'  => $def['slug'],
				'name'  => $def['label'],
				'color' => $tokens['colors'][ $key ],
			);
		}

		$font_sizes = array();
		foreach ( array( 'caption', 'body', 'lead', 'h4', 'h3', 'h2', 'h1' ) as $role ) {
			$t            = $tokens['type'][ $role ];
			$font_sizes[] = array(
				'slug'  => $role,
				'name'  => Schema::type_roles()[ $role ]['label'],
				'size'  => $t['max'] . 'px',
				'fluid' => $t['min'] === $t['max'] ? false : array(
					'min' => $t['min'] . 'px',
					'max' => $t['max'] . 'px',
				),
			);
		}

		$unit    = (int) $tokens['scalars']['space_unit'];
		$spacing = array();
		foreach ( array( 10 => 1, 20 => 2, 30 => 3, 40 => 4, 50 => 6, 60 => 8, 70 => 12 ) as $slug => $multiple ) {
			$spacing[] = array(
				'slug' => (string) $slug,
				'name' => sprintf( '%d × unit', $multiple ),
				'size' => ( $unit * $multiple ) . 'px',
			);
		}

		return array(
			'version'  => 3,
			'settings' => array(
				'color'      => array( 'palette' => $palette ),
				'typography' => array( 'fontSizes' => $font_sizes ),
				'spacing'    => array( 'spacingSizes' => $spacing ),
				'layout'     => array(
					'contentSize' => $tokens['scalars']['content_width'] . 'px',
					'wideSize'    => $tokens['scalars']['wide_width'] . 'px',
				),
				'custom'     => array( 'cic' => self::custom_tokens( $tokens ) ),
			),
		);
	}

	/**
	 * settings.custom.cic → --wp--custom--cic--* custom properties.
	 *
	 * @param array<string, mixed> $tokens Resolved tokens.
	 * @return array<string, mixed>
	 */
	public static function custom_tokens( array $tokens ): array {
		$s     = $tokens['scalars'];
		$color = static fn( string $key ) => 'var(--wp--preset--color--' . Schema::colors()[ $key ]['slug'] . ')';
		$font  = static fn( string $key ) => 'var(--wp--preset--font-family--' . Schema::fonts()[ $key ]['slug'] . ')';
		$tint  = static fn( int $pct ) => 'color-mix(in srgb, var(--wp--preset--color--text-primary) ' . $pct . '%, transparent)';

		$fs = array();
		foreach ( $tokens['type'] as $role => $t ) {
			$fs[ $role ] = array(
				'min'   => (int) $t['min'],
				'max'   => (int) $t['max'],
				'lhMin' => (int) $t['lh_min'],
				'lhMax' => (int) $t['lh_max'],
			);
		}

		$card_shadow = array(
			'none'   => 'none',
			'soft'   => '0 8px 24px ' . $tint( 10 ),
			'medium' => '0 14px 36px ' . $tint( 16 ),
		)[ $s['card_shadow'] ];

		return array(
			'font'      => array(
				'heading'            => $font( $s['font_heading'] ),
				'body'               => $font( $s['font_body'] ),
				'headingWeight'      => (int) $s['heading_weight'],
				'bodyWeight'         => (int) $s['body_weight'],
				'uiWeight'           => (int) $s['ui_weight'],
				'strongWeight'       => (int) $s['strong_weight'],
				'headingAccentStyle' => $s['heading_accent_style'],
				'headingTracking'    => $s['heading_tracking'] . 'em',
				'eyebrowTracking'    => $s['eyebrow_tracking'] . 'em',
				'buttonTracking'     => $s['button_tracking'] . 'em',
			),
			'fs'        => $fs,
			'layout'    => array(
				'gutterMin'  => (int) $s['gutter_min'],
				'gutterMax'  => (int) $s['gutter_max'],
				'sectionMin' => (int) $s['section_min'],
				'sectionMax' => (int) $s['section_max'],
				'space'      => (int) $s['space_unit'] . 'px',
			),
			'radius'    => array(
				'card'   => (int) $s['radius_card'] . 'px',
				'button' => (int) $s['radius_button'] . 'px',
				'field'  => (int) $s['radius_field'] . 'px',
				'image'  => (int) $s['radius_image'] . 'px',
			),
			'component' => array(
				'buttonPadY'          => (int) $s['button_pad_y'] . 'px',
				'buttonPadX'          => (int) $s['button_pad_x'] . 'px',
				'buttonShadow'        => 'soft' === $s['button_shadow'] ? '0 6px 16px ' . $tint( 14 ) : 'none',
				'fieldHeight'         => (int) $s['field_height'] . 'px',
				'fieldBorder'         => $color( $s['field_border'] ),
				'cardShadow'          => $card_shadow,
				'cardBorder'          => 'hairline' === $s['card_border'] ? '1px solid var(--wp--preset--color--border)' : '0 solid transparent',
				'accordionSurface'    => 'filled' === $s['accordion_style'] ? $color( 'surface_soft' ) : $color( 'surface_base' ),
				'accordionBorder'     => 'filled' === $s['accordion_style'] ? '0 solid transparent' : '1px solid var(--wp--preset--color--border)',
				'callPanelSurface'    => $color( $s['call_panel_surface'] ),
				'callPanelText'       => $color( $s['call_panel_text'] ),
				'quoteStarterSurface' => $color( $s['quote_starter_surface'] ),
				'headerSurface'       => $color( $s['header_surface'] ),
				'footerSurface'       => $color( $s['footer_surface'] ),
				'footerText'          => $color( $s['footer_text'] ),
			),
		);
	}
}
