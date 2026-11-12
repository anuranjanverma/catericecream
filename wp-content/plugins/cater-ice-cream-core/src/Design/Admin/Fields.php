<?php
/**
 * Shared admin field rendering for the Design System screen and the page override meta box.
 * Every row shows what it inherits, a visible "active" badge when set, and a Reset button.
 *
 * @package CIC
 */

namespace CIC\Design\Admin;

use CIC\Design\Schema;
use CIC\Design\Settings;
use CIC\Design\Validate;

defined( 'ABSPATH' ) || exit;

final class Fields {

	public static function enqueue(): void {
		wp_enqueue_style( 'cic-design-admin', CIC_CORE_URL . 'assets/css/design-admin.css', array(), (string) filemtime( CIC_CORE_DIR . 'assets/css/design-admin.css' ) );
		wp_enqueue_script( 'cic-design-admin', CIC_CORE_URL . 'assets/js/design-admin.js', array(), (string) filemtime( CIC_CORE_DIR . 'assets/js/design-admin.js' ), true );
	}

	/** "Label — #HEX" for a colour reference. */
	public static function color_label( string $ref ): string {
		$colors = Schema::colors();
		if ( isset( $colors[ $ref ] ) ) {
			return $colors[ $ref ]['label'] . ' — ' . Settings::hex( $ref );
		}
		return __( 'Custom', 'cater-ice-cream-core' ) . ' — ' . ( Validate::hex( $ref ) ?? $ref );
	}

	public static function swatch( ?string $hex ): string {
		return $hex ? '<span class="cic-swatch" style="background:' . esc_attr( $hex ) . '" aria-hidden="true"></span>' : '';
	}

	/**
	 * <option> list of colour tokens.
	 */
	public static function color_options( string $selected ): string {
		$html = '';
		foreach ( Schema::colors() as $key => $def ) {
			$html .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $selected, $key, false ), esc_html( $def['label'] . ' — ' . Settings::hex( $key ) ) );
		}
		return $html;
	}

	/**
	 * Row wrapper with badge and reset control.
	 *
	 * @param string $badge_text Text shown when the row has a value.
	 */
	public static function row_open( string $label, string $for, bool $active, string $badge_text ): void {
		printf(
			'<tr class="cic-row%1$s" data-cic-row><th scope="row"><label for="%2$s">%3$s</label></th><td>',
			$active ? ' is-active' : '',
			esc_attr( $for ),
			esc_html( $label )
		);
		self::$badge = $badge_text;
	}

	private static string $badge = '';

	public static function row_close( string $inherits ): void {
		printf(
			'<p class="cic-inherits">%1$s</p></td><td class="cic-row-state"><span class="cic-badge">%2$s</span> <button type="button" class="button-link cic-reset" data-cic-reset>%3$s</button></td></tr>',
			wp_kses( $inherits, array( 'span' => array( 'class' => true, 'style' => true, 'aria-hidden' => true ) ) ),
			esc_html( self::$badge ),
			esc_html__( 'Reset to inherited', 'cater-ice-cream-core' )
		);
	}

	/**
	 * Colour choice control: inherit / token / custom hex.
	 *
	 * @param string $extra_option Optional extra option HTML placed after "inherit".
	 */
	public static function color_choice( string $name, string $custom_name, string $id, string $value, string $inherit_label, string $extra_option = '' ): void {
		$is_custom = '' !== $value && null !== Validate::hex( $value );
		$selected  = $is_custom ? 'custom' : $value;
		printf( '<select id="%1$s" name="%2$s" class="cic-color-choice">', esc_attr( $id ), esc_attr( $name ) );
		printf( '<option value="">%s</option>', esc_html( $inherit_label ) );
		echo $extra_option; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* by callers.
		echo self::color_options( $selected ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in color_options().
		printf( '<option value="custom"%1$s>%2$s</option></select>', selected( $selected, 'custom', false ), esc_html__( 'Custom colour…', 'cater-ice-cream-core' ) );
		printf(
			' <span class="cic-custom-hex"%4$s><input type="text" class="cic-hex" name="%1$s" value="%2$s" placeholder="#RRGGBB" pattern="#[0-9A-Fa-f]{6}" maxlength="7" size="9" aria-label="%3$s"> <input type="color" class="cic-hex-picker" value="%5$s" tabindex="-1" aria-hidden="true"></span>',
			esc_attr( $custom_name ),
			esc_attr( $is_custom ? $value : '' ),
			esc_attr__( 'Custom hex colour', 'cater-ice-cream-core' ),
			$is_custom ? '' : ' hidden',
			esc_attr( $is_custom ? strtolower( $value ) : '#000000' )
		);
	}

	/**
	 * Override table for page/section scope (meta box).
	 *
	 * @param array<string, array<string, mixed>> $fields  Override fields.
	 * @param array<string, mixed>                $values  Saved sparse values.
	 */
	public static function render_override_table( string $base, array $fields, array $values, string $context ): void {
		$tokens = Settings::resolved();
		$roles  = $tokens['contexts'][ $context ]['roles'];
		echo '<table class="form-table cic-table cic-override-table" role="presentation"><tbody>';
		foreach ( $fields as $key => $def ) {
			$id     = 'cic-ov-' . $key;
			$name   = $base . '[' . $key . ']';
			$value  = isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
			$active = '' !== $value;
			self::row_open( $def['label'], $id, $active, __( 'Override active', 'cater-ice-cream-core' ) );
			switch ( $def['type'] ) {
				case 'context':
					printf( '<select id="%1$s" name="%2$s"><option value="">%3$s</option>', esc_attr( $id ), esc_attr( $name ), esc_html__( 'Inherit (site default)', 'cater-ice-cream-core' ) );
					foreach ( Schema::contexts() as $ckey => $cdef ) {
						printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $ckey ), selected( $value, $ckey, false ), esc_html( $cdef['label'] ) );
					}
					echo '</select>';
					$inherits = __( 'Inherits: Default brand, unless this content type assigns its own preset.', 'cater-ice-cream-core' );
					break;
				case 'color':
					self::color_choice( $name, $base . '[__custom][' . $key . ']', $id, $value, __( 'Inherit', 'cater-ice-cream-core' ) );
					$ref      = $roles[ $def['role'] ];
					$inherits = sprintf( '%s %s', self::swatch( Settings::hex( $ref ) ), esc_html( sprintf( __( 'Inherits: %s', 'cater-ice-cream-core' ), self::color_label( $ref ) ) ) );
					break;
				case 'font':
					printf( '<select id="%1$s" name="%2$s"><option value="">%3$s</option>', esc_attr( $id ), esc_attr( $name ), esc_html__( 'Inherit', 'cater-ice-cream-core' ) );
					foreach ( Schema::fonts() as $fkey => $fdef ) {
						printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $fkey ), selected( $value, $fkey, false ), esc_html( $fdef['label'] ) );
					}
					echo '</select>';
					$global   = 'font_body' === $key ? $tokens['scalars']['font_body'] : $tokens['scalars']['font_heading'];
					$inherits = esc_html( sprintf( __( 'Inherits: %s', 'cater-ice-cream-core' ), Schema::fonts()[ $global ]['label'] ) );
					break;
				case 'weight':
					printf( '<select id="%1$s" name="%2$s"><option value="">%3$s</option>', esc_attr( $id ), esc_attr( $name ), esc_html__( 'Inherit', 'cater-ice-cream-core' ) );
					foreach ( array( 400, 500, 600, 700 ) as $w ) {
						printf( '<option value="%1$d"%2$s>%1$d</option>', (int) $w, selected( $value, (string) $w, false ) );
					}
					echo '</select>';
					$inherits = esc_html( sprintf( __( 'Inherits: %d (only weights bundled for the heading font are accepted)', 'cater-ice-cream-core' ), $tokens['contexts'][ $context ]['heading_weight'] ?? $tokens['scalars']['heading_weight'] ) );
					break;
				case 'select':
					printf( '<select id="%1$s" name="%2$s"><option value="">%3$s</option>', esc_attr( $id ), esc_attr( $name ), esc_html__( 'Inherit', 'cater-ice-cream-core' ) );
					foreach ( $def['choices'] as $ckey => $clabel ) {
						printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $ckey ), selected( $value, $ckey, false ), esc_html( $clabel ) );
					}
					echo '</select>';
					$inherits = esc_html( sprintf( __( 'Inherits: %s', 'cater-ice-cream-core' ), $tokens['contexts'][ $context ]['heading_accent_style'] ?? $tokens['scalars']['heading_accent_style'] ) );
					break;
				case 'size':
					printf(
						'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$d" max="%5$d" step="1" class="small-text" placeholder="%6$d"> px',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $value ),
						(int) Schema::TYPE_BOUNDS['min'],
						(int) Schema::TYPE_BOUNDS['max'],
						(int) $tokens['type'][ $def['type_role'] ][ $def['edge'] ]
					);
					$inherits = esc_html( sprintf( __( 'Inherits: %d px', 'cater-ice-cream-core' ), $tokens['type'][ $def['type_role'] ][ $def['edge'] ] ) );
					break;
				default:
					$inherits = '';
			}
			self::row_close( $inherits );
		}
		echo '</tbody></table>';
	}
}
