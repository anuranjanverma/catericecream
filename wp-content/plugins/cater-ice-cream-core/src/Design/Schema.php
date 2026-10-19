<?php
/**
 * Design token schema: the single allowlist of every editable design value, its default,
 * bounds and how it maps into theme.json. Defaults mirror the theme's theme.json
 * (BUILD_SPECIFICATION §5); tests check the two stay in parity.
 *
 * @package CIC
 */

namespace CIC\Design;

defined( 'ABSPATH' ) || exit;

final class Schema {

	/** Fluid type interpolates between these viewport widths (px). */
	public const VIEWPORT_MIN = 390;
	public const VIEWPORT_MAX = 1440;

	/**
	 * Global colour tokens. Each becomes a theme.json palette preset (--wp--preset--color--{slug}).
	 *
	 * @return array<string, array{slug: string, label: string, group: string, default: string, help?: string}>
	 */
	public static function colors(): array {
		return array(
			'brand_raspberry'  => array( 'slug' => 'brand-raspberry', 'label' => 'Brand raspberry', 'group' => 'base', 'default' => '#DE2879', 'help' => 'Large accents and decoration. About 4.48:1 on white, so not for small text.' ),
			'action_primary'   => array( 'slug' => 'action-primary', 'label' => 'Primary action', 'group' => 'action', 'default' => '#D92373', 'help' => 'Buttons and calls to action.' ),
			'action_hover'     => array( 'slug' => 'action-primary-hover', 'label' => 'Primary action hover', 'group' => 'action', 'default' => '#BC1F64' ),
			'on_action'        => array( 'slug' => 'on-action', 'label' => 'Text on actions', 'group' => 'action', 'default' => '#FFFFFF' ),
			'action_secondary' => array( 'slug' => 'action-secondary', 'label' => 'Secondary action', 'group' => 'action', 'default' => '#503564', 'help' => 'Outline buttons and secondary links.' ),
			'focus'            => array( 'slug' => 'focus', 'label' => 'Focus ring', 'group' => 'action', 'default' => '#503564' ),
			'error'            => array( 'slug' => 'error', 'label' => 'Error messages and invalid fields', 'group' => 'action', 'default' => '#B3261E' ),
			'text_primary'     => array( 'slug' => 'text-primary', 'label' => 'Text (deep plum)', 'group' => 'base', 'default' => '#503564' ),
			'text_muted'       => array( 'slug' => 'text-muted', 'label' => 'Muted text', 'group' => 'base', 'default' => '#6B587A' ),
			'strawberry'       => array( 'slug' => 'strawberry', 'label' => 'Strawberry', 'group' => 'base', 'default' => '#F3A2BD' ),
			'lavender'         => array( 'slug' => 'lavender', 'label' => 'Lavender', 'group' => 'base', 'default' => '#B9A0DD' ),
			'surface_soft'     => array( 'slug' => 'surface-soft', 'label' => 'Surface soft', 'group' => 'base', 'default' => '#F3EFF9' ),
			'surface_base'     => array( 'slug' => 'surface-base', 'label' => 'Surface base', 'group' => 'base', 'default' => '#FFFFFF' ),
			'border'           => array( 'slug' => 'border', 'label' => 'Borders and field outlines', 'group' => 'base', 'default' => '#DDD3EA' ),
			'birthday_sky'     => array( 'slug' => 'birthday-sky', 'label' => 'Birthday sky', 'group' => 'accent', 'default' => '#63C9EB' ),
			'birthday_mint'    => array( 'slug' => 'birthday-mint', 'label' => 'Birthday mint', 'group' => 'accent', 'default' => '#8CDCBF' ),
			'birthday_violet'  => array( 'slug' => 'birthday-violet', 'label' => 'Birthday violet', 'group' => 'accent', 'default' => '#7652B8' ),
			'birthday_surface' => array( 'slug' => 'birthday-surface', 'label' => 'Birthday pale surface', 'group' => 'accent', 'default' => '#EAF6FB' ),
			'wedding_ivory'    => array( 'slug' => 'wedding-ivory', 'label' => 'Wedding ivory', 'group' => 'accent', 'default' => '#FFFDF8' ),
			'wedding_gold'     => array( 'slug' => 'wedding-gold', 'label' => 'Wedding champagne gold', 'group' => 'accent', 'default' => '#B29458', 'help' => 'Decorative only, not reading text.' ),
			'wedding_blush'    => array( 'slug' => 'wedding-blush', 'label' => 'Wedding blush', 'group' => 'accent', 'default' => '#F5E7E8' ),
			'treat_chocolate'  => array( 'slug' => 'treat-chocolate', 'label' => 'Treat chocolate', 'group' => 'accent', 'default' => '#7A4129' ),
			'treat_blush'      => array( 'slug' => 'treat-blush', 'label' => 'Treat blush surface', 'group' => 'accent', 'default' => '#FBEFF0' ),
			'treat_mint'       => array( 'slug' => 'treat-mint', 'label' => 'Treat mint surface', 'group' => 'accent', 'default' => '#EBF8F5' ),
			'treat_sky'        => array( 'slug' => 'treat-sky', 'label' => 'Treat sky surface', 'group' => 'accent', 'default' => '#EBF6FD' ),
		);
	}

	/**
	 * Approved font families. Only these can be selected; there is no upload path.
	 * 'weights' lists the weights the bundled files cover (system stacks rely on installed fonts).
	 *
	 * @return array<string, array{label: string, slug: string, weights: int[]}>
	 */
	public static function fonts(): array {
		return array(
			'fraunces'     => array( 'label' => 'Fraunces (approved heading font)', 'slug' => 'fraunces', 'weights' => array( 600, 700 ) ),
			'dm-sans'      => array( 'label' => 'DM Sans (approved body font)', 'slug' => 'dm-sans', 'weights' => array( 400, 500, 600, 700 ) ),
			'system-serif' => array( 'label' => 'System serif fallback', 'slug' => 'system-serif', 'weights' => array( 400, 600, 700 ) ),
			'system-sans'  => array( 'label' => 'System sans-serif fallback', 'slug' => 'system-sans', 'weights' => array( 400, 500, 600, 700 ) ),
		);
	}

	/**
	 * Responsive type roles: mobile (at 390px) and desktop (at 1440px) size and line height in px.
	 *
	 * @return array<string, array{label: string, min: int, max: int, lh_min: int, lh_max: int}>
	 */
	public static function type_roles(): array {
		return array(
			'h1'      => array( 'label' => 'Heading 1', 'min' => 40, 'max' => 64, 'lh_min' => 44, 'lh_max' => 70 ),
			'h2'      => array( 'label' => 'Heading 2', 'min' => 32, 'max' => 42, 'lh_min' => 38, 'lh_max' => 48 ),
			'h3'      => array( 'label' => 'Heading 3', 'min' => 24, 'max' => 28, 'lh_min' => 30, 'lh_max' => 34 ),
			'h4'      => array( 'label' => 'Heading 4', 'min' => 20, 'max' => 22, 'lh_min' => 26, 'lh_max' => 28 ),
			'lead'    => array( 'label' => 'Lead paragraph', 'min' => 18, 'max' => 20, 'lh_min' => 28, 'lh_max' => 30 ),
			'body'    => array( 'label' => 'Body', 'min' => 16, 'max' => 18, 'lh_min' => 26, 'lh_max' => 28 ),
			'caption' => array( 'label' => 'Caption', 'min' => 13, 'max' => 14, 'lh_min' => 18, 'lh_max' => 20 ),
			'label'   => array( 'label' => 'Form label', 'min' => 14, 'max' => 14, 'lh_min' => 20, 'lh_max' => 20 ),
			'button'  => array( 'label' => 'Button', 'min' => 16, 'max' => 16, 'lh_min' => 20, 'lh_max' => 20 ),
			'eyebrow' => array( 'label' => 'Eyebrow / kicker', 'min' => 12, 'max' => 13, 'lh_min' => 16, 'lh_max' => 18 ),
			'nav'     => array( 'label' => 'Navigation', 'min' => 16, 'max' => 16, 'lh_min' => 22, 'lh_max' => 22 ),
		);
	}

	/** Bounds shared by all type sizes and line heights (px). */
	public const TYPE_BOUNDS = array( 'min' => 10, 'max' => 120 );

	/**
	 * Scalar tokens other than colours and type sizes.
	 * type: number (bounded int/float) | font | weight | select | color_ref (a colour token key).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function scalars(): array {
		return array(
			// Typography.
			'font_heading'          => array( 'group' => 'typography', 'type' => 'font', 'label' => 'Heading font', 'default' => 'fraunces' ),
			'font_body'             => array( 'group' => 'typography', 'type' => 'font', 'label' => 'Body and interface font', 'default' => 'dm-sans' ),
			'heading_weight'        => array( 'group' => 'typography', 'type' => 'weight', 'label' => 'Heading weight', 'default' => 700, 'font' => 'font_heading' ),
			'body_weight'           => array( 'group' => 'typography', 'type' => 'weight', 'label' => 'Body weight', 'default' => 400, 'font' => 'font_body' ),
			'ui_weight'             => array( 'group' => 'typography', 'type' => 'weight', 'label' => 'Interface weight (buttons, labels, navigation)', 'default' => 500, 'font' => 'font_body' ),
			'strong_weight'         => array( 'group' => 'typography', 'type' => 'weight', 'label' => 'Bold text weight', 'default' => 700, 'font' => 'font_body' ),
			'heading_accent_style'  => array( 'group' => 'typography', 'type' => 'select', 'label' => 'Heading accent words', 'default' => 'normal', 'choices' => array( 'normal' => 'Upright', 'italic' => 'Italic' ) ),
			'heading_tracking'      => array( 'group' => 'typography', 'type' => 'number', 'label' => 'Heading letter spacing (em)', 'default' => 0, 'min' => -0.05, 'max' => 0.05, 'step' => 0.005, 'unit' => 'em' ),
			'eyebrow_tracking'      => array( 'group' => 'typography', 'type' => 'number', 'label' => 'Eyebrow letter spacing (em)', 'default' => 0.18, 'min' => 0, 'max' => 0.3, 'step' => 0.01, 'unit' => 'em' ),
			'button_tracking'       => array( 'group' => 'typography', 'type' => 'number', 'label' => 'Button letter spacing (em)', 'default' => 0, 'min' => 0, 'max' => 0.2, 'step' => 0.01, 'unit' => 'em' ),
			// Layout.
			'content_width'         => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Content width (px)', 'default' => 1200, 'min' => 960, 'max' => 1440, 'step' => 10, 'unit' => 'px' ),
			'wide_width'            => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Wide section width (px)', 'default' => 1440, 'min' => 1200, 'max' => 1920, 'step' => 10, 'unit' => 'px' ),
			'gutter_min'            => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Side gutter, mobile (px)', 'default' => 16, 'min' => 12, 'max' => 40, 'step' => 1, 'unit' => 'px' ),
			'gutter_max'            => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Side gutter, desktop (px)', 'default' => 32, 'min' => 16, 'max' => 80, 'step' => 1, 'unit' => 'px' ),
			'section_min'           => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Section padding, mobile (px)', 'default' => 48, 'min' => 16, 'max' => 120, 'step' => 4, 'unit' => 'px' ),
			'section_max'           => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Section padding, desktop (px)', 'default' => 88, 'min' => 32, 'max' => 200, 'step' => 4, 'unit' => 'px' ),
			'space_unit'            => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Spacing scale unit (px)', 'default' => 8, 'min' => 4, 'max' => 12, 'step' => 1, 'unit' => 'px' ),
			'radius_card'           => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Card radius (px)', 'default' => 20, 'min' => 0, 'max' => 40, 'step' => 1, 'unit' => 'px' ),
			'radius_button'         => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Button radius (px, 999 = pill)', 'default' => 999, 'min' => 0, 'max' => 999, 'step' => 1, 'unit' => 'px' ),
			'radius_field'          => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Field radius (px)', 'default' => 10, 'min' => 0, 'max' => 24, 'step' => 1, 'unit' => 'px' ),
			'radius_image'          => array( 'group' => 'layout', 'type' => 'number', 'label' => 'Image radius (px)', 'default' => 24, 'min' => 0, 'max' => 48, 'step' => 1, 'unit' => 'px' ),
			// Components.
			'button_pad_y'          => array( 'group' => 'components', 'type' => 'number', 'label' => 'Button vertical padding (px)', 'default' => 14, 'min' => 8, 'max' => 24, 'step' => 1, 'unit' => 'px' ),
			'button_pad_x'          => array( 'group' => 'components', 'type' => 'number', 'label' => 'Button horizontal padding (px)', 'default' => 28, 'min' => 12, 'max' => 48, 'step' => 1, 'unit' => 'px' ),
			'button_shadow'         => array( 'group' => 'components', 'type' => 'select', 'label' => 'Button shadow', 'default' => 'soft', 'choices' => array( 'none' => 'None', 'soft' => 'Soft' ) ),
			'field_height'          => array( 'group' => 'components', 'type' => 'number', 'label' => 'Field height (px, minimum 44 for touch)', 'default' => 48, 'min' => 44, 'max' => 64, 'step' => 1, 'unit' => 'px' ),
			'field_border'          => array( 'group' => 'components', 'type' => 'color_ref', 'label' => 'Field border colour', 'default' => 'border' ),
			'card_shadow'           => array( 'group' => 'components', 'type' => 'select', 'label' => 'Card shadow', 'default' => 'soft', 'choices' => array( 'none' => 'None', 'soft' => 'Soft', 'medium' => 'Medium' ) ),
			'card_border'           => array( 'group' => 'components', 'type' => 'select', 'label' => 'Card outline', 'default' => 'none', 'choices' => array( 'none' => 'None', 'hairline' => 'Hairline' ) ),
			'accordion_style'       => array( 'group' => 'components', 'type' => 'select', 'label' => 'Accordion style', 'default' => 'bordered', 'choices' => array( 'bordered' => 'Bordered rows', 'filled' => 'Filled rows' ) ),
			'call_panel_surface'    => array( 'group' => 'components', 'type' => 'color_ref', 'label' => 'Call panel background', 'default' => 'action_primary' ),
			'call_panel_text'       => array( 'group' => 'components', 'type' => 'color_ref', 'label' => 'Call panel text', 'default' => 'on_action' ),
			'quote_starter_surface' => array( 'group' => 'components', 'type' => 'color_ref', 'label' => 'Quote starter card background', 'default' => 'surface_base' ),
			'header_surface'        => array( 'group' => 'components', 'type' => 'color_ref', 'label' => 'Main header background', 'default' => 'surface_base' ),
			'footer_surface'        => array( 'group' => 'components', 'type' => 'color_ref', 'label' => 'Footer background', 'default' => 'text_primary' ),
			'footer_text'           => array( 'group' => 'components', 'type' => 'color_ref', 'label' => 'Footer text', 'default' => 'on_action' ),
		);
	}

	/**
	 * Semantic colour roles consumed by components as --cic-color-{role}.
	 *
	 * @return array<string, string> role => label
	 */
	public static function roles(): array {
		return array(
			'surface'        => 'Page surface',
			'surface_alt'    => 'Alternate band surface',
			'text'           => 'Body text',
			'muted'          => 'Muted text',
			'heading'        => 'Headings',
			'heading_accent' => 'Heading accent words',
			'accent'         => 'Accent 1',
			'accent_2'       => 'Accent 2',
			'decor'          => 'Decorative lines and icons',
			'action'         => 'Primary action',
			'action_hover'   => 'Primary action hover',
			'on_action'      => 'Text on actions',
			'secondary'      => 'Secondary action',
			'link'           => 'Links',
			'border'         => 'Borders',
			'focus'          => 'Focus ring',
		);
	}

	/**
	 * Default brand mapping of every role to a colour token.
	 *
	 * @return array<string, string>
	 */
	public static function brand_roles(): array {
		return array(
			'surface'        => 'surface_base',
			'surface_alt'    => 'surface_soft',
			'text'           => 'text_primary',
			'muted'          => 'text_muted',
			'heading'        => 'text_primary',
			'heading_accent' => 'brand_raspberry',
			'accent'         => 'strawberry',
			'accent_2'       => 'lavender',
			'decor'          => 'lavender',
			'action'         => 'action_primary',
			'action_hover'   => 'action_hover',
			'on_action'      => 'on_action',
			'secondary'      => 'action_secondary',
			'link'           => 'action_primary',
			'border'         => 'border',
			'focus'          => 'focus',
		);
	}

	/**
	 * Named visual contexts (presets). 'roles' are sparse references to colour tokens; anything not
	 * listed inherits the brand mapping. Typography entries are optional per preset.
	 *
	 * @return array<string, array{label: string, kind: string, roles: array<string, string>, heading_weight?: int, heading_accent_style?: string}>
	 */
	public static function contexts(): array {
		return array(
			'brand'       => array( 'label' => 'Default brand', 'kind' => 'brand', 'roles' => array() ),
			'birthday'    => array(
				'label' => 'Birthday',
				'kind'  => 'event',
				'roles' => array(
					'surface_alt' => 'birthday_surface',
					'accent'      => 'birthday_sky',
					'accent_2'    => 'birthday_mint',
					'decor'       => 'birthday_violet',
				),
			),
			'wedding'     => array(
				'label'                => 'Wedding',
				'kind'                 => 'event',
				'roles'                => array(
					'surface'     => 'wedding_ivory',
					'surface_alt' => 'wedding_blush',
					'accent'      => 'wedding_blush',
					'accent_2'    => 'wedding_gold',
					'decor'       => 'wedding_gold',
				),
				'heading_weight'       => 600,
				'heading_accent_style' => 'italic',
			),
			'anniversary' => array(
				'label' => 'Anniversary',
				'kind'  => 'event',
				'roles' => array(
					'accent' => 'strawberry',
					'decor'  => 'strawberry',
				),
			),
			'corporate'   => array(
				'label' => 'Corporate',
				'kind'  => 'event',
				'roles' => array(
					'accent' => 'lavender',
					'decor'  => 'lavender',
				),
			),
			'dots'        => array(
				'label' => "Treat: Dippin' Dots",
				'kind'  => 'treat',
				'roles' => array(
					'surface_alt' => 'treat_sky',
					'accent'      => 'birthday_sky',
					'accent_2'    => 'strawberry',
				),
			),
			'banana'      => array(
				'label' => "Treat: DipN' Bananas",
				'kind'  => 'treat',
				'roles' => array(
					'surface_alt' => 'treat_blush',
					'accent'      => 'treat_chocolate',
					'decor'       => 'treat_chocolate',
				),
			),
			'classic'     => array(
				'label' => 'Treat: Classic Ice Cream',
				'kind'  => 'treat',
				'roles' => array(
					'accent'   => 'strawberry',
					'accent_2' => 'treat_chocolate',
				),
			),
			'yogurt'      => array(
				'label' => 'Treat: Frozen Yogurt',
				'kind'  => 'treat',
				'roles' => array(
					'surface_alt' => 'treat_mint',
					'accent'      => 'birthday_mint',
					'decor'       => 'birthday_mint',
				),
			),
		);
	}

	/**
	 * Fields a page or section override may set. Element overrides use element_fields().
	 * Colours accept a colour token key or a #RRGGBB value; empty means inherit.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function scope_fields(): array {
		$fields = array(
			'context' => array( 'type' => 'context', 'label' => 'Visual preset' ),
		);
		foreach ( array( 'surface', 'surface_alt', 'text', 'heading', 'heading_accent', 'accent', 'decor', 'action', 'action_hover', 'on_action', 'link' ) as $role ) {
			$fields[ $role ] = array( 'type' => 'color', 'label' => self::roles()[ $role ], 'role' => $role );
		}
		$fields['font_heading']         = array( 'type' => 'font', 'label' => 'Heading font' );
		$fields['font_body']            = array( 'type' => 'font', 'label' => 'Body font' );
		$fields['heading_weight']       = array( 'type' => 'weight', 'label' => 'Heading weight' );
		$fields['heading_accent_style'] = array( 'type' => 'select', 'label' => 'Heading accent words', 'choices' => array( 'normal' => 'Upright', 'italic' => 'Italic' ) );
		foreach ( array( 'h1', 'h2', 'h3', 'body' ) as $role ) {
			$label                       = self::type_roles()[ $role ]['label'];
			$fields[ $role . '_min' ]    = array( 'type' => 'size', 'label' => $label . ' mobile size (px)', 'type_role' => $role, 'edge' => 'min' );
			$fields[ $role . '_max' ]    = array( 'type' => 'size', 'label' => $label . ' desktop size (px)', 'type_role' => $role, 'edge' => 'max' );
		}
		return $fields;
	}

	/**
	 * Element override fields per element kind.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function element_fields(): array {
		$size = static fn( string $edge ) => array( 'type' => 'size', 'label' => 'min' === $edge ? 'Mobile size (px)' : 'Desktop size (px)', 'edge' => $edge );
		return array(
			'heading' => array(
				'heading'        => array( 'type' => 'color', 'label' => 'Heading colour', 'role' => 'heading' ),
				'heading_accent' => array( 'type' => 'color', 'label' => 'Accent words colour', 'role' => 'heading_accent' ),
				'size_min'       => $size( 'min' ),
				'size_max'       => $size( 'max' ),
			),
			'text'    => array(
				'text'     => array( 'type' => 'color', 'label' => 'Text colour', 'role' => 'text' ),
				'link'     => array( 'type' => 'color', 'label' => 'Link colour', 'role' => 'link' ),
				'size_min' => $size( 'min' ),
				'size_max' => $size( 'max' ),
			),
			'button'  => array(
				'action'       => array( 'type' => 'color', 'label' => 'Button colour', 'role' => 'action' ),
				'action_hover' => array( 'type' => 'color', 'label' => 'Button hover colour', 'role' => 'action_hover' ),
				'on_action'    => array( 'type' => 'color', 'label' => 'Button text colour', 'role' => 'on_action' ),
			),
		);
	}

	/**
	 * Block types that accept overrides and the scope/kind they use.
	 *
	 * @return array<string, string> block name => 'section' | element kind
	 */
	public static function block_scopes(): array {
		return array(
			'core/group'     => 'section',
			'core/cover'     => 'section',
			'core/columns'   => 'section',
			'core/column'    => 'section',
			'core/heading'   => 'heading',
			'core/paragraph' => 'text',
			'core/list'      => 'text',
			'core/button'    => 'button',
		);
	}

	/**
	 * Complete default global configuration (no contexts customised).
	 *
	 * @return array{colors: array<string, string>, scalars: array<string, mixed>, type: array<string, array<string, int>>, contexts: array<string, array<string, mixed>>}
	 */
	public static function defaults(): array {
		$type = array();
		foreach ( self::type_roles() as $role => $def ) {
			$type[ $role ] = array(
				'min'    => $def['min'],
				'max'    => $def['max'],
				'lh_min' => $def['lh_min'],
				'lh_max' => $def['lh_max'],
			);
		}
		return array(
			'colors'   => array_map( static fn( $c ) => $c['default'], self::colors() ),
			'scalars'  => array_map( static fn( $s ) => $s['default'], self::scalars() ),
			'type'     => $type,
			'contexts' => array(),
		);
	}
}
