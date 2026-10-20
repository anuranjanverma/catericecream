<?php
/**
 * Enquiry mail, form and reminder settings (Enquiries → Settings, administrators only; Settings
 * API with nonce + sanitisation). Stored per environment in the database, never hardcoded.
 *
 * - Operator recipient: initial value anuranjan.verma25@gmail.com. A notification recipient only,
 *   separate from the public business email (Site\Contact) and from any WordPress login.
 * - Sender name/email: pending the verified production domain; empty = WordPress default sender.
 * - Customer acknowledgement and operator notification templates are editable.
 * - Reminders are internal-only and stay disabled until a timing is configured.
 *
 * @package CIC
 */

namespace CIC\Enquiries;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'cic_enquiry_settings';
	public const PAGE   = 'cic-enquiry-settings';

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return array(
			'recipient'           => 'anuranjan.verma25@gmail.com',
			'from_name'           => '',
			'from_email'          => '',
			'quote_form_id'       => 0,
			'contact_form_id'     => 0,
			'operator_subject'    => 'New {type} enquiry #{id}: {event} · {location}',
			'customer_enabled'    => 1,
			'quote_ack_subject'   => 'We have received your quote request',
			'quote_ack_body'      => "Hi {name},\n\nThank you for sharing your plans with Cater Ice Cream. We have received your quote request and our team will review your event details and contact you to discuss availability and your quote.\n\nThis is not a booking confirmation: your date and serving details are confirmed with your quote.\n\n{summary}\n\nIf you have a question in the meantime, call us on {phone}.\n\nCater Ice Cream",
			'contact_ack_subject' => 'We have received your message',
			'contact_ack_body'    => "Hi {name},\n\nThank you for contacting Cater Ice Cream. We have received your message and will reply as soon as we can.\n\nIf it is urgent, call us on {phone}.\n\nCater Ice Cream",
			'reminders_enabled'   => 0,
			'reminder_time'       => '',
			'reminder_days'       => 0,
			'reminder_fallback'   => '',
		);
	}

	/** @return array<string, mixed> */
	public static function get(): array {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/** @return mixed */
	public static function value( string $key ) {
		return self::get()[ $key ] ?? null;
	}

	public static function update( array $changes ): void {
		update_option( self::OPTION, array_merge( self::get(), $changes ), false );
	}

	/** Reminders run only when switched on AND a valid time of day is configured. */
	public static function reminders_ready(): bool {
		$s = self::get();
		return ! empty( $s['reminders_enabled'] ) && (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $s['reminder_time'] );
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	public function register_setting(): void {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * @param mixed $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$old   = self::get();
		$out   = $old;
		$email = static function ( string $key, bool $required ) use ( $input, $old, &$out ): void {
			$raw = trim( (string) ( $input[ $key ] ?? '' ) );
			if ( '' === $raw && ! $required ) {
				$out[ $key ] = '';
				return;
			}
			if ( is_email( $raw ) ) {
				$out[ $key ] = sanitize_email( $raw );
				return;
			}
			$out[ $key ] = $old[ $key ];
			add_settings_error( self::OPTION, 'cic_' . $key, sprintf( /* translators: %s: field. */ __( '%s: enter a valid email address. The previous value was kept.', 'cater-ice-cream-core' ), $key ) );
		};
		$email( 'recipient', true );
		$email( 'from_email', false );
		$email( 'reminder_fallback', false );
		$out['from_name'] = mb_substr( sanitize_text_field( (string) ( $input['from_name'] ?? '' ) ), 0, 80 );
		foreach ( array( 'quote_form_id', 'contact_form_id' ) as $key ) {
			$id          = absint( $input[ $key ] ?? 0 );
			$out[ $key ] = ( $id && 'wpcf7_contact_form' === get_post_type( $id ) ) ? $id : (int) $old[ $key ];
		}
		foreach ( array( 'operator_subject', 'quote_ack_subject', 'contact_ack_subject' ) as $key ) {
			$out[ $key ] = mb_substr( sanitize_text_field( (string) ( $input[ $key ] ?? '' ) ), 0, 150 ) ?: self::defaults()[ $key ];
		}
		foreach ( array( 'quote_ack_body', 'contact_ack_body' ) as $key ) {
			$out[ $key ] = mb_substr( sanitize_textarea_field( (string) ( $input[ $key ] ?? '' ) ), 0, 3000 ) ?: self::defaults()[ $key ];
		}
		$out['customer_enabled']  = empty( $input['customer_enabled'] ) ? 0 : 1;
		$out['reminders_enabled'] = empty( $input['reminders_enabled'] ) ? 0 : 1;
		$time                     = trim( (string) ( $input['reminder_time'] ?? '' ) );
		$out['reminder_time']     = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '';
		$out['reminder_days']     = max( 0, min( 14, (int) ( $input['reminder_days'] ?? 0 ) ) );
		if ( $out['reminders_enabled'] && '' === $out['reminder_time'] ) {
			add_settings_error( self::OPTION, 'cic_reminder_time', __( 'Reminders stay off until a reminder time (HH:MM) is set.', 'cater-ice-cream-core' ), 'warning' );
		}
		if ( $out['reminders_enabled'] !== (int) $old['reminders_enabled'] || $out['reminder_time'] !== $old['reminder_time'] || $out['reminder_days'] !== (int) $old['reminder_days'] ) {
			// Re-plan once the new settings are stored.
			add_action( 'update_option_' . self::OPTION, array( Workflow::class, 'reschedule_all' ) );
		}
		return $out;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change enquiry settings.', 'cater-ice-cream-core' ), '', array( 'response' => 403 ) );
		}
		$s     = self::get();
		$forms = get_posts( array( 'post_type' => 'wpcf7_contact_form', 'numberposts' => 50, 'post_status' => 'any' ) );
		$name  = static fn( string $k ): string => self::OPTION . '[' . $k . ']';
		$text  = static function ( string $k, string $label, string $help = '', string $type = 'text' ) use ( $s, $name ): void {
			echo '<tr><th scope="row"><label for="cic-es-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" type="' . esc_attr( $type ) . '" id="cic-es-' . esc_attr( $k ) . '" name="' . esc_attr( $name( $k ) ) . '" value="' . esc_attr( (string) $s[ $k ] ) . '">' . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		$area  = static function ( string $k, string $label, string $help = '' ) use ( $s, $name ): void {
			echo '<tr><th scope="row"><label for="cic-es-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><textarea class="large-text" rows="8" id="cic-es-' . esc_attr( $k ) . '" name="' . esc_attr( $name( $k ) ) . '">' . esc_textarea( (string) $s[ $k ] ) . '</textarea>' . ( '' !== $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '' ) . '</td></tr>';
		};
		$form  = static function ( string $k, string $label ) use ( $s, $name, $forms ): void {
			echo '<tr><th scope="row"><label for="cic-es-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><select id="cic-es-' . esc_attr( $k ) . '" name="' . esc_attr( $name( $k ) ) . '"><option value="0">—</option>';
			foreach ( $forms as $f ) {
				echo '<option value="' . (int) $f->ID . '"' . selected( (int) $s[ $k ], (int) $f->ID, false ) . '>' . esc_html( $f->post_title . ' (#' . $f->ID . ')' ) . '</option>';
			}
			echo '</select></td></tr>';
		};

		echo '<div class="wrap"><h1>' . esc_html__( 'Enquiry settings', 'cater-ice-cream-core' ) . '</h1>';
		settings_errors( self::OPTION );
		echo '<form method="post" action="options.php">';
		settings_fields( self::PAGE );
		echo '<h2>' . esc_html__( 'Notifications', 'cater-ice-cream-core' ) . '</h2><table class="form-table" role="presentation">';
		$text( 'recipient', __( 'Operator notification recipient', 'cater-ice-cream-core' ), __( 'Receives a notification for each saved enquiry. Separate from the public contact email shown on the website (Cater Ice Cream → Contact) and from any login. Local and staging sites capture mail in Mailpit.', 'cater-ice-cream-core' ), 'email' );
		$text( 'from_name', __( 'Sender name', 'cater-ice-cream-core' ), __( 'Pending production setup. Empty uses the WordPress default.', 'cater-ice-cream-core' ) );
		$text( 'from_email', __( 'Sender email', 'cater-ice-cream-core' ), __( 'Pending: use an address on the verified sending domain at deployment. Empty uses the WordPress default sender. Customer replies go to the customer (Reply-To) on operator notifications.', 'cater-ice-cream-core' ), 'email' );
		$text( 'operator_subject', __( 'Operator subject', 'cater-ice-cream-core' ), __( 'Placeholders: {type} {id} {event} {location}. Keep personal details out of subjects.', 'cater-ice-cream-core' ) );
		echo '<tr><th scope="row">' . esc_html__( 'Customer acknowledgement', 'cater-ice-cream-core' ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name( 'customer_enabled' ) ) . '" value="1"' . checked( ! empty( $s['customer_enabled'] ), true, false ) . '> ' . esc_html__( 'Send an acknowledgement to the customer after the enquiry is saved', 'cater-ice-cream-core' ) . '</label></td></tr>';
		$text( 'quote_ack_subject', __( 'Quote acknowledgement subject', 'cater-ice-cream-core' ) );
		$area( 'quote_ack_body', __( 'Quote acknowledgement text', 'cater-ice-cream-core' ), __( 'Placeholders: {name} {summary} {phone}. Never promise a booking or a date.', 'cater-ice-cream-core' ) );
		$text( 'contact_ack_subject', __( 'Contact acknowledgement subject', 'cater-ice-cream-core' ) );
		$area( 'contact_ack_body', __( 'Contact acknowledgement text', 'cater-ice-cream-core' ), __( 'Placeholders: {name} {phone}.', 'cater-ice-cream-core' ) );
		echo '</table><h2>' . esc_html__( 'Forms', 'cater-ice-cream-core' ) . '</h2><table class="form-table" role="presentation">';
		$form( 'quote_form_id', __( 'Quote form (Contact Form 7)', 'cater-ice-cream-core' ) );
		$form( 'contact_form_id', __( 'Contact form (Contact Form 7)', 'cater-ice-cream-core' ) );
		echo '</table><h2>' . esc_html__( 'Internal follow-up reminders', 'cater-ice-cream-core' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Reminders email the assigned team member (or the fallback address) about an enquiry’s next follow-up date. They never contact customers. Reminder timing is pending: reminders stay off until it is set. Times are America/Los_Angeles.', 'cater-ice-cream-core' ) . '</p><table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Reminders', 'cater-ice-cream-core' ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name( 'reminders_enabled' ) ) . '" value="1"' . checked( ! empty( $s['reminders_enabled'] ), true, false ) . '> ' . esc_html__( 'Enable internal follow-up reminders', 'cater-ice-cream-core' ) . '</label><p class="description">' . esc_html( self::reminders_ready() ? __( 'Active.', 'cater-ice-cream-core' ) : __( 'Not active.', 'cater-ice-cream-core' ) ) . '</p></td></tr>';
		$text( 'reminder_time', __( 'Reminder time (HH:MM)', 'cater-ice-cream-core' ), __( 'Time of day the reminder is sent, for example 09:00.', 'cater-ice-cream-core' ) );
		$text( 'reminder_days', __( 'Days before the follow-up date', 'cater-ice-cream-core' ), __( '0 = on the follow-up date.', 'cater-ice-cream-core' ), 'number' );
		$text( 'reminder_fallback', __( 'Fallback reminder recipient', 'cater-ice-cream-core' ), __( 'Used when an enquiry has no assignee. Empty uses the operator notification recipient.', 'cater-ice-cream-core' ), 'email' );
		echo '</table>';
		submit_button();
		echo '</form></div>';
	}
}
