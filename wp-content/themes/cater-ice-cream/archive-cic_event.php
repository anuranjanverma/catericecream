<?php
/**
 * Events overview (/events/, approved reference designs/03_events_overview.png). The text is
 * edited at Events → Overview page; occasion cards come from the published events (menu order)
 * and the treats from published treats, so new records appear automatically. Explore opens the
 * event page; quote actions open the dedicated quote route with the selection carried.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = class_exists( '\CIC\Content\ViewModel' ) ? \CIC\Content\ViewModel::events_overview() : null;
get_header();
if ( null === $cic_vm ) {
	get_template_part( 'index' );
	get_footer();
	return;
}
$cic_h = $cic_vm['hero'];
$cic_c = $cic_vm['cards'];
$cic_t = $cic_vm['treats'];
?>
<div class="cic-pages cic-events-page">
	<section class="cic-pg-hero cic-pg-hero--events">
		<div class="cic-container cic-container--wide cic-pg-hero__grid">
			<div class="cic-pg-hero__text">
				<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( post_type_archive_title( '', false ), '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( '' !== $cic_h['eyebrow'] ) : ?>
					<p class="cic-eyebrow cic-pg-eyebrow"><span class="cic-swoosh" aria-hidden="true"></span><?php echo esc_html( $cic_h['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h1 class="cic-pg-hero__title"><?php echo $cic_h['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
				<?php if ( '' !== $cic_h['intro'] ) : ?>
					<p class="cic-lead"><?php echo esc_html( $cic_h['intro'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $cic_h['image'] ) : ?>
				<div class="cic-pg-hero__media cic-pg-hero__media--soft"><?php echo cic_theme_image( $cic_h['image'], '(max-width: 899px) 100vw, 50vw', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
		</div>
	</section>

	<section class="cic-section cic-pg-section--tight" id="occasions">
		<div class="cic-container cic-container--wide">
			<h2 class="cic-center"><span class="cic-swoosh" aria-hidden="true"></span><?php echo $cic_c['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php if ( '' !== $cic_c['intro'] ) : ?>
				<p class="cic-center cic-section-intro"><?php echo esc_html( $cic_c['intro'] ); ?></p>
			<?php endif; ?>
			<ul class="cic-ov-cards">
				<?php foreach ( $cic_c['items'] as $cic_item ) : ?>
					<li class="cic-card cic-ov-card" data-cic-event="<?php echo esc_attr( $cic_item['key'] ); ?>">
						<div class="cic-ov-card__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 767px) 100vw, 50vw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
						<div class="cic-ov-card__body">
							<div>
								<h3 class="cic-ov-card__title"><?php echo esc_html( $cic_item['title'] ); ?></h3>
								<?php if ( '' !== $cic_item['excerpt'] ) : ?>
									<p><?php echo esc_html( $cic_item['excerpt'] ); ?></p>
								<?php endif; ?>
							</div>
							<?php echo cic_theme_arrow_link( $cic_item['url'], $cic_item['link'], 'cic-button cic-ov-card__cta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( '' !== $cic_c['other_link'] ) : ?>
				<p class="cic-center cic-ov-other"><?php echo esc_html( $cic_c['other_text'] ); ?> <?php echo cic_theme_arrow_link( $cic_c['other_url'], $cic_c['other_link'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $cic_t['large'] ) : ?>
		<section class="cic-section cic-pg-section--tight cic-brands">
			<div class="cic-container cic-container--wide">
				<h2 class="cic-center"><?php echo $cic_t['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<?php if ( '' !== $cic_t['intro'] ) : ?>
					<p class="cic-center cic-section-intro"><?php echo esc_html( $cic_t['intro'] ); ?></p>
				<?php endif; ?>
				<ul class="cic-brand-pair">
					<?php foreach ( $cic_t['large'] as $cic_item ) : ?>
						<li class="cic-brand-pair__card" data-cic-product="<?php echo esc_attr( $cic_item['key'] ); ?>">
							<div class="cic-brand-pair__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 599px) 45vw, 300px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
							<div class="cic-brand-pair__body">
								<h3 class="cic-brand-pair__name"><a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?></a></h3>
								<p class="cic-brand-pair__more" aria-hidden="true"><?php esc_html_e( 'Explore', 'cater-ice-cream' ); ?> →</p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="cic-brand-links">
					<?php foreach ( $cic_t['small'] as $cic_i => $cic_item ) : ?>
						<?php echo 0 < $cic_i ? '<span aria-hidden="true">·</span>' : ''; ?><a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?></a>
					<?php endforeach; ?>
					<span class="cic-brand-links__rule" aria-hidden="true"></span>
					<a class="cic-brand-links__all" href="<?php echo esc_url( $cic_t['more_url'] ); ?>"><?php esc_html_e( 'View all treats', 'cater-ice-cream' ); ?> <span aria-hidden="true">→</span></a>
				</p>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $cic_vm['steps']['items'] ) : ?>
		<section class="cic-section cic-plan-band">
			<div class="cic-container cic-container--wide">
				<h2 class="cic-center cic-plan-band__title"><span class="cic-swoosh" aria-hidden="true"></span><?php echo $cic_vm['steps']['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<ol class="cic-plan-band__steps">
					<?php foreach ( $cic_vm['steps']['items'] as $cic_i => $cic_step ) : ?>
						<li class="cic-plan-band__step">
							<span class="cic-plan-band__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $cic_i + 1 ) ); ?></span>
							<span class="cic-plan-band__text"><strong><?php echo esc_html( $cic_step['title'] ); ?></strong><span><?php echo esc_html( $cic_step['text'] ); ?></span></span>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>
	<?php endif; ?>

	<section class="cic-section cic-pg-start cic-pg-start--center">
		<div class="cic-container cic-container--wide">
			<h2 class="cic-center cic-pg-start__title"><?php echo $cic_vm['start']['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php
			get_template_part(
				'template-parts/components/quote-starter',
				null,
				array(
					'id'     => 'cic-events-quote',
					'fields' => array( 'location', 'event', 'date' ),
					'labels' => array( 'location' => __( 'Location', 'cater-ice-cream' ), 'date' => __( 'Date', 'cater-ice-cream' ) ),
					'events' => $cic_vm['start']['events'],
					'cta'    => $cic_vm['start']['cta'],
					'note'   => $cic_vm['start']['note'],
					'call'   => true,
					'class'  => 'cic-qs--row cic-qs--inline',
				)
			);
			?>
		</div>
	</section>
</div>
<?php
get_footer();
