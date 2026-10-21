<?php
/**
 * Confirmation state shared by the quote and Contact forms (18_quote_later_steps.png, "After
 * submission"). Rendered visible by the server for a valid receipt, otherwise hidden and filled by
 * quote.js after the server confirmed one saved enquiry. Only non-personal labels are shown.
 * Wording: request received and reviewed by the team — never a booking or confirmed date.
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
				<ul class="cic-received__chips" data-cic-chips>
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
