<?php
/**
 * Our Treats catalogue (approved reference designs/08_treats_catalogue.png). The shop page holds
 * the editable composition ("Our Treats composition"); rows come from the live product query
 * (published, active treats in menu order), so a newly published treat appears automatically.
 * Each row: product image, badge, name, subtitle, long description, two points, Explore (detail
 * page) and "Ask about this treat" (quote route with the treat selected).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
$cic_vm      = \CIC\Content\ViewModel::catalogue( $cic_shop_id );
$cic_h       = $cic_vm['hero'];
?>
<div class="cic-pages cic-catalogue-page">
	<section class="cic-pg-hero cic-pg-hero--treats">
		<div class="cic-container cic-container--wide cic-pg-hero__grid">
			<div class="cic-pg-hero__text">
				<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<?php if ( '' !== $cic_h['eyebrow'] ) : ?>
					<p class="cic-eyebrow cic-eyebrow--accent"><?php echo esc_html( $cic_h['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h1 class="cic-pg-hero__title"><?php echo $cic_h['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
				<?php if ( '' !== $cic_h['intro'] ) : ?>
					<p class="cic-lead"><?php echo esc_html( $cic_h['intro'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $cic_h['image'] ) : ?>
				<div class="cic-pg-hero__media"><?php echo cic_theme_image( $cic_h['image'], '(max-width: 899px) 100vw, 50vw', 'lcp' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $cic_vm['rows'] ) : ?>
		<nav class="cic-jump" aria-label="<?php esc_attr_e( 'Treats on this page', 'cater-ice-cream' ); ?>">
			<div class="cic-container cic-jump__inner">
				<span class="cic-jump__label"><?php echo esc_html( $cic_vm['jump'] ); ?></span>
				<ul>
					<?php foreach ( $cic_vm['rows'] as $cic_row ) : ?>
						<li><a href="#<?php echo esc_attr( $cic_row['anchor'] ); ?>"><?php echo esc_html( $cic_row['title'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</nav>

		<div class="cic-cat-rows">
			<?php foreach ( $cic_vm['rows'] as $cic_i => $cic_row ) : ?>
				<section class="cic-cat-row cic-cat-row--<?php echo 0 === $cic_i % 2 ? 'img-left' : 'img-right'; ?> cic-ctx cic-context-<?php echo esc_attr( $cic_row['context'] ); ?>" id="<?php echo esc_attr( $cic_row['anchor'] ); ?>" data-cic-product="<?php echo esc_attr( $cic_row['key'] ); ?>" aria-labelledby="<?php echo esc_attr( $cic_row['anchor'] ); ?>-title">
					<?php if ( $cic_row['image'] ) : ?>
						<div class="cic-cat-row__media"><?php echo cic_theme_image( $cic_row['image'], '(max-width: 899px) 100vw, 50vw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
					<?php endif; ?>
					<div class="cic-cat-row__body">
						<?php if ( '' !== $cic_row['badge'] ) : ?>
							<p class="cic-badge-pill"><?php echo esc_html( $cic_row['badge'] ); ?></p>
						<?php endif; ?>
						<h2 class="cic-cat-row__title" id="<?php echo esc_attr( $cic_row['anchor'] ); ?>-title"><?php echo esc_html( $cic_row['title'] ); ?></h2>
						<?php if ( '' !== $cic_row['subtitle'] ) : ?>
							<p class="cic-cat-row__subtitle"><?php echo esc_html( $cic_row['subtitle'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $cic_row['text'] ) : ?>
							<p class="cic-cat-row__text"><?php echo esc_html( $cic_row['text'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $cic_row['experience'] || '' !== $cic_row['discuss'] ) : ?>
							<ul class="cic-cat-row__points">
								<?php if ( '' !== $cic_row['experience'] ) : ?>
									<li><span class="cic-icon-badge"><?php echo cic_theme_icon( 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><span><strong><?php esc_html_e( 'The experience', 'cater-ice-cream' ); ?></strong><?php echo esc_html( $cic_row['experience'] ); ?></span></li>
								<?php endif; ?>
								<?php if ( '' !== $cic_row['discuss'] ) : ?>
									<li><span class="cic-icon-badge"><?php echo cic_theme_icon( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><span><strong><?php esc_html_e( "Let's discuss", 'cater-ice-cream' ); ?></strong><?php echo esc_html( $cic_row['discuss'] ); ?></span></li>
								<?php endif; ?>
							</ul>
						<?php endif; ?>
						<div class="cic-hero__actions">
							<?php echo cic_theme_arrow_link( $cic_row['url'], $cic_row['cta'], 'cic-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
							<a class="cic-text-link" href="<?php echo esc_url( $cic_row['quote_url'] ); ?>"><?php esc_html_e( 'Ask about this treat', 'cater-ice-cream' ); ?><span class="screen-reader-text"> (<?php echo esc_html( $cic_row['title'] ); ?>)</span></a>
						</div>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php if ( '' !== $cic_vm['note'] ) : ?>
			<p class="cic-container cic-caption cic-cat-note"><?php echo esc_html( $cic_vm['note'] ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p class="cic-container"><?php esc_html_e( 'Treats will appear here soon.', 'cater-ice-cream' ); ?></p>
	<?php endif; ?>

	<?php if ( '' !== $cic_vm['help']['heading'] ) : ?>
		<section class="cic-section cic-pg-section--tight">
			<div class="cic-container cic-container--wide">
				<div class="cic-help-panel">
					<div class="cic-help-panel__intro">
						<h2 class="cic-help-panel__title"><?php echo $cic_vm['help']['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
						<?php if ( '' !== $cic_vm['help']['text'] ) : ?>
							<p><?php echo esc_html( $cic_vm['help']['text'] ); ?></p>
						<?php endif; ?>
					</div>
					<ul class="cic-help-panel__items">
						<?php foreach ( $cic_vm['help']['items'] as $cic_item ) : ?>
							<li><span class="cic-help-panel__icon"><?php echo cic_theme_icon( '' !== $cic_item['icon'] ? $cic_item['icon'] : 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><strong><?php echo esc_html( $cic_item['title'] ); ?></strong><span><?php echo esc_html( $cic_item['text'] ); ?></span></li>
						<?php endforeach; ?>
					</ul>
					<?php echo cic_theme_arrow_link( $cic_vm['help']['url'], $cic_vm['help']['cta'], 'cic-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="cic-section cic-pg-start">
		<div class="cic-container cic-container--wide">
			<?php if ( '' !== $cic_vm['start']['heading'] ) : ?>
				<h2 class="cic-pg-start__title"><?php echo $cic_vm['start']['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $cic_vm['start']['subheading'] ) : ?>
				<p class="cic-pg-start__sub"><?php echo esc_html( $cic_vm['start']['subheading'] ); ?></p>
			<?php endif; ?>
			<?php
			get_template_part(
				'template-parts/components/quote-starter',
				null,
				array(
					'id'     => 'cic-treats-quote',
					'fields' => array( 'location', 'event', 'date' ),
					'labels' => array( 'location' => __( 'Location', 'cater-ice-cream' ), 'date' => __( 'Date', 'cater-ice-cream' ) ),
					'events' => $cic_vm['start']['events'],
					'cta'    => $cic_vm['start']['cta'],
					'note'   => $cic_vm['start']['note'],
					'class'  => 'cic-qs--row cic-qs--inline',
				)
			);
			?>
		</div>
	</section>
</div>
