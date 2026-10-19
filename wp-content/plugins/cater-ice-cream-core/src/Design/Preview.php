<?php
/**
 * Private style preview: real components rendered with the live theme header, footer and saved
 * tokens, for every preset. Only users who can edit theme options can open it; never indexed or cached.
 *
 * @package CIC
 */

namespace CIC\Design;

defined( 'ABSPATH' ) || exit;

final class Preview {

	public const QUERY_VAR = 'cic_style_preview';

	public function register(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'guard' ), 0 );
		add_filter( 'template_include', array( $this, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 40 );
	}

	public function enqueue(): void {
		if ( self::is_preview() && current_user_can( 'edit_theme_options' ) ) {
			wp_enqueue_style( 'cic-style-preview', CIC_CORE_URL . 'assets/css/style-preview.css', array( Stylesheet::HANDLE ), (string) filemtime( CIC_CORE_DIR . 'assets/css/style-preview.css' ) );
		}
	}

	public static function url( string $context = '' ): string {
		$args = array( self::QUERY_VAR => '1' );
		if ( '' !== $context ) {
			$args['ctx'] = $context;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function is_preview(): bool {
		return '1' === (string) get_query_var( self::QUERY_VAR );
	}

	public function guard(): void {
		if ( ! self::is_preview() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view the style preview.', 'cater-ice-cream-core' ), '', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'document_title_parts', static fn( $parts ) => array_merge( $parts, array( 'title' => __( 'Style preview', 'cater-ice-cream-core' ) ) ) );
		status_header( 200 );
		global $wp_query;
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
	}

	public function template( string $template ): string {
		if ( self::is_preview() && current_user_can( 'edit_theme_options' ) && ThemeJson::is_active_theme() ) {
			return CIC_CORE_DIR . 'templates/style-preview.php';
		}
		return $template;
	}

	/** Context requested with ?ctx=, limited to known presets. */
	public static function focus_context(): string {
		$ctx = isset( $_GET['ctx'] ) ? sanitize_key( wp_unslash( $_GET['ctx'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
		return isset( Schema::contexts()[ $ctx ] ) ? $ctx : '';
	}
}
