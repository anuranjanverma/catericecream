<?php
/**
 * Dedicated quote route (/get-a-quote/). The three-step CF7 quote flow is built in Phase 06; until
 * then this page confirms the validated selections it received and offers the contact options.
 * Only server-validated, non-personal context is shown. Nothing here confirms availability.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
$cic_context = class_exists( '\CIC\Quote\Context' ) ? \CIC\Quote\Context::from_request() : array();
$cic_contact = cic_theme_contact();
$cic_rows    = array();
if ( isset( $cic_context['event'] ) ) {
	$cic_event  = 'other' === $cic_context['event'] ? __( 'Other occasion', 'cater-ice-cream' ) : get_the_title( \CIC\Content\Catalog::find_by_key( 'cic_event', $cic_context['event'] ) );
	$cic_rows[] = array( __( 'Event type', 'cater-ice-cream' ), $cic_event, 'event' );
}
if ( isset( $cic_context['treat'] ) ) {
	$cic_titles = array_map( static fn( $k ) => get_the_title( \CIC\Content\Catalog::find_by_key( 'product', $k ) ), explode( ',', $cic_context['treat'] ) );
	$cic_rows[] = array( __( 'Treats', 'cater-ice-cream' ), implode( ', ', $cic_titles ), 'treat' );
}
if ( isset( $cic_context['location'] ) ) {
	$cic_rows[] = array( __( 'Location', 'cater-ice-cream' ), preg_match( '/^\d{5}$/', $cic_context['location'] ) ? $cic_context['location'] : ucwords( str_replace( '-', ' ', $cic_context['location'] ) ), 'location' );
}
if ( isset( $cic_context['date'] ) ) {
	$cic_rows[] = array( __( 'Date', 'cater-ice-cream' ), wp_date( get_option( 'date_format' ), strtotime( $cic_context['date'] . ' 12:00:00 America/Los_Angeles' ), new DateTimeZone( 'America/Los_Angeles' ) ), 'date' );
}
if ( isset( $cic_context['guests'] ) ) {
	$cic_rows[] = array( __( 'Guests', 'cater-ice-cream' ), (string) $cic_context['guests'], 'guests' );
}
?>
<section class="cic-section cic-quote-route">
	<div class="cic-container cic-quote-route__inner">
		<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( get_the_title(), '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<h1><?php the_title(); ?></h1>
		<?php if ( $cic_rows ) : ?>
			<p class="cic-lead"><?php esc_html_e( "Here's what you've chosen so far:", 'cater-ice-cream' ); ?></p>
			<dl class="cic-quote-summary" data-cic-quote-summary>
				<?php foreach ( $cic_rows as $cic_row ) : ?>
					<div data-field="<?php echo esc_attr( $cic_row[2] ); ?>"><dt><?php echo esc_html( $cic_row[0] ); ?></dt><dd><?php echo esc_html( $cic_row[1] ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		<?php else : ?>
			<p class="cic-lead"><?php esc_html_e( 'Tell us about your event and the treats you have in mind.', 'cater-ice-cream' ); ?></p>
		<?php endif; ?>
		<p><?php echo esc_html( $cic_contact['availability_note'] ); ?></p>
		<div class="cic-hero__actions">
			<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
				<a class="cic-button" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== $cic_contact['email'] ) : ?>
				<a class="cic-text-link" href="<?php echo esc_url( 'mailto:' . $cic_contact['email'] ); ?>"><?php echo esc_html( $cic_contact['email'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php
get_footer();
