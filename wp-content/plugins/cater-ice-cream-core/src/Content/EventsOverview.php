<?php
/**
 * Editable content for the /events/ archive (03_events_overview.png). The archive is the Events
 * CPT archive (no duplicate Page, BUILD_SPECIFICATION §3), so its introduction, section headings,
 * steps and starter labels live in one option edited at Events → Overview page (Settings API; no
 * ACF Options Pages). Event cards and treats come from published records.
 *
 * @package CIC
 */

namespace CIC\Content;

defined( 'ABSPATH' ) || exit;

final class EventsOverview {

	public const OPTION = 'cic_events_overview';
	public const SLUG   = 'cic-events-overview';

	/** Field key => [type text|area|image|icon, label, default]. */
	public static function fields(): array {
		return array(
			'eyebrow'         => array( 'text', 'Hero eyebrow', 'Find your occasion' ),
			'headline'        => array( 'area', 'Hero headline (one line per row; <em> for accent words)', "A sweet addition\n<em>to every occasion.</em>" ),
			'intro'           => array( 'area', 'Hero introduction', 'Staffed ice cream catering for birthdays, weddings, corporate events, anniversaries and the moments in between.' ),
			'image'           => array( 'image', 'Hero photo', 'asset:events-treat-trio' ),
			'cards_heading'   => array( 'text', 'Occasions heading', 'What are you celebrating?' ),
			'cards_intro'     => array( 'text', 'Occasions introduction', "Choose your occasion. We'll help with the sweet part." ),
			'other_text'      => array( 'text', '"Something else" text', 'Celebrating something else?' ),
			'other_link'      => array( 'text', '"Something else" link label', 'Tell us about your event' ),
			'treats_heading'  => array( 'text', 'Treats heading (<em> for accent words)', 'Your occasion. <em>Your favourites.</em>' ),
			'treats_intro'    => array( 'text', 'Treats introduction', "Explore the treats you'd love to share." ),
			'steps_heading'   => array( 'text', 'Steps heading (<em> for accent words)', 'From a little idea to a sweet <em>celebration.</em>' ),
			'step_1_title'    => array( 'text', 'Step 1: title', 'Share your plans' ),
			'step_1_text'     => array( 'text', 'Step 1: text', 'Tell us your date, location and occasion.' ),
			'step_2_title'    => array( 'text', 'Step 2: title', 'Receive your quote' ),
			'step_2_text'     => array( 'text', 'Step 2: text', "We'll confirm the details and availability." ),
			'step_3_title'    => array( 'text', 'Step 3: title', 'Celebrate with us' ),
			'step_3_text'     => array( 'text', 'Step 3: text', 'Enjoy your event with our serving team.' ),
			'start_heading'   => array( 'text', 'Quote starter heading (<em> for accent words)', "Let's plan something <em>sweet.</em>" ),
			'start_cta'       => array( 'text', 'Quote starter button', 'Start My Quote' ),
			'start_note'      => array( 'text', 'Quote starter note', 'Your selections carry into our quote form. Availability is confirmed with your quote.' ),
		);
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
	}

	private static function capability(): string {
		$type = get_post_type_object( EventType::POST_TYPE );
		return $type ? (string) $type->cap->edit_others_posts : 'manage_options';
	}

	/**
	 * Saved values over defaults (image defaults resolve to imported media).
	 *
	 * @return array<string, string|int>
	 */
	public static function get(): array {
		$saved = (array) get_option( self::OPTION, array() );
		$out   = array();
		foreach ( self::fields() as $key => $def ) {
			$value = array_key_exists( $key, $saved ) ? $saved[ $key ] : $def[2];
			if ( 'image' === $def[0] ) {
				$value = is_string( $value ) && str_starts_with( $value, 'asset:' ) ? Media::find( substr( $value, 6 ) ) : (int) $value;
				$value = $value && wp_attachment_is_image( $value ) ? $value : 0;
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	public function menu(): void {
		add_submenu_page( 'edit.php?post_type=' . EventType::POST_TYPE, __( 'Events overview page', 'cater-ice-cream-core' ), __( 'Overview page', 'cater-ice-cream-core' ), self::capability(), self::SLUG, array( $this, 'render' ) );
	}

	public function settings(): void {
		register_setting(
			self::SLUG,
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
				'default'           => array(),
			)
		);
		add_filter( 'option_page_capability_' . self::SLUG, static fn() => self::capability() );
	}

	/**
	 * @param mixed $input Posted values.
	 * @return array<string, string|int>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = array();
		foreach ( self::fields() as $key => $def ) {
			$raw = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
			switch ( $def[0] ) {
				case 'image':
					$id            = absint( $raw );
					$clean[ $key ] = $id && wp_attachment_is_image( $id ) ? $id : 0;
					break;
				case 'area':
					$clean[ $key ] = sanitize_textarea_field( (string) $raw );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( (string) $raw );
			}
			if ( 'area' !== $def[0] && 'image' !== $def[0] && str_contains( (string) $raw, '<em>' ) ) {
				$clean[ $key ] = wp_kses( (string) $raw, array( 'em' => array() ) );
			}
			if ( 'area' === $def[0] && str_contains( (string) $raw, '<em>' ) ) {
				$clean[ $key ] = wp_kses( (string) $raw, array( 'em' => array() ) );
			}
		}
		return $clean;
	}

	public function render(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this page.', 'cater-ice-cream-core' ) );
		}
		wp_enqueue_media();
		$values = self::get();
		echo '<div class="wrap"><h1>' . esc_html__( 'Events overview page', 'cater-ice-cream-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Text for the /events/ page. The occasion cards and treats come from published events and treats automatically (card text = each event\'s excerpt; card photo = its "overview card photo" or featured image).', 'cater-ice-cream-core' ) . ' <a href="' . esc_url( (string) get_post_type_archive_link( EventType::POST_TYPE ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the page', 'cater-ice-cream-core' ) . '</a></p>';
		settings_errors();
		echo '<form method="post" action="options.php">';
		settings_fields( self::SLUG );
		echo '<table class="form-table" role="presentation">';
		foreach ( self::fields() as $key => $def ) {
			$id   = 'cic-eo-' . $key;
			$name = self::OPTION . '[' . $key . ']';
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $def[1] ) . '</label></th><td>';
			if ( 'area' === $def[0] ) {
				echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $values[ $key ] ) . '</textarea>';
			} elseif ( 'image' === $def[0] ) {
				$img = (int) $values[ $key ];
				echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $img ) . '">';
				echo '<span class="cic-eo-preview" id="' . esc_attr( $id ) . '-preview">' . ( $img ? wp_get_attachment_image( $img, 'thumbnail' ) : '' ) . '</span> ';
				echo '<button type="button" class="button" data-cic-media="' . esc_attr( $id ) . '">' . esc_html__( 'Choose photo', 'cater-ice-cream-core' ) . '</button>';
			} else {
				echo '<input class="regular-text" type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $values[ $key ] ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		submit_button();
		echo '</form></div>';
		?>
		<script>
		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-cic-media]' );
			if ( ! b || ! window.wp || ! wp.media ) { return; }
			var id = b.getAttribute( 'data-cic-media' );
			var frame = wp.media( { title: b.textContent, library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				document.getElementById( id ).value = a.id;
				document.getElementById( id + '-preview' ).innerHTML = '<img src="' + ( a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url ) + '" width="80" alt="">';
			} );
			frame.open();
		} );
		</script>
		<?php
	}
}
