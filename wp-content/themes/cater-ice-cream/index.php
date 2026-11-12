<?php
/**
 * Fallback template. Dedicated compositions arrive in later phases.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_404() ) :
	?>
	<h1><?php esc_html_e( 'Page not found', 'cater-ice-cream' ); ?></h1>
	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return to the homepage', 'cater-ice-cream' ); ?></a></p>
	<?php
elseif ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		get_template_part( 'template-parts/content' );
	endwhile;
	the_posts_pagination();
else :
	?>
	<p><?php esc_html_e( 'Nothing found.', 'cater-ice-cream' ); ?></p>
	<?php
endif;

get_footer();
