<?php
/**
 * Prefooter "Cities We Serve": a quiet, wrapping list of city links directly above the footer, in
 * the footer palette. Cities come from the location repository (published, indexable, production,
 * ranked by served ZIP codes; the counts are not shown). Omitted on the reduced Get a Quote page.
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\CIC\Content\ViewModel' ) ) {
	return;
}
$cic_quote_page = (int) \CIC\Content\Catalog::find_by_key( 'page', 'get-a-quote' );
if ( $cic_quote_page && is_page( $cic_quote_page ) ) {
	return; // The Get a Quote page keeps its reduced layout.
}
$cic_cities = \CIC\Content\ViewModel::served_cities();
if ( null === $cic_cities ) {
	return;
}
?>
<section class="cic-prefooter" aria-labelledby="cic-prefooter-title">
	<div class="cic-container cic-prefooter__inner">
		<h2 class="cic-prefooter__title" id="cic-prefooter-title"><?php echo esc_html( $cic_cities['heading'] ); ?></h2>
		<ul class="cic-prefooter__list">
			<?php foreach ( $cic_cities['items'] as $cic_city ) : ?>
				<li><a href="<?php echo esc_url( $cic_city['url'] ); ?>"><?php echo esc_html( $cic_city['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( '' !== $cic_cities['all_label'] ) : ?>
			<p class="cic-prefooter__all"><a href="<?php echo esc_url( $cic_cities['all_url'] ); ?>"><?php echo esc_html( $cic_cities['all_label'] ); ?> <span aria-hidden="true">→</span></a></p>
		<?php endif; ?>
	</div>
</section>
