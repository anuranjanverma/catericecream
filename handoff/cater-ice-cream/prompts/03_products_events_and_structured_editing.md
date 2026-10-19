# Prompt 03 Products Events and structured editing

Implement specification sections 6 to 8 using the existing design service. Treats must be real WooCommerce products, not a duplicate CPT or hardcoded array. Seed the four stable catalogue keys and correct canonical product routes without fake prices. Register Events CPT and its four seed occasions in the core plugin, with revisions and appropriate editor support.

Implement editable hero, feelings, photos, occasion pairings, theme boards, detail copy, related records, FAQs, ordering and style overrides. Reuse normal Woo title/excerpt/content/media instead of unnecessary duplicate fields. Native structured blocks or fields must let an editor change visible content without editing PHP. Register proper REST schemas and capabilities for editable metadata. Use one product-event relationship source and validate related IDs.

Show how to add a fifth product or event without source edits. Verify content survives a theme change, revisions work where supported, draft/unpublished records are not selectable in public quote controls, and paired descriptions are consistent in both directions. No paid dependencies: ACF Free fixed fields, core patterns for repeatable content, native Settings API global options and Woo product galleries. No ACF Pro APIs. Preserve catalogue guard behaviour.

## Version 1.1 acceptance additions

Use ACF Free fixed fields and Gutenberg patterns/core blocks for repeatable sections. Use indexed product-event relationships, not serialized-meta LIKE queries. Seed identities without overwriting edited records. Fully compose one product/event for the slice first.
