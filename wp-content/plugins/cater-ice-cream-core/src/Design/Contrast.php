<?php
/**
 * WCAG 2.x contrast checks for resolved tokens. Warnings are reported only; saved brand values are
 * never changed automatically (BUILD_SPECIFICATION §5).
 *
 * @package CIC
 */

namespace CIC\Design;

defined( 'ABSPATH' ) || exit;

final class Contrast {

	public static function ratio( string $fg, string $bg ): float {
		$l1 = self::luminance( $fg );
		$l2 = self::luminance( $bg );
		return round( ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 ), 2 );
	}

	public static function luminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		$rgb = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		$lin = array_map(
			static function ( $c ) {
				$c /= 255;
				return $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			},
			$rgb
		);
		return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
	}

	/**
	 * Text/background pairs used by components, per preset. 4.5:1 for normal text, 3:1 for large headings.
	 *
	 * @return array<int, array{context: string, label: string, fg: string, bg: string, ratio: float, required: float, pass: bool}>
	 */
	public static function checks( ?array $tokens = null ): array {
		$tokens = $tokens ?? Settings::resolved();
		$hex    = static function ( string $ref ) use ( $tokens ): string {
			return Validate::hex( $ref ) ?? $tokens['colors'][ $ref ];
		};
		$pairs  = array(
			array( 'text', 'surface', 'Body text on page surface', 4.5 ),
			array( 'text', 'surface_alt', 'Body text on alternate band', 4.5 ),
			array( 'muted', 'surface', 'Muted text on page surface', 4.5 ),
			array( 'heading', 'surface', 'Headings on page surface (large text)', 3.0 ),
			array( 'heading_accent', 'surface', 'Heading accent words (large text)', 3.0 ),
			array( 'link', 'surface', 'Links on page surface', 4.5 ),
			array( 'secondary', 'surface', 'Secondary action on page surface', 4.5 ),
			array( 'on_action', 'action', 'Button text on primary action', 4.5 ),
			array( 'on_action', 'action_hover', 'Button text on hover colour', 4.5 ),
		);
		$out    = array();
		foreach ( $tokens['contexts'] as $key => $context ) {
			foreach ( $pairs as list( $fg_role, $bg_role, $label, $required ) ) {
				$fg    = $hex( $context['roles'][ $fg_role ] );
				$bg    = $hex( $context['roles'][ $bg_role ] );
				$ratio = self::ratio( $fg, $bg );
				$out[] = array(
					'context'  => Schema::contexts()[ $key ]['label'],
					'label'    => $label,
					'fg'       => $fg,
					'bg'       => $bg,
					'ratio'    => $ratio,
					'required' => $required,
					'pass'     => $ratio >= $required,
				);
			}
		}
		$s          = $tokens['scalars'];
		$components = array(
			array( $s['call_panel_text'], $s['call_panel_surface'], 'Call panel text' ),
			array( $s['footer_text'], $s['footer_surface'], 'Footer text' ),
			array( 'text_primary', $s['quote_starter_surface'], 'Quote starter text' ),
			array( 'text_primary', $s['header_surface'], 'Header navigation text' ),
		);
		foreach ( $components as list( $fg_ref, $bg_ref, $label ) ) {
			$fg    = $hex( $fg_ref );
			$bg    = $hex( $bg_ref );
			$ratio = self::ratio( $fg, $bg );
			$out[] = array(
				'context'  => 'Components',
				'label'    => $label,
				'fg'       => $fg,
				'bg'       => $bg,
				'ratio'    => $ratio,
				'required' => 4.5,
				'pass'     => $ratio >= 4.5,
			);
		}
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function failures( ?array $tokens = null ): array {
		return array_values( array_filter( self::checks( $tokens ), static fn( $c ) => ! $c['pass'] ) );
	}
}
