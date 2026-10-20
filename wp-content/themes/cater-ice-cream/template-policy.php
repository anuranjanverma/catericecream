<?php
/**
 * Template Name: Policy
 *
 * Readable policy layout (approved layout designs/19_policy_and_404.png): title with the mascot,
 * a "Content pending approval" label until the wording is approved, an "On this page" list built
 * from the content's headings, numbered sections, and a contact section from the global contact
 * settings. Policy pages stay drafts until approved; placeholder text is never final wording.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) {
	the_post();
	$cic_vm      = \CIC\Content\ViewModel::policy( get_the_ID() );
	$cic_contact = cic_theme_contact();
	$cic_n       = count( $cic_vm['sections'] );
	?>
	<div class="cic-pages cic-policy-page">
		<section class="cic-policy-hero">
			<div class="cic-container cic-container--wide cic-policy-hero__inner">
				<div>
					<?php echo cic_theme_breadcrumbs( array( array( __( 'Home', 'cater-ice-cream' ), home_url( '/' ) ), array( __( 'Policies', 'cater-ice-cream' ), '' ), array( $cic_vm['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<h1><?php echo esc_html( $cic_vm['title'] ); ?></h1>
					<?php if ( ! $cic_vm['approved'] ) : ?>
						<p class="cic-policy-status"><?php echo cic_theme_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Content pending approval', 'cater-ice-cream' ); ?></p>
					<?php elseif ( '' !== $cic_vm['updated'] ) : ?>
						<?php /* translators: %s: date. */ ?>
						<p class="cic-policy-updated"><?php echo esc_html( sprintf( __( 'Last updated: %s', 'cater-ice-cream' ), $cic_vm['updated'] ) ); ?></p>
					<?php endif; ?>
				</div>
				<span class="cic-policy-hero__mascot"><?php echo cic_theme_mascot( 220, array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
			</div>
		</section>
		<div class="cic-section cic-pg-section--tight">
			<div class="cic-container cic-container--wide cic-policy">
				<nav class="cic-policy-toc" aria-label="<?php esc_attr_e( 'On this page', 'cater-ice-cream' ); ?>">
					<p class="cic-policy-toc__title"><?php esc_html_e( 'On this page', 'cater-ice-cream' ); ?></p>
					<ol>
						<?php foreach ( $cic_vm['sections'] as $cic_i => $cic_section ) : ?>
							<li><a href="#<?php echo esc_attr( $cic_section['id'] ); ?>"><span><?php echo esc_html( sprintf( '%02d', $cic_i + 1 ) ); ?></span><?php echo esc_html( $cic_section['label'] ); ?></a></li>
						<?php endforeach; ?>
						<li><a href="#policy-contact"><span><?php echo esc_html( sprintf( '%02d', $cic_n + 1 ) ); ?></span><?php esc_html_e( 'Contact', 'cater-ice-cream' ); ?></a></li>
					</ol>
				</nav>
				<div class="cic-policy-body">
					<div class="cic-policy-content"><?php echo $cic_vm['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content output. ?></div>
					<section class="cic-policy-contact" aria-labelledby="policy-contact">
						<h2 id="policy-contact"><?php esc_html_e( 'Contact', 'cater-ice-cream' ); ?></h2>
						<p><?php esc_html_e( 'For questions about this policy, please contact:', 'cater-ice-cream' ); ?></p>
						<?php if ( '' !== $cic_contact['email'] ) : ?>
							<p><a class="cic-policy-contact__email" href="<?php echo esc_url( 'mailto:' . $cic_contact['email'] ); ?>"><?php echo cic_theme_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $cic_contact['email'] ); ?></a></p>
						<?php endif; ?>
					</section>
				</div>
			</div>
		</div>
	</div>
	<?php
}
get_footer();
