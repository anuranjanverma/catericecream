<?php
/**
 * Contact (approved reference designs/16_contact.png; mobile column of 20_mobile_supporting.png).
 * Phone, email and location come from the global contact settings. The message panel holds the
 * shared CF7 Contact form (saved in Flamingo before any notification, same foundation as the quote
 * form). If Contact Form 7 or Flamingo is unavailable it shows the call and email routes instead.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
$cic_vm      = \CIC\Content\ViewModel::contact( (int) get_queried_object_id() );
$cic_contact = cic_theme_contact();
$cic_h       = $cic_vm['hero'];
$cic_p       = $cic_vm['plan'];
$cic_w       = $cic_vm['where'];
$cic_c       = $cic_vm['close'];
$cic_steps   = array(
	array( __( 'Your event', 'cater-ice-cream' ), __( 'Tell us the details of your occasion.', 'cater-ice-cream' ) ),
	array( __( 'Your treats', 'cater-ice-cream' ), __( 'Choose your favourites.', 'cater-ice-cream' ) ),
	array( __( 'Review & send', 'cater-ice-cream' ), __( "We'll confirm the details.", 'cater-ice-cream' ) ),
);
?>
<div class="cic-pages cic-contact-page">
	<section class="cic-pg-hero cic-pg-hero--contact">
		<div class="cic-container cic-container--wide cic-pg-hero__grid">
			<div class="cic-pg-hero__text">
				<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<h1 class="cic-pg-hero__title"><?php echo $cic_h['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
				<?php if ( '' !== $cic_h['intro'] ) : ?>
					<p class="cic-lead"><?php echo esc_html( $cic_h['intro'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $cic_contact['location'] ) : ?>
					<p class="cic-contact-location"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['location'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $cic_h['image'] ) : ?>
				<div class="cic-pg-hero__media cic-pg-hero__media--soft"><?php echo cic_theme_image( $cic_h['image'], '(max-width: 899px) 100vw, 50vw', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
		</div>
		<div class="cic-container cic-container--wide cic-contact-cards">
			<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
				<div class="cic-contact-card">
					<span class="cic-contact-card__icon"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<div>
						<h2 class="cic-contact-card__title"><?php echo esc_html( $cic_h['call_title'] ); ?></h2>
						<p class="cic-contact-card__value"><?php echo esc_html( $cic_contact['phone'] ); ?></p>
						<a class="cic-button" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php esc_html_e( 'Call now', 'cater-ice-cream' ); ?> <span aria-hidden="true">→</span><span class="screen-reader-text"> <?php echo esc_html( $cic_contact['phone'] ); ?></span></a>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $cic_contact['email'] ) : ?>
				<div class="cic-contact-card">
					<span class="cic-contact-card__icon"><?php echo cic_theme_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<div>
						<h2 class="cic-contact-card__title"><?php echo esc_html( $cic_h['email_title'] ); ?></h2>
						<p class="cic-contact-card__value cic-contact-card__value--email"><?php echo esc_html( $cic_contact['email'] ); ?></p>
						<a class="cic-button cic-button--secondary" href="<?php echo esc_url( 'mailto:' . $cic_contact['email'] ); ?>"><?php esc_html_e( 'Email us', 'cater-ice-cream' ); ?> <span aria-hidden="true">→</span></a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="cic-section cic-pg-section--tight">
		<div class="cic-container cic-container--wide cic-contact-grid">
			<div class="cic-card cic-contact-help" id="message">
				<h2><?php echo $cic_vm['help']['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<?php if ( '' !== $cic_vm['help']['intro'] ) : ?>
					<p><?php echo esc_html( $cic_vm['help']['intro'] ); ?> <a href="<?php echo esc_url( $cic_contact['quote_url'] ); ?>"><?php esc_html_e( 'Open the quote form', 'cater-ice-cream' ); ?></a></p>
				<?php endif; ?>
				<?php
				get_template_part(
					'template-parts/components/enquiry-received',
					null,
					array(
						'type'    => 'contact',
						'receipt' => $cic_vm['success']['receipt'],
						'success' => $cic_vm['success'],
					)
				);
				?>
				<div class="cic-contact-form" data-cic-form-area<?php echo $cic_vm['success']['receipt'] ? ' hidden' : ''; ?>>
				<?php if ( '' !== $cic_vm['form'] ) : ?>
					<?php echo $cic_vm['form']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Contact Form 7 output. ?>
				<?php else : ?>
				<div class="cic-contact-pending" role="note">
					<span class="cic-contact-pending__icon"><?php echo cic_theme_icon( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<p><?php echo esc_html( $cic_vm['help']['pending'] ); ?></p>
				</div>
				<div class="cic-contact-help__actions">
					<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
						<a class="cic-button" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $cic_contact['email'] ) : ?>
						<a class="cic-button cic-button--secondary" href="<?php echo esc_url( 'mailto:' . $cic_contact['email'] ); ?>"><?php echo cic_theme_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['email'] ); ?></a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
				</div>
			</div>
			<div class="cic-contact-side">
				<div class="cic-contact-plan">
					<h2><?php echo $cic_p['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
					<?php if ( '' !== $cic_p['text'] ) : ?>
						<p><?php echo esc_html( $cic_p['text'] ); ?></p>
					<?php endif; ?>
					<?php if ( $cic_p['images'] ) : ?>
						<div class="cic-contact-plan__photos cic-contact-plan__photos--<?php echo (int) count( $cic_p['images'] ); ?>">
							<?php foreach ( $cic_p['images'] as $cic_img ) : ?>
								<div class="cic-contact-plan__photo"><?php echo cic_theme_image( $cic_img, '(max-width: 899px) 50vw, 240px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<a class="cic-button cic-button--block" href="<?php echo esc_url( $cic_contact['quote_url'] ); ?>"><?php echo esc_html( $cic_p['cta'] ); ?> <span aria-hidden="true">→</span></a>
					<ol class="cic-contact-steps">
						<?php foreach ( $cic_steps as $cic_i => $cic_step ) : ?>
							<li><span class="cic-contact-steps__num" aria-hidden="true"><?php echo (int) $cic_i + 1; ?></span><span><strong><?php echo esc_html( $cic_step[0] ); ?></strong><?php echo esc_html( $cic_step[1] ); ?></span></li>
						<?php endforeach; ?>
					</ol>
					<?php if ( '' !== $cic_p['link'] ) : ?>
						<p class="cic-contact-plan__link"><?php echo cic_theme_icon( 'sundae' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo cic_theme_arrow_link( $cic_p['shop'], $cic_p['link'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
					<?php endif; ?>
				</div>
				<div class="cic-contact-where">
					<div>
						<h2 class="cic-contact-where__title"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo $cic_w['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
						<p><?php echo esc_html( $cic_w['text'] ); ?></p>
						<a class="cic-button cic-button--secondary" href="<?php echo esc_url( $cic_w['url'] ); ?>"><?php echo esc_html( $cic_w['link'] ); ?> <span aria-hidden="true">→</span></a>
					</div>
					<?php get_template_part( 'template-parts/components/ca-map', null, array( 'points' => $cic_w['points'], 'id' => 'cic-contact-map' ) ); ?>
				</div>
			</div>
		</div>
	</section>

	<?php if ( $cic_vm['faq']['items'] ) : ?>
		<section class="cic-section cic-pg-section--tight">
			<div class="cic-container cic-container--wide">
				<h2><?php echo $cic_vm['faq']['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<?php get_template_part( 'template-parts/event/faq-list', null, array( 'items' => $cic_vm['faq']['items'] ) ); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $cic_c['heading'] ) : ?>
		<section class="cic-contact-close">
			<div class="cic-container cic-container--wide cic-contact-close__grid">
				<div>
					<h2 class="cic-contact-close__title"><?php echo $cic_c['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
					<?php if ( '' !== $cic_c['text'] ) : ?>
						<p><?php echo esc_html( $cic_c['text'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
					<div class="cic-contact-close__item">
						<span class="cic-contact-card__icon"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<div><strong><?php echo esc_html( $cic_h['call_title'] ); ?></strong><span><?php echo esc_html( $cic_contact['phone'] ); ?></span><a class="cic-button" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php esc_html_e( 'Call now', 'cater-ice-cream' ); ?> <span aria-hidden="true">→</span></a></div>
					</div>
				<?php endif; ?>
				<div class="cic-contact-close__item">
					<span class="cic-contact-card__icon"><?php echo cic_theme_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<div><strong><?php echo esc_html( $cic_c['quote_title'] ); ?></strong><span><?php echo esc_html( $cic_c['quote_text'] ); ?></span><a class="cic-button" href="<?php echo esc_url( $cic_contact['quote_url'] ); ?>"><?php echo esc_html( $cic_contact['quote_label'] ); ?> <span aria-hidden="true">→</span></a></div>
				</div>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
