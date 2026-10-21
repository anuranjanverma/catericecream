<?php
/**
 * California map: a simplified local outline (no third-party map service) with approximate
 * points from the reviewed location data, grouped by county. Points are approximate ZIP centres,
 * never service boundaries, radii or customer addresses. The text directory works without it.
 *
 * Interaction (assets/js/map.js, progressive): pins drop in once when the map enters the viewport
 * (not with reduced motion), and each county's pin + label opens a compact preview on hover, focus
 * or tap: county name, the editable line and approved photo from the Service Areas page county slots
 * (text-only when none), and a note that the pin is a reference point, not served territory.
 * Without JavaScript the map is the same static figure as before.
 *
 * Args: points (label, x, y, count, slug, text, image, url), caption, id.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_id     = $args['id'] ?? 'cic-ca-map';
$cic_points = $args['points'] ?? array();
?>
<figure class="cic-ca-map" data-cic-map>
	<svg viewBox="0 0 300 300" role="<?php echo $cic_points ? 'group' : 'img'; ?>" aria-labelledby="<?php echo esc_attr( $cic_id ); ?>-title <?php echo esc_attr( $cic_id ); ?>-desc">
		<title id="<?php echo esc_attr( $cic_id ); ?>-title"><?php esc_html_e( 'Map of California', 'cater-ice-cream' ); ?></title>
		<desc id="<?php echo esc_attr( $cic_id ); ?>-desc">
			<?php
			echo esc_html(
				$cic_points
					/* translators: %s: comma-separated county names. */
					? sprintf( __( 'Approximate service-area points in %s.', 'cater-ice-cream' ), implode( ', ', wp_list_pluck( $cic_points, 'label' ) ) )
					: __( 'Service-area points appear once reviewed location data is connected.', 'cater-ice-cream' )
			);
			?>
		</desc>
		<g class="cic-ca-map__sun" aria-hidden="true">
			<circle cx="236" cy="70" r="13"/>
			<path d="M236 46v8M236 86v8M212 70h8M252 70h8M219 53l6 6M247 81l6 6M219 87l6-6M247 59l6-6"/>
		</g>
		<path class="cic-ca-map__state" d="M11.2 6.0 L128.8 6.0 L128.8 96.0 L280.0 216.0 L277.2 237.0 L282.8 276.0 L277.2 285.0 L210.0 291.0 L204.4 273.0 L184.8 255.0 L170.8 246.0 L151.2 241.5 L137.2 234.0 L112.0 229.5 L112.0 210.0 L92.4 195.0 L75.6 168.0 L61.6 150.0 L58.8 132.0 L44.8 117.0 L25.2 96.0 L22.4 72.0 L5.6 54.0 L14.0 36.0 Z"/>
		<path class="cic-ca-map__waves" aria-hidden="true" d="M40 262c8-6 16-6 24 0s16 6 24 0M52 276c8-6 16-6 24 0s16 6 24 0"/>
		<g class="cic-ca-map__points">
			<?php
			// Labels sit in a staggered row in the ocean below the coast, joined to their pins.
			usort( $cic_points, static fn( $a, $b ) => $a['x'] <=> $b['x'] );
			$cic_step = count( $cic_points ) > 1 ? min( 62, 170 / ( count( $cic_points ) - 1 ) ) : 0;
			?>
			<?php foreach ( $cic_points as $cic_i => $cic_point ) : ?>
				<?php
				$cic_lx = 92 + $cic_i * $cic_step;
				$cic_ly = 266 + ( $cic_i % 2 ) * 14;
				?>
				<?php
				$cic_img = ! empty( $cic_point['image'] ) ? wp_get_attachment_image_src( (int) $cic_point['image'], 'medium_large' ) : false;
				/* translators: %s: county name. */
				$cic_aria = sprintf( __( '%s: show county preview', 'cater-ice-cream' ), $cic_point['label'] );
				?>
				<g class="cic-ca-map__point" style="--i: <?php echo (int) $cic_i; ?>" data-name="<?php echo esc_attr( $cic_point['label'] ); ?>" data-text="<?php echo esc_attr( (string) ( $cic_point['text'] ?? '' ) ); ?>" data-count="<?php echo (int) ( $cic_point['count'] ?? 0 ); ?>" data-url="<?php echo esc_url( (string) ( $cic_point['url'] ?? '' ) ); ?>"<?php echo $cic_img ? ' data-img="' . esc_url( $cic_img[0] ) . '" data-img-w="' . (int) $cic_img[1] . '" data-img-h="' . (int) $cic_img[2] . '"' : ''; ?> aria-label="<?php echo esc_attr( $cic_aria ); ?>">
					<?php // Larger, invisible hit areas: the pin head, and the label extended down into the empty ocean (no visual change). ?>
					<circle class="cic-ca-map__hit" cx="<?php echo esc_attr( (string) $cic_point['x'] ); ?>" cy="<?php echo esc_attr( (string) ( $cic_point['y'] - 11 ) ); ?>" r="11"/>
					<rect class="cic-ca-map__hit" x="<?php echo esc_attr( (string) ( $cic_lx - max( 20, $cic_step / 2 - 2 ) ) ); ?>" y="<?php echo esc_attr( (string) ( $cic_ly - 16 ) ); ?>" width="<?php echo esc_attr( (string) ( 2 * max( 20, $cic_step / 2 - 2 ) ) ); ?>" height="44" rx="6"/>
					<path class="cic-ca-map__leader" d="M<?php echo esc_attr( (string) $cic_point['x'] ); ?> <?php echo esc_attr( (string) ( $cic_point['y'] + 2 ) ); ?>L<?php echo esc_attr( (string) $cic_lx ); ?> <?php echo esc_attr( (string) ( $cic_ly - 9 ) ); ?>"/>
					<path class="cic-ca-map__pin" d="M<?php echo esc_attr( (string) $cic_point['x'] ); ?> <?php echo esc_attr( (string) $cic_point['y'] ); ?>c-5-6-8-9.5-8-13a8 8 0 0 1 16 0c0 3.5-3 7-8 13z"/>
					<circle class="cic-ca-map__pin-dot" cx="<?php echo esc_attr( (string) $cic_point['x'] ); ?>" cy="<?php echo esc_attr( (string) ( $cic_point['y'] - 13 ) ); ?>" r="3"/>
					<text x="<?php echo esc_attr( (string) $cic_lx ); ?>" y="<?php echo esc_attr( (string) $cic_ly ); ?>" text-anchor="middle"><?php echo esc_html( preg_replace( '/\s+County$/', '', $cic_point['label'] ) ); ?></text>
				</g>
			<?php endforeach; ?>
		</g>
	</svg>
	<?php if ( $cic_points ) : ?>
		<div class="cic-ca-map__card" id="<?php echo esc_attr( $cic_id ); ?>-card" role="region" aria-label="<?php esc_attr_e( 'County preview', 'cater-ice-cream' ); ?>" hidden data-note="<?php /* translators: %d: number of ZIP codes. */ echo esc_attr__( 'Approximate county reference point, not a service boundary. %d ZIP codes here are marked as served in our location data; your exact ZIP code is confirmed with your quote.', 'cater-ice-cream' ); ?>" data-note-none="<?php esc_attr_e( 'Approximate county reference point, not a service boundary. Your exact ZIP code is confirmed with your quote.', 'cater-ice-cream' ); ?>" data-link="<?php esc_attr_e( 'See cities in this county', 'cater-ice-cream' ); ?>">
			<button type="button" class="cic-ca-map__close" aria-label="<?php esc_attr_e( 'Close county preview', 'cater-ice-cream' ); ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
			<span class="cic-ca-map__media" hidden></span>
			<p class="cic-ca-map__name"></p>
			<p class="cic-ca-map__text" hidden></p>
			<p class="cic-ca-map__note"></p>
			<a class="cic-text-link cic-ca-map__link" href="#"></a>
		</div>
	<?php endif; ?>
	<?php if ( '' !== ( $args['caption'] ?? '' ) ) : ?>
		<figcaption class="cic-ca-map__caption"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $args['caption'] ); ?></figcaption>
	<?php endif; ?>
</figure>
