<?php
/**
 * Treat "Photos and videos" (2026-10-24): authentic photos and short videos with factual captions.
 * Videos are click-to-play only (controls, playsinline, preload="none", no autoplay or muted loop)
 * with a poster taken from the same footage; width/height and aspect-ratio reserve the space. The
 * first item is the largest; low-resolution clips stay small. Fields: Treat page composition →
 * Photos and videos.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_m = $args['vm']['media'];

/*
 * A row that mixes photos and videos shares one height (owner, 2026-11-12): the tallest height
 * every item can reach at its normal maximum width (the widths below mirror compositions.css;
 * low-resolution clips stay capped at 1.5× their own size), so nothing is enlarged. Each item then
 * gets the width that gives it that height (--cic-media-row-w, used from 600 px where they sit side
 * by side). Rows of one media type keep their approved sizes (first item largest).
 */
$cic_row_h = 0.0;
if ( count( array_unique( array_map( static fn( $i ) => $i['video'] ? 'video' : 'photo', $cic_m['items'] ) ) ) > 1 ) {
	foreach ( $cic_m['items'] as $cic_i => $cic_item ) {
		if ( (int) $cic_item['width'] <= 0 || (int) $cic_item['height'] <= 0 ) {
			$cic_row_h = 0.0;
			break;
		}
		$cic_max_w = $cic_item['lowres'] ? round( $cic_item['width'] * 1.5 ) : ( 0 === $cic_i ? ( $cic_item['video'] ? 360 : 420 ) : 280 );
		$cic_h     = $cic_max_w * (int) $cic_item['height'] / (int) $cic_item['width'];
		$cic_row_h = 0.0 === $cic_row_h ? $cic_h : min( $cic_row_h, $cic_h );
	}
}
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section cic-media' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container">
		<?php if ( '' !== $cic_m['heading'] ) : ?>
			<h2 class="cic-center"><?php echo $cic_m['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $cic_m['intro'] ) : ?>
			<p class="cic-section-intro cic-center"><?php echo esc_html( $cic_m['intro'] ); ?></p>
		<?php endif; ?>
		<ul class="cic-media-grid<?php echo $cic_row_h > 0 ? ' cic-media-grid--equal' : ''; ?>" role="list">
			<?php foreach ( $cic_m['items'] as $cic_i => $cic_item ) : ?>
				<?php
				$cic_cap   = 'cic-media-cap-' . (int) $args['vm']['id'] . '-' . ( $cic_i + 1 );
				$cic_class = 'cic-media-item' . ( 0 === $cic_i ? ' cic-media-item--lead' : '' ) . ( $cic_item['video'] ? ' cic-media-item--video' : '' ) . ( $cic_item['lowres'] ? ' cic-media-item--lowres' : '' );
				// Low-resolution clips: at most 1.5× their own size, so they are never blown up.
				$cic_style = '--cic-media-ratio:' . (int) $cic_item['width'] . ' / ' . (int) $cic_item['height'] . ( $cic_item['lowres'] ? ';--cic-media-max:' . (int) round( $cic_item['width'] * 1.5 ) . 'px' : '' ) . ( $cic_row_h > 0 ? ';--cic-media-row-w:' . (int) round( $cic_row_h * (int) $cic_item['width'] / (int) $cic_item['height'] ) . 'px' : '' );
				?>
				<li class="<?php echo esc_attr( $cic_class ); ?>" style="<?php echo esc_attr( $cic_style ); ?>">
					<figure class="cic-media-item__figure">
						<div class="cic-media-item__frame">
							<?php if ( $cic_item['video'] ) : ?>
								<video class="cic-media-item__video" controls playsinline preload="none" width="<?php echo (int) $cic_item['width']; ?>" height="<?php echo (int) $cic_item['height']; ?>"<?php echo '' !== $cic_item['poster'] ? ' poster="' . esc_url( $cic_item['poster'] ) . '"' : ''; ?><?php echo '' !== $cic_item['caption'] ? ' aria-labelledby="' . esc_attr( $cic_cap ) . '"' : ''; ?> data-cic-media-video>
									<source src="<?php echo esc_url( $cic_item['src'] ); ?>" type="<?php echo esc_attr( $cic_item['mime'] ); ?>">
									<a href="<?php echo esc_url( $cic_item['src'] ); ?>"><?php esc_html_e( 'Download the video', 'cater-ice-cream' ); ?></a>
								</video>
							<?php else : ?>
								<?php echo cic_theme_image( $cic_item['image'], 0 === $cic_i ? '(max-width: 599px) 100vw, 420px' : '(max-width: 599px) 100vw, 320px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $cic_item['caption'] ) : ?>
							<figcaption class="cic-caption cic-media-item__caption" id="<?php echo esc_attr( $cic_cap ); ?>"><?php echo esc_html( $cic_item['caption'] ); ?></figcaption>
						<?php endif; ?>
					</figure>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
