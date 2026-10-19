<?php
/**
 * Sparse local overrides at page, section and element scope.
 *
 * - Only fields an editor deliberately sets are stored; an empty field means inherit.
 * - Values are allowlisted (token key, #hex, bounded integer, named choice) and emitted only as
 *   custom properties, so no arbitrary CSS can be stored or printed.
 * - Page overrides: post meta, scoped to body.cic-page-{ID}.
 * - Section/element overrides: the cicStyle block attribute, applied as inline custom properties
 *   on that block instance only, so two instances of a block can differ.
 *
 * @package CIC
 */

namespace CIC\Design;

defined( 'ABSPATH' ) || exit;

final class Overrides {

	public const META  = '_cic_design_overrides';
	public const NONCE = 'cic_design_overrides_nonce';

	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_page_css' ), 30 );
		add_filter( 'register_block_type_args', array( $this, 'add_block_attribute' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'render_block' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_script' ) );
	}

	/**
	 * Post types whose entries can carry page-scope overrides. Later phases add cic_event via the filter.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		return (array) apply_filters( 'cic_design_override_post_types', array( 'page', 'post', 'product', 'cic_event' ) );
	}

	public function register_meta(): void {
		foreach ( self::post_types() as $type ) {
			register_post_meta(
				$type,
				self::META,
				array(
					'type'              => 'object',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => static function ( $value ) {
						$errors = array();
						return self::sanitize( is_array( $value ) ? $value : array(), Schema::scope_fields(), $errors );
					},
					'auth_callback'     => static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
				)
			);
		}
	}

	/* ------------------------------------------------------------------ *
	 * Validation and CSS generation (shared by every scope)
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string, mixed>                $input  Raw values.
	 * @param array<string, array<string, mixed>> $fields Allowed fields.
	 * @param string[]                            $errors Messages (by reference).
	 * @return array<string, mixed> Sparse clean values.
	 */
	public static function sanitize( array $input, array $fields, array &$errors ): array {
		$clean  = array();
		$tokens = Settings::resolved();
		foreach ( $fields as $key => $def ) {
			if ( ! isset( $input[ $key ] ) || is_array( $input[ $key ] ) ) {
				continue;
			}
			$raw = trim( (string) $input[ $key ] );
			if ( '' === $raw ) {
				continue;
			}
			$value = null;
			switch ( $def['type'] ) {
				case 'context':
					$value = isset( Schema::contexts()[ $raw ] ) ? $raw : null;
					break;
				case 'color':
					$value = Validate::color_ref( $raw );
					break;
				case 'font':
					$value = isset( Schema::fonts()[ $raw ] ) ? $raw : null;
					break;
				case 'weight':
					$family = ( isset( $input['font_heading'] ) && isset( Schema::fonts()[ $input['font_heading'] ] ) ) ? $input['font_heading'] : $tokens['scalars']['font_heading'];
					$int    = Validate::int_in_range( $raw, 100, 900 );
					$value  = ( null !== $int && in_array( $int, Schema::fonts()[ $family ]['weights'], true ) ) ? $int : null;
					break;
				case 'select':
					$value = isset( $def['choices'][ $raw ] ) ? $raw : null;
					break;
				case 'size':
					$value = Validate::int_in_range( $raw, Schema::TYPE_BOUNDS['min'], Schema::TYPE_BOUNDS['max'] );
					break;
			}
			if ( null === $value ) {
				$errors[] = sprintf( '%s: value not allowed, so it was ignored.', $def['label'] );
				continue;
			}
			$clean[ $key ] = $value;
		}

		// A mobile size larger than the desktop size is rejected (both edges dropped).
		foreach ( array( 'size', 'h1', 'h2', 'h3', 'body' ) as $prefix ) {
			if ( isset( $clean[ $prefix . '_min' ], $clean[ $prefix . '_max' ] ) && $clean[ $prefix . '_min' ] > $clean[ $prefix . '_max' ] ) {
				$errors[] = 'A mobile size cannot be larger than the desktop size, so both were ignored.';
				unset( $clean[ $prefix . '_min' ], $clean[ $prefix . '_max' ] );
			}
		}
		return $clean;
	}

	/**
	 * Classes and custom-property declarations for clean override values.
	 *
	 * @param array<string, mixed> $clean     Sanitized values.
	 * @param string               $kind      page | section | heading | text | button.
	 * @param string|null          $type_role Type role for element sizes (h1–h4, body).
	 * @return array{classes: string[], decls: string[]}
	 */
	public static function declarations( array $clean, string $kind, ?string $type_role = null ): array {
		$classes = array();
		$decls   = array();
		$tokens  = Settings::resolved();
		$fonts   = Schema::fonts();

		foreach ( $clean as $key => $value ) {
			if ( 'context' === $key ) {
				$classes[] = 'cic-ctx';
				$classes[] = 'cic-context-' . $value;
				continue;
			}
			if ( in_array( $key, array_keys( Schema::roles() ), true ) ) {
				$decls[] = Stylesheet::role_var( $key ) . ':' . Stylesheet::color_value( $value );
				if ( 'section' === $kind && in_array( $key, array( 'surface', 'text' ), true ) ) {
					$classes[] = 'cic-ctx';
				}
				continue;
			}
			switch ( $key ) {
				case 'font_heading':
				case 'font_body':
					$decls[] = '--cic-' . str_replace( '_', '-', $key ) . ':var(--wp--preset--font-family--' . $fonts[ $value ]['slug'] . ')';
					break;
				case 'heading_weight':
					$decls[] = '--cic-heading-weight:' . (int) $value;
					break;
				case 'heading_accent_style':
					$decls[] = '--cic-heading-accent-style:' . $value;
					break;
			}
			if ( preg_match( '/^(h1|h2|h3|body)_(min|max)$/', $key, $m ) ) {
				$role    = $tokens['type'][ $m[1] ];
				$lh      = (int) round( (int) $value * $role[ 'lh_' . $m[2] ] / max( 1, $role[ $m[2] ] ) );
				$decls[] = '--cic-fs-' . $m[1] . '-' . $m[2] . ':' . (int) $value;
				$decls[] = '--cic-fs-' . $m[1] . '-lh-' . $m[2] . ':' . $lh;
			}
		}

		if ( $type_role && ( isset( $clean['size_min'] ) || isset( $clean['size_max'] ) ) ) {
			$role      = $tokens['type'][ $type_role ];
			$classes[] = 'cic-has-size';
			foreach ( array( 'min', 'max' ) as $edge ) {
				if ( isset( $clean[ 'size_' . $edge ] ) ) {
					$size    = (int) $clean[ 'size_' . $edge ];
					$decls[] = '--cic-fs-' . $edge . ':' . $size;
					$decls[] = '--cic-lh-' . $edge . ':' . (int) round( $size * $role[ 'lh_' . $edge ] / max( 1, $role[ $edge ] ) );
				} else {
					$decls[] = '--cic-fs-' . $edge . ':var(--cic-fs-' . $type_role . '-' . $edge . ')';
					$decls[] = '--cic-lh-' . $edge . ':var(--cic-fs-' . $type_role . '-lh-' . $edge . ')';
				}
			}
		}

		return array(
			'classes' => array_values( array_unique( $classes ) ),
			'decls'   => $decls,
		);
	}

	/* ------------------------------------------------------------------ *
	 * Page scope
	 * ------------------------------------------------------------------ */

	/**
	 * @return array<string, mixed>
	 */
	public static function page_overrides( int $post_id ): array {
		$saved = get_post_meta( $post_id, self::META, true );
		if ( ! is_array( $saved ) || ! $saved ) {
			return array();
		}
		$errors = array();
		return self::sanitize( $saved, Schema::scope_fields(), $errors );
	}

	/** The preset applied to the current request's <body>. */
	public static function current_context(): string {
		$context = 'brand';
		if ( is_singular() ) {
			$overrides = self::page_overrides( (int) get_queried_object_id() );
			if ( isset( $overrides['context'] ) ) {
				$context = $overrides['context'];
			}
		}
		$context = (string) apply_filters( 'cic_design_page_context', $context );
		return isset( Schema::contexts()[ $context ] ) ? $context : 'brand';
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		if ( ! ThemeJson::is_active_theme() ) {
			return $classes;
		}
		$classes[] = 'cic-ctx';
		$classes[] = 'cic-context-' . self::current_context();
		if ( is_singular() && self::page_overrides( (int) get_queried_object_id() ) ) {
			$classes[] = 'cic-page-' . (int) get_queried_object_id();
		}
		return $classes;
	}

	public function enqueue_page_css(): void {
		if ( ! ThemeJson::is_active_theme() || ! is_singular() ) {
			return;
		}
		$css = self::page_css( (int) get_queried_object_id() );
		if ( '' !== $css && wp_style_is( Stylesheet::HANDLE, 'enqueued' ) ) {
			wp_add_inline_style( Stylesheet::HANDLE, $css );
		}
	}

	/**
	 * Scoped CSS for one page's overrides. Also targets nested presets so a page override still
	 * applies inside a section that switches preset (an explicit section override still wins).
	 */
	public static function page_css( int $post_id ): string {
		$overrides = self::page_overrides( $post_id );
		unset( $overrides['context'] );
		if ( ! $overrides ) {
			return '';
		}
		$parts = self::declarations( $overrides, 'page' );
		if ( ! $parts['decls'] ) {
			return '';
		}
		$scope = 'body.cic-page-' . $post_id;
		return '/* page ' . $post_id . " overrides */\n" . $scope . ',' . $scope . ' .cic-ctx{' . implode( ';', $parts['decls'] ) . "}\n";
	}

	public function add_meta_box(): void {
		if ( ! ThemeJson::is_active_theme() || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		foreach ( self::post_types() as $type ) {
			add_meta_box( 'cic-design-overrides', __( 'Design overrides (this page only)', 'cater-ice-cream-core' ), array( $this, 'render_meta_box' ), $type, 'normal', 'low' );
		}
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'cic_save_overrides_' . $post->ID, self::NONCE );
		$values = self::page_overrides( $post->ID );
		Admin\Fields::enqueue();
		echo '<p class="description">' . esc_html__( 'Everything here inherits from the Design System by default. Set a field only to make a deliberate exception for this page; use Reset to inherit again, including later global changes.', 'cater-ice-cream-core' ) . '</p>';
		Admin\Fields::render_override_table( 'cic_overrides', Schema::scope_fields(), $values, $values['context'] ?? 'brand' );
	}

	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'cic_save_overrides_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$raw    = isset( $_POST['cic_overrides'] ) && is_array( $_POST['cic_overrides'] ) ? wp_unslash( $_POST['cic_overrides'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- allowlisted in sanitize().
		$raw    = self::flatten_color_choices( $raw );
		$errors = array();
		$clean  = self::sanitize( $raw, Schema::scope_fields(), $errors );
		if ( $clean ) {
			update_post_meta( $post_id, self::META, $clean );
		} else {
			delete_post_meta( $post_id, self::META );
		}
		if ( $errors ) {
			set_transient( 'cic_override_errors_' . get_current_user_id(), $errors, 60 );
		}
		do_action( 'cic_design_page_overrides_saved', $post_id, $clean );
	}

	/**
	 * Colour rows post a select (token key, '' or 'custom') plus a hex box; collapse to one value.
	 *
	 * @param array<string, mixed> $raw Posted values.
	 * @return array<string, mixed>
	 */
	public static function flatten_color_choices( array $raw ): array {
		$custom = isset( $raw['__custom'] ) && is_array( $raw['__custom'] ) ? $raw['__custom'] : array();
		unset( $raw['__custom'] );
		foreach ( $raw as $key => $value ) {
			if ( 'custom' === $value ) {
				$raw[ $key ] = isset( $custom[ $key ] ) ? (string) $custom[ $key ] : '';
			}
		}
		return $raw;
	}

	/* ------------------------------------------------------------------ *
	 * Section and element scope (blocks)
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string, mixed> $args Block type args.
	 * @return array<string, mixed>
	 */
	public function add_block_attribute( array $args, string $name ): array {
		if ( isset( Schema::block_scopes()[ $name ] ) ) {
			$args['attributes']             = $args['attributes'] ?? array();
			$args['attributes']['cicStyle'] = array( 'type' => 'object' );
		}
		return $args;
	}

	/**
	 * @param array<string, mixed> $block Parsed block.
	 */
	public function render_block( string $html, array $block ): string {
		$scope = Schema::block_scopes()[ $block['blockName'] ?? '' ] ?? null;
		if ( null === $scope || empty( $block['attrs']['cicStyle'] ) || ! is_array( $block['attrs']['cicStyle'] ) || '' === trim( $html ) ) {
			return $html;
		}
		$parts = self::block_parts( $block['blockName'], $block['attrs'] );
		if ( ! $parts || ( ! $parts['classes'] && ! $parts['decls'] ) ) {
			return $html;
		}
		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return $html;
		}
		foreach ( array_merge( array( 'cic-has-override' ), $parts['classes'] ) as $class ) {
			$processor->add_class( $class );
		}
		if ( $parts['decls'] ) {
			$existing = trim( (string) $processor->get_attribute( 'style' ) );
			$style    = ( '' !== $existing ? rtrim( $existing, ';' ) . ';' : '' ) . implode( ';', $parts['decls'] );
			$processor->set_attribute( 'style', $style );
		}
		return $processor->get_updated_html();
	}

	/**
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array{classes: string[], decls: string[]}|null
	 */
	public static function block_parts( string $block_name, array $attrs ): ?array {
		$scope = Schema::block_scopes()[ $block_name ] ?? null;
		if ( null === $scope ) {
			return null;
		}
		$fields    = 'section' === $scope ? Schema::scope_fields() : Schema::element_fields()[ $scope ];
		$errors    = array();
		$clean     = self::sanitize( (array) ( $attrs['cicStyle'] ?? array() ), $fields, $errors );
		$type_role = null;
		if ( 'heading' === $scope ) {
			$type_role = 'h' . min( 4, max( 1, (int) ( $attrs['level'] ?? 2 ) ) );
		} elseif ( 'text' === $scope ) {
			$type_role = 'body';
		}
		return $clean ? self::declarations( $clean, $scope, $type_role ) : null;
	}

	public function enqueue_editor_script(): void {
		if ( ! ThemeJson::is_active_theme() ) {
			return;
		}
		$path = CIC_CORE_DIR . 'assets/js/editor-overrides.js';
		wp_enqueue_script(
			'cic-editor-overrides',
			CIC_CORE_URL . 'assets/js/editor-overrides.js',
			array( 'wp-blocks', 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			(string) filemtime( $path ),
			true
		);
		wp_add_inline_script( 'cic-editor-overrides', 'window.cicDesign = ' . wp_json_encode( self::editor_data() ) . ';', 'before' );
	}

	/**
	 * Data the editor panel needs to show inherited values and preview overrides.
	 *
	 * @return array<string, mixed>
	 */
	public static function editor_data(): array {
		$tokens = Settings::resolved();
		$colors = array();
		foreach ( Schema::colors() as $key => $def ) {
			$colors[] = array(
				'key'   => $key,
				'label' => $def['label'],
				'slug'  => $def['slug'],
				'hex'   => $tokens['colors'][ $key ],
			);
		}
		$contexts = array();
		foreach ( Schema::contexts() as $key => $def ) {
			$contexts[] = array(
				'key'   => $key,
				'label' => $def['label'],
				'roles' => $tokens['contexts'][ $key ]['roles'],
			);
		}
		$fonts = array();
		foreach ( Schema::fonts() as $key => $def ) {
			$fonts[] = array(
				'key'     => $key,
				'label'   => $def['label'],
				'slug'    => $def['slug'],
				'weights' => $def['weights'],
			);
		}
		return array(
			'colors'        => $colors,
			'contexts'      => $contexts,
			'fonts'         => $fonts,
			'type'          => $tokens['type'],
			'fontHeading'   => $tokens['scalars']['font_heading'],
			'headingWeight' => $tokens['scalars']['heading_weight'],
			'scopeFields'   => Schema::scope_fields(),
			'elementFields' => Schema::element_fields(),
			'blockScopes'   => Schema::block_scopes(),
			'roles'         => Schema::roles(),
			'bounds'        => Schema::TYPE_BOUNDS,
		);
	}
}
