<?php
/**
 * Strict value validators shared by global settings and local overrides. Every value that reaches
 * CSS passes through one of these, so arbitrary CSS, JavaScript or style strings cannot be stored.
 *
 * @package CIC
 */

namespace CIC\Design;

defined( 'ABSPATH' ) || exit;

final class Validate {

	/** Normalised uppercase #RRGGBB, or null. Shorthand #RGB is expanded. */
	public static function hex( string $value ): ?string {
		$value = trim( $value );
		if ( preg_match( '/^#([0-9a-f]{3})$/i', $value, $m ) ) {
			$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		return preg_match( '/^#[0-9a-f]{6}$/i', $value ) ? strtoupper( $value ) : null;
	}

	/** A colour token key or a #RRGGBB value, or null. */
	public static function color_ref( string $value ): ?string {
		if ( isset( Schema::colors()[ $value ] ) ) {
			return $value;
		}
		return self::hex( $value );
	}

	public static function int_in_range( string $value, int $min, int $max ): ?int {
		if ( ! preg_match( '/^-?\d+$/', trim( $value ) ) ) {
			return null;
		}
		$int = (int) $value;
		return ( $int >= $min && $int <= $max ) ? $int : null;
	}

	/**
	 * Number within bounds, rounded to the step. Returns int when the step is whole.
	 *
	 * @return int|float|null
	 */
	public static function number_in_range( string $value, float $min, float $max, float $step ) {
		$value = trim( $value );
		if ( ! preg_match( '/^-?(\d+(\.\d+)?|\.\d+)$/', $value ) ) {
			return null;
		}
		$num = (float) $value;
		if ( $num < $min - 1e-9 || $num > $max + 1e-9 ) {
			return null;
		}
		$num = round( round( ( $num - $min ) / $step ) * $step + $min, 4 );
		return floor( $step ) === $step && floor( $min ) === $min ? (int) $num : $num;
	}
}
