<?php
/**
 * Design System settings: storage, validation and resolution into one token object.
 *
 * Storage is sparse: only values that differ from Schema defaults are saved, so an empty field
 * always means "use the default" and a reset simply removes the saved value.
 *
 * @package CIC
 */

namespace CIC\Design;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION  = 'cic_design_settings';
	public const VERSION = 'cic_design_version';

	/** Per-request cache of resolved tokens. */
	private static ?array $resolved = null;

	/**
	 * Saved sparse settings.
	 *
	 * @return array{colors: array, scalars: array, type: array, contexts: array}
	 */
	public static function saved(): array {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'colors'   => is_array( $saved['colors'] ?? null ) ? $saved['colors'] : array(),
			'scalars'  => is_array( $saved['scalars'] ?? null ) ? $saved['scalars'] : array(),
			'type'     => is_array( $saved['type'] ?? null ) ? $saved['type'] : array(),
			'contexts' => is_array( $saved['contexts'] ?? null ) ? $saved['contexts'] : array(),
		);
	}

	public static function version(): int {
		return (int) get_option( self::VERSION, 1 );
	}

	public static function flush_cache(): void {
		self::$resolved = null;
	}

	/**
	 * Defaults merged with saved values, plus every context fully resolved to token keys or hex values.
	 *
	 * @return array{colors: array<string, string>, scalars: array<string, mixed>, type: array<string, array<string, int>>, contexts: array<string, array<string, mixed>>}
	 */
	public static function resolved(): array {
		if ( null !== self::$resolved ) {
			return self::$resolved;
		}
		$defaults = Schema::defaults();
		$saved    = self::saved();

		$tokens = array(
			'colors'   => array_merge( $defaults['colors'], array_intersect_key( $saved['colors'], $defaults['colors'] ) ),
			'scalars'  => array_merge( $defaults['scalars'], array_intersect_key( $saved['scalars'], $defaults['scalars'] ) ),
			'type'     => $defaults['type'],
			'contexts' => array(),
		);
		foreach ( $tokens['type'] as $role => $values ) {
			if ( isset( $saved['type'][ $role ] ) && is_array( $saved['type'][ $role ] ) ) {
				$tokens['type'][ $role ] = array_merge( $values, array_intersect_key( $saved['type'][ $role ], $values ) );
			}
		}

		$brand = self::context_roles( 'brand', $saved['contexts'], Schema::brand_roles() );
		foreach ( Schema::contexts() as $key => $context ) {
			$roles = 'brand' === $key ? $brand : self::context_roles( $key, $saved['contexts'], $brand );
			$saved_ctx = $saved['contexts'][ $key ] ?? array();
			$tokens['contexts'][ $key ] = array(
				'roles'                => $roles,
				'heading_weight'       => $saved_ctx['heading_weight'] ?? ( $context['heading_weight'] ?? null ),
				'heading_accent_style' => $saved_ctx['heading_accent_style'] ?? ( $context['heading_accent_style'] ?? null ),
			);
		}

		self::$resolved = $tokens;
		return $tokens;
	}

	/**
	 * Resolve one context's roles: saved value > preset default > brand mapping.
	 * A saved value of 'brand' explicitly follows the brand mapping.
	 *
	 * @param array<string, mixed>  $saved_contexts Saved sparse contexts.
	 * @param array<string, string> $brand          Resolved brand roles (or schema brand roles for 'brand').
	 * @return array<string, string>
	 */
	private static function context_roles( string $key, array $saved_contexts, array $brand ): array {
		$preset = Schema::contexts()[ $key ]['roles'] ?? array();
		$saved  = $saved_contexts[ $key ]['roles'] ?? array();
		$roles  = array();
		foreach ( Schema::roles() as $role => $label ) {
			$value = $saved[ $role ] ?? '';
			if ( 'brand' === $value || '' === $value ) {
				$value = '' === $value ? ( $preset[ $role ] ?? $brand[ $role ] ) : $brand[ $role ];
			}
			$roles[ $role ] = $value;
		}
		return $roles;
	}

	/**
	 * Hex value of a colour reference (token key or #hex) using resolved tokens.
	 */
	public static function hex( string $ref ): ?string {
		if ( Validate::hex( $ref ) ) {
			return Validate::hex( $ref );
		}
		return self::resolved()['colors'][ $ref ] ?? null;
	}

	/**
	 * Sanitize a submitted (or imported) configuration into sparse storage.
	 * Invalid values are reported and the previously saved value is kept; nothing is silently changed.
	 *
	 * @param mixed                $input    Raw input.
	 * @param array<string, mixed> $previous Previously saved sparse settings.
	 * @param string[]             $errors   Collected error messages (by reference).
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input, array $previous, array &$errors ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = Schema::defaults();
		$clean    = array(
			'colors'   => array(),
			'scalars'  => array(),
			'type'     => array(),
			'contexts' => array(),
		);

		// Colours.
		foreach ( Schema::colors() as $key => $def ) {
			$raw = isset( $input['colors'][ $key ] ) ? trim( (string) $input['colors'][ $key ] ) : '';
			if ( '' === $raw ) {
				continue;
			}
			$hex = Validate::hex( $raw );
			if ( null === $hex ) {
				$errors[] = sprintf( '%s: "%s" is not a valid #RRGGBB colour. The previous value was kept.', $def['label'], self::shorten( $raw ) );
				if ( isset( $previous['colors'][ $key ] ) ) {
					$clean['colors'][ $key ] = $previous['colors'][ $key ];
				}
				continue;
			}
			if ( strcasecmp( $hex, $def['default'] ) !== 0 ) {
				$clean['colors'][ $key ] = $hex;
			}
		}

		// Scalars (fonts first, so weights can be checked against the chosen family).
		$scalars = Schema::scalars();
		foreach ( $scalars as $key => $def ) {
			$raw = isset( $input['scalars'][ $key ] ) ? trim( (string) $input['scalars'][ $key ] ) : '';
			if ( '' === $raw ) {
				continue;
			}
			$value = self::sanitize_scalar( $def, $raw );
			if ( null === $value ) {
				$errors[] = sprintf( '%s: "%s" is not allowed. %s The previous value was kept.', $def['label'], self::shorten( $raw ), self::bounds_hint( $def ) );
				if ( isset( $previous['scalars'][ $key ] ) ) {
					$clean['scalars'][ $key ] = $previous['scalars'][ $key ];
				}
				continue;
			}
			if ( $value != $def['default'] ) { // phpcs:ignore Universal.Operators.StrictComparisons -- numeric strings vs numbers.
				$clean['scalars'][ $key ] = $value;
			}
		}
		foreach ( $scalars as $key => $def ) {
			if ( 'weight' !== $def['type'] ) {
				continue;
			}
			$family  = $clean['scalars'][ $def['font'] ] ?? $scalars[ $def['font'] ]['default'];
			$weight  = (int) ( $clean['scalars'][ $key ] ?? $def['default'] );
			$allowed = Schema::fonts()[ $family ]['weights'];
			if ( ! in_array( $weight, $allowed, true ) ) {
				$errors[] = sprintf( '%s %d is not bundled for %s (available: %s). It was set to %d.', $def['label'], $weight, Schema::fonts()[ $family ]['label'], implode( ', ', $allowed ), self::closest( $weight, $allowed ) );
				$value = self::closest( $weight, $allowed );
				if ( $value === (int) $def['default'] ) {
					unset( $clean['scalars'][ $key ] );
				} else {
					$clean['scalars'][ $key ] = $value;
				}
			}
		}

		// Responsive type scale.
		foreach ( Schema::type_roles() as $role => $def ) {
			$merged = $defaults['type'][ $role ];
			$given  = array();
			foreach ( array( 'min', 'max', 'lh_min', 'lh_max' ) as $edge ) {
				$raw = isset( $input['type'][ $role ][ $edge ] ) ? trim( (string) $input['type'][ $role ][ $edge ] ) : '';
				if ( '' === $raw ) {
					continue;
				}
				$value = Validate::int_in_range( $raw, Schema::TYPE_BOUNDS['min'], Schema::TYPE_BOUNDS['max'] );
				if ( null === $value ) {
					$errors[] = sprintf( '%s %s: "%s" must be a whole number from %d to %d px. The previous value was kept.', $def['label'], self::edge_label( $edge ), self::shorten( $raw ), Schema::TYPE_BOUNDS['min'], Schema::TYPE_BOUNDS['max'] );
					if ( isset( $previous['type'][ $role ][ $edge ] ) ) {
						$given[ $edge ] = (int) $previous['type'][ $role ][ $edge ];
					}
					continue;
				}
				$given[ $edge ] = $value;
			}
			$check = array_merge( $merged, $given );
			if ( $check['min'] > $check['max'] || $check['lh_min'] < $check['min'] || $check['lh_max'] < $check['max'] ) {
				$errors[] = sprintf( '%s: mobile size must not exceed desktop size and line heights must be at least the font size. The previous values were kept.', $def['label'] );
				$given = is_array( $previous['type'][ $role ] ?? null ) ? $previous['type'][ $role ] : array();
			}
			foreach ( $given as $edge => $value ) {
				if ( (int) $value !== $merged[ $edge ] ) {
					$clean['type'][ $role ][ $edge ] = (int) $value;
				}
			}
		}

		// Contexts: sparse role references.
		foreach ( Schema::contexts() as $key => $context ) {
			$raw_ctx = $input['contexts'][ $key ] ?? array();
			if ( ! is_array( $raw_ctx ) ) {
				continue;
			}
			foreach ( Schema::roles() as $role => $label ) {
				$raw_choice = isset( $raw_ctx['roles'][ $role ] ) ? trim( (string) $raw_ctx['roles'][ $role ] ) : '';
				if ( 'custom' === $raw_choice ) {
					$raw_choice = isset( $raw_ctx['custom'][ $role ] ) ? trim( (string) $raw_ctx['custom'][ $role ] ) : '';
				}
				if ( '' === $raw_choice ) {
					continue;
				}
				if ( 'brand' === $raw_choice && 'brand' !== $key ) {
					$clean['contexts'][ $key ]['roles'][ $role ] = 'brand';
					continue;
				}
				$ref = Validate::color_ref( $raw_choice );
				if ( null === $ref ) {
					$errors[] = sprintf( '%s preset, %s: "%s" is not a colour token or #RRGGBB value. The previous value was kept.', $context['label'], $label, self::shorten( $raw_choice ) );
					if ( isset( $previous['contexts'][ $key ]['roles'][ $role ] ) ) {
						$clean['contexts'][ $key ]['roles'][ $role ] = $previous['contexts'][ $key ]['roles'][ $role ];
					}
					continue;
				}
				$seed = $context['roles'][ $role ] ?? ( 'brand' === $key ? Schema::brand_roles()[ $role ] : null );
				if ( $ref !== $seed ) {
					$clean['contexts'][ $key ]['roles'][ $role ] = $ref;
				}
			}
			$weight = isset( $raw_ctx['heading_weight'] ) ? trim( (string) $raw_ctx['heading_weight'] ) : '';
			if ( '' !== $weight ) {
				$family  = $clean['scalars']['font_heading'] ?? $scalars['font_heading']['default'];
				$allowed = Schema::fonts()[ $family ]['weights'];
				$value   = Validate::int_in_range( $weight, 100, 900 );
				if ( null === $value || ! in_array( $value, $allowed, true ) ) {
					$errors[] = sprintf( '%s preset heading weight "%s" is not bundled for the heading font. The previous value was kept.', $context['label'], self::shorten( $weight ) );
					if ( isset( $previous['contexts'][ $key ]['heading_weight'] ) ) {
						$clean['contexts'][ $key ]['heading_weight'] = $previous['contexts'][ $key ]['heading_weight'];
					}
				} elseif ( $value !== ( $context['heading_weight'] ?? null ) ) {
					$clean['contexts'][ $key ]['heading_weight'] = $value;
				}
			}
			$style = isset( $raw_ctx['heading_accent_style'] ) ? trim( (string) $raw_ctx['heading_accent_style'] ) : '';
			if ( '' !== $style ) {
				if ( ! in_array( $style, array( 'normal', 'italic' ), true ) ) {
					$errors[] = sprintf( '%s preset accent style "%s" is not allowed.', $context['label'], self::shorten( $style ) );
				} elseif ( $style !== ( $context['heading_accent_style'] ?? null ) ) {
					$clean['contexts'][ $key ]['heading_accent_style'] = $style;
				}
			}
		}

		return $clean;
	}

	/**
	 * @param array<string, mixed> $def Scalar definition.
	 * @return mixed|null Sanitized value or null when invalid.
	 */
	private static function sanitize_scalar( array $def, string $raw ) {
		switch ( $def['type'] ) {
			case 'number':
				return Validate::number_in_range( $raw, (float) $def['min'], (float) $def['max'], (float) $def['step'] );
			case 'font':
				return isset( Schema::fonts()[ $raw ] ) ? $raw : null;
			case 'weight':
				return Validate::int_in_range( $raw, 100, 900 );
			case 'select':
				return isset( $def['choices'][ $raw ] ) ? $raw : null;
			case 'color_ref':
				return isset( Schema::colors()[ $raw ] ) ? $raw : null;
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $def Scalar definition.
	 */
	private static function bounds_hint( array $def ): string {
		if ( 'number' === $def['type'] ) {
			return sprintf( 'Use a number from %s to %s.', $def['min'], $def['max'] );
		}
		return 'Choose one of the listed options.';
	}

	private static function edge_label( string $edge ): string {
		return array(
			'min'    => 'mobile size',
			'max'    => 'desktop size',
			'lh_min' => 'mobile line height',
			'lh_max' => 'desktop line height',
		)[ $edge ];
	}

	/**
	 * @param int[] $allowed Allowed values.
	 */
	private static function closest( int $value, array $allowed ): int {
		usort( $allowed, static fn( $a, $b ) => abs( $a - $value ) <=> abs( $b - $value ) );
		return $allowed[0];
	}

	/** Short, plain echo of rejected input for messages (escaped again on output). */
	private static function shorten( string $raw ): string {
		$raw = wp_strip_all_tags( $raw );
		return mb_strlen( $raw ) > 40 ? mb_substr( $raw, 0, 40 ) . '…' : $raw;
	}
}
