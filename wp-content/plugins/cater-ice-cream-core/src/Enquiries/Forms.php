<?php
/**
 * Contact Form 7 integration for the quote and Contact forms (BUILD_SPECIFICATION §12–13A).
 *
 * - Two CF7 forms are created once (IDs stored per environment in Enquiry settings, never
 *   hardcoded). Their markup is code-owned through the supported wpcf7_contact_form_properties
 *   filter, so the three-step structure cannot be broken from the CF7 editor.
 * - Custom form tags ([cic_field name], [cic_steps], [cic_guard], [cic_submit])
 *   render the approved controls with CF7's wrapper/error conventions, so CF7's JavaScript, its
 *   no-JavaScript re-render (hangover values + inline errors) and Flamingo storage all work.
 *   Event and treat options come from published records at render time; the server validates
 *   them again (Validator).
 * - Prefill priority: values from a failed no-JS submission, then the validated quote context
 *   (URL / starter forms), then empty.
 * - CF7 scripts load only on the two form pages; CF7's stylesheet is replaced by the theme.
 *
 * @package CIC
 */

namespace CIC\Enquiries;

use CIC\Content\Catalog;
use CIC\Quote\Context;
use CIC\Site\Contact;

defined( 'ABSPATH' ) || exit;

final class Forms {

	public const TYPES = array( 'quote', 'contact' );

	/** Bump to rewrite the stored copy of the code-owned form markup. */
	public const MARKUP_VERSION = '1';

	public function register(): void {
		add_action( 'wpcf7_init', array( $this, 'add_tags' ) );
		add_filter( 'wpcf7_contact_form_properties', array( $this, 'properties' ), 10, 2 );
		add_filter( 'wpcf7_autop_or_not', array( $this, 'autop' ) );
		add_filter( 'wpcf7_load_js', array( $this, 'load_js' ) );
		add_filter( 'wpcf7_load_css', '__return_false' );
		add_filter( 'wpcf7_form_class_attr', array( $this, 'form_class' ) );
		add_action( 'admin_init', array( self::class, 'install' ) );
	}

	public static function available(): bool {
		return defined( 'WPCF7_VERSION' ) && class_exists( 'Flamingo_Inbound_Message' ) && Store::ready();
	}

	/** Configured CF7 form id for a type (0 when missing). */
	public static function id( string $type ): int {
		$id = (int) Settings::value( $type . '_form_id' );
		return ( $id && 'wpcf7_contact_form' === get_post_type( $id ) ) ? $id : 0;
	}

	/** Our form type for a CF7 form id ('' when not ours). */
	public static function type_of( int $form_id ): string {
		foreach ( self::TYPES as $type ) {
			if ( $form_id && self::id( $type ) === $form_id ) {
				return $type;
			}
		}
		return '';
	}

	/** Current submission's form type ('' when none / not ours). */
	public static function current_type(): string {
		$submission = class_exists( 'WPCF7_Submission' ) ? \WPCF7_Submission::get_instance() : null;
		return $submission ? self::type_of( (int) $submission->get_contact_form()->id() ) : '';
	}

	/**
	 * Create the two CF7 forms once and store their IDs. Safe to re-run (existing forms are kept).
	 */
	public static function install(): void {
		if ( ! defined( 'WPCF7_VERSION' ) || ! class_exists( 'WPCF7_ContactForm' ) ) {
			return;
		}
		$titles  = array( 'quote' => 'Quote request (Cater Ice Cream)', 'contact' => 'Contact message (Cater Ice Cream)' );
		$changes = array();
		foreach ( self::TYPES as $type ) {
			if ( self::id( $type ) ) {
				continue;
			}
			$form = \WPCF7_ContactForm::get_template( array( 'title' => $titles[ $type ] ) );
			$form->set_properties( self::stored_properties( $type ) );
			$id = (int) $form->save();
			if ( $id ) {
				$changes[ $type . '_form_id' ] = $id;
			}
		}
		if ( $changes ) {
			Settings::update( $changes );
		}
	}

	/**
	 * Properties saved with the CF7 form (the form markup itself is supplied from code at runtime).
	 *
	 * @return array<string, mixed>
	 */
	private static function stored_properties( string $type ): array {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return array(
			'form'                => self::markup( $type ),
			'mail'                => array(
				'active'             => true,
				'subject'            => 'quote' === $type ? 'Quote request' : 'Contact message',
				'sender'             => '[_site_title] <wordpress@' . $host . '>',
				'recipient'          => '[_site_admin_email]',
				'body'               => 'Notifications are sent by the Cater Ice Cream core plugin after the enquiry is saved (CF7 mail is skipped).',
				'additional_headers' => '',
				'attachments'        => '',
				'use_html'           => false,
				'exclude_blank'      => false,
			),
			'mail_2'              => array( 'active' => false ),
			'messages'            => self::messages( $type ),
			'additional_settings' => "skip_mail: on\nflamingo_email: \"[your_email]\"\nflamingo_name: \"[your_name]\"\nflamingo_subject: \"" . ( 'quote' === $type ? 'Quote request' : 'Contact message' ) . '"',
		);
	}

	/** @return array<string, string> */
	private static function messages( string $type ): array {
		return array(
			'mail_sent_ok'     => 'quote' === $type ? __( 'Thanks! We received your quote request.', 'cater-ice-cream-core' ) : __( 'Thanks! We received your message.', 'cater-ice-cream-core' ),
			'mail_sent_ng'     => __( 'We saved your request, but we couldn’t send the confirmation email. We’ll still see your request and get back to you.', 'cater-ice-cream-core' ),
			'validation_error' => __( 'Please check the highlighted fields and try again.', 'cater-ice-cream-core' ),
			'spam'             => __( 'Sorry, we couldn’t send this right now. Please try again later or give us a call.', 'cater-ice-cream-core' ),
			'accept_terms'     => __( 'Please confirm before sending.', 'cater-ice-cream-core' ),
			'invalid_required' => __( 'Please fill in this field.', 'cater-ice-cream-core' ),
			'invalid_too_long' => __( 'This is too long.', 'cater-ice-cream-core' ),
			'invalid_too_short' => __( 'This is too short.', 'cater-ice-cream-core' ),
		);
	}

	/**
	 * Supply the code-owned markup and messages for our two forms.
	 *
	 * @param array<string, mixed> $properties Properties.
	 * @param \WPCF7_ContactForm   $form       Form.
	 * @return array<string, mixed>
	 */
	public function properties( $properties, $form ) {
		$type = is_object( $form ) && method_exists( $form, 'id' ) ? self::type_of( (int) $form->id() ) : '';
		if ( '' !== $type && is_array( $properties ) ) {
			$properties['form']     = self::markup( $type );
			$properties['messages'] = array_merge( (array) ( $properties['messages'] ?? array() ), self::messages( $type ) );
		}
		return $properties;
	}

	public function autop( $autop ) {
		return '' !== self::rendering_type() ? false : $autop;
	}

	public function load_js( $load ) {
		return is_admin() ? $load : ( is_page( array( 'get-a-quote', 'contact' ) ) || '' !== self::current_type() );
	}

	public function form_class( string $class ): string {
		return $class . ' cic-form';
	}

	/** The form currently being rendered or submitted ('' when not ours). */
	private static function rendering_type(): string {
		$form = class_exists( 'WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_current() : null;
		return $form ? self::type_of( (int) $form->id() ) : '';
	}

	/**
	 * Render a form (or '' when the dependencies or the form are missing: the theme then shows the
	 * call/email fallback and never accepts unsaved enquiries).
	 */
	public static function render( string $type ): string {
		if ( ! self::available() || ! self::id( $type ) ) {
			return '';
		}
		return do_shortcode( sprintf( '[contact-form-7 id="%d" html_class="cic-form--%s" html_title="%s"]', self::id( $type ), esc_attr( $type ), esc_attr( 'quote' === $type ? __( 'Quote request', 'cater-ice-cream-core' ) : __( 'Contact message', 'cater-ice-cream-core' ) ) ) );
	}

	/* ------------------------------------------------------------------ *
	 * Markup
	 * ------------------------------------------------------------------ */

	public static function markup( string $type ): string {
		if ( 'contact' === $type ) {
			return '<div class="cic-cf novalidate" data-cic-form="contact">'
				. '<div class="cic-qf__errors" data-cic-errors hidden tabindex="-1"></div>'
				. '[cic_field topic][cic_field your_name][cic_field your_email][cic_field phone][cic_field message]'
				. '<p class="cic-qf__privacy">' . self::privacy() . '</p>'
				. '[cic_guard cic_guard][cic_submit cic_submit]'
				. '</div>';
		}
		// Two steps (2026-10-23): your details & favorites, then your event. Optional details (notes,
		// theme, dietary) are asked after submission on the confirmation (Drafts). Without JavaScript
		// both steps show on one page and the form submits as before.
		$step = static fn( int $n, string $title, string $intro ): string => '<fieldset class="cic-qf__step" data-step="' . $n . '" id="cic-step-' . $n . '"><legend class="cic-qf__legend"><span class="cic-qf__kicker">' . esc_html( sprintf( /* translators: %d: step. */ __( 'Step %d of 2', 'cater-ice-cream-core' ), $n ) ) . '</span> <span class="cic-qf__title">' . esc_html( $title ) . '</span></legend>' . ( '' !== $intro ? '<p class="cic-qf__intro">' . esc_html( $intro ) . '</p>' : '' );
		return '<div class="cic-qf novalidate" data-cic-form="quote">'
			. '[cic_steps cic_steps]'
			. '<div class="cic-qf__errors" data-cic-errors hidden tabindex="-1"></div>'
			. $step( 1, __( 'Your details & favorites', 'cater-ice-cream-core' ), '' )
			. '<div class="cic-qf__grid">[cic_field your_name][cic_field your_email]</div>'
			. '<div class="cic-qf__grid">[cic_field phone][cic_field contact_pref]</div>'
			. '<h3 class="cic-qf__subhead">' . esc_html__( 'Pick your favorites, or let us help you choose.', 'cater-ice-cream-core' ) . '</h3>'
			. '[cic_field treats][cic_field treats_unsure]'
			. '<div class="cic-qf__nav cic-qf__nav--continue cic-js-only"><p class="cic-qf__consent">' . esc_html__( 'By continuing, you agree that we may contact you about your event.', 'cater-ice-cream-core' ) . self::privacy_link() . '</p><button type="button" class="cic-button" data-cic-next>' . esc_html__( 'Continue', 'cater-ice-cream-core' ) . ' <span aria-hidden="true">→</span></button><p class="cic-qf__failure" data-cic-failure role="alert" hidden></p></div>'
			. '</fieldset>'
			. $step( 2, __( 'Your event', 'cater-ice-cream-core' ), '' )
			. '[cic_field event]<div class="cic-qf__grid">[cic_field event_other]</div>'
			. '<div class="cic-qf__grid">[cic_field event_date][cic_field guests]</div>'
			. '<div class="cic-qf__grid">[cic_field location][cic_field location_detail]</div>'
			. '<div class="cic-qf__secondary">[cic_field venue][cic_field serve_time][cic_field serve_duration]</div>'
			. '<p class="cic-qf__note">' . esc_html__( 'We’ll confirm service for your exact event location and date.', 'cater-ice-cream-core' ) . '</p>'
			. '<p class="cic-qf__privacy">' . self::privacy() . '</p>'
			. '<div class="cic-qf__nav cic-qf__nav--send"><button type="button" class="cic-qf__back cic-js-only" data-cic-back>← ' . esc_html__( 'Back to your details', 'cater-ice-cream-core' ) . '</button>[cic_guard cic_guard][cic_submit cic_submit]</div>'
			. '<p class="cic-qf__after">' . esc_html__( 'This is a quote request, not a booking. We’ll confirm availability, pricing and serving details with you.', 'cater-ice-cream-core' ) . '</p>'
			. '</fieldset></div>';
	}

	/** " Privacy Policy" link (only once the policy page is published). */
	private static function privacy_link(): string {
		$url = get_privacy_policy_url();
		return '' !== $url ? ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Privacy Policy', 'cater-ice-cream-core' ) . '</a>' : '';
	}

	private static function privacy(): string {
		$url  = get_privacy_policy_url();
		$text = esc_html__( 'We’ll use these details to reply to your inquiry.', 'cater-ice-cream-core' );
		return '' !== $url ? $text . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Privacy Policy', 'cater-ice-cream-core' ) . '</a>' : $text;
	}

	/**
	 * Field definitions (provisional pending the owner's form review).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function specs(): array {
		$opt = __( '(optional)', 'cater-ice-cream-core' );
		return array(
			'event'           => array( 'type' => 'event', 'label' => __( 'What are you celebrating?', 'cater-ice-cream-core' ), 'required' => true ),
			'event_other'     => array( 'type' => 'text', 'label' => __( 'Tell us about the occasion', 'cater-ice-cream-core' ), 'optional' => $opt, 'placeholder' => __( 'e.g. baby shower, retirement, school event', 'cater-ice-cream-core' ), 'max' => 80, 'wrap' => 'data-cic-other' ),
			'event_date'      => array( 'type' => 'date', 'label' => __( 'Event date', 'cater-ice-cream-core' ), 'icon' => 'calendar', 'required' => true, 'after' => 'date_unknown' ),
			'date_unknown'    => array( 'type' => 'check', 'label' => __( 'Date not set yet', 'cater-ice-cream-core' ) ),
			'guests'          => array( 'type' => 'number', 'label' => __( 'Approximate guest count', 'cater-ice-cream-core' ), 'placeholder' => __( 'Estimated number', 'cater-ice-cream-core' ), 'icon' => 'people', 'required' => true ),
			'location'        => array( 'type' => 'location', 'label' => __( 'City or ZIP code', 'cater-ice-cream-core' ), 'placeholder' => __( 'Where is your event?', 'cater-ice-cream-core' ), 'icon' => 'pin', 'required' => true ),
			'location_detail' => array( 'type' => 'text', 'label' => __( 'Venue city or event ZIP code', 'cater-ice-cream-core' ), 'optional' => __( '(if we ask for it)', 'cater-ice-cream-core' ), 'placeholder' => __( 'e.g. Pasadena, or 91101', 'cater-ice-cream-core' ), 'max' => 60, 'wrap' => 'data-cic-detail', 'help' => __( 'Some ZIP codes cover more than one community, and some cities are only partly served. We use this to confirm your exact location.', 'cater-ice-cream-core' ) ),
			'venue'           => array( 'type' => 'text', 'label' => __( 'Venue or address', 'cater-ice-cream-core' ), 'optional' => $opt, 'placeholder' => __( 'If you know it', 'cater-ice-cream-core' ), 'icon' => 'home', 'max' => 200, 'autocomplete' => 'off' ),
			'serve_time'      => array( 'type' => 'time', 'label' => __( 'Serving start time', 'cater-ice-cream-core' ), 'optional' => $opt, 'icon' => 'clock' ),
			'serve_duration'  => array( 'type' => 'select', 'label' => __( 'Serving duration', 'cater-ice-cream-core' ), 'optional' => $opt, 'placeholder' => __( 'Choose', 'cater-ice-cream-core' ), 'options' => Validator::DURATIONS, 'icon' => 'clock' ),
			'treats'          => array( 'type' => 'treats', 'label' => __( 'Treats', 'cater-ice-cream-core' ) ),
			'treats_unsure'   => array( 'type' => 'unsure', 'label' => __( 'Not sure yet — help me choose', 'cater-ice-cream-core' ), 'help' => __( 'You can select more than one treat.', 'cater-ice-cream-core' ) ),
			'flavours'        => array( 'type' => 'textarea', 'label' => __( 'Flavor or topping preferences', 'cater-ice-cream-core' ), 'optional' => $opt, 'placeholder' => __( 'e.g. chocolate, vanilla, fresh fruit, sprinkles…', 'cater-ice-cream-core' ), 'max' => 500 ),
			'party_theme'     => array( 'type' => 'textarea', 'label' => __( 'Party theme or feeling', 'cater-ice-cream-core' ), 'optional' => $opt, 'placeholder' => __( 'Playful, nostalgic, elegant… tell us your ideas', 'cater-ice-cream-core' ), 'max' => 500 ),
			'dietary'         => array( 'type' => 'textarea', 'label' => __( 'Dietary requirements', 'cater-ice-cream-core' ), 'optional' => $opt, 'placeholder' => __( 'e.g. nut allergies, gluten-free, dairy-free…', 'cater-ice-cream-core' ), 'max' => 500, 'help' => __( 'We’ll review current ingredients and suitable options with you.', 'cater-ice-cream-core' ) ),
			'your_name'       => array( 'type' => 'text', 'label' => __( 'Full name', 'cater-ice-cream-core' ), 'placeholder' => __( 'Your name', 'cater-ice-cream-core' ), 'required' => true, 'max' => 100, 'autocomplete' => 'name' ),
			'your_email'      => array( 'type' => 'email', 'label' => __( 'Email address', 'cater-ice-cream-core' ), 'placeholder' => __( 'you@example.com', 'cater-ice-cream-core' ), 'required' => true, 'max' => 190, 'autocomplete' => 'email' ),
			'phone'           => array( 'type' => 'tel', 'label' => __( 'Phone', 'cater-ice-cream-core' ), 'optional' => $opt, 'placeholder' => __( '(818) 555-0100', 'cater-ice-cream-core' ), 'max' => 25, 'autocomplete' => 'tel', 'help' => __( 'Needed if you would like us to call you.', 'cater-ice-cream-core' ) ),
			'contact_pref'    => array( 'type' => 'radio', 'label' => __( 'Preferred contact method', 'cater-ice-cream-core' ), 'options' => array( 'email' => __( 'Email', 'cater-ice-cream-core' ), 'phone' => __( 'Phone', 'cater-ice-cream-core' ) ), 'default' => 'email' ),
			'notes'           => array( 'type' => 'textarea', 'label' => __( 'Additional notes', 'cater-ice-cream-core' ), 'optional' => $opt, 'placeholder' => __( 'Anything else we should know about your event?', 'cater-ice-cream-core' ), 'max' => 1500 ),
			'topic'           => array( 'type' => 'select', 'label' => __( 'Topic', 'cater-ice-cream-core' ), 'placeholder' => __( 'Select a topic', 'cater-ice-cream-core' ), 'options' => Validator::TOPICS, 'required' => true ),
			'message'         => array( 'type' => 'textarea', 'label' => __( 'Message', 'cater-ice-cream-core' ), 'placeholder' => __( 'How can we help?', 'cater-ice-cream-core' ), 'required' => true, 'max' => 3000, 'rows' => 5 ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Form tags
	 * ------------------------------------------------------------------ */

	public function add_tags(): void {
		wpcf7_add_form_tag( 'cic_field', array( $this, 'field_tag' ), array( 'name-attr' => true ) );
		wpcf7_add_form_tag( 'cic_steps', array( $this, 'steps_tag' ), array( 'name-attr' => true, 'do-not-store' => true ) );
		wpcf7_add_form_tag( 'cic_guard', array( $this, 'guard_tag' ), array( 'name-attr' => true, 'do-not-store' => true ) );
		wpcf7_add_form_tag( 'cic_submit', array( $this, 'submit_tag' ), array( 'name-attr' => true, 'do-not-store' => true ) );
	}

	/** Prefill: failed no-JS submission → validated quote context → spec default. */
	private static function value( string $name, array $spec ) {
		static $context = null;
		if ( null === $context ) {
			$context = Context::from_request();
		}
		$map     = array( 'event' => 'event', 'event_date' => 'date', 'guests' => 'guests', 'location' => 'location', 'treats' => 'treat' );
		$default = $spec['default'] ?? '';
		if ( isset( $map[ $name ], $context[ $map[ $name ] ] ) ) {
			$default = 'treats' === $name ? explode( ',', (string) $context['treat'] ) : (string) $context[ $map[ $name ] ];
			if ( 'location' === $name ) {
				// The context carries the canonical key; visitors see the repository label (never the slug).
				$default = \CIC\Locations\Service::display_label( $default );
			}
		}
		return function_exists( 'wpcf7_get_hangover' ) ? wpcf7_get_hangover( $name, $default ) : $default;
	}

	private static function icon( string $name ): string {
		return '' !== $name && function_exists( 'cic_theme_icon' ) ? '<span class="cic-field__icon" aria-hidden="true">' . cic_theme_icon( $name ) . '</span>' : '';
	}

	/**
	 * @param \WPCF7_FormTag $tag Tag.
	 */
	public function field_tag( $tag ): string {
		$specs = self::specs();
		$name  = (string) $tag->name;
		if ( ! isset( $specs[ $name ] ) ) {
			return '';
		}
		$spec  = $specs[ $name ];
		$error = function_exists( 'wpcf7_get_validation_error' ) ? wpcf7_get_validation_error( $name ) : '';
		$value = self::value( $name, $spec );
		$id    = 'cic-f-' . str_replace( '_', '-', $name );
		$aria  = ' aria-invalid="' . ( $error ? 'true' : 'false' ) . '"' . ( $error ? ' aria-describedby="' . esc_attr( wpcf7_get_validation_error_reference( $name ) ) . '"' : '' ) . ( ! empty( $spec['required'] ) ? ' aria-required="true"' : '' );
		$class = 'wpcf7-form-control cic-input' . ( $error ? ' wpcf7-not-valid' : '' );
		$label = '<span class="cic-field__label">' . esc_html( $spec['label'] ) . ( ! empty( $spec['required'] ) ? ' <span class="cic-req" aria-hidden="true">*</span>' : '' ) . ( isset( $spec['optional'] ) ? ' <span class="cic-opt">' . esc_html( $spec['optional'] ) . '</span>' : '' ) . '</span>';
		$help  = isset( $spec['help'] ) ? '<span class="cic-field__help" id="' . esc_attr( $id ) . '-help">' . esc_html( $spec['help'] ) . '</span>' : '';
		$wrap  = static fn( string $control ): string => '<span class="wpcf7-form-control-wrap" data-name="' . esc_attr( $name ) . '">' . $control . $error . '</span>';
		$extra = isset( $spec['wrap'] ) ? ' ' . $spec['wrap'] : '';

		switch ( $spec['type'] ) {
			case 'event':
				$events = Catalog::selectable_events();
				$items  = '';
				foreach ( array_merge( $events, array( array( 'key' => 'other', 'title' => __( 'Something else', 'cater-ice-cream-core' ) ) ) ) as $e ) {
					$other  = 'other' === $e['key'];
					$items .= '<label class="cic-tile' . ( $other ? ' cic-tile--other' : '' ) . '"><input type="radio" class="cic-tile__input" name="event" value="' . esc_attr( $e['key'] ) . '"' . checked( (string) $value, $e['key'], false ) . '><span class="cic-tile__box">' . self::icon( $other ? 'dots' : Catalog::event_icon( (int) $e['id'] ) ) . '<span class="cic-tile__label">' . esc_html( $e['label'] ?? $e['title'] ) . '</span><span class="cic-tile__check" aria-hidden="true"></span></span></label>';
				}
				return '<fieldset class="cic-field cic-field--tiles" id="' . esc_attr( $id ) . '"><legend class="cic-field__label">' . esc_html( $spec['label'] ) . ' <span class="cic-req" aria-hidden="true">*</span></legend>' . $wrap( '<span class="wpcf7-form-control cic-tiles' . ( $error ? ' wpcf7-not-valid' : '' ) . '" role="radiogroup"' . $aria . '>' . $items . '</span>' ) . '</fieldset>';

			case 'treats':
				$chosen = array_map( 'strval', (array) $value );
				$items  = '';
				foreach ( Catalog::selectable_products() as $p ) {
					$img    = get_post_thumbnail_id( $p['id'] );
					$items .= '<label class="cic-treat"><input type="checkbox" class="cic-treat__input" name="treats[]" value="' . esc_attr( $p['key'] ) . '"' . checked( in_array( $p['key'], $chosen, true ), true, false ) . ' data-cic-treat><span class="cic-treat__box">' . ( $img ? wp_get_attachment_image( $img, 'medium', false, array( 'class' => 'cic-treat__img', 'alt' => '', 'loading' => 'lazy', 'sizes' => '(max-width: 599px) 45vw, 240px' ) ) : '' ) . '<span class="cic-treat__check" aria-hidden="true"></span><span class="cic-treat__label">' . esc_html( $p['title'] ) . '</span></span></label>';
				}
				return '<fieldset class="cic-field cic-field--treats" id="' . esc_attr( $id ) . '"><legend class="screen-reader-text">' . esc_html__( 'Choose one or more treats', 'cater-ice-cream-core' ) . '</legend>' . $wrap( '<span class="wpcf7-form-control cic-treats' . ( $error ? ' wpcf7-not-valid' : '' ) . '"' . $aria . '>' . $items . '</span>' ) . '</fieldset>';

			case 'unsure':
				return '<div class="cic-field cic-field--unsure">' . $wrap( '<label class="cic-unsure"><input type="checkbox" class="wpcf7-form-control cic-unsure__input" name="treats_unsure" value="1"' . checked( '' !== (string) ( is_array( $value ) ? implode( '', $value ) : $value ), true, false ) . ' data-cic-unsure aria-describedby="' . esc_attr( $id ) . '-help"><span class="cic-unsure__box"><span class="cic-unsure__label">' . esc_html( $spec['label'] ) . '</span><span class="cic-unsure__help" id="' . esc_attr( $id ) . '-help">' . esc_html( $spec['help'] ) . '</span></span></label>' ) . '</div>';

			case 'check':
				return '<label class="cic-check">' . $wrap( '<input type="checkbox" class="wpcf7-form-control" name="' . esc_attr( $name ) . '" value="1"' . checked( '' !== (string) ( is_array( $value ) ? implode( '', $value ) : $value ), true, false ) . ' data-cic-' . esc_attr( str_replace( '_', '-', $name ) ) . '>' ) . '<span>' . esc_html( $spec['label'] ) . '</span></label>';

			case 'radio':
				$items = '';
				foreach ( $spec['options'] as $k => $l ) {
					$items .= '<label class="cic-radio"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $k ) . '"' . checked( (string) $value, (string) $k, false ) . '><span>' . esc_html( $l ) . '</span></label>';
				}
				return '<fieldset class="cic-field cic-field--radio"><legend class="cic-field__label">' . esc_html( $spec['label'] ) . '</legend>' . $wrap( '<span class="wpcf7-form-control cic-radios"' . $aria . '>' . $items . '</span>' ) . '</fieldset>';

			case 'select':
				$opts = '<option value="">' . esc_html( $spec['placeholder'] ?? '—' ) . '</option>';
				foreach ( $spec['options'] as $k => $l ) {
					$opts .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				$control = '<span class="cic-field__control">' . self::icon( $spec['icon'] ?? '' ) . '<select class="' . esc_attr( $class ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $aria . '>' . $opts . '</select></span>';
				return '<div class="cic-field"' . $extra . '><label for="' . esc_attr( $id ) . '">' . $label . '</label>' . $wrap( $control ) . $help . '</div>';

			case 'textarea':
				$control = '<textarea class="' . esc_attr( $class ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . (int) ( $spec['rows'] ?? 2 ) . '" maxlength="' . (int) $spec['max'] . '" placeholder="' . esc_attr( $spec['placeholder'] ?? '' ) . '"' . $aria . ( $help ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '' ) . '>' . esc_textarea( (string) $value ) . '</textarea>';
				return '<div class="cic-field"' . $extra . '><label for="' . esc_attr( $id ) . '">' . $label . '</label>' . $wrap( $control ) . $help . '</div>';

			default:
				$types = array( 'text' => 'text', 'email' => 'email', 'tel' => 'tel', 'number' => 'number', 'date' => 'date', 'time' => 'time', 'location' => 'text' );
				$atts  = ' type="' . esc_attr( $types[ $spec['type'] ] ?? 'text' ) . '"';
				if ( isset( $spec['max'] ) ) {
					$atts .= ' maxlength="' . (int) $spec['max'] . '"';
				}
				if ( 'number' === $spec['type'] ) {
					$atts .= ' min="1" max="10000" step="1" inputmode="numeric"';
				}
				if ( 'date' === $spec['type'] ) {
					$cfg   = Context::client_config();
					$atts .= ' min="' . esc_attr( $cfg['today'] ) . '" max="' . esc_attr( $cfg['maxDate'] ) . '"';
				}
				if ( 'location' === $spec['type'] ) {
					$atts .= ' data-cic-autocomplete data-cic-location autocomplete="off" maxlength="60"';
				} elseif ( isset( $spec['autocomplete'] ) ) {
					$atts .= ' autocomplete="' . esc_attr( $spec['autocomplete'] ) . '"';
				}
				if ( $help && false === strpos( $aria, 'aria-describedby' ) ) {
					$atts .= ' aria-describedby="' . esc_attr( $id ) . '-help"';
				}
				$control = '<span class="cic-field__control">' . self::icon( $spec['icon'] ?? '' ) . '<input class="' . esc_attr( $class ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_array( $value ) ? '' : (string) $value ) . '" placeholder="' . esc_attr( $spec['placeholder'] ?? '' ) . '"' . $atts . $aria . '></span>';
				$after   = '';
				if ( isset( $spec['after'] ) ) {
					$after = $this->field_tag( (object) array( 'name' => $spec['after'] ) );
				}
				$status = 'location' === $spec['type'] ? '<span class="cic-qf__coverage" data-cic-coverage role="status" aria-live="polite" hidden></span>' : '';
				return '<div class="cic-field"' . $extra . '><label for="' . esc_attr( $id ) . '">' . $label . '</label>' . $wrap( $control ) . $help . $status . $after . '</div>';
		}
	}

	public function steps_tag( $tag ): string {
		$steps = array( __( 'Your details & favorites', 'cater-ice-cream-core' ), __( 'Your event', 'cater-ice-cream-core' ) );
		$out   = '<nav class="cic-qf__progress cic-js-only" aria-label="' . esc_attr__( 'Quote steps', 'cater-ice-cream-core' ) . '"><ol>';
		foreach ( $steps as $i => $label ) {
			$out .= '<li data-step="' . ( $i + 1 ) . '"><button type="button" class="cic-qf__stepbtn" data-cic-goto="' . ( $i + 1 ) . '"><span class="cic-qf__num" aria-hidden="true">' . ( $i + 1 ) . '</span><span class="cic-qf__steplabel">' . esc_html( $label ) . '</span><span class="screen-reader-text" data-cic-stepstate></span></button></li>';
		}
		return $out . '</ol></nav>';
	}

	/** Honeypot, signed render time and the retry-safe submission key (none are stored). */
	public function guard_tag( $tag ): string {
		$error = function_exists( 'wpcf7_get_validation_error' ) ? wpcf7_get_validation_error( 'cic_guard' ) : '';
		$key   = isset( $_POST['cic_key'] ) && Store::valid_key( sanitize_text_field( wp_unslash( $_POST['cic_key'] ) ) ) ? sanitize_text_field( wp_unslash( $_POST['cic_key'] ) ) : self::new_key(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the same key survives a failed no-JS submission.
		return '<span class="wpcf7-form-control-wrap" data-name="cic_guard"><span class="cic-hp" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'cater-ice-cream-core' ) . '<input type="text" name="cic_website" value="" tabindex="-1" autocomplete="off"></label></span>'
			. '<input type="hidden" name="cic_guard" value="1"><input type="hidden" name="cic_ts" value="' . esc_attr( Guard::token() ) . '" data-cic-ts><input type="hidden" name="cic_key" value="' . esc_attr( $key ) . '" data-cic-key><input type="hidden" name="cic_source" value="" data-cic-source><input type="hidden" name="cic_draft_key" value="" data-cic-draft-key>' . $error . '</span>';
	}

	public function submit_tag( $tag ): string {
		$type = self::rendering_type();
		return '<button type="submit" class="wpcf7-form-control wpcf7-submit cic-button cic-button--send">' . esc_html( 'contact' === $type ? __( 'Send my message', 'cater-ice-cream-core' ) : __( 'Request My Quote', 'cater-ice-cream-core' ) ) . ' <span aria-hidden="true">→</span></button>';
	}

	public static function new_key(): string {
		return rtrim( strtr( base64_encode( random_bytes( 24 ) ), '+/', '-_' ), '=' );
	}

	/** Where the confirmation of a form type is shown. */
	public static function page_url( string $type ): string {
		if ( 'contact' === $type ) {
			$id = Catalog::find_by_key( 'page', 'contact' );
			return $id ? (string) get_permalink( $id ) : home_url( '/contact/' );
		}
		return Contact::get()['quote_url'];
	}
}
