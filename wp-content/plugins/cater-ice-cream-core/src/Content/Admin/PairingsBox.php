<?php
/**
 * Pairing editor on the treat (product) screen, and a read-only "Paired treats" list on the event
 * screen. Pairing copy is edited in one place only (the treat), so the two pages cannot disagree.
 *
 * @package CIC
 */

namespace CIC\Content\Admin;

use CIC\Content\EventType;
use CIC\Content\Pairings;

defined( 'ABSPATH' ) || exit;

final class PairingsBox {

	public const NONCE = 'cic_pairings_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post_product', array( $this, 'save' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function add_boxes(): void {
		add_meta_box( 'cic-pairings', __( 'Occasion pairings', 'cater-ice-cream-core' ), array( $this, 'render_product' ), 'product', 'normal', 'default' );
		add_meta_box( 'cic-paired-treats', __( 'Paired treats', 'cater-ice-cream-core' ), array( $this, 'render_event' ), EventType::POST_TYPE, 'side', 'default' );
	}

	/**
	 * @return \WP_Post[]
	 */
	private static function events(): array {
		return get_posts(
			array(
				'post_type'   => EventType::POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => -1,
				'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $row Pairing row.
	 */
	private function row( string $index, array $row, array $events ): string {
		$name  = static fn( string $f ) => 'cic_pairings[' . $index . '][' . $f . ']';
		$image = (int) ( $row['image_id'] ?? 0 );
		$src   = $image ? (string) wp_get_attachment_image_url( $image, 'thumbnail' ) : '';
		$opts  = '<option value="">' . esc_html__( '— choose an event —', 'cater-ice-cream-core' ) . '</option>';
		foreach ( $events as $event ) {
			$status = 'publish' === $event->post_status ? '' : ' (' . $event->post_status . ')';
			$opts  .= sprintf( '<option value="%1$d"%2$s>%3$s</option>', $event->ID, selected( (int) ( $row['event_id'] ?? 0 ), $event->ID, false ), esc_html( $event->post_title . $status ) );
		}
		ob_start();
		?>
		<fieldset class="cic-pairing" data-cic-pairing>
			<legend class="screen-reader-text"><?php esc_html_e( 'Pairing', 'cater-ice-cream-core' ); ?></legend>
			<div class="cic-pairing__grid">
				<p><label><?php esc_html_e( 'Event', 'cater-ice-cream-core' ); ?><br><select name="<?php echo esc_attr( $name( 'event_id' ) ); ?>"><?php echo $opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></select></label></p>
				<p><label><?php esc_html_e( 'Order in this list (the treat page itself follows the central event order)', 'cater-ice-cream-core' ); ?><br><input type="number" class="small-text" name="<?php echo esc_attr( $name( 'product_sort' ) ); ?>" value="<?php echo esc_attr( (string) ( $row['product_sort'] ?? 0 ) ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Order on the event page', 'cater-ice-cream-core' ); ?><br><input type="number" class="small-text" name="<?php echo esc_attr( $name( 'event_sort' ) ); ?>" value="<?php echo esc_attr( (string) ( $row['event_sort'] ?? 0 ) ); ?>"></label></p>
				<p class="cic-pairing__wide"><label><?php esc_html_e( 'Feeling headline', 'cater-ice-cream-core' ); ?><br><input type="text" class="widefat" maxlength="200" name="<?php echo esc_attr( $name( 'headline' ) ); ?>" value="<?php echo esc_attr( (string) ( $row['headline'] ?? '' ) ); ?>"></label></p>
				<p class="cic-pairing__wide"><label><?php esc_html_e( 'Explanation (shown on the treat and the event page)', 'cater-ice-cream-core' ); ?><br><textarea class="widefat" rows="3" name="<?php echo esc_attr( $name( 'description' ) ); ?>"><?php echo esc_textarea( (string) ( $row['description'] ?? '' ) ); ?></textarea></label></p>
				<p class="cic-pairing__wide"><label><?php esc_html_e( 'Optional event-page introduction', 'cater-ice-cream-core' ); ?><br><textarea class="widefat" rows="2" name="<?php echo esc_attr( $name( 'event_intro' ) ); ?>"><?php echo esc_textarea( (string) ( $row['event_intro'] ?? '' ) ); ?></textarea></label></p>
				<p><label><?php esc_html_e( 'Event link label', 'cater-ice-cream-core' ); ?><br><input type="text" class="regular-text" maxlength="120" name="<?php echo esc_attr( $name( 'link_label' ) ); ?>" value="<?php echo esc_attr( (string) ( $row['link_label'] ?? '' ) ); ?>"></label></p>
				<div class="cic-image-field" data-cic-image>
					<span><?php esc_html_e( 'Photo', 'cater-ice-cream-core' ); ?></span><br>
					<input type="hidden" name="<?php echo esc_attr( $name( 'image_id' ) ); ?>" value="<?php echo esc_attr( (string) $image ); ?>">
					<img class="cic-image-preview" src="<?php echo esc_url( $src ); ?>" alt=""<?php echo $src ? '' : ' hidden'; ?>>
					<button type="button" class="button" data-cic-image-select><?php esc_html_e( 'Choose photo', 'cater-ice-cream-core' ); ?></button>
					<button type="button" class="button-link" data-cic-image-clear<?php echo $image ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove photo', 'cater-ice-cream-core' ); ?></button>
				</div>
			</div>
			<p><button type="button" class="button-link button-link-delete" data-cic-pairing-remove><?php esc_html_e( 'Remove this pairing', 'cater-ice-cream-core' ); ?></button></p>
		</fieldset>
		<?php
		return (string) ob_get_clean();
	}

	public function render_product( \WP_Post $post ): void {
		\CIC\Design\Admin\Fields::enqueue();
		wp_enqueue_media();
		wp_enqueue_script( 'cic-pairings-admin', CIC_CORE_URL . 'assets/js/pairings-admin.js', array( 'cic-design-admin' ), (string) filemtime( CIC_CORE_DIR . 'assets/js/pairings-admin.js' ), true );
		wp_nonce_field( 'cic_pairings_' . $post->ID, self::NONCE );
		$events = self::events();
		echo '<p class="description">' . esc_html__( 'One pairing per event. The explanation is stored once and appears on this treat page and on the event page. Drafts can be paired but only appear publicly once both records are published.', 'cater-ice-cream-core' ) . '</p>';
		echo '<input type="hidden" name="cic_pairings_present" value="1"><div data-cic-pairings>';
		foreach ( Pairings::for_product( $post->ID ) as $i => $row ) {
			echo $this->row( (string) $i, $row, $events ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row().
		}
		echo '</div><template data-cic-pairing-template>' . $this->row( '__INDEX__', array(), $events ) . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row().
		echo '<p><button type="button" class="button" data-cic-pairing-add>' . esc_html__( 'Add pairing', 'cater-ice-cream-core' ) . '</button></p>';
	}

	public function render_event( \WP_Post $post ): void {
		$rows = Pairings::for_event( $post->ID );
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No treats are paired with this event yet. Pairings are added on each treat.', 'cater-ice-cream-core' ) . '</p>';
			return;
		}
		echo '<ol>';
		foreach ( $rows as $row ) {
			$status = get_post_status( $row['product_id'] );
			printf(
				'<li><a href="%1$s">%2$s</a>%3$s<br><span class="description">%4$s</span></li>',
				esc_url( (string) get_edit_post_link( $row['product_id'] ) ),
				esc_html( get_the_title( $row['product_id'] ) ),
				'publish' === $status ? '' : ' <em>(' . esc_html( (string) $status ) . ')</em>',
				esc_html( $row['headline'] )
			);
		}
		echo '</ol><p class="description">' . esc_html__( 'Edit the shared text on the treat, so both pages stay consistent.', 'cater-ice-cream-core' ) . '</p>';
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( empty( $_POST['cic_pairings_present'] ) || ! isset( $_POST[ self::NONCE ] ) || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'cic_pairings_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$rows   = isset( $_POST['cic_pairings'] ) && is_array( $_POST['cic_pairings'] ) ? wp_unslash( $_POST['cic_pairings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated in Pairings::validate().
		unset( $rows['__INDEX__'] );
		$errors = Pairings::replace_for_product( $post_id, array_values( $rows ) );
		if ( $errors ) {
			set_transient( 'cic_pairing_errors_' . get_current_user_id(), $errors, 60 );
		}
	}

	public function notice(): void {
		$errors = get_transient( 'cic_pairing_errors_' . get_current_user_id() );
		if ( ! is_array( $errors ) || ! $errors ) {
			return;
		}
		delete_transient( 'cic_pairing_errors_' . get_current_user_id() );
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Some pairings were not saved:', 'cater-ice-cream-core' ) . '</p><ul class="ul-disc">';
		foreach ( $errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';
	}
}
