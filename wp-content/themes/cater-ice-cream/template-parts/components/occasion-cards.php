<?php
/**
 * Event (occasion) cards linking to the event pages. "row" = photo beside text in two columns
 * (city page); "grid" = vertical cards (Service Areas; four per row, or three per row for five or
 * six events). Events follow the central event order. Whole card is the link target.
 *
 * Args: items (event cards), variant row|grid.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_variant = $args['variant'] ?? 'grid';
?>
<ul class="cic-occasions cic-occasions--<?php echo esc_attr( $cic_variant ); ?> cic-occasions--n<?php echo (int) count( $args['items'] ); ?>">
	<?php foreach ( $args['items'] as $cic_item ) : ?>
		<li class="cic-card cic-occasion" data-cic-event="<?php echo esc_attr( $cic_item['key'] ); ?>">
			<div class="cic-occasion__media"><?php echo cic_theme_image( $cic_item['image'], 'row' === $cic_variant ? '(max-width: 639px) 40vw, 240px' : '(max-width: 639px) 100vw, (max-width: 1099px) 50vw, 280px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<div class="cic-occasion__body">
				<h3 class="cic-occasion__title"><a href="<?php echo esc_url( $cic_item['url'] ); ?>"><?php echo esc_html( $cic_item['title'] ); ?></a></h3>
				<?php if ( '' !== $cic_item['excerpt'] ) : ?>
					<p><?php echo esc_html( $cic_item['excerpt'] ); ?></p>
				<?php endif; ?>
				<p class="cic-occasion__more" aria-hidden="true"><?php echo esc_html( $cic_item['link'] ); ?> →</p>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
