<?php
/**
 * Generic entry markup used by the fallback template.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php if ( is_singular() ) : ?>
		<h1><?php the_title(); ?></h1>
	<?php else : ?>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
	<?php endif; ?>
	<div class="cic-entry-content">
		<?php is_singular() ? the_content() : the_excerpt(); ?>
	</div>
</article>
