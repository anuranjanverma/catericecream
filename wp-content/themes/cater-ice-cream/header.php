<?php
/**
 * Document head and site header shell. The approved three-row header is built in Phase 04.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="cic-skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'cater-ice-cream' ); ?></a>
<header class="cic-site-header">
	<div class="cic-site-header__brand">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
		<?php endif; ?>
	</div>
	<?php if ( has_nav_menu( 'primary' ) ) : ?>
		<nav class="cic-site-header__nav" aria-label="<?php esc_attr_e( 'Primary', 'cater-ice-cream' ); ?>">
			<?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false ) ); ?>
		</nav>
	<?php endif; ?>
</header>
<main id="main" class="cic-main" tabindex="-1">
