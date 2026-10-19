<?php
/**
 * Site footer shell. The approved dynamic footer is built in Phase 04.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;
?>
</main>
<footer class="cic-site-footer">
	<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
