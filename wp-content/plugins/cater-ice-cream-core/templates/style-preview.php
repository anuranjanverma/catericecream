<?php
/**
 * Style preview template (core plugin). Uses the active theme's header and footer so header rows,
 * mobile navigation and footer are previewed with the same saved settings.
 *
 * Copy here is neutral sample text for checking styles; it is not page content.
 *
 * @package CIC
 */

use CIC\Design\Contrast;
use CIC\Design\Preview;
use CIC\Design\Schema;
use CIC\Site\Contact;

defined( 'ABSPATH' ) || exit;

$cic_focus    = Preview::focus_context();
$cic_contexts = Schema::contexts();
$cic_contact  = Contact::get();

/**
 * One compact set of components inside a preset wrapper.
 */
$cic_strip = static function ( string $key, array $def ) use ( $cic_contact ): void {
	?>
	<section class="cic-ctx cic-context-<?php echo esc_attr( $key ); ?> cic-section cic-preview-strip" data-cic-preview="<?php echo esc_attr( $key ); ?>" aria-labelledby="cic-pv-<?php echo esc_attr( $key ); ?>">
		<div class="cic-container">
			<p class="cic-eyebrow"><?php echo esc_html( $def['label'] ); ?> <?php esc_html_e( 'preset', 'cater-ice-cream-core' ); ?> · <code>.cic-context-<?php echo esc_html( $key ); ?></code></p>
			<h2 id="cic-pv-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'A sample heading', 'cater-ice-cream-core' ); ?> <span class="cic-accent"><?php esc_html_e( 'with accent words.', 'cater-ice-cream-core' ); ?></span></h2>
			<p><?php esc_html_e( 'Body text inherits the global body font and text colour. Links use the link role:', 'cater-ice-cream-core' ); ?> <a href="#top"><?php esc_html_e( 'a sample link', 'cater-ice-cream-core' ); ?></a>.</p>
			<div class="cic-preview-row">
				<a class="cic-button" href="#top" data-cic-sample="button"><?php esc_html_e( 'Primary action', 'cater-ice-cream-core' ); ?> <span aria-hidden="true">→</span></a>
				<a class="cic-button cic-button--secondary" href="#top"><?php esc_html_e( 'Secondary action', 'cater-ice-cream-core' ); ?></a>
				<a class="cic-text-link" href="#top"><?php esc_html_e( 'Explore', 'cater-ice-cream-core' ); ?> <span aria-hidden="true">→</span></a>
			</div>
			<div class="cic-preview-grid">
				<article class="cic-card">
					<div class="cic-card__media cic-surface-alt" aria-hidden="true"><span class="cic-decor-dot"></span><span class="cic-decor-dot cic-decor-dot--2"></span></div>
					<div class="cic-card__body">
						<h3><?php esc_html_e( 'Card title', 'cater-ice-cream-core' ); ?></h3>
						<p><?php esc_html_e( 'Short supporting text for a card.', 'cater-ice-cream-core' ); ?></p>
						<a class="cic-text-link" href="#top"><?php esc_html_e( 'Explore', 'cater-ice-cream-core' ); ?> <span aria-hidden="true">→</span></a>
					</div>
				</article>
				<div class="cic-accordion">
					<details class="cic-accordion__item">
						<summary><?php esc_html_e( 'A sample question?', 'cater-ice-cream-core' ); ?></summary>
						<div class="cic-accordion__panel"><p><?php esc_html_e( 'A sample answer revealed by the accordion.', 'cater-ice-cream-core' ); ?></p></div>
					</details>
					<details class="cic-accordion__item">
						<summary><?php esc_html_e( 'Another question?', 'cater-ice-cream-core' ); ?></summary>
						<div class="cic-accordion__panel"><p><?php esc_html_e( 'Another sample answer.', 'cater-ice-cream-core' ); ?></p></div>
					</details>
				</div>
			</div>
		</div>
	</section>
	<?php
};

get_header();
?>
<div class="cic-style-preview" id="top">
	<section class="cic-section cic-surface-alt">
		<div class="cic-container">
			<p class="cic-eyebrow"><?php esc_html_e( 'Design System', 'cater-ice-cream-core' ); ?></p>
			<h1><?php esc_html_e( 'Style preview', 'cater-ice-cream-core' ); ?> <span class="cic-accent"><?php esc_html_e( 'for every preset.', 'cater-ice-cream-core' ); ?></span></h1>
			<p class="cic-lead"><?php esc_html_e( 'Real components rendered with the saved Design System values. Change a global token and every preset that inherits it updates here; explicit local overrides stay local.', 'cater-ice-cream-core' ); ?></p>
			<nav class="cic-preview-nav" aria-label="<?php esc_attr_e( 'Presets', 'cater-ice-cream-core' ); ?>">
				<a href="<?php echo esc_url( Preview::url() ); ?>"<?php echo '' === $cic_focus ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All presets', 'cater-ice-cream-core' ); ?></a>
				<?php foreach ( $cic_contexts as $cic_key => $cic_def ) : ?>
					<a href="<?php echo esc_url( Preview::url( $cic_key ) ); ?>"<?php echo $cic_focus === $cic_key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $cic_def['label'] ); ?></a>
				<?php endforeach; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cic-design-system' ) ); ?>"><?php esc_html_e( 'Edit Design System', 'cater-ice-cream-core' ); ?></a>
			</nav>
		</div>
	</section>

	<?php if ( '' === $cic_focus || 'brand' === $cic_focus ) : ?>
	<section class="cic-section cic-ctx cic-context-brand" data-cic-preview="brand-full" aria-labelledby="cic-pv-type">
		<div class="cic-container">
			<h2 id="cic-pv-type" class="cic-preview-label"><?php esc_html_e( 'Typography', 'cater-ice-cream-core' ); ?></h2>
			<p class="cic-eyebrow"><?php esc_html_e( 'Eyebrow · for the moments worth celebrating', 'cater-ice-cream-core' ); ?></p>
			<p class="cic-h1"><?php esc_html_e( 'Heading one.', 'cater-ice-cream-core' ); ?> <span class="cic-accent"><?php esc_html_e( 'Accent words.', 'cater-ice-cream-core' ); ?></span></p>
			<p class="cic-h2"><?php esc_html_e( 'Heading two', 'cater-ice-cream-core' ); ?> <span class="cic-accent"><?php esc_html_e( 'with accent.', 'cater-ice-cream-core' ); ?></span></p>
			<p class="cic-h3"><?php esc_html_e( 'Heading three', 'cater-ice-cream-core' ); ?></p>
			<p class="cic-h4"><?php esc_html_e( 'Heading four', 'cater-ice-cream-core' ); ?></p>
			<p class="cic-lead"><?php esc_html_e( 'Lead paragraph for introductions under a hero heading.', 'cater-ice-cream-core' ); ?></p>
			<p data-cic-sample="body"><?php esc_html_e( 'Body paragraph with', 'cater-ice-cream-core' ); ?> <strong><?php esc_html_e( 'bold text', 'cater-ice-cream-core' ); ?></strong> <?php esc_html_e( 'and', 'cater-ice-cream-core' ); ?> <a href="#top"><?php esc_html_e( 'a link', 'cater-ice-cream-core' ); ?></a>. <?php esc_html_e( 'Full explanatory paragraphs must stay readable at every width.', 'cater-ice-cream-core' ); ?></p>
			<p class="cic-caption"><?php esc_html_e( 'Caption text for notes such as service confirmation.', 'cater-ice-cream-core' ); ?></p>

			<h2 class="cic-preview-label"><?php esc_html_e( 'Buttons', 'cater-ice-cream-core' ); ?></h2>
			<div class="cic-preview-row">
				<a class="cic-button" href="#top" data-cic-sample="brand-button"><?php echo esc_html( $cic_contact['quote_label'] ); ?> <span aria-hidden="true">→</span></a>
				<button type="button" class="cic-button"><?php esc_html_e( 'Button element', 'cater-ice-cream-core' ); ?></button>
				<a class="cic-button cic-button--secondary" href="#top"><?php esc_html_e( 'Secondary action', 'cater-ice-cream-core' ); ?></a>
				<a class="cic-text-link" href="#top"><?php esc_html_e( 'Explore the treats', 'cater-ice-cream-core' ); ?> <span aria-hidden="true">→</span></a>
			</div>

			<h2 class="cic-preview-label"><?php esc_html_e( 'Fields', 'cater-ice-cream-core' ); ?></h2>
			<form class="cic-form cic-preview-form" action="#" onsubmit="return false" novalidate>
				<div class="cic-field">
					<label class="cic-label" for="pv-name"><?php esc_html_e( 'Your name', 'cater-ice-cream-core' ); ?> <span class="cic-required" aria-hidden="true">*</span></label>
					<input class="cic-input" id="pv-name" type="text" placeholder="<?php esc_attr_e( 'Full name', 'cater-ice-cream-core' ); ?>" autocomplete="off">
				</div>
				<div class="cic-field">
					<label class="cic-label" for="pv-event"><?php esc_html_e( 'Event type', 'cater-ice-cream-core' ); ?></label>
					<select class="cic-input" id="pv-event"><option><?php esc_html_e( 'Select event', 'cater-ice-cream-core' ); ?></option><option><?php esc_html_e( 'Birthday', 'cater-ice-cream-core' ); ?></option></select>
				</div>
				<div class="cic-field">
					<label class="cic-label" for="pv-date"><?php esc_html_e( 'Event date', 'cater-ice-cream-core' ); ?></label>
					<input class="cic-input" id="pv-date" type="date">
				</div>
				<div class="cic-field cic-field--error">
					<label class="cic-label" for="pv-zip"><?php esc_html_e( 'City or ZIP code', 'cater-ice-cream-core' ); ?></label>
					<input class="cic-input" id="pv-zip" type="text" aria-invalid="true" aria-describedby="pv-zip-error" value="00000">
					<p class="cic-field__error" id="pv-zip-error"><?php esc_html_e( 'Sample error message shown next to the field.', 'cater-ice-cream-core' ); ?></p>
				</div>
				<div class="cic-field cic-field--wide">
					<label class="cic-label" for="pv-notes"><?php esc_html_e( 'Tell us about your event (optional)', 'cater-ice-cream-core' ); ?></label>
					<textarea class="cic-input" id="pv-notes" rows="3"></textarea>
				</div>
				<div class="cic-field cic-field--wide">
					<label class="cic-check"><input type="checkbox"> <?php esc_html_e( 'Sample checkbox', 'cater-ice-cream-core' ); ?></label>
				</div>
			</form>

			<h2 class="cic-preview-label"><?php esc_html_e( 'Cards', 'cater-ice-cream-core' ); ?></h2>
			<div class="cic-preview-cards">
				<?php for ( $cic_i = 1; $cic_i <= 3; $cic_i++ ) : ?>
					<article class="cic-card">
						<div class="cic-card__media cic-surface-alt" aria-hidden="true"><span class="cic-decor-dot"></span><span class="cic-decor-dot cic-decor-dot--2"></span></div>
						<div class="cic-card__body">
							<h3><?php echo esc_html( sprintf( __( 'Sample card %d', 'cater-ice-cream-core' ), $cic_i ) ); ?></h3>
							<p><?php esc_html_e( 'Cards inherit radius, shadow and colours from the Design System.', 'cater-ice-cream-core' ); ?></p>
							<a class="cic-text-link" href="#top"><?php esc_html_e( 'Explore', 'cater-ice-cream-core' ); ?> <span aria-hidden="true">→</span></a>
						</div>
					</article>
				<?php endfor; ?>
			</div>

			<h2 class="cic-preview-label"><?php esc_html_e( 'Accordion', 'cater-ice-cream-core' ); ?></h2>
			<div class="cic-accordion">
				<?php foreach ( array( __( 'Can I choose more than one type of treat?', 'cater-ice-cream-core' ), __( 'How do I check my event location?', 'cater-ice-cream-core' ), __( 'How do we get started?', 'cater-ice-cream-core' ) ) as $cic_i => $cic_q ) : ?>
					<details class="cic-accordion__item"<?php echo 0 === $cic_i ? ' open' : ''; ?>>
						<summary><?php echo esc_html( $cic_q ); ?></summary>
						<div class="cic-accordion__panel"><p><?php esc_html_e( 'Sample answer text. Accordions use native details/summary so they work by keyboard and without JavaScript.', 'cater-ice-cream-core' ); ?></p></div>
					</details>
				<?php endforeach; ?>
			</div>

			<h2 class="cic-preview-label"><?php esc_html_e( 'Quote starter', 'cater-ice-cream-core' ); ?></h2>
			<div class="cic-quote-starter">
				<div class="cic-field"><label class="cic-label" for="qs-loc"><?php esc_html_e( 'Location', 'cater-ice-cream-core' ); ?></label><input class="cic-input" id="qs-loc" type="text" placeholder="<?php esc_attr_e( 'City or ZIP code', 'cater-ice-cream-core' ); ?>"></div>
				<div class="cic-field"><label class="cic-label" for="qs-type"><?php esc_html_e( 'Event type', 'cater-ice-cream-core' ); ?></label><select class="cic-input" id="qs-type"><option><?php esc_html_e( 'Select event', 'cater-ice-cream-core' ); ?></option></select></div>
				<div class="cic-field"><label class="cic-label" for="qs-date"><?php esc_html_e( 'Date', 'cater-ice-cream-core' ); ?></label><input class="cic-input" id="qs-date" type="date"></div>
				<a class="cic-button" href="#top"><?php esc_html_e( 'Plan my event', 'cater-ice-cream-core' ); ?> <span aria-hidden="true">→</span></a>
				<p class="cic-caption cic-quote-starter__note"><?php echo esc_html( $cic_contact['availability_note'] ); ?></p>
			</div>
		</div>
	</section>

	<section class="cic-call-panel" data-cic-preview="call-panel" aria-labelledby="cic-pv-call">
		<div class="cic-container cic-call-panel__inner">
			<h2 id="cic-pv-call" class="cic-call-panel__title"><?php esc_html_e( 'Sample call panel heading.', 'cater-ice-cream-core' ); ?></h2>
			<div class="cic-call-panel__actions">
				<a class="cic-button cic-button--inverse" href="<?php echo esc_url( $cic_contact['quote_url'] ); ?>"><?php echo esc_html( $cic_contact['quote_label'] ); ?> <span aria-hidden="true">→</span></a>
				<?php if ( '' !== $cic_contact['phone_href'] ) : ?>
					<a class="cic-call-panel__phone" href="<?php echo esc_url( $cic_contact['phone_href'] ); ?>"><?php echo esc_html( $cic_contact['call_label'] . ' ' . $cic_contact['phone'] ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php
	foreach ( $cic_contexts as $cic_key => $cic_def ) {
		if ( 'brand' === $cic_key || ( '' !== $cic_focus && $cic_focus !== $cic_key ) ) {
			continue;
		}
		$cic_strip( $cic_key, $cic_def );
	}
	?>

	<section class="cic-section" aria-labelledby="cic-pv-contrast">
		<div class="cic-container">
			<h2 id="cic-pv-contrast" class="cic-preview-label"><?php esc_html_e( 'Contrast check (saved values)', 'cater-ice-cream-core' ); ?></h2>
			<?php $cic_fail = Contrast::failures(); ?>
			<p><?php echo $cic_fail ? esc_html( sprintf( _n( '%d pair is below its WCAG target:', '%d pairs are below their WCAG target:', count( $cic_fail ), 'cater-ice-cream-core' ), count( $cic_fail ) ) ) : esc_html__( 'All checked text and background pairs meet their WCAG target.', 'cater-ice-cream-core' ); ?></p>
			<?php if ( $cic_fail ) : ?>
				<ul class="cic-contrast-warnings" data-cic-contrast-failures="<?php echo (int) count( $cic_fail ); ?>">
					<?php foreach ( $cic_fail as $cic_c ) : ?>
						<li><?php echo esc_html( sprintf( '%s — %s: %s:1 (needs %s:1)', $cic_c['context'], $cic_c['label'], number_format( $cic_c['ratio'], 2 ), $cic_c['required'] ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>
</div>
<?php
get_footer();
