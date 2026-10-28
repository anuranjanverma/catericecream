<?php
/**
 * Confirmation state shared by the quote and Contact forms (18_quote_later_steps.png, "After
 * submission"). Rendered visible by the server for a valid receipt, otherwise hidden and filled by
 * quote.js after the server confirmed one saved enquiry. Only non-personal labels are shown.
 * Wording: request received and reviewed by the team — never a booking or confirmed date.
 *
 * Quote (2026-10-23): "Your event at a glance" (contact, event and treats) and the optional
 * "Add a few more details" section (notes, theme, dietary; autosaved to the SAME inquiry). Both
 * are shown only to the browser that submitted the inquiry: from the server's confirmed response
 * (quote.js) or, on a reload, from args['mine'] (Drafts::for_receipt()).
 *
 * @package CaterIceCream
 *
 * @var array $args { type, receipt (array|null), success (heading, text, note, cta, home), next }
 */

defined( 'ABSPATH' ) || exit;

$cic_type    = (string) ( $args['type'] ?? 'quote' );
$cic_receipt = $args['receipt'] ?? null;
$cic_ok      = (array) ( $args['success'] ?? array() );
$cic_next    = (array) ( $args['next']['items'] ?? array() );
$cic_contact = cic_theme_contact();
$cic_mine    = 'quote' === $cic_type ? ( $args['success']['mine'] ?? null ) : null;
$cic_glance  = (array) ( $cic_mine['glance'] ?? array() );
$cic_rows    = array(
	__( 'Your details', 'cater-ice-cream' ) => array(
		'name'    => __( 'Name', 'cater-ice-cream' ),
		'email'   => __( 'Email', 'cater-ice-cream' ),
		'phone'   => __( 'Phone', 'cater-ice-cream' ),
		'contact' => __( 'Preferred contact', 'cater-ice-cream' ),
	),
	__( 'Your event', 'cater-ice-cream' )   => array(
		'event'    => __( 'Celebrating', 'cater-ice-cream' ),
		'date'     => __( 'Date', 'cater-ice-cream' ),
		'guests'   => __( 'Guests', 'cater-ice-cream' ),
		'location' => __( 'Location', 'cater-ice-cream' ),
		'venue'    => __( 'Venue', 'cater-ice-cream' ),
		'serving'  => __( 'Serving', 'cater-ice-cream' ),
		'treats'   => __( 'Treats', 'cater-ice-cream' ),
	),
);
$cic_more    = array(
	'notes'       => array( __( 'Additional notes', 'cater-ice-cream' ), __( 'Anything else we should know about your event?', 'cater-ice-cream' ), 1500 ),
	'party_theme' => array( __( 'Party theme or feeling', 'cater-ice-cream' ), __( 'Playful, nostalgic, elegant… tell us your ideas', 'cater-ice-cream' ), 500 ),
	'dietary'     => array( __( 'Dietary requirements', 'cater-ice-cream' ), __( 'e.g. nut allergies, gluten-free, dairy-free…', 'cater-ice-cream' ), 500 ),
);
$cic_chips   = array(
	'event'    => array( 'party', (string) ( $cic_receipt['event'] ?? '' ) ),
	'location' => array( 'pin', (string) ( $cic_receipt['location'] ?? '' ) ),
	'treats'   => array( 'sundae', implode( ', ', (array) ( $cic_receipt['treats'] ?? array() ) ) ),
);
?>
<section class="cic-received cic-received--<?php echo esc_attr( $cic_type ); ?>" id="confirmation" tabindex="-1" aria-labelledby="cic-received-title" data-cic-received<?php echo $cic_receipt ? '' : ' hidden'; ?>>
	<div class="cic-received__main">
		<span class="cic-received__icon" aria-hidden="true"><?php echo cic_theme_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<div class="cic-received__body">
			<h2 class="cic-received__title" id="cic-received-title"><?php echo esc_html( (string) ( $cic_ok['heading'] ?? '' ) ); ?></h2>
			<?php if ( ! empty( $cic_ok['text'] ) ) : ?>
				<p><?php echo esc_html( $cic_ok['text'] ); ?></p>
			<?php endif; ?>
			<?php if ( 'quote' === $cic_type ) : ?>
				<ul class="cic-received__chips" data-cic-chips<?php echo $cic_glance ? ' hidden' : ''; ?>>
					<?php foreach ( $cic_chips as $cic_key => $cic_chip ) : ?>
						<li data-chip="<?php echo esc_attr( $cic_key ); ?>"<?php echo '' === $cic_chip[1] ? ' hidden' : ''; ?>><?php echo cic_theme_icon( $cic_chip[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span data-chip-value><?php echo esc_html( $cic_chip[1] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $cic_ok['note'] ) ) : ?>
				<p class="cic-received__note"><?php echo esc_html( $cic_ok['note'] ); ?></p>
			<?php endif; ?>
			<div class="cic-received__actions">
				<?php if ( 'quote' === $cic_type ) : ?>
					<a class="cic-button" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/treats/' ) ); ?>"><?php echo esc_html( (string) ( $cic_ok['cta'] ?? '' ) ); ?></a>
				<?php endif; ?>
				<a class="cic-button cic-button--secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( (string) ( $cic_ok['home'] ?? __( 'Back to homepage', 'cater-ice-cream' ) ) ); ?></a>
			</div>
		</div>
	</div>
	<?php if ( 'quote' === $cic_type ) : ?>
		<section class="cic-glance-box" data-cic-glance-box aria-labelledby="cic-glance-title"<?php echo $cic_glance ? '' : ' hidden'; ?>>
			<h3 class="cic-glance-box__title" id="cic-glance-title"><?php esc_html_e( 'Your event at a glance', 'cater-ice-cream' ); ?></h3>
			<div class="cic-glance-box__grid">
				<?php foreach ( $cic_rows as $cic_group => $cic_fields ) : ?>
					<dl class="cic-glance-box__group" aria-label="<?php echo esc_attr( $cic_group ); ?>">
						<?php foreach ( $cic_fields as $cic_key => $cic_label ) : ?>
							<div data-g="<?php echo esc_attr( $cic_key ); ?>"<?php echo empty( $cic_glance[ $cic_key ] ) ? ' hidden' : ''; ?>><dt><?php echo esc_html( $cic_label ); ?></dt><dd data-g-value><?php echo esc_html( (string) ( $cic_glance[ $cic_key ] ?? '' ) ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
				<?php endforeach; ?>
			</div>
		</section>
		<section class="cic-more" data-cic-more aria-labelledby="cic-more-title"<?php echo $cic_mine ? '' : ' hidden'; ?>>
			<h3 class="cic-more__title" id="cic-more-title"><?php esc_html_e( 'Add a few more details', 'cater-ice-cream' ); ?> <span class="cic-opt"><?php esc_html_e( '(optional)', 'cater-ice-cream' ); ?></span></h3>
			<p class="cic-more__intro" id="cic-more-intro"><?php esc_html_e( 'Optional—these details help us tailor your quote. Changes save automatically.', 'cater-ice-cream' ); ?></p>
			<div class="cic-more__fields">
				<?php foreach ( $cic_more as $cic_key => $cic_def ) : ?>
					<div class="cic-field">
						<label for="cic-more-<?php echo esc_attr( $cic_key ); ?>"><span class="cic-field__label"><?php echo esc_html( $cic_def[0] ); ?></span></label>
						<textarea class="cic-input" id="cic-more-<?php echo esc_attr( $cic_key ); ?>" name="<?php echo esc_attr( $cic_key ); ?>" rows="2" maxlength="<?php echo (int) $cic_def[2]; ?>" placeholder="<?php echo esc_attr( $cic_def[1] ); ?>" aria-describedby="cic-more-intro" data-cic-more-field><?php echo esc_textarea( (string) ( $cic_mine['optional'][ $cic_key ] ?? '' ) ); ?></textarea>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="cic-more__status" role="status" aria-live="polite"><span data-cic-more-status></span> <button type="button" class="cic-text-link cic-more__retry" data-cic-more-retry hidden><?php esc_html_e( 'Try again', 'cater-ice-cream' ); ?></button></p>
		</section>
	<?php endif; ?>
	<?php if ( $cic_next ) : ?>
		<div class="cic-received__next">
			<h3><?php esc_html_e( 'What happens next?', 'cater-ice-cream' ); ?></h3>
			<ol>
				<?php foreach ( $cic_next as $cic_item ) : ?>
					<li><?php echo cic_theme_icon( $cic_item['icon'] ?: 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><strong><?php echo esc_html( $cic_item['title'] ); ?></strong><span><?php echo esc_html( $cic_item['text'] ); ?></span></li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php endif; ?>
	<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
		<p class="cic-received__call"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Have a question?', 'cater-ice-cream' ); ?> <a href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a></p>
	<?php endif; ?>
</section>
