<?php
/**
 * Get a Quote (approved references designs/17_quote_step_1.png, 18_quote_later_steps.png; mobile
 * column of 21_mobile_detail_and_quote.png). One shared CF7 form in three steps (Your event, Your
 * treats, Review and send), progressively enhanced by assets/js/quote.js; without JavaScript the
 * whole form is usable on one page. Incoming selections are prefilled by the server.
 *
 * The confirmation state (#confirmation) is rendered by the server for a valid receipt
 * (?received=…, no-JS submissions and refreshes) and filled in place by quote.js after an accepted
 * submission. It says the request was received, never that anything is booked.
 * If Contact Form 7 or Flamingo is unavailable, call/email options replace the form.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
$cic_vm      = \CIC\Content\ViewModel::quote( (int) get_queried_object_id() );
$cic_contact = cic_theme_contact();
$cic_h       = $cic_vm['hero'];
$cic_s       = $cic_vm['side'];
$cic_ok      = $cic_vm['success'];
$cic_rec     = $cic_ok['receipt'];
?>
<div class="cic-quote-page">
	<section class="cic-quote-hero">
		<div class="cic-container cic-container--wide cic-quote-hero__grid">
			<div class="cic-quote-hero__text">
				<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( '' !== $cic_h['eyebrow'] ) : ?>
					<p class="cic-eyebrow"><?php echo esc_html( $cic_h['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h1 class="cic-quote-hero__title"><?php echo $cic_h['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
				<?php if ( '' !== $cic_h['intro'] ) : ?>
					<p class="cic-lead"><?php echo $cic_h['intro']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></p>
				<?php endif; ?>
			</div>
			<div class="cic-quote-hero__art"><?php echo cic_theme_mascot( 240, array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></div>
		</div>
	</section>

	<section class="cic-quote-main">
		<div class="cic-container cic-container--wide cic-quote-main__grid">
			<div class="cic-quote-card" id="quote-form">
				<?php
				get_template_part(
					'template-parts/components/enquiry-received',
					null,
					array(
						'type'    => 'quote',
						'receipt' => $cic_rec,
						'success' => $cic_ok,
						'next'    => $cic_vm['next'],
					)
				);
				?>
				<div class="cic-quote-card__form" data-cic-form-area<?php echo $cic_rec ? ' hidden' : ''; ?>>
					<?php if ( '' !== $cic_vm['form'] ) : ?>
						<?php echo $cic_vm['form']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Contact Form 7 output. ?>
					<?php else : ?>
						<div class="cic-quote-unavailable" role="status">
							<h2><?php esc_html_e( 'Talk to us about your event', 'cater-ice-cream' ); ?></h2>
							<p><?php echo esc_html( $cic_vm['unavailable'] ); ?></p>
							<div class="cic-hero__actions">
								<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
									<a class="cic-button" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
								<?php endif; ?>
								<?php if ( '' !== $cic_contact['email'] ) : ?>
									<a class="cic-button cic-button--secondary" href="<?php echo esc_url( 'mailto:' . $cic_contact['email'] ); ?>"><?php echo esc_html( $cic_contact['email'] ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<aside class="cic-quote-side" aria-label="<?php esc_attr_e( 'Planning help', 'cater-ice-cream' ); ?>">
				<?php if ( $cic_s['items'] ) : ?>
					<div class="cic-quote-plan">
						<div class="cic-quote-plan__head">
							<h2 class="cic-quote-plan__title"><?php echo $cic_s['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
							<span class="cic-quote-plan__mascot"><?php echo cic_theme_mascot( 72 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
						</div>
						<ul class="cic-quote-plan__list">
							<?php foreach ( $cic_s['items'] as $cic_item ) : ?>
								<li>
									<span class="cic-quote-plan__icon"><?php echo cic_theme_icon( $cic_item['icon'] ?: 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
									<span><strong><?php echo esc_html( $cic_item['title'] ); ?></strong><?php echo esc_html( $cic_item['text'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<div class="cic-quote-help">
					<?php if ( '' !== $cic_s['help'] ) : ?>
						<h2 class="cic-quote-help__title"><?php echo esc_html( $cic_s['help'] ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== $cic_s['text'] ) : ?>
						<p><?php echo esc_html( $cic_s['text'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
						<a class="cic-button cic-button--block" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $cic_s['link'] ) : ?>
						<p class="cic-quote-help__link"><?php echo cic_theme_arrow_link( $cic_s['shop'], $cic_s['link'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
					<?php endif; ?>
				</div>
				<?php if ( $cic_s['image'] ) : ?>
					<div class="cic-quote-side__photo"><?php echo cic_theme_image( $cic_s['image'], '(max-width: 899px) 100vw, 360px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
				<?php endif; ?>
			</aside>
		</div>
	</section>

	<?php if ( $cic_vm['next']['items'] ) : ?>
		<section class="cic-quote-next-band">
			<div class="cic-container cic-container--wide">
				<div class="cic-quote-next">
					<h2 class="cic-quote-next__title"><?php echo esc_html( $cic_vm['next']['heading'] ); ?></h2>
					<ol class="cic-quote-next__list">
						<?php foreach ( $cic_vm['next']['items'] as $cic_item ) : ?>
							<li>
								<span class="cic-quote-next__icon"><?php echo cic_theme_icon( $cic_item['icon'] ?: 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
								<span><strong><?php echo esc_html( $cic_item['title'] ); ?></strong><?php echo esc_html( $cic_item['text'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
