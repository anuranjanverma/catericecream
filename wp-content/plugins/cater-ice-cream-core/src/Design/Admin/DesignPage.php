<?php
/**
 * Cater Ice Cream → Design System screen (Settings API). The only editable source of global
 * design values. Includes contrast warnings, export and validated import.
 *
 * @package CIC
 */

namespace CIC\Design\Admin;

use CIC\Design\Contrast;
use CIC\Design\Preview;
use CIC\Design\Schema;
use CIC\Design\Settings;

defined( 'ABSPATH' ) || exit;

final class DesignPage {

	public const SLUG       = 'cic-design-system';
	public const GROUP      = 'cic_design';
	public const CAPABILITY = 'manage_options';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_post_cic_design_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_cic_design_import', array( $this, 'handle_import' ) );
		add_filter( 'option_page_capability_' . self::GROUP, static fn() => self::CAPABILITY );
	}

	public function register_setting(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
				'default'           => array(),
			)
		);
	}

	/**
	 * @param mixed $input Posted settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		static $done = array();
		$errors      = array();
		$clean       = Settings::sanitize( $input, Settings::saved(), $errors );
		// add_option() can run the callback twice for a new option; report each message once.
		foreach ( array_unique( $errors ) as $i => $message ) {
			if ( ! isset( $done[ $message ] ) && function_exists( 'add_settings_error' ) ) {
				add_settings_error( Settings::OPTION, 'cic_design_' . $i, $message, 'error' );
				$done[ $message ] = true;
			}
		}
		return $clean;
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to edit the design system.', 'cater-ice-cream-core' ), 403 );
		}
		Fields::enqueue();
		$saved    = Settings::saved();
		$tokens   = Settings::resolved();
		$defaults = Schema::defaults();
		$opt      = Settings::OPTION;
		$tabs     = array(
			'colours'    => __( 'Brand colours', 'cater-ice-cream-core' ),
			'typography' => __( 'Typography', 'cater-ice-cream-core' ),
			'scale'      => __( 'Responsive scale', 'cater-ice-cream-core' ),
			'layout'     => __( 'Layout', 'cater-ice-cream-core' ),
			'components' => __( 'Components', 'cater-ice-cream-core' ),
			'presets'    => __( 'Presets', 'cater-ice-cream-core' ),
			'contrast'   => __( 'Contrast', 'cater-ice-cream-core' ),
			'transfer'   => __( 'Export / import', 'cater-ice-cream-core' ),
		);
		$failures = Contrast::failures( $tokens );
		?>
		<div class="wrap cic-admin" id="cic-design-system">
			<h1><?php esc_html_e( 'Design System', 'cater-ice-cream-core' ); ?>
				<a class="page-title-action" href="<?php echo esc_url( Preview::url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open style preview', 'cater-ice-cream-core' ); ?></a>
			</h1>
			<p class="cic-intro"><?php esc_html_e( 'One central configuration for colours, typography, sizes and spacing. Presets and local overrides inherit from these values. Empty fields use the approved default shown beside them; Reset returns a field to that default.', 'cater-ice-cream-core' ); ?></p>
			<?php settings_errors( 'general' ); ?>
			<?php settings_errors( Settings::OPTION ); ?>
			<?php $this->render_transfer_notice(); ?>
			<?php if ( $failures ) : ?>
				<div class="notice notice-warning"><p><strong><?php esc_html_e( 'Contrast warning:', 'cater-ice-cream-core' ); ?></strong>
					<?php echo esc_html( sprintf( _n( '%d text and background pair is below the WCAG target.', '%d text and background pairs are below the WCAG target.', count( $failures ), 'cater-ice-cream-core' ), count( $failures ) ) ); ?>
					<a href="#contrast"><?php esc_html_e( 'Review on the Contrast tab.', 'cater-ice-cream-core' ); ?></a>
					<?php esc_html_e( 'Saved values were not changed automatically.', 'cater-ice-cream-core' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper cic-tabs" aria-label="<?php esc_attr_e( 'Design System sections', 'cater-ice-cream-core' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="#<?php echo esc_attr( $key ); ?>" class="nav-tab" data-cic-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" class="cic-design-form" novalidate>
				<?php settings_fields( self::GROUP ); ?>

				<section class="cic-panel" id="colours" data-cic-panel="colours">
					<?php
					$groups = array(
						'base'   => __( 'Base colours and text', 'cater-ice-cream-core' ),
						'action' => __( 'Actions, hover and focus', 'cater-ice-cream-core' ),
						'accent' => __( 'Occasion and treat accents (used by presets)', 'cater-ice-cream-core' ),
					);
					foreach ( $groups as $group => $group_label ) :
						?>
						<h2><?php echo esc_html( $group_label ); ?></h2>
						<table class="form-table cic-table" role="presentation"><tbody>
						<?php
						foreach ( Schema::colors() as $key => $def ) :
							if ( $def['group'] !== $group ) {
								continue;
							}
							$value = $saved['colors'][ $key ] ?? '';
							$id    = 'cic-color-' . $key;
							Fields::row_open( $def['label'], $id, '' !== $value, __( 'Changed', 'cater-ice-cream-core' ) );
							printf(
								'<span class="cic-color-field"><input type="text" id="%1$s" class="cic-hex" name="%2$s[colors][%3$s]" value="%4$s" placeholder="%5$s" pattern="#[0-9A-Fa-f]{6}" maxlength="7" size="9" data-cic-token="%3$s" data-default="%5$s"> <input type="color" class="cic-hex-picker" value="%6$s" tabindex="-1" aria-hidden="true"></span>',
								esc_attr( $id ),
								esc_attr( $opt ),
								esc_attr( $key ),
								esc_attr( $value ),
								esc_attr( $def['default'] ),
								esc_attr( strtolower( $tokens['colors'][ $key ] ) )
							);
							if ( ! empty( $def['help'] ) ) {
								echo '<p class="description">' . esc_html( $def['help'] ) . '</p>';
							}
							Fields::row_close( Fields::swatch( $def['default'] ) . ' ' . esc_html( sprintf( __( 'Approved default: %s', 'cater-ice-cream-core' ), $def['default'] ) ) );
						endforeach;
						?>
						</tbody></table>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'The logo artwork is an image and never changes colour with these settings.', 'cater-ice-cream-core' ); ?></p>
				</section>

				<?php foreach ( array( 'typography', 'layout', 'components' ) as $group ) : ?>
					<section class="cic-panel" id="<?php echo esc_attr( $group ); ?>" data-cic-panel="<?php echo esc_attr( $group ); ?>">
						<table class="form-table cic-table" role="presentation"><tbody>
						<?php
						foreach ( Schema::scalars() as $key => $def ) {
							if ( $def['group'] === $group ) {
								$this->render_scalar( $key, $def, $saved['scalars'][ $key ] ?? '' );
							}
						}
						?>
						</tbody></table>
						<?php if ( 'typography' === $group ) : ?>
							<p class="description"><?php esc_html_e( 'Only the approved bundled fonts and system fallbacks can be selected. There is no font upload. Weights are limited to those bundled for the chosen family.', 'cater-ice-cream-core' ); ?></p>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>

				<section class="cic-panel" id="scale" data-cic-panel="scale">
					<p><?php echo esc_html( sprintf( __( 'Sizes interpolate smoothly between the mobile value (at %1$d px wide) and the desktop value (at %2$d px wide). Bounds: %3$d–%4$d px; mobile must not exceed desktop.', 'cater-ice-cream-core' ), Schema::VIEWPORT_MIN, Schema::VIEWPORT_MAX, Schema::TYPE_BOUNDS['min'], Schema::TYPE_BOUNDS['max'] ) ); ?></p>
					<div class="cic-scroll"><table class="widefat striped cic-scale-table">
						<thead><tr>
							<th scope="col"><?php esc_html_e( 'Role', 'cater-ice-cream-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Mobile size', 'cater-ice-cream-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Mobile line height', 'cater-ice-cream-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Desktop size', 'cater-ice-cream-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Desktop line height', 'cater-ice-cream-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'State', 'cater-ice-cream-core' ); ?></th>
						</tr></thead><tbody>
						<?php foreach ( Schema::type_roles() as $role => $def ) : ?>
							<?php $changed = ! empty( $saved['type'][ $role ] ); ?>
							<tr class="cic-row<?php echo $changed ? ' is-active' : ''; ?>" data-cic-row>
								<th scope="row"><?php echo esc_html( $def['label'] ); ?></th>
								<?php foreach ( array( 'min', 'lh_min', 'max', 'lh_max' ) as $edge ) : ?>
									<td><input type="number" class="small-text" min="<?php echo (int) Schema::TYPE_BOUNDS['min']; ?>" max="<?php echo (int) Schema::TYPE_BOUNDS['max']; ?>" step="1"
										name="<?php echo esc_attr( $opt . '[type][' . $role . '][' . $edge . ']' ); ?>"
										value="<?php echo esc_attr( (string) ( $saved['type'][ $role ][ $edge ] ?? '' ) ); ?>"
										placeholder="<?php echo (int) $defaults['type'][ $role ][ $edge ]; ?>"
										aria-label="<?php echo esc_attr( $def['label'] . ' ' . $edge ); ?>"> px</td>
								<?php endforeach; ?>
								<td class="cic-row-state"><span class="cic-badge"><?php esc_html_e( 'Changed', 'cater-ice-cream-core' ); ?></span> <button type="button" class="button-link cic-reset" data-cic-reset><?php esc_html_e( 'Reset', 'cater-ice-cream-core' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody></table></div>
				</section>

				<section class="cic-panel" id="presets" data-cic-panel="presets">
					<p><?php esc_html_e( 'Presets reference the global colour tokens instead of copying them. "Preset default" keeps the approved mapping; "Same as brand" follows the Default brand preset; choosing a token links to it; a custom colour is a deliberate local exception.', 'cater-ice-cream-core' ); ?></p>
					<?php foreach ( Schema::contexts() as $ckey => $cdef ) : ?>
						<?php
						$ctx_saved   = $saved['contexts'][ $ckey ] ?? array();
						$ctx_changes = count( $ctx_saved['roles'] ?? array() ) + ( isset( $ctx_saved['heading_weight'] ) ? 1 : 0 ) + ( isset( $ctx_saved['heading_accent_style'] ) ? 1 : 0 );
						?>
						<details class="cic-preset" <?php echo 'brand' === $ckey ? 'open' : ''; ?>>
							<summary><strong><?php echo esc_html( $cdef['label'] ); ?></strong>
								<?php if ( $ctx_changes ) : ?>
									<span class="cic-badge is-visible"><?php echo esc_html( sprintf( _n( '%d change', '%d changes', $ctx_changes, 'cater-ice-cream-core' ), $ctx_changes ) ); ?></span>
								<?php endif; ?>
								<code>.cic-context-<?php echo esc_html( $ckey ); ?></code>
							</summary>
							<table class="form-table cic-table" role="presentation"><tbody>
							<?php
							foreach ( Schema::roles() as $role => $role_label ) :
								$value    = (string) ( $ctx_saved['roles'][ $role ] ?? '' );
								$seed     = $cdef['roles'][ $role ] ?? ( 'brand' === $ckey ? Schema::brand_roles()[ $role ] : null );
								$id       = 'cic-ctx-' . $ckey . '-' . $role;
								$inherit  = $seed ? sprintf( __( 'Preset default: %s', 'cater-ice-cream-core' ), Fields::color_label( $seed ) ) : sprintf( __( 'Same as brand: %s', 'cater-ice-cream-core' ), Fields::color_label( $tokens['contexts']['brand']['roles'][ $role ] ) );
								$extra    = ( 'brand' !== $ckey && $seed ) ? sprintf( '<option value="brand"%1$s>%2$s</option>', selected( $value, 'brand', false ), esc_html( sprintf( __( 'Same as brand: %s', 'cater-ice-cream-core' ), Fields::color_label( $tokens['contexts']['brand']['roles'][ $role ] ) ) ) ) : '';
								$resolved = $tokens['contexts'][ $ckey ]['roles'][ $role ];
								Fields::row_open( $role_label, $id, '' !== $value, __( 'Changed', 'cater-ice-cream-core' ) );
								Fields::color_choice( $opt . '[contexts][' . $ckey . '][roles][' . $role . ']', $opt . '[contexts][' . $ckey . '][custom][' . $role . ']', $id, $value, $inherit, $extra );
								Fields::row_close( Fields::swatch( Settings::hex( $resolved ) ) . ' ' . esc_html( sprintf( __( 'Now uses: %s', 'cater-ice-cream-core' ), Fields::color_label( $resolved ) ) ) );
							endforeach;

							$w_value = (string) ( $ctx_saved['heading_weight'] ?? '' );
							Fields::row_open( __( 'Heading weight', 'cater-ice-cream-core' ), 'cic-ctx-' . $ckey . '-hw', '' !== $w_value, __( 'Changed', 'cater-ice-cream-core' ) );
							$w_default = $cdef['heading_weight'] ?? null;
							printf( '<select id="%1$s" name="%2$s"><option value="">%3$s</option>', esc_attr( 'cic-ctx-' . $ckey . '-hw' ), esc_attr( $opt . '[contexts][' . $ckey . '][heading_weight]' ), esc_html( $w_default ? sprintf( __( 'Preset default: %d', 'cater-ice-cream-core' ), $w_default ) : __( 'Global heading weight', 'cater-ice-cream-core' ) ) );
							foreach ( Schema::fonts()[ $tokens['scalars']['font_heading'] ]['weights'] as $w ) {
								printf( '<option value="%1$d"%2$s>%1$d</option>', (int) $w, selected( $w_value, (string) $w, false ) );
							}
							echo '</select>';
							Fields::row_close( esc_html( sprintf( __( 'Now uses: %d', 'cater-ice-cream-core' ), $tokens['contexts'][ $ckey ]['heading_weight'] ?? $tokens['scalars']['heading_weight'] ) ) );

							$s_value = (string) ( $ctx_saved['heading_accent_style'] ?? '' );
							Fields::row_open( __( 'Heading accent words', 'cater-ice-cream-core' ), 'cic-ctx-' . $ckey . '-as', '' !== $s_value, __( 'Changed', 'cater-ice-cream-core' ) );
							$s_default = $cdef['heading_accent_style'] ?? null;
							printf( '<select id="%1$s" name="%2$s"><option value="">%3$s</option>', esc_attr( 'cic-ctx-' . $ckey . '-as' ), esc_attr( $opt . '[contexts][' . $ckey . '][heading_accent_style]' ), esc_html( $s_default ? sprintf( __( 'Preset default: %s', 'cater-ice-cream-core' ), $s_default ) : __( 'Global accent style', 'cater-ice-cream-core' ) ) );
							foreach ( array( 'normal' => __( 'Upright', 'cater-ice-cream-core' ), 'italic' => __( 'Italic', 'cater-ice-cream-core' ) ) as $sv => $sl ) {
								printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $sv ), selected( $s_value, $sv, false ), esc_html( $sl ) );
							}
							echo '</select>';
							Fields::row_close( esc_html( sprintf( __( 'Now uses: %s', 'cater-ice-cream-core' ), $tokens['contexts'][ $ckey ]['heading_accent_style'] ?? $tokens['scalars']['heading_accent_style'] ) ) );
							?>
							</tbody></table>
							<p class="description"><?php esc_html_e( 'Presets never change the heading or body font family; global font changes reach every preset. A different family needs a visible page or section override.', 'cater-ice-cream-core' ); ?></p>
						</details>
					<?php endforeach; ?>
				</section>

				<section class="cic-panel" id="contrast" data-cic-panel="contrast">
					<p><?php esc_html_e( 'Checked against WCAG 2.2: 4.5:1 for normal text, 3:1 for large headings. Warnings never change your saved colours. The live check below updates as you edit global colours; the full table reflects the last save.', 'cater-ice-cream-core' ); ?></p>
					<div id="cic-live-contrast" class="cic-live-contrast" aria-live="polite"></div>
					<div class="cic-scroll"><table class="widefat striped cic-contrast-table">
						<thead><tr><th scope="col"><?php esc_html_e( 'Preset', 'cater-ice-cream-core' ); ?></th><th scope="col"><?php esc_html_e( 'Pair', 'cater-ice-cream-core' ); ?></th><th scope="col"><?php esc_html_e( 'Sample', 'cater-ice-cream-core' ); ?></th><th scope="col"><?php esc_html_e( 'Ratio', 'cater-ice-cream-core' ); ?></th><th scope="col"><?php esc_html_e( 'Result', 'cater-ice-cream-core' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( Contrast::checks( $tokens ) as $check ) : ?>
							<tr class="<?php echo $check['pass'] ? 'is-pass' : 'is-fail'; ?>">
								<td><?php echo esc_html( $check['context'] ); ?></td>
								<td><?php echo esc_html( $check['label'] ); ?></td>
								<td><span class="cic-sample" style="color:<?php echo esc_attr( $check['fg'] ); ?>;background:<?php echo esc_attr( $check['bg'] ); ?>">Aa <?php echo esc_html( $check['fg'] . ' / ' . $check['bg'] ); ?></span></td>
								<td><?php echo esc_html( number_format( $check['ratio'], 2 ) . ':1' ); ?></td>
								<td><?php echo $check['pass'] ? esc_html__( 'Pass', 'cater-ice-cream-core' ) : '<strong>' . esc_html( sprintf( __( 'Below %s:1', 'cater-ice-cream-core' ), $check['required'] ) ) . '</strong>'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				</section>

				<div class="cic-submit"><?php submit_button( __( 'Save design system', 'cater-ice-cream-core' ), 'primary', 'submit', false ); ?></div>
			</form>

			<section class="cic-panel" id="transfer" data-cic-panel="transfer">
				<h2><?php esc_html_e( 'Export', 'cater-ice-cream-core' ); ?></h2>
				<p><?php esc_html_e( 'Download the saved configuration (only values that differ from the approved defaults) as JSON.', 'cater-ice-cream-core' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cic_design_export' ), 'cic_design_export' ) ); ?>"><?php esc_html_e( 'Download design configuration', 'cater-ice-cream-core' ); ?></a></p>
				<h2><?php esc_html_e( 'Import', 'cater-ice-cream-core' ); ?></h2>
				<p><?php esc_html_e( 'Paste a previously exported configuration. Every value is validated; if anything is invalid, nothing is imported.', 'cater-ice-cream-core' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cic_design_import">
					<?php wp_nonce_field( 'cic_design_import' ); ?>
					<label for="cic-import-json" class="screen-reader-text"><?php esc_html_e( 'Configuration JSON', 'cater-ice-cream-core' ); ?></label>
					<textarea id="cic-import-json" name="cic_import_json" rows="8" class="large-text code" required></textarea>
					<p><label><input type="checkbox" name="cic_import_confirm" value="1" required> <?php esc_html_e( 'Replace the current saved design configuration', 'cater-ice-cream-core' ); ?></label></p>
					<?php submit_button( __( 'Validate and import', 'cater-ice-cream-core' ), 'secondary', 'submit', false ); ?>
				</form>
			</section>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $def Scalar definition.
	 * @param mixed                $value Saved value or ''.
	 */
	private function render_scalar( string $key, array $def, $value ): void {
		$id    = 'cic-scalar-' . $key;
		$name  = Settings::OPTION . '[scalars][' . $key . ']';
		$value = (string) $value;
		Fields::row_open( $def['label'], $id, '' !== $value, __( 'Changed', 'cater-ice-cream-core' ) );
		switch ( $def['type'] ) {
			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="%6$s" placeholder="%7$s" class="small-text"> %8$s',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( (string) $def['min'] ),
					esc_attr( (string) $def['max'] ),
					esc_attr( (string) $def['step'] ),
					esc_attr( (string) $def['default'] ),
					esc_html( $def['unit'] )
				);
				$default = $def['default'] . ' ' . $def['unit'] . ' (' . $def['min'] . '–' . $def['max'] . ')';
				break;
			case 'font':
			case 'weight':
			case 'select':
			case 'color_ref':
				$choices = array();
				if ( 'font' === $def['type'] ) {
					$choices = array_map( static fn( $f ) => $f['label'], Schema::fonts() );
				} elseif ( 'weight' === $def['type'] ) {
					foreach ( Schema::fonts()[ Settings::resolved()['scalars'][ $def['font'] ] ]['weights'] as $w ) {
						$choices[ (string) $w ] = (string) $w;
					}
				} elseif ( 'select' === $def['type'] ) {
					$choices = $def['choices'];
				} else {
					foreach ( Schema::colors() as $ckey => $cdef ) {
						$choices[ $ckey ] = $cdef['label'] . ' — ' . Settings::hex( $ckey );
					}
				}
				$default_label = $choices[ (string) $def['default'] ] ?? (string) $def['default'];
				printf( '<select id="%1$s" name="%2$s"><option value="">%3$s</option>', esc_attr( $id ), esc_attr( $name ), esc_html( sprintf( __( 'Default: %s', 'cater-ice-cream-core' ), $default_label ) ) );
				foreach ( $choices as $ckey => $clabel ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $ckey ), selected( $value, (string) $ckey, false ), esc_html( $clabel ) );
				}
				echo '</select>';
				$default = $default_label;
				break;
			default:
				$default = '';
		}
		Fields::row_close( esc_html( sprintf( __( 'Approved default: %s', 'cater-ice-cream-core' ), $default ) ) );
	}

	private function render_transfer_notice(): void {
		$notice = get_transient( 'cic_design_transfer_' . get_current_user_id() );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( 'cic_design_transfer_' . get_current_user_id() );
		printf( '<div class="notice notice-%1$s"><p>%2$s</p>', esc_attr( $notice['type'] ), esc_html( $notice['message'] ) );
		if ( ! empty( $notice['errors'] ) ) {
			echo '<ul class="ul-disc">';
			foreach ( $notice['errors'] as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	public function handle_export(): void {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_admin_referer( 'cic_design_export' ) ) {
			wp_die( esc_html__( 'You do not have permission to export the design system.', 'cater-ice-cream-core' ), 403 );
		}
		$payload = self::export_payload();
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cic-design-' . gmdate( 'Ymd-His' ) . '.json"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function export_payload(): array {
		return array(
			'format'      => 'cic-design',
			'schema'      => 1,
			'exported_at' => gmdate( 'c' ),
			'settings'    => Settings::saved(),
		);
	}

	/**
	 * Validates a JSON payload. Returns [clean settings|null, errors].
	 *
	 * @return array{0: array<string, mixed>|null, 1: string[]}
	 */
	public static function validate_import( string $json ): array {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || 'cic-design' !== ( $data['format'] ?? '' ) || ! is_array( $data['settings'] ?? null ) ) {
			return array( null, array( __( 'This is not a Cater Ice Cream design export (expected "format": "cic-design").', 'cater-ice-cream-core' ) ) );
		}
		if ( 1 !== (int) ( $data['schema'] ?? 0 ) ) {
			return array( null, array( __( 'Unsupported export schema version.', 'cater-ice-cream-core' ) ) );
		}
		$errors = array();
		$clean  = Settings::sanitize( $data['settings'], array(), $errors );
		return array( $errors ? null : $clean, $errors );
	}

	public function handle_import(): void {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_admin_referer( 'cic_design_import' ) ) {
			wp_die( esc_html__( 'You do not have permission to import a design configuration.', 'cater-ice-cream-core' ), 403 );
		}
		$json = isset( $_POST['cic_import_json'] ) ? (string) wp_unslash( $_POST['cic_import_json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and allowlisted.
		if ( empty( $_POST['cic_import_confirm'] ) ) {
			$notice = array( 'type' => 'error', 'message' => __( 'Import cancelled: please confirm the replacement.', 'cater-ice-cream-core' ) );
		} else {
			list( $clean, $errors ) = self::validate_import( $json );
			if ( null === $clean ) {
				$notice = array( 'type' => 'error', 'message' => __( 'Nothing was imported because the configuration did not validate:', 'cater-ice-cream-core' ), 'errors' => $errors );
			} else {
				update_option( Settings::OPTION, $clean );
				$notice = array( 'type' => 'success', 'message' => __( 'Design configuration imported and validated.', 'cater-ice-cream-core' ) );
			}
		}
		set_transient( 'cic_design_transfer_' . get_current_user_id(), $notice, 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '#transfer' ) );
		exit;
	}
}
