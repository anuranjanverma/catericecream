<?php
/**
 * Process ("how it works") strip: heading, numbered steps and a quote CTA. Reusable.
 * Args: process (heading, steps, cta, url).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

$cic_p = $args['process'];
?>
<div class="cic-process">
	<?php if ( '' !== $cic_p['heading'] ) : ?>
		<h2 class="cic-center cic-process__title"><?php echo $cic_p['heading']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered. ?></h2>
	<?php endif; ?>
	<div class="cic-process__row">
		<ol class="cic-process__steps">
			<?php foreach ( $cic_p['steps'] as $cic_i => $cic_step ) : ?>
				<li class="cic-process__step">
					<span class="cic-process__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $cic_i + 1 ) ); ?></span>
					<span class="cic-process__text"><strong><?php echo esc_html( $cic_step['title'] ); ?></strong> <span><?php echo esc_html( $cic_step['text'] ); ?></span></span>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php if ( '' !== $cic_p['cta'] ) : ?>
			<a class="cic-button cic-process__cta" href="<?php echo esc_url( $cic_p['url'] ); ?>" data-cic-quote-link><?php echo esc_html( $cic_p['cta'] ); ?> <span aria-hidden="true">→</span></a>
		<?php endif; ?>
	</div>
</div>
