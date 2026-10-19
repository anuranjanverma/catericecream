# Cater Ice Cream project instructions

Merge these instructions into the existing project CLAUDE.md. Do not overwrite unrelated project rules.
This pack is intended to live at handoff/cater-ice-cream/ inside the implementation repository.
If it lives elsewhere, resolve the actual directory and record it; do not invent missing paths.

## Read before implementation
- Read this file, docs/BUILD_SPECIFICATION.md, docs/DESIGN_REFERENCE_INDEX.md and designs/manifest.json from the handoff pack.
- Read only the current phase prompt and the related visual references before coding that phase.
- Written specification and the owner's latest corrections override text errors in raster images.
- Inspect the actual repository, installed versions and configured environment before assuming paths or APIs.
- Update BUILD_STATUS.md with phase, decisions, changed files, real verification and remaining inputs.

## Architecture
- Custom hybrid PHP theme cater-ice-cream, Gutenberg/core patterns and ACF Free fixed fields; no Pro-only features.
- Core plugin cater-ice-cream-core owns Events, product meta, location tables, admin design settings and enquiries.
- Standalone cic-catalog-guard.php MU plugin defaults to catalogue mode and survives theme/core-plugin changes.
- Treats are WooCommerce products; events are cic_event CPT; enquiries are stored only in Flamingo with workflow metadata.
- Cities are custom database records with virtual routes, never thousands of WordPress Pages.
- No page builder, paid dependency, external CRM or new SaaS account is part of the baseline.
- Never edit WordPress or WooCommerce core. Use the configured database prefix, CRUD and supported hooks.

## Design rules
- Match the approved compositions, not just colours. Build real HTML and interactive controls, never a page-sized screenshot.
- Birthday, Wedding, Anniversary and Corporate page references are final selections.
- Preserve the approved logo. Do not recreate it by typing a similar font.
- All global colours, typography, sizing and spacing are editable in one Design System admin screen.
- Use semantic CSS variables and named presets. No page-specific hardcoded palette scattered through CSS.
- Sparse page, section and element overrides inherit by default and can reset to inherited values.
- Birthday has sky, mint, violet and raspberry; Wedding has ivory, blush and decorative champagne gold.
- Use readable colour contrast, local fonts, visible focus and reduced motion.
- Do not reproduce prices or minimum-order text from old images. No public minimum-order copy anywhere.
- Do not invent ratings, testimonials, local branches, availability, flavours, dietary or equipment guarantees.
- Correct AI image text errors and strip unsupported registered-trademark symbols from invented brand graphics.
- Keep the global menu and business contact data consistent across templates.

## Enquiry and coverage rules
- Explicit selections carry into the three-step quote flow; page visits do not silently select options.
- Quote steps are Your event, Your treats, Review and send; multiple treats OR Not sure yet.
- Event dates are local America/Los_Angeles dates; unknown date is allowed. No UTC day shifting.
- City and ZIP lookup confirms geography only; it does not reserve a date.
- Geography imports do not become approved coverage by default. Unknown/shared ZIPs need review.
- Compton is excluded; shared ZIP mappings must not bypass that exclusion.
- Save each valid request once before queued notifications. Use atomic idempotency protection.
- Private leads, notes, addresses and dietary details are never public REST/search/sitemap content.
- Do not put personal information in URLs, browser persistent storage, analytics or logs.
- Quote, contact, mail and reminders must be functional and verified in a safe local mail capture setup.

## Catalogue safety
- Hide public shopping UI AND prevent all public purchasing on the server.
- Test normal, query-string, form POST, AJAX, variation, Store API and batch entry paths.
- Audit Store API read dependencies and deny purchase/checkout initialization paths before side effects; do not disable unrelated REST or authenticated Woo product admin.
- Bypass tests must create no customer cart/order/payment or real email.
- Never set a fake zero price or add a zero-priced Offer schema.
- Future online sales need real sellable SKUs and a separate tested release, not an editor toggling a styling switch.

## Execution and verification
- Work in local or staging. No live deployment, DNS, payment setup or real client email without explicit instruction.
- Preserve existing changes and data. No destructive reset, unreviewed import overwrite or uninstall purge.
- Use the current phase's acceptance cases. Stop expanding tests once the relevant gate passes.
- For visual work, compare real browser screenshots at 390, 768 and 1440 px to the matching mockups.
- Report what you actually ran, what passed, what failed and what could not be tested.
- Missing credentials block only the dependent task; continue independent local work.
- Do not claim a plugin, database, notification or page is complete without verification.
- If a plugin or environment choice changes the agreed architecture, explain the impact before introducing it.

## Version 1.1 binding decisions
- CF7 is the only quote/contact submission path. Flamingo is the only enquiry store. No cic_lead CPT or parallel CIC submission endpoints. Prove storage-before-notification with installed versions.
- Initial operator recipient anuranjan.verma25@gmail.com is admin-editable; use safe mail capture on staging. Both Yan and Web Karma have scoped enquiry access.
- Global fonts and shared colour tokens propagate to all templates; occasion presets reference central tokens and do not freeze base styling. Only explicit visible overrides win.
- Header has independently editable top, main and bottom rows; footer has controlled editable columns/menus/contact/legal content.
- ACF Free only; use core Gutenberg patterns, Settings API and Woo galleries instead of Pro features. Do not recreate a page builder.
- Owner will provide city template/rewrite code later. Audit and adapt it; fallback tables are provisional.
- Confirmed outside-area locations show an error and block quote progression/submission. Unknown/shared results request clarification.
- Complete one product/event/city plus full form vertical slice before expanding compositions.
- Final form fields, reminder timing, hosting and deployment owner remain pending. No production database overwrite or seed overwrite of edited records.
