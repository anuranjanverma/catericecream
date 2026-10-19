<?php
/**
 * Document head and the shared site header.
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
<?php get_template_part( 'template-parts/header/site-header' ); ?>
<main id="main" class="cic-main" tabindex="-1">
