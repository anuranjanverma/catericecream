# Prompt 00 Environment and plan

Act as the senior WordPress engineer for Cater Ice Cream. Read the handoff CLAUDE.md, docs/BUILD_SPECIFICATION.md, docs/DESIGN_REFERENCE_INDEX.md and designs/manifest.json. Locate the actual WordPress root, repository and local or staging environment. Inspect existing changes, WordPress/WooCommerce/PHP/database versions, active plugins, theme, permalinks, SEO, mail and scheduled-job configuration. Do not reveal credentials.

Do not start the implementation yet. Create ENVIRONMENT.md and BUILD_STATUS.md with the existing state, architectural decisions, file ownership, risks, dependencies and a phase plan matching this pack. Confirm custom hybrid PHP theme, companion plugin, standalone MU purchase guard, native editing, Woo products, Events CPT and database city routes. Explain any genuine incompatibility. Do not choose a page builder or paid dependency automatically.

Inspect whether an existing city repository or schema is actually supplied. If absent, plan the documented local tables and adapter contract; do not connect to another site by assumption. Identify which launch inputs are still needed while allowing independent local development. Return the plan, confirmed versions and next phase, without claiming any unrun test. No live deployment, paid account setup or external messages.

## Version 1.1 acceptance additions

Confirm free-only dependencies, CF7/Flamingo versions and their storage/mail hooks. Record city-template code as pending if absent; no competing production schema. Record unresolved form fields, hosting, reminder timing and deployment owner.


# Prompt 01 Foundation and catalogue protection

Read the specification sections 1 to 3 and 14. Implement the theme shell, core plugin and standalone MU catalogue guard in the inspected repository. Preserve existing changes. Add namespaced bootstrapping, dependency notices, assets, configuration defaults, migration/version boundaries and clear ownership of theme versus business logic.

Catalogue mode must fail closed when configuration is absent and remain active if the theme or core plugin changes. Make public products and variations non purchasable. Prevent legacy query/form/AJAX purchasing and Store API checkout/cart side effects; audit read dependencies before broad blocking. Do not disable WordPress REST or authenticated WooCommerce product administration. No fake zero prices, purchase controls or commerce schema. Ensure safe handling when WooCommerce is absent.

Verify activation, theme switching, core plugin deactivation and administrative product editing. Exercise add-to-cart query strings, legacy AJAX, direct checkout, Store API cart/checkout/batch and variation attempts using the installed APIs. Assert that bypass tests do not create customer carts, draft orders, payment attempts or email. Use fixtures and mail capture. Report exact tested paths and remaining installed-extension exposure. Update BUILD_STATUS.md. Do not build every page yet.


# Prompt 02 Design system and admin controls

Implement the global Design System service and admin screen from sections 4 and 5. One saved token configuration must drive frontend CSS variables and editor presets. Keep theme.json defaults, validate settings and avoid a second unrestricted source of global style overrides. Implement base colours, action/hover/focus roles, font families and weights, responsive heading/body scales, spacing, content width, radius and component settings.

Add named contexts for brand, Birthday, Wedding, Anniversary, Corporate and treat moods. Implement sparse page, section and element overrides with visible inheritance and reset. Do not freeze inherited values by copying the complete palette into every page. Bound numeric inputs, validate hex values, allow only approved font families/assets and emit safe scoped CSS.

Create a style preview showing real buttons, fields, headings, cards, accordions and a call panel. Verify changing one global action colour or body font updates all inheriting examples, while an explicit local override stays local. Reset it and verify inheritance returns. Verify mobile and desktop size changes and persistence after reload/cache clear. Check contrast warnings. Do not hardcode per-page CSS colours outside the central defaults/preset configuration. Supply screenshots and test evidence.

## Version 1.1 acceptance additions

Global heading/body font and colour changes must propagate across all occasion presets. Seed central accent tokens, not frozen per-page values. Add native admin controls for all three header rows, mobile navigation and footer columns; use no ACF Pro APIs.


# Prompt 03 Products Events and structured editing

Implement specification sections 6 to 8 using the existing design service. Treats must be real WooCommerce products, not a duplicate CPT or hardcoded array. Seed the four stable catalogue keys and correct canonical product routes without fake prices. Register Events CPT and its four seed occasions in the core plugin, with revisions and appropriate editor support.

Implement editable hero, feelings, photos, occasion pairings, theme boards, detail copy, related records, FAQs, ordering and style overrides. Reuse normal Woo title/excerpt/content/media instead of unnecessary duplicate fields. Native structured blocks or fields must let an editor change visible content without editing PHP. Register proper REST schemas and capabilities for editable metadata. Use one product-event relationship source and validate related IDs.

Show how to add a fifth product or event without source edits. Verify content survives a theme change, revisions work where supported, draft/unpublished records are not selectable in public quote controls, and paired descriptions are consistent in both directions. No paid dependencies: ACF Free fixed fields, core patterns for repeatable content, native Settings API global options and Woo product galleries. No ACF Pro APIs. Preserve catalogue guard behaviour.

## Version 1.1 acceptance additions

Use ACF Free fixed fields and Gutenberg patterns/core blocks for repeatable sections. Use indexed product-event relationships, not serialized-meta LIKE queries. Seed identities without overwriting edited records. Fully compose one product/event for the slice first.


# Prompt 04 Homepage and shared components

Read homepage references and manifest corrections before coding. Implement the homepage and reusable header, footer, buttons, hero search, cards, call panel, process, FAQ and quote starter. Match the approved composition at actual viewport size; do not use a mockup image as content. Remove old minimum-order text, add Anniversary and apply the global menu consistently.

Hero fields are Location, Event type and Date. Plan My Event opens the dedicated quote route with validated nonpersonal context. Existing explicit selections persist, and visiting another page does not overwrite them. Quote controls are not availability guarantees. Set up a central context helper now so later pages reuse it.

At 390, 768 and 1440 px, capture screenshots and compare hierarchy, section order, width, typography, image crop, spacing and CTA prominence to the matching references. Use real responsive image elements and semantic headings. Correct visible differences before adding all pages. Verify keyboard menu, forms, focus, tel links and no horizontal overflow. Record placeholder assets separately from layout defects.

## Version 1.1 acceptance additions

Implement top/main/bottom header rows, visibility, native menus, configurable sticky behaviour, mobile drawer focus/Escape and footer controls per section 16. Verify global edits across templates and sticky anchor offsets.


# Prompt 05 Locations import search routes and SEO

Implement sections 9 to 11. Create versioned tables using the configured prefix, a repository/adapter boundary, safe CSV import with dry run and deterministic upsert, reviewed coverage states and city content overrides. Import only reviewed fixtures initially; geography rows do not become served locations automatically. Preserve ZIP strings and handle shared city/ZIP mappings and Compton exclusions.

Build indexed autocomplete, coverage evaluation, county directories, state hub and virtual city routes. Use the approved Pasadena and Service Areas designs. Search returns covered, outside_area, manual_review, unknown or invalid without claiming a date is available. Unknown and shared ZIPs must not be falsely confirmed. The actual source data defines points; decorative mockup maps are not boundary datasets.

Integrate canonical, metadata, breadcrumbs, noindex and city sitemap behaviour through one SEO adapter for the installed plugin or core provider. Resolve normal canonical city routes, aliases, unknown slugs and unpublished rows. No city-event-treat permutation routes. Test 404 status, duplicate imports, prepared lookup queries, prefix indexes, sitemap exclusion and correct cache invalidation. Produce a mapping report if adapting historical code; do not invent its schema.

## Version 1.1 acceptance additions

Audit owner-supplied city template/rewrite function when available; use provisional local adapters meanwhile. Implement city preview/revisions/slug redirects using existing workflow or private editorial records. Explicit exclusions win. Confirmed outside-area errors block quote progression and server acceptance; unknown/shared locations require clarification. Never flush rewrite rules on every request.


# Prompt 06 Quote flow lead management mail and reminders

Implement sections 12, 13, 15 and 17. Build the real three-step form and success state using the quote reference boards. Preserve explicit incoming selections and ask only missing required information. Date not set yet and Other event must work. Multiple treats and Not sure yet are exclusive. Phone is optional unless Phone is the contact preference. Handle local California dates without UTC shifting.

Use CF7 submission transport with server-side validation, request limits, abuse controls and atomic idempotency. Persist one Flamingo enquiry before notifications; do not create cic_lead or duplicate endpoints. No personal information in query strings, analytics, public APIs, search or logs. Mail failure must leave a recoverable saved request and retry state, not a duplicate submission. Provide the lead admin list, details, status, notes, assignee, next follow-up, safe export and editable mail/reminder configuration.

Use mail capture and .test addresses on staging. Configure supported queuing and scheduled reminders with deduplication, safe rescheduling and cancellation for closed/spam leads. Do not send real messages, enable SMS or connect an external CRM. Implement Contact through the same safe submission foundation. Verify retry/double-click, validation, expired token, network failure, malformed identifiers, mail failure, admin access and confirmation refresh. Test analytics only after server acceptance and without personal payloads.

## Version 1.1 acceptance additions

CF7 is the sole transport and Flamingo the sole store. No private cic_lead duplicate. Use one progressively enhanced CF7 form for all steps. Implement supported dynamic option validation and server coverage checks. Test save-before-mail hook ordering, atomic retry coordination, hidden-field errors, no-JS form, plugin-dependency failure and saved-enquiry/mail-failure recovery. Workflow metadata references Flamingo IDs. Both Yan/team get scoped access. Initial admin-editable recipient anuranjan.verma25@gmail.com; production sender pending. Choose one notification owner; no duplicate CF7/custom emails. Reminders internal-only and disabled until timing is configured. Existing fields are provisional.


# Prompt 07 Event and treat compositions

Read the exact visual references for every page, not just one screenshot. Implement the four event compositions and four single-treat pages using the existing editable blocks/fields and semantic preset system. Birthday is the final colourful sky/mint/lilac design; Wedding is the final premium ivory/champagne design; Anniversary and Corporate are also final references. Do not flatten them into the same reordered generic template.

Treat pages must explain the feeling a host can create and why each occasion/theme pairing makes sense. Dippin' Dots: playful discovery and nostalgia. DipN' Bananas: playful indulgence and an unexpected chocolate detail. Classic Ice Cream: familiarity and shared memories. Frozen Yogurt: a bright, relaxed mood without health claims. Theme boards are inspiration, not a promise of decor or packaging. Keep related product and event links internal.

Use the dedicated quote flow for all Plan actions, carrying event/product context. Build real SVG motifs, not embedded screenshots. Restrict brief confetti to relevant birthday areas; give wedding delicate ring/botanical line motion; support reduced motion. Match actual mobile reading order, colours and imagery scale. Test editability, inherited styles and contextual quote actions from every detail page. Supply representative screenshots at all three breakpoints.

## Version 1.1 acceptance additions

Begin expansion only after the vertical slice passes: global settings/header/footer, one complete product/event/city and complete CF7-Flamingo quote flow. Preserve global font/colour inheritance across occasion moods.


# Prompt 08 Archives and supporting pages

Implement the Our Treats archive with full descriptive product listings and jump links, the updated Events overview with Anniversary, About, Contact, Service Areas hub, state/city content, readable policy template and branded 404. Reuse configured contact values and shared components instead of duplicating literals. The verified phone is +1 818 726 9700; the supplied email is ceo.frostyyummy@gmail.com.

Use actual product/event queries rather than fixed arrays so new published records appear. Events Explore opens the detail page; Quote actions open the shared wizard. Contact is a general enquiry form with a clear event-quote alternative. Do not invent office hours, addresses, history, testimonials, stock flavours or dietary guarantees. About uses verified facts; replace the initials with a real portrait only when one is supplied. Policy pages hold approved content and must not publish mockup placeholders as final legal wording.

Verify all navigation, links, tel/mailto values, contact persistence, submit errors/success, city search and FAQ interaction. Check true 404 responses and that draft/policy-placeholder pages are not accidentally published. Apply manifest corrections for text errors and outdated minimums. Keep the homepage and all public pages free of public pricing/minimum-order references.


# Prompt 09 Visual accessibility and performance review

Review all page families against their named references and the written specification at 390, 768 and 1440 px. Compare actual screenshots before changing code. Correct mismatched composition, oversized whitespace, tiny paragraphs, incorrect typography, misplaced maps, inconsistent buttons, broken image ratios and mobile overflow. The image board is not evidence of actual product stock, equipment or coverage.

Verify contrast, keyboard operation, focus, headings, labels, errors, accessible step changes and accordions, reduced motion, alt text and touch targets. Ensure the sidebar is rearranged sensibly on mobile and no fixed control covers a field. Audit image dimensions/srcset, hero loading priority, local font payload, per-page assets, map loading and unnecessary commerce scripts. Record measured performance and conditions; do not invent passing scores.

Exercise the admin acceptance checklist: a global colour/font change reaches all inheriting templates including virtual cities; local reset works; a new record appears without source edits; paired content is consistent; phone changes propagate. Recheck functional and catalogue cases when code touches those areas. Correct actual remaining defects, not arbitrary differences caused by approved content changes. Return evidence and a short unresolved list.


# Prompt 10 Release candidate and operational guide

Run the release acceptance checklist against the staging build. Confirm location/coverage review, real product and event images, client-approved content/policies, sender-domain mail configuration, actual cron, SEO metadata/sitemaps, catalogue protection and no live purchase capability. Back up files/database before any migration; rehearse restore on a safe staging copy. Keep secrets outside version control.

Prepare the install/update guide, version manifest, data migrations, admin manual, map-data notes, font/media licences, mail and cron instructions, lead export/retention settings and rollback plan. Explain how to edit colours and typography globally, override/reset a section, manage products/events, import ZIP data, edit virtual city content and operate leads/reminders.

Produce a release candidate and remaining client-input list. Do not deploy to production, modify DNS, configure payments or send real messages without a separate explicit instruction. Do not claim launch complete if critical inputs are absent. Record exactly what was tested and what depends on the actual hosting environment.

## Version 1.1 acceptance additions

Release code and allowlisted configuration without replacing production enquiries/content. Version migrations; non-destructive seeds. Demonstrate Flamingo metadata backup/restore and scoped Yan/team permissions. Pending form review, reminder timing, city code, sender, hosting or deployment owner must be reported honestly.


# Prompt 11 Controlled independent sequence

Execute the numbered build phases in this handoff sequentially on local or staging. Complete phase 00 first and inspect the actual environment. Continue routine implementation without asking me to approve every small choice, using the specification and references as authority. Keep every phase's acceptance gate: do not skip failed tests or silently reinterpret a required feature. Update BUILD_STATUS.md after each phase.

Continue independent work when one input is missing. Pause only the dependent part for credentials, an actual contradiction, a protected/destructive action or a decision that materially changes the agreed architecture or commercial scope. Do not introduce paid tools, publish, change DNS or send real customer/client emails. Do not use another project's database without supplied authorisation and schema. Leave the result as a verified staging release candidate with honest remaining inputs.

## Version 1.1 acceptance additions

Enforce the one-product/one-event/one-city/full-form vertical-slice gate before bulk composition. No paid dependency or duplicate enquiry architecture. Apply v1.1 CF7/Flamingo and global inheritance decisions to all phases.


# Prompt 12 Correct a visual mismatch

Compare the current page screenshot with its named approved design and the written corrections. First identify the five largest concrete mismatches in hierarchy, composition, spacing, typography, colour roles or mobile order. Do not redesign unrelated components or change the data model to fix CSS.

Correct those mismatches using the design token and preset system. If a token needs adjustment, decide whether the change belongs globally, to an occasion preset or to a specific section. Do not paste hardcoded one-off styles across pages. Recheck other pages sharing that token. Capture the same viewport again, report differences fixed and any remaining asset-related differences, and run functional checks only where the changes could affect behaviour.
