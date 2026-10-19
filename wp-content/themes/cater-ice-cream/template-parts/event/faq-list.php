<?php
/**
 * FAQ accordion (native details/summary; keyboard accessible without JavaScript).
 *
 * @package CaterIceCream
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $args['items'] ) ) {
	return;
}
?>
<div class="cic-accordion">
	<?php foreach ( $args['items'] as $cic_faq ) : ?>
		<details class="cic-accordion__item">
			<summary><?php echo esc_html( $cic_faq['q'] ); ?></summary>
			<div class="cic-accordion__panel"><p><?php echo esc_html( $cic_faq['a'] ); ?></p></div>
		</details>
	<?php endforeach; ?>
</div>
