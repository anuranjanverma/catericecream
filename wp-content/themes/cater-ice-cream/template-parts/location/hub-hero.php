<?php
/**
 * Hub hero: breadcrumb, eyebrow, headline, intro and the area check (left); the California map
 * with approximate reviewed points and the service photo (right on desktop, map below the search
 * on mobile). County shortcuts follow the hero.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_vm = $args['vm'];
$cic_h  = $cic_vm['hero'];
echo cic_theme_section_open( 'cic-areas', $args['section'], 'cic-areas-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
?>
	<div class="cic-container cic-container--home cic-areas-hero__grid">
		<div class="cic-areas-hero__text">
			<?php echo cic_theme_breadcrumbs( $cic_vm['breadcrumbs'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<?php if ( '' !== $cic_h['eyebrow'] ) : ?>
				<p class="cic-eyebrow cic-loc-eyebrow"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_h['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="cic-areas-hero__title"><?php echo $cic_h['headline']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h1>
			<?php if ( '' !== $cic_h['intro'] ) : ?>
				<p class="cic-areas-hero__intro"><?php echo esc_html( $cic_h['intro'] ); ?></p>
			<?php endif; ?>
			<div class="cic-areas-hero__search" id="check-your-area">
				<?php
				get_template_part(
					'template-parts/components/area-check',
					null,
					array(
						'id'          => 'cic-areas-location',
						'placeholder' => $cic_h['search']['placeholder'],
						'button'      => $cic_h['search']['button'],
						'endpoint'    => $cic_h['search']['endpoint'],
						'hint'        => $cic_h['search']['hint'],
						'class'       => 'cic-area-check--card',
					)
				);
				?>
			</div>
		</div>
		<div class="cic-areas-hero__media">
			<?php if ( $cic_h['image'] ) : ?>
				<div class="cic-areas-hero__photo"><?php echo cic_theme_image( $cic_h['image'], '360px', 'eager' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup. ?></div>
			<?php endif; ?>
			<?php get_template_part( 'template-parts/components/ca-map', null, array( 'points' => $cic_h['points'], 'caption' => $cic_h['caption'], 'id' => 'cic-areas-map' ) ); ?>
		</div>
	</div>
	<?php if ( count( $cic_vm['counties']['items'] ) > 1 ) : ?>
		<nav class="cic-container cic-county-tabs" aria-label="<?php esc_attr_e( 'Counties', 'cater-ice-cream' ); ?>">
			<ul>
				<?php foreach ( $cic_vm['counties']['items'] as $cic_i => $cic_county ) : ?>
					<li><a class="cic-county-tab<?php echo 0 === $cic_i ? ' is-first' : ''; ?>" href="#county-<?php echo esc_attr( $cic_county['slug'] ); ?>"><?php echo cic_theme_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_county['name'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</section>
