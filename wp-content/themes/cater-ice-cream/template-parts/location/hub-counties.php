<?php
/**
 * County directory: one card per county with published cities from the location repository
 * (links to the virtual city pages). Cards are accordions on mobile (native details), open on
 * wider screens. The state hub's ?county= view lists one county with pagination. Listing a city
 * never claims every ZIP is served.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
$cic_c  = $cic_vm['counties'];
echo cic_theme_section_open( 'cic-areas', $args['section'], 'cic-section cic-areas-counties' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home">
		<?php if ( '' !== $cic_c['heading'] ) : ?>
			<h2 class="cic-center"><?php echo $cic_c['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $cic_c['intro'] ) : ?>
			<p class="cic-center cic-section-intro"><?php echo esc_html( $cic_c['intro'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $cic_vm['county'] ) : ?>
			<p class="cic-center"><a class="cic-text-link" href="<?php echo esc_url( $cic_vm['state_url'] ); ?>">← <?php esc_html_e( 'All counties', 'cater-ice-cream' ); ?></a></p>
		<?php endif; ?>
		<ul class="cic-county-cards<?php echo '' !== $cic_vm['county'] ? ' cic-county-cards--single' : ''; ?>">
			<?php foreach ( $cic_c['items'] as $cic_i => $cic_county ) : ?>
				<li class="cic-card cic-county-card" id="county-<?php echo esc_attr( $cic_county['slug'] ); ?>">
					<?php if ( $cic_county['image'] ) : ?>
						<div class="cic-county-card__media"><?php echo cic_theme_image( $cic_county['image'], '(max-width: 1099px) 50vw, 360px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
					<?php endif; ?>
					<details class="cic-county-card__toggle" data-cic-county<?php echo 0 === $cic_i ? ' open' : ''; ?>>
						<summary>
							<h3 class="cic-county-card__name"><?php echo esc_html( $cic_county['name'] ); ?></h3>
							<span class="cic-county-card__chevron" aria-hidden="true"><?php echo cic_theme_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						</summary>
						<div class="cic-county-card__body">
							<?php if ( '' !== $cic_county['text'] ) : ?>
								<p class="cic-county-card__text"><?php echo esc_html( $cic_county['text'] ); ?></p>
							<?php endif; ?>
							<?php if ( $cic_county['cities'] ) : ?>
								<ul class="cic-county-card__cities">
									<?php foreach ( $cic_county['cities'] as $cic_city ) : ?>
										<li>
											<?php if ( '' !== $cic_city['url'] ) : ?>
												<a href="<?php echo esc_url( $cic_city['url'] ); ?>"><?php echo esc_html( $cic_city['name'] ); ?> <span aria-hidden="true">›</span></a>
											<?php else : ?>
												<?php echo esc_html( $cic_city['name'] ); ?>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="cic-caption"><?php esc_html_e( 'City list coming soon. Check your exact ZIP code for now.', 'cater-ice-cream' ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $cic_county['all'] ) : ?>
								<?php /* translators: 1: number of cities, 2: county name. */ ?>
								<p class="cic-county-card__all"><a class="cic-text-link" href="<?php echo esc_url( $cic_county['all'] ); ?>"><?php echo esc_html( sprintf( __( 'All %1$d cities in %2$s', 'cater-ice-cream' ), $cic_county['total'], $cic_county['name'] ) ); ?></a></p>
							<?php endif; ?>
							<?php if ( $cic_county['pages'] > 1 ) : ?>
								<nav class="cic-pagination" aria-label="<?php esc_attr_e( 'City pages', 'cater-ice-cream' ); ?>">
									<?php
									echo wp_kses_post(
										paginate_links(
											array(
												'base'    => add_query_arg( array( 'county' => $cic_county['slug'], 'pg' => '%#%' ), $cic_vm['state_url'] ),
												'format'  => '',
												'current' => max( 1, isset( $_GET['pg'] ) ? absint( $_GET['pg'] ) : 1 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
												'total'   => $cic_county['pages'],
											)
										)
									);
									?>
								</nav>
							<?php endif; ?>
							<a class="cic-county-card__check" href="#check-your-area" data-cic-focus="#cic-areas-location"><?php echo esc_html( $cic_county['link'] ); ?> <span aria-hidden="true">→</span></a>
						</div>
					</details>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="cic-caption cic-areas-counties__note"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Your exact ZIP code and event details confirm service.', 'cater-ice-cream' ); ?></p>
	</div>
</section>
