<?php
/**
 * Cater Ice Cream → Header, Footer & Contact screen. Native Settings API; menus themselves are
 * edited in Appearance → Menus. No ACF Options Pages.
 *
 * @package CIC
 */

namespace CIC\Site\Admin;

use CIC\Config;
use CIC\Design\Admin\Fields;
use CIC\Design\Preview;
use CIC\Design\Schema;
use CIC\Design\Settings;
use CIC\Site\Contact;
use CIC\Site\Layout;

defined( 'ABSPATH' ) || exit;

final class LayoutPage {

	public const SLUG       = 'cic-header-footer';
	public const GROUP      = 'cic_site';
	public const CAPABILITY = 'manage_options';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'option_page_capability_' . self::GROUP, static fn() => self::CAPABILITY );
	}

	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Layout::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize_layout' ),
				'show_in_rest'      => false,
				'default'           => array(),
			)
		);
		register_setting(
			self::GROUP,
			Config::OPTION_SETTINGS,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize_contact' ),
				'show_in_rest'      => false,
				'default'           => array(),
			)
		);
	}

	/**
	 * @param mixed $input Posted layout.
	 * @return array<string, mixed>
	 */
	public function sanitize_layout( $input ): array {
		$errors = array();
		$clean  = Layout::sanitize( $input, $errors );
		$this->report( $errors, Layout::OPTION );
		return $clean;
	}

	/**
	 * Only the public contact keys are editable here; other keys of the option are preserved.
	 *
	 * @param mixed $input Posted contact fields.
	 * @return array<string, mixed>
	 */
	public function sanitize_contact( $input ): array {
		$errors = array();
		$clean  = Contact::sanitize( $input, $errors );
		$this->report( $errors, Config::OPTION_SETTINGS );
		return $clean;
	}

	/**
	 * @param string[] $errors Messages.
	 */
	private function report( array $errors, string $setting ): void {
		static $seen = array();
		foreach ( array_unique( $errors ) as $i => $message ) {
			if ( empty( $seen[ $message ] ) && function_exists( 'add_settings_error' ) ) {
				add_settings_error( $setting, $setting . '_' . $i, $message, 'error' );
				$seen[ $message ] = true;
			}
		}
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to edit these settings.', 'cater-ice-cream-core' ), 403 );
		}
		Fields::enqueue();
		wp_enqueue_media();
		$layout  = Layout::get();
		$contact = Config::all();
		$tabs    = array(
			'contact' => __( 'Contact & calls to action', 'cater-ice-cream-core' ),
			'top'     => __( 'Header: top row', 'cater-ice-cream-core' ),
			'main'    => __( 'Header: main row', 'cater-ice-cream-core' ),
			'bottom'  => __( 'Header: bottom row', 'cater-ice-cream-core' ),
			'mobile'  => __( 'Mobile navigation', 'cater-ice-cream-core' ),
			'footer'  => __( 'Footer', 'cater-ice-cream-core' ),
		);
		?>
		<div class="wrap cic-admin" id="cic-header-footer">
			<h1><?php esc_html_e( 'Header, Footer & Contact', 'cater-ice-cream-core' ); ?>
				<a class="page-title-action" href="<?php echo esc_url( Preview::url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open style preview', 'cater-ice-cream-core' ); ?></a>
			</h1>
			<p class="cic-intro">
				<?php esc_html_e( 'These settings apply to every page, product, event, archive and city page. Menu items are edited in', 'cater-ice-cream-core' ); ?>
				<a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Appearance → Menus', 'cater-ice-cream-core' ); ?></a>.
				<?php esc_html_e( 'Colours come from the Design System tokens.', 'cater-ice-cream-core' ); ?>
			</p>
			<?php settings_errors( 'general' ); ?>
			<?php settings_errors( Layout::OPTION ); ?>
			<?php settings_errors( Config::OPTION_SETTINGS ); ?>
			<nav class="nav-tab-wrapper cic-tabs" aria-label="<?php esc_attr_e( 'Sections', 'cater-ice-cream-core' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="#<?php echo esc_attr( $key ); ?>" class="nav-tab" data-cic-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<form method="post" action="options.php" novalidate>
				<?php settings_fields( self::GROUP ); ?>

				<section class="cic-panel" id="contact" data-cic-panel="contact">
					<p><?php esc_html_e( 'Public details shown in the header, footer, call panels and every tel: link. The private enquiry notification recipient is configured separately and is never shown publicly.', 'cater-ice-cream-core' ); ?></p>
					<table class="form-table" role="presentation"><tbody>
					<?php
					$contact_fields = array(
						'business_name'     => __( 'Public business name', 'cater-ice-cream-core' ),
						'legal_entity'      => __( 'Legal entity', 'cater-ice-cream-core' ),
						'business_phone'    => __( 'Public phone', 'cater-ice-cream-core' ),
						'business_email'    => __( 'Public email', 'cater-ice-cream-core' ),
						'business_location' => __( 'Public business location', 'cater-ice-cream-core' ),
						'quote_label'       => __( 'Quote button label', 'cater-ice-cream-core' ),
						'quote_url'         => __( 'Quote button link', 'cater-ice-cream-core' ),
						'call_label'        => __( 'Call action label', 'cater-ice-cream-core' ),
						'availability_note' => __( 'Availability note (shown under quote starters)', 'cater-ice-cream-core' ),
						'website_label'     => __( 'Website label (footer)', 'cater-ice-cream-core' ),
						'social_instagram'  => __( 'Instagram URL', 'cater-ice-cream-core' ),
						'social_facebook'   => __( 'Facebook URL', 'cater-ice-cream-core' ),
						'social_tiktok'     => __( 'TikTok URL', 'cater-ice-cream-core' ),
						'social_yelp'       => __( 'Yelp URL', 'cater-ice-cream-core' ),
					);
					foreach ( $contact_fields as $key => $label ) :
						$type = 'business_email' === $key ? 'email' : ( str_starts_with( $key, 'social_' ) ? 'url' : 'text' );
						?>
						<tr>
							<th scope="row"><label for="cic-contact-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="<?php echo esc_attr( $type ); ?>" class="regular-text" id="cic-contact-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( Config::OPTION_SETTINGS . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) $contact[ $key ] ); ?>"<?php echo str_starts_with( $key, 'social_' ) ? ' placeholder="https://"' : ''; ?>>
							<?php if ( 'quote_url' === $key ) : ?><p class="description"><?php esc_html_e( 'A site path such as /get-a-quote/ or an https:// address.', 'cater-ice-cream-core' ); ?></p><?php endif; ?>
							<?php if ( str_starts_with( $key, 'social_' ) ) : ?><p class="description"><?php esc_html_e( 'Leave empty unless a real profile exists. Empty profiles are never displayed.', 'cater-ice-cream-core' ); ?></p><?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody></table>
				</section>

				<?php foreach ( Layout::schema() as $section => $fields ) : ?>
					<section class="cic-panel" id="<?php echo esc_attr( $section ); ?>" data-cic-panel="<?php echo esc_attr( $section ); ?>">
						<?php $this->section_intro( $section ); ?>
						<table class="form-table" role="presentation"><tbody>
						<?php foreach ( $fields as $key => $def ) : ?>
							<?php $this->render_field( $section, $key, $def, $layout[ $section ][ $key ] ); ?>
						<?php endforeach; ?>
						</tbody></table>
					</section>
				<?php endforeach; ?>

				<?php submit_button( __( 'Save header, footer and contact', 'cater-ice-cream-core' ) ); ?>
			</form>
		</div>
		<?php
	}

	private function section_intro( string $section ): void {
		$menus = get_nav_menu_locations();
		$text  = array(
			'top'    => __( 'A thin row above the main header for an announcement and contact or social links.', 'cater-ice-cream-core' ),
			'main'   => __( 'Logo, the "Primary navigation" menu and the call/quote actions.', 'cater-ice-cream-core' ),
			'bottom' => __( 'An optional secondary row using the "Header bottom row" menu.', 'cater-ice-cream-core' ),
			'mobile' => __( 'Below 1024 px the primary menu moves into an accessible drawer (toggle button, submenus, close button, Escape key and focus return).', 'cater-ice-cream-core' ),
			'footer' => __( 'Uses the "Footer navigation", "Footer second menu" and "Footer legal links" menus.', 'cater-ice-cream-core' ),
		)[ $section ] ?? '';
		echo '<p>' . esc_html( $text ) . '</p>';
		$needs = array(
			'main'   => array( 'primary' ),
			'bottom' => array( 'secondary' ),
			'footer' => array( 'footer', 'legal' ),
		)[ $section ] ?? array();
		foreach ( $needs as $location ) {
			if ( empty( $menus[ $location ] ) ) {
				echo '<p class="cic-inline-note">' . esc_html( sprintf( __( 'No menu is assigned to the "%s" location yet.', 'cater-ice-cream-core' ), get_registered_nav_menus()[ $location ] ?? $location ) ) . '</p>';
			}
		}
	}

	/**
	 * @param array<string, mixed> $def   Field definition.
	 * @param mixed                $value Current value.
	 */
	private function render_field( string $section, string $key, array $def, $value ): void {
		$id   = 'cic-layout-' . $section . '-' . $key;
		$name = Layout::OPTION . '[' . $section . '][' . $key . ']';
		echo '<tr><th scope="row">';
		if ( 'bool' !== $def['type'] ) {
			printf( '<label for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $def['label'] ) );
		} else {
			echo esc_html( $def['label'] );
		}
		echo '</th><td>';
		switch ( $def['type'] ) {
			case 'bool':
				printf( '<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ), esc_html__( 'Enabled', 'cater-ice-cream-core' ) );
				break;
			case 'text':
				printf( '<input type="text" class="large-text" id="%1$s" name="%2$s" value="%3$s" maxlength="%4$d">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), (int) $def['max'] );
				break;
			case 'int':
				printf( '<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$d" min="%4$d" max="%5$d" step="1"> px', esc_attr( $id ), esc_attr( $name ), (int) $value, (int) $def['min'], (int) $def['max'] );
				break;
			case 'color_ref':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				echo Fields::color_options( (string) $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in color_options().
				echo '</select> ' . Fields::swatch( Settings::hex( (string) $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in swatch().
				break;
			case 'choice':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $def['choices'] as $ckey => $clabel ) {
					printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $ckey ), selected( (string) $value, $ckey, false ), esc_html( $clabel ) );
				}
				echo '</select>';
				break;
			case 'image':
				$src = $value ? wp_get_attachment_image_url( (int) $value, 'medium' ) : '';
				printf(
					'<div class="cic-image-field" data-cic-image><input type="hidden" id="%1$s" name="%2$s" value="%3$s"><img class="cic-image-preview" src="%4$s" alt=""%5$s> <button type="button" class="button" data-cic-image-select>%6$s</button> <button type="button" class="button-link" data-cic-image-clear%7$s>%8$s</button></div>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) (int) $value ),
					esc_url( (string) $src ),
					$src ? '' : ' hidden',
					esc_html__( 'Choose image', 'cater-ice-cream-core' ),
					$value ? '' : ' hidden',
					esc_html__( 'Use bundled logo', 'cater-ice-cream-core' )
				);
				break;
		}
		if ( ! empty( $def['help'] ) ) {
			echo '<p class="description">' . esc_html( $def['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}
}
