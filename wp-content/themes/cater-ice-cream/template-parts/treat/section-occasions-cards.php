<?php
/**
 * Frozen Yogurt occasions (12): four vertical cards, photo on top with an occasion icon badge,
 * event eyebrow, headline and explanation; the whole card links to the event page.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_o     = $args['vm']['occasions'];
$cic_icons = array( 'birthday' => 'cake', 'wedding' => 'rings', 'corporate' => 'briefcase', 'anniversary' => 'heart' );
echo cic_theme_section_open( 'cic-treat', $args['section'], 'cic-section cic-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--wide">
		<h2 class="cic-center"><?php echo $cic_o['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<ul class="cic-occ-cards">
			<?php foreach ( $cic_o['items'] as $cic_item ) : ?>
				<li class="cic-card cic-occ-card" data-cic-pairing-event="<?php echo esc_attr( (string) $cic_item['event_id'] ); ?>">
					<div class="cic-occ-card__media">
						<?php echo cic_theme_image( $cic_item['image'], '(max-width: 639px) 50vw, (max-width: 1099px) 33vw, 260px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?>
						<span class="cic-occ-card__badge"><?php echo cic_theme_icon( $cic_icons[ $cic_item['event_key'] ] ?? 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					</div>
					<div class="cic-occ-card__body">
						<p class="cic-eyebrow cic-eyebrow--accent"><?php echo esc_html( $cic_item['event_title'] ); ?></p>
						<h3 class="cic-occ-card__title"><a href="<?php echo esc_url( $cic_item['event_url'] ); ?>"><?php echo esc_html( $cic_item['headline'] ); ?></a></h3>
						<p><?php echo esc_html( $cic_item['description'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
