<?php
/**
 * /events/ archive (functional list in menu order; the approved Events overview composition is
 * Phase 08). Two columns on desktop, one on mobile.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="cic-section cic-catalogue">
	<div class="cic-container">
		<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( post_type_archive_title( '', false ), '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		<h1><?php post_type_archive_title(); ?></h1>
		<?php if ( have_posts() ) : ?>
			<ul class="cic-catalogue__list cic-catalogue__list--events">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<li class="cic-card cic-catalogue__item" data-cic-event="<?php echo esc_attr( (string) get_post_meta( get_the_ID(), 'cic_key', true ) ); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="cic-catalogue__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php echo cic_theme_image( (int) get_post_thumbnail_id(), '(max-width: 767px) 100vw, 560px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></a>
						<?php endif; ?>
						<div class="cic-card__body">
							<h2 class="cic-h3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
							<?php echo cic_theme_arrow_link( get_permalink(), __( 'Explore', 'cater-ice-cream' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
						</div>
					</li>
				<?php endwhile; ?>
			</ul>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
