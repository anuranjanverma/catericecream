<?php
/**
 * About (approved reference designs/14_about.png; mobile column of 20_mobile_supporting.png).
 * Content from the "About composition" fields; business name, location, phone and email from the
 * global contact settings. Verified facts only; the initials graphic stands in until a real
 * portrait is supplied (DESIGN_REFERENCE_INDEX).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
$cic_vm      = \CIC\Content\ViewModel::about( (int) get_queried_object_id() );
$cic_contact = cic_theme_contact();
$cic_h       = $cic_vm['hero'];
$cic_s       = $cic_vm['story'];
$cic_m       = $cic_vm['meet'];
$cic_a       = $cic_vm['areas'];
?>
<div class="cic-pages cic-about-page">
	<section class="cic-pg-hero cic-pg-hero--about">
		<div class="cic-container cic-container--wide cic-pg-hero__grid">
			<div class="cic-pg-hero__text">
				<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( '' !== $cic_h['eyebrow'] ) : ?>
					<p class="cic-eyebrow cic-eyebrow--soft"><?php echo esc_html( $cic_h['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h1 class="cic-pg-hero__title"><?php echo $cic_h['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
				<?php if ( '' !== $cic_h['intro'] ) : ?>
					<p class="cic-lead"><?php echo esc_html( $cic_h['intro'] ); ?></p>
				<?php endif; ?>
			</div>
			<div class="cic-pg-hero__media cic-pg-hero__media--inset">
				<?php if ( $cic_h['images'][0] ) : ?>
					<div class="cic-pg-hero__main"><?php echo cic_theme_image( $cic_h['images'][0], '(max-width: 899px) 100vw, 50vw', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
				<?php endif; ?>
				<?php if ( $cic_h['images'][2] ) : ?>
					<div class="cic-pg-hero__inset"><?php echo cic_theme_image( $cic_h['images'][2], '(max-width: 899px) 45vw, 240px', 'eager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
				<?php endif; ?>
				<?php echo cic_theme_motif( 'strokes', 'cic-pg-hero__strokes' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?>
			</div>
		</div>
	</section>

	<?php if ( '' !== $cic_s['heading'] ) : ?>
		<section class="cic-section cic-pg-section--tight">
			<div class="cic-container cic-container--wide cic-about-story">
				<?php if ( $cic_s['image'] ) : ?>
					<div class="cic-about-story__media"><?php echo cic_theme_image( $cic_s['image'], '(max-width: 899px) 100vw, 50vw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
				<?php endif; ?>
				<div class="cic-about-story__body">
					<h2><?php echo $cic_s['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
					<?php foreach ( $cic_s['paragraphs'] as $cic_para ) : ?>
						<p><?php echo esc_html( $cic_para ); ?></p>
					<?php endforeach; ?>
					<?php if ( '' !== $cic_s['quote'] ) : ?>
						<blockquote class="cic-about-quote"><p><?php echo $cic_s['quote']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></p><span class="cic-about-quote__mascot"><?php echo cic_theme_mascot( 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span></blockquote>
					<?php endif; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $cic_vm['brands']['items'] ) : ?>
		<section class="cic-section cic-pg-section--tight">
			<div class="cic-container cic-container--wide cic-about-brands">
				<h2 class="cic-center"><?php echo $cic_vm['brands']['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<ul class="cic-about-brands__list">
					<?php foreach ( $cic_vm['brands']['items'] as $cic_item ) : ?>
						<li class="cic-card cic-about-brand">
							<div class="cic-about-brand__media"><?php echo cic_theme_image( $cic_item['image'], '(max-width: 767px) 45vw, 240px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
							<div class="cic-about-brand__body">
								<h3><a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?></a></h3>
								<?php if ( '' !== $cic_item['tagline'] ) : ?>
									<p><?php echo esc_html( $cic_item['tagline'] ); ?></p>
								<?php endif; ?>
								<p class="cic-about-brand__more" aria-hidden="true"><?php echo esc_html( $cic_item['link'] ); ?> →</p>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="cic-about-brands__line"><span class="cic-about-brands__icons" aria-hidden="true"><?php echo cic_theme_icon( 'cone' ) . cic_theme_icon( 'cup' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><span><?php echo esc_html( $cic_vm['brands']['line'] ); ?></span><?php echo cic_theme_arrow_link( $cic_vm['brands']['url'], $cic_vm['brands']['link'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $cic_vm['approach']['items'] ) : ?>
		<section class="cic-section cic-pg-section--tight">
			<div class="cic-container cic-container--wide">
				<h2 class="cic-center"><?php echo $cic_vm['approach']['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
				<ul class="cic-ev-values__list cic-about-approach">
					<?php foreach ( $cic_vm['approach']['items'] as $cic_item ) : ?>
						<li class="cic-ev-value">
							<span class="cic-ev-value__icon"><?php echo cic_theme_icon( '' !== $cic_item['icon'] ? $cic_item['icon'] : 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span class="cic-ev-value__text"><strong class="cic-ev-value__title"><?php echo esc_html( $cic_item['title'] ); ?></strong><span><?php echo esc_html( $cic_item['text'] ); ?></span></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $cic_m['heading'] ) : ?>
		<section class="cic-section cic-pg-section--tight">
			<div class="cic-container cic-container--wide">
				<div class="cic-meet">
					<div class="cic-meet__id">
						<?php if ( $cic_m['portrait'] ) : ?>
							<div class="cic-meet__portrait"><?php echo cic_theme_image( $cic_m['portrait'], '220px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
						<?php else : ?>
							<span class="cic-meet__initials" aria-hidden="true"><?php echo esc_html( $cic_m['initials'] ); ?></span>
							<span class="cic-meet__mascot"><?php echo cic_theme_mascot( 88 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
						<?php endif; ?>
					</div>
					<div class="cic-meet__body">
						<h2 class="cic-meet__title"><?php echo $cic_m['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
						<?php if ( '' !== $cic_m['byline'] ) : ?>
							<p class="cic-meet__byline"><?php echo esc_html( $cic_m['byline'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $cic_m['text'] ) : ?>
							<p><?php echo esc_html( $cic_m['text'] ); ?></p>
						<?php endif; ?>
						<div class="cic-meet__actions">
							<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
								<a class="cic-button cic-meet__call" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
							<?php endif; ?>
							<?php if ( '' !== $cic_contact['email'] ) : ?>
								<a class="cic-meet__email" href="<?php echo esc_url( 'mailto:' . $cic_contact['email'] ); ?>"><?php echo cic_theme_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['email'] ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( $cic_m['image'] ) : ?>
						<div class="cic-meet__photo"><?php echo cic_theme_image( $cic_m['image'], '(max-width: 899px) 100vw, 320px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
					<?php endif; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $cic_a['heading'] ) : ?>
		<section class="cic-section cic-pg-section--tight">
			<div class="cic-container cic-container--wide cic-about-areas">
				<div class="cic-about-areas__text">
					<h2><?php echo $cic_a['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
					<?php if ( '' !== $cic_a['intro'] ) : ?>
						<p><?php echo esc_html( $cic_a['intro'] ); ?></p>
					<?php endif; ?>
					<ul class="cic-county-tiles">
						<?php foreach ( $cic_a['counties'] as $cic_i => $cic_county ) : ?>
							<li><a href="<?php echo esc_url( $cic_a['url'] . '#county-' . $cic_county['slug'] ); ?>"><?php echo cic_theme_motif( array( 'skyline', 'coast', 'mission' )[ $cic_i % 3 ], 'cic-county-tiles__art' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static decorative SVG. ?><span><?php echo esc_html( $cic_county['name'] ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="cic-about-areas__map">
					<?php get_template_part( 'template-parts/components/ca-map', null, array( 'points' => $cic_a['points'], 'id' => 'cic-about-map' ) ); ?>
					<div class="cic-about-areas__note">
						<?php if ( '' !== $cic_a['note_title'] ) : ?>
							<strong><?php echo esc_html( $cic_a['note_title'] ); ?></strong>
						<?php endif; ?>
						<p><?php echo esc_html( $cic_a['note'] ); ?></p>
						<?php echo cic_theme_arrow_link( $cic_a['url'], $cic_a['link'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $cic_vm['cta']['heading'] ) : ?>
		<section class="cic-section cic-pg-section--tight">
			<div class="cic-container cic-container--wide">
				<div class="cic-cta-band">
					<span class="cic-cta-band__icon" aria-hidden="true"><?php echo cic_theme_icon( 'cone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<h2 class="cic-cta-band__title"><?php echo $cic_vm['cta']['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
					<div class="cic-cta-band__actions">
						<a class="cic-button cic-button--inverse" href="<?php echo esc_url( $cic_contact['quote_url'] ); ?>"><?php echo esc_html( $cic_contact['quote_label'] ); ?> <span aria-hidden="true">→</span></a>
						<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
							<a class="cic-button cic-button--secondary cic-cta-band__call" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo cic_theme_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
