<?php
/**
 * Generated preset (context) stylesheet. Each preset maps semantic roles to colour tokens by
 * reference (var(--wp--preset--color--…)), so a global token change reaches every preset that
 * inherits it. Only explicitly chosen #hex values are literal.
 *
 * Printed inline after the theme stylesheet on the frontend and inside the block editor canvas.
 *
 * @package CIC
 */

namespace CIC\Design;

defined( 'ABSPATH' ) || exit;

final class Stylesheet {

	public const HANDLE = 'cic-design';

	/** Per-request cache, invalidated with Settings::flush_cache(). */
	private static ?string $css = null;
	private static int $css_version = 0;

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 20 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor' ), 20 );
	}

	public function enqueue_frontend(): void {
		if ( ! ThemeJson::is_active_theme() ) {
			return;
		}
		$deps = wp_style_is( 'cic-main', 'registered' ) ? array( 'cic-main' ) : array();
		wp_register_style( self::HANDLE, false, $deps, (string) Settings::version() );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, self::css() );
	}

	/** enqueue_block_assets also runs on the frontend; only act inside the editor here. */
	public function enqueue_editor(): void {
		if ( ! is_admin() || ! ThemeJson::is_active_theme() ) {
			return;
		}
		wp_register_style( self::HANDLE . '-editor', false, array(), (string) Settings::version() );
		wp_enqueue_style( self::HANDLE . '-editor' );
		wp_add_inline_style( self::HANDLE . '-editor', self::css() );
	}

	public static function css(): string {
		if ( null !== self::$css && self::$css_version === Settings::version() ) {
			return self::$css;
		}
		$tokens = Settings::resolved();
		$out    = '/* Cater Ice Cream design presets v' . Settings::version() . " (generated) */\n";
		foreach ( $tokens['contexts'] as $key => $context ) {
			$selector = 'brand' === $key ? ':root,.cic-context-brand' : '.cic-context-' . $key;
			$decls    = array();
			foreach ( $context['roles'] as $role => $ref ) {
				$decls[] = self::role_var( $role ) . ':' . self::color_value( $ref );
			}
			$decls[] = '--cic-heading-weight:' . ( null !== $context['heading_weight'] ? (int) $context['heading_weight'] : 'var(--wp--custom--cic--font--heading-weight)' );
			$decls[] = '--cic-heading-accent-style:' . ( null !== $context['heading_accent_style'] ? $context['heading_accent_style'] : 'var(--wp--custom--cic--font--heading-accent-style)' );
			$out    .= $selector . '{' . implode( ';', $decls ) . "}\n";
		}
		self::$css         = $out;
		self::$css_version = Settings::version();
		return $out;
	}

	public static function role_var( string $role ): string {
		return '--cic-color-' . str_replace( '_', '-', $role );
	}

	/**
	 * CSS value for a validated colour reference: token key → preset variable, #hex → literal.
	 */
	public static function color_value( string $ref ): string {
		$colors = Schema::colors();
		if ( isset( $colors[ $ref ] ) ) {
			return 'var(--wp--preset--color--' . $colors[ $ref ]['slug'] . ')';
		}
		return Validate::hex( $ref ) ?? 'inherit';
	}

	public static function reset(): void {
		self::$css = null;
	}
}
