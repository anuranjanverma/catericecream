# Instructions for Claude Code: image assets

Use this pack as a supplement to the existing v1.1 handoff. Keep all approved sections, section order, page compositions, original logo and original palette. This pack supplies photographs; it does not authorize a redesign.

## Placement
Read ASSET_MANIFEST.json, especially pages[page].placements. Each placement defines a route, section slot, asset ID, preferred file, source reference, aspect ratio, size and responsive variants. Use PAGE_IMAGE_MAP.csv for a quick checklist. The contact sheet is an index only, never a website asset. The filename is not permission to choose a different slot. All source generation prompts and provenance are recorded in GENERATION_NOTES.json.

The same photo may intentionally serve more than one page. Keep all slots even when they share one attachment. For a new database city, inherit the city-pasadena photo assignments unless approved city-specific media is supplied. State hubs inherit service-areas. Mobile reuses the same original images with the corresponding responsive variants; do not create unrelated mobile photos.

## Sizing
Widths are pixels of the delivered file, not browser CSS widths. Slot CSS estimates use a 1200px content container and must be checked against the real approved layout. A roughly 560px split photo uses up to 1120px; normal cards use smaller files; only the widest slots use up to 1440px. Do not serve a 1920px image to every slot or upscale a source. High-density displays may warrant about 2x CSS width, subject to available resolution. Supplied variants are capped by source dimensions.

Import the 57 master photos once into WordPress Media Library. Keep asset-ID to attachment-ID mapping in an idempotent importer. Preserve attachment edits and avoid duplicate imports. Register the required image sizes and WebP output, then render attachments with wp_get_attachment_image or equivalent proper srcset/sizes. The supplied variants may be reused directly when the build supports their metadata; do not import every variant as a separate attachment. Import only the relevant original logo/mascot brand assets.

Use slot aspect ratio, width/height attributes, responsive sizes and object-fit:cover. Hero photos should not be lazy loaded. Only the actual LCP image should receive high fetch priority, not every image in a hero collage. Lazy-load below-fold imagery. Apply rounded masks, circles, overlaps and decorative contours in CSS/SVG without permanently baking page layouts into photographs. Check face/product crops at 390, 768 and 1440px. If a crop fails, adjust object-position locally; do not reorder the section.

## Content and provenance
These are high-resolution AI recreations matched to the approved mockups. The small flattened screenshot photos cannot supply full-resolution originals; the recreations are not pixel-identical extractions. They must not be described as actual Cater Ice Cream customers, client weddings, staff portraits, equipment inventory or verified local venues. No reviews or client endorsements may be inferred. Product scene garnishes and toppings are visual styling, not confirmed availability. Named product photos need client review against actual product and brand requirements before production. The photos use unbranded cups; render approved brand labels in page HTML rather than generating fake trademarks or packaging.

All supplied photographic exports are WebP. SVG/CSS icons, maps and graphics remain appropriate. Use the non_photo_graphics inventory instead of slicing headings, forms, maps or icons out of the screenshot. Follow existing handoff corrections about old minimum-order text, Anniversary and inconsistent AI mockup copy.

## Acceptance
Account for every placement in PAGE_IMAGE_MAP.csv and every raster/photo region in its named mockup. Confirm all 19 page-template entries, mobile reference mappings, original logo, full quote state and no-photo policy/404 states. Preserve the visual composition and explain any unresolved source/asset mismatch. Test real frontend and editable Media Library controls; a successful file import alone does not verify the page.
