<?php
/**
 * /treats/ catalogue (functional list; the approved "Our Treats" composition is Phase 08).
 * Uses the main WooCommerce product query (published, active treats in menu order).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="cic-section cic-catalogue">
	<div class="cic-container">
		<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( woocommerce_page_title( false ), '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<h1><?php woocommerce_page_title(); ?></h1>
		<?php if ( have_posts() ) : ?>
			<ul class="cic-catalogue__list">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<li class="cic-card cic-catalogue__item" data-cic-product="<?php echo esc_attr( (string) get_post_meta( get_the_ID(), 'cic_key', true ) ); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="cic-catalogue__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php echo cic_theme_image( (int) get_post_thumbnail_id(), '(max-width: 767px) 100vw, 480px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></a>
						<?php endif; ?>
						<div class="cic-card__body">
							<h2 class="cic-h3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
							<?php echo cic_theme_arrow_link( get_permalink(), __( 'Explore', 'cater-ice-cream' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
						</div>
					</li>
				<?php endwhile; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'Treats will appear here soon.', 'cater-ice-cream' ); ?></p>
		<?php endif; ?>
	</div>
</section>
