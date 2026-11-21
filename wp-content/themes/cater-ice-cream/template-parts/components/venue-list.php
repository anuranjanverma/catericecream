<?php
/**
 * "Popular Event Venues" (2026-11-21): a plain list of venue names, shared by city pages (that city's
 * venues, max 12) and event pages (the occasion's venues across served cities, max 32). Names only:
 * no links, addresses, ratings or partnership claims. Renders nothing without names.
 * Grid: 2 per row on mobile, 3 on tablet, 4 on desktop (compositions.css "Popular Event Venues").
 *
 * Args: prefix (section class prefix), section (key + preset), venues (heading + items).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_venues = $args['venues'] ?? array();
if ( empty( $cic_venues['items'] ) ) {
	return;
}
$cic_title_id = $args['section']['key'] . '-title';
echo cic_theme_section_open( $args['prefix'], $args['section'], 'cic-section cic-venues' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<h2 class="cic-venues__title" id="<?php echo esc_attr( $cic_title_id ); ?>"><?php echo esc_html( $cic_venues['heading'] ); ?></h2>
		<ul class="cic-venues__list" role="list" aria-labelledby="<?php echo esc_attr( $cic_title_id ); ?>">
			<?php foreach ( $cic_venues['items'] as $cic_venue ) : ?>
				<li class="cic-venues__item"><?php echo esc_html( $cic_venue ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
