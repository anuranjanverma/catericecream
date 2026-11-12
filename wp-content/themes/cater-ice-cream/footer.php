<?php
/**
 * Closes the main region and prints the shared prefooter ("Cities We Serve"), footer and the
 * fixed mobile "Get a Quote" button.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;
?>
</main>
<?php get_template_part( 'template-parts/footer/cities-we-serve' ); ?>
<?php get_template_part( 'template-parts/footer/site-footer' ); ?>
<?php get_template_part( 'template-parts/footer/mobile-quote' ); ?>
<?php wp_footer(); ?>
</body>
</html>
