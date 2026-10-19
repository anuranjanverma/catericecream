# Cater Ice Cream — Build Status

Spec: `handoff/cater-ice-cream/` v1.1 (BUILD_SPECIFICATION.md, REVISION_NOTES.md) plus image pack `handoff/Cater_Ice_Cream_WebP_Assets/`.
Environment facts: see `ENVIRONMENT.md`.

## Current phase

| Phase | Status | Date |
| --- | --- | --- |
| 00 Environment and plan | Complete (documentation only) | 2026-10-19 |
| 01 Foundation and catalogue protection | **Complete: gate passed** (see verification) | 2026-10-19 |
| 02 Design system and admin controls | **Complete: gate passed** (see verification) | 2026-10-19 |
| 03 Products, events and structured editing | **Complete: gate passed** (see verification) | 2026-10-19 |
| 04 Homepage and shared components | Not started. Awaiting owner instruction | |

Git (local only, no remote): `7f2cbc3` Phase 00–01 baseline; `ccc05e9` Phase 02, committed at the owner's request after reviewing the staged set. Phase 03 work is **not committed yet**.

---

## Phase 03 log: Products, events and structured editing

Prompt: `handoff/cater-ice-cream/prompts/03_products_events_and_structured_editing.md`.

### Backup and authorised changes

- Backup: `C:\laragon\backup\catericecream\pre-phase03-20261019-202015\` (52 tables, `wp-config.php`, `htaccess.txt`, SHA256SUMS), outside the web root.
- **ACF Free 6.8.10** installed and activated (owner-authorised). `ACF_PRO` is not defined.
- Schema migration v2: table `{prefix}cic_product_events`; event capabilities for administrator and editor.
- WooCommerce routes: the Shop page is now `/treats/` ("Our Treats"); product base `/treats` → `/treats/{slug}/`. The seeder only changes these while they are still at WooCommerce's defaults.

### Decisions

| Concern | Implementation |
| --- | --- |
| Treats | The four seeds are real WooCommerce simple products (`dippin-dots`, `dipn-bananas`, `classic-ice-cream`, `frozen-yogurt`), with no duplicate CPT. Title, short description (card text), long description ("what it is" in Product detail), featured image and gallery stay in the normal Woo fields. Prices are empty; there is no fake zero. Identity meta is registered with REST schemas: `cic_key` (stable, unique, pattern), `cic_catalog_role=showcase`, `cic_catalog_active` (`"1"`/`"0"`), `cic_brand_type`, `cic_preset`. |
| Brands | Reuses WooCommerce's built-in `product_brand`. Dippin' Dots and DipN' Bananas have brand terms; Classic and Frozen Yogurt are generic with no brand. Brand, category and tag archives 301 to the brand's single product or `/treats/`, so they never compete. |
| Events | `cic_event` in the core plugin: public, REST (`/wp/v2/events`), archive `/events/`, revisions (including revisioned `cic_preset` meta), title/editor/excerpt/thumbnail/page-attributes, mapped `cic_event` capabilities. Seeds are Birthday, Wedding, Corporate and Anniversary; Anniversary is the fourth card. |
| Structured fields | ACF Free local field groups ("Treat page composition", "Event page composition"), using only free field types (text, textarea, image, select, true/false, number, relationship, tab). Repeating content uses fixed named slots (3 chips, 4 qualities, 3 themes, 3 points, 6 FAQs, 2 stories, 3 steps); empty slots are hidden. Each section has show/order/preset controls. `show_in_rest` is on. Templates read plain post meta through `CIC\Content\ViewModel`, never ACF calls, so pages render even if ACF is off (tested). Optional core-block patterns ("FAQ list", "Story with photo") are available for the main editor area. |
| Pairings (single source) | Table `cic_product_events` has UNIQUE(product_id, event_id) and an index on event_id. It stores the pairing copy itself (headline, description, photo, link label, optional event intro, two sort orders), so the same text renders on both pages. All writes go through `CIC\Content\Pairings`, which validates post types and IDs and rejects duplicates; REST writes are all-or-nothing. Permanent deletion of either record removes its pairings; trashed or draft records are kept but never shown publicly. Edited in the "Occasion pairings" box on the treat; the event screen shows a read-only "Paired treats" list. REST field `cic_pairings` has a schema: readable on products and events, writable on products with `edit_post`. |
| Draft visibility | Public views and `Catalog::selectable_products()` / `selectable_events()` (for the Phase 06 quote controls) include only published records; treats must also be active. Inactive treats leave the catalogue and get `noindex`. |
| Seeding | `wp cic seed-catalog` matches records by `cic_key` and fills only never-seeded fields (`_cic_seeded_fields`). The seed log `cic_seed_log` stops the seeder recreating a record an editor deleted or trashed, or a pairing an editor removed. Re-running reports 0 created / 0 filled. |
| Media | `wp cic import-media` / the seeder imports only the masters placed on the `dippin-dots` and `birthday` manifest pages: **20 attachments**, which include the four treat featured images. Each is imported once (`_cic_asset_id`); later runs reuse it and keep attachment edits. Alt text comes from the manifest, and a provenance note (AI recreation, client review needed) goes in the description. WordPress generates the WebP sub-sizes (GD). |
| Vertical slice | **Dippin' Dots** (09) and **Birthday** (04) are fully composed in approved section order. DD: hero (eyebrow, accent headline, CTA, jump link, feeling chips, photo), feelings, occasion pairings 2×2, theme inspiration with token swatches, detail split, quote starter. Birthday: centred three-colour headline (accent and second-accent markup), balloons and confetti as decorative SVG/CSS, three-photo collage, quote starter strip (POST, occasion pre-set), featured treats (2 large + compact + "More Frozen Treats"), scalloped story band, planning steps, quote panel + FAQ + service-area link. The other six records are minimal (identity, card text, featured image, headline) and use the same templates. |
| Quote actions | Quote links carry the explicit selection (`?treat=dippin-dots`, `?event=birthday`). The Birthday starter strip POSTs location/date/guests with the occasion, so there is no personal data in URLs. The `/get-a-quote/` page and form are Phase 06, so these currently land on a 404. |
| Design | Logo, palette and Design System defaults unchanged. Pages use their preset (`cic_preset` → `.cic-context-dots` / `-birthday`) unless a page override picks another. Composition CSS uses tokens only. |

### Files

- Core plugin 0.3.0: `src/Content/{Service,EventType,Catalog,Pairings,Fields,ViewModel,Media,Seeder,SeedData,Cli}.php`, `src/Content/Admin/PairingsBox.php`, `assets/js/pairings-admin.js`; `src/Database/Migrations.php` (v2); `src/Plugin.php`; `assets/css/design-admin.css` (pairing box).
- Theme: `woocommerce.php` (treat routing), `single-cic_event.php`, `archive-cic_event.php`, `inc/content.php` (icons, images, breadcrumbs, section wrapper, balloons/confetti), `template-parts/treat/{single,archive,section-hero,section-feelings,section-occasions,section-themes,section-detail,section-faq,section-quote}.php`, `template-parts/event/{section-hero,section-starter,section-treats,section-stories,section-planning,section-quote,faq-list}.php`, `assets/css/compositions.css`, `functions.php`.
- Dev: `dev/tests/phase03/{catalog-test.php,admin-edit-test.cjs,state.php}`; `dev/tests/phase02/browser-test.cjs` now deletes its own fixtures in `finally`.

### How to add a fifth treat or event (no source edits)

Products → Add new (or Events → Add new): enter the title, short description and image, and fill the composition tabs. A stable key is assigned from the slug on first save. Add pairings in "Occasion pairings" on the treat. The page renders at `/treats/{slug}/` or `/events/{slug}/` through the same templates, and appears in public choices once published (and active, for treats). Verified by test G (created, rendered both ways, deleted).

### Verification actually performed

1. `wp eval-file dev/tests/phase03/catalog-test.php`: **46/46 passed** on the final run:
   - schema/index/capabilities; ACF Free only;
   - 4 non-purchasable priceless products and 4 ordered events;
   - routes (200s, 301s for `/product/…`, `/shop/`, brand/category archives, 404s for unknown slugs);
   - pairings identical both ways and rendered on both pages; strict validation; DB-level duplicate refusal; indexed reverse query;
   - draft/inactive visibility;
   - seed rerun keeps edits, keeps a cleared field empty and does not recreate a removed pairing;
   - fifth event and treat;
   - REST read/permission/schema/invalid-meta;
   - revisions incl. meta;
   - no purchase markup, add-to-cart refused, Store API 403;
   - media idempotency and alt-edit preservation;
   - Twenty Twenty-Five theme switch and ACF deactivation still render.

   The first run had one false-positive failure (the navigation menu legitimately links `/events/wedding/`); its `finally` block restored everything, which confirmed the cleanup.
2. `node dev/tests/phase03/admin-edit-test.cjs` (headless Edge, real admin): **22/22 passed**:
   - ACF tabs, 4 pairing rows, key and empty price on the product screen;
   - saving via **Update** changed the ACF field on the treat page and the pairing text on **both** the treat and Birthday pages;
   - an ACF field saved from the **block editor** reached the event page (accent markup kept);
   - no horizontal overflow at 390/768/1440 on all four pages;
   - approved section order on both slice pages;
   - one high-priority hero image;
   - Birthday preset colour from the central token;
   - decorations `aria-hidden`; one `h1`; starter posts the occasion.

   Content restored afterwards.
3. Regression on the final code: Phase 01 guard **69/69**, Phase 02 server **51/51**, Phase 02 browser **58/58**. No PHP notices or warnings from site code in the debug log during the final runs.
4. Seeder: first run created 8 records, filled 135 fields, added 7 pairings and imported 20 media; every later run reported 0 created / 0 filled / 0 pairings / 0 media.
5. Screenshots (git-ignored) in `dev/tests/output/phase03/`: `treat-dippin-dots-{390,768,1440}.png`, `event-birthday-{390,768,1440}.png`, `treats-list-1440.png`, `events-list-1440.png`, `admin-treat-pairings.png`, `admin-event-block-editor.png`.
6. Final state: 4 products, 4 events, 7 pairings, 0 fixtures, theme `cater-ice-cream`, Design/layout options at defaults, only the owner's login session.

### Issues found and fixed during Phase 03

- `register_meta` notices: registered defaults must satisfy the REST schema, so defaults are now set only where valid.
- Boolean `false` meta is stored as `''` (indistinguishable from unset), so the active flag is stored as `"1"`/`"0"`.
- The seeder would have recreated deliberately deleted pairings or trashed seed records; this is fixed with the seed log.
- Mobile visual fixes: confetti over heading letters and dot decoration over hero text.
- Test robustness: WooCommerce admin pages never reach network idle (now wait for editor elements, and the browser is always closed on failure; one run hung before this fix, with nothing modified and the restore executed); the Phase 02 browser test left its two fixture pages behind (now deleted in `finally`).

### Unresolved / pending

- **Pending visual/content work:**
  - Footer logo treatment: the original raster logo sits on a light plate until an approved reversed logo exists.
  - Navigation destinations: menus are custom links. `/about/`, `/service-areas/`, `/get-a-quote/` and the policy pages do not exist yet, and custom links do not follow a record's draft status (the Wedding link stays in the menu if Wedding is drafted).
  - Quote targets 404 until Phase 06.
- **Not yet built (later phases):**
  - The other six records stay minimal, and Wedding/Anniversary/Corporate use the standard composition until Phase 07.
  - `/treats/` and `/events/` are functional lists until Phase 08, and the "other event enquiries" text link is Phase 08.
  - "Optional location overrides" on products wait for the Phase 05 location adapter / owner city code.
- **Client confirmation needed:**
  - Whether DipN' Bananas is a supplier brand (it has a brand term provisionally).
  - Treat copy, options, ingredients and the AI photos.
- **Not tested:** screen readers, Safari/Firefox and real touch devices; editor experience for non-admin roles (the editor role has event caps; Woo product caps are WooCommerce defaults); PHPCS (not installed).
- Media sub-sizes use WordPress defaults (no custom crop sizes registered); slot-specific sizing will be reviewed in Phase 09.

---

## Phase 02 log: Design system and admin controls

Prompt: `handoff/cater-ice-cream/prompts/02_design_system_and_admin_controls.md`.

### Backup

`C:\laragon\backup\catericecream\pre-phase02-20261019-191021\` (outside the web root): `catericecream.sql` (52 tables, via `wp db export` with Laragon's MySQL bin on PATH; credentials never printed), `wp-config.php`, `htaccess.txt`, `SHA256SUMS.txt`.

### Fonts (owner decision: approved Fraunces + DM Sans, self-hosted WOFF2)

- Source: the official Google Fonts distribution (CSS2 API → `fonts.gstatic.com`), latin subset only. Google serves variable WOFF2, so three files cover every needed weight: Fraunces roman (600–700), Fraunces italic (600, for Wedding emphasis), DM Sans roman (400–700). Total ≈ 96 KB.
- Licences: SIL OFL 1.1 texts copied from `github.com/google/fonts/ofl/{fraunces,dmsans}/OFL.txt`, kept beside the fonts. Source, SHA-256 hashes and rules are in `wp-content/themes/cater-ice-cream/assets/fonts/FONTS.md`.
- Declared once in `theme.json` (`fontFamilies` + `fontFace`), so WordPress prints `@font-face` on the frontend and in the editor. No font upload path; the WordPress Font Library is disabled.

### How the design system works (decisions)

| Concern | Implementation |
| --- | --- |
| Single source | Option `cic_design_settings`, **sparse** (only values that differ from the approved defaults). Empty field = default; Reset = remove the stored value. |
| Allowlist | `CIC\Design\Schema`: 26 colour tokens, approved fonts, weights limited to bundled weights, 11 responsive type roles (mobile/desktop size + line height), layout, radius and component tokens, 9 presets, override fields. Everything else is rejected. |
| Frontend + editor parity | Resolved tokens feed the supported `wp_theme_json_data_theme` filter (palette, font sizes, spacing, layout widths, `settings.custom.cic.*`). WordPress prints the same variables on the frontend and in the editor iframe. `theme.json` holds identical defaults (generated by `dev/tools/build-theme-json.php`; parity tested) so the theme still works if the core plugin is off. |
| No competing source | User Global Styles are ignored while this theme is active (`wp_theme_json_data_user`), Font Library disabled, custom colours/gradients/font sizes/line height/letter spacing/padding/margin disabled in `theme.json`. |
| Presets (contexts) | Default brand, Birthday, Wedding, Anniversary, Corporate, Dippin' Dots, DipN' Bananas, Classic, Frozen Yogurt. Each maps semantic roles (`--cic-color-action`, `-heading`, `-accent`…) to tokens **by reference** (`var(--wp--preset--color--…)`), so global changes reach every inheriting preset. Only an explicitly chosen custom hex is literal. Presets never set a font family; Wedding sets weight 600 + italic accents only. Birthday/Wedding/treat accents are central named tokens. Treat-mood and pale-surface starting values were sampled from the approved mockups (no hex was given in the spec) and are editable. |
| Local overrides | Page scope: post meta `_cic_design_overrides` + "Design overrides (this page only)" meta box, scoped to `body.cic-page-{ID}`. Section scope (group/cover/columns/column) and element scope (heading, paragraph, list, button): `cicStyle` block attribute + "Cater style overrides" inspector panel, applied server-side as inline custom properties on that block instance only (saved markup unchanged, so blocks stay valid). Every field shows what it inherits, an "Override active" badge and "Reset to inherited". |
| Fluid type | Each role interpolates between 390 px and 1440 px with bounded `clamp()`; element size overrides replace only that element's min/max. |
| Contrast | WCAG checks per preset + components (4.5:1 text, 3:1 large headings) on the Design System screen (live for unsaved global colours, full table after save) and on the style preview. Warnings never change saved values. |
| Export / import | JSON export of the saved sparse configuration; import validates every value and rejects the whole file if anything is invalid. |
| Cache invalidation | Saving design, layout or contact settings bumps `cic_design_version`, clears theme.json caches and calls any installed page-cache purge (LiteSpeed, WP Rocket, W3TC, WP Super Cache, SiteGround) plus a `cic_purge_page_caches` hook. Hosting cache layer still unknown. |
| Header / footer | Option `cic_site_layout`; screen **Cater Ice Cream → Header, Footer & Contact**. Top row (enabled, announcement placeholder, phone/email/social toggles, token colours, desktop/mobile visibility); main row (desktop/mobile logos from Media Library with the bundled original logo as fallback, widths, call action, quote CTA, sticky); bottom row (enabled, secondary menu, colours, visibility, default off); mobile drawer (call/quote/email/secondary menu); footer (logo + light plate, intro, 3 column layouts, native menus, contact/social, legal row, `{year}` copyright). |
| Contact / CTAs | Public fields in `cic_core_settings` (name, legal entity, phone, email, location, quote label/link, call label, availability note, website label, social URLs empty by default). One source for every header/footer/call panel/tel: link. The private operator recipient is preserved and never shown. |
| Navigation | `wp cic seed-navigation` created the approved menus only for empty locations (Primary: Our Treats ▸4, Events ▸4, How It Works, About, Service Areas; Footer; Legal). Re-running skips assigned locations. Links point at planned routes (pages arrive in later phases). |
| Style preview | `/?cic_style_preview=1`, admins only (`edit_theme_options`), noindex, uncached. Real headings, buttons, fields (incl. error state), cards, accordions, quote starter and call panel for the brand, plus a strip per preset and the contrast summary, inside the live header/footer. |
| Capability | `manage_options` for both screens; page overrides need `edit_post` + `edit_theme_options`. |

### Files

- Core plugin 0.2.0: `src/Design/{Schema,Validate,Settings,ThemeJson,Stylesheet,Overrides,Contrast,Preview,Service,Cli}.php`, `src/Design/Admin/{Fields,DesignPage}.php`, `src/Site/{Contact,Layout}.php`, `src/Site/Admin/LayoutPage.php`, `templates/style-preview.php`, `assets/js/{design-admin,editor-overrides}.js` (plain JS, no build step), `assets/css/{design-admin,style-preview}.css`. Changed: `Plugin.php` (registers the Design service), `Config.php` (public CTA/contact defaults + `all()`), main file version.
- Theme 0.2.0: `theme.json` (full token set, fonts, disabled free-form controls), `assets/css/main.css` (token aliases, fluid type, buttons, fields, cards, accordion, call panel, quote starter, header rows, drawer, footer; **no hex colours**), `assets/js/main.js` (drawer, submenu buttons, sticky offset), `inc/template-tags.php`, `template-parts/header/site-header.php`, `template-parts/footer/site-footer.php`, `header.php`, `footer.php`, `inc/setup.php` (adds `footer_secondary` location and editor styles; removes `custom-logo` support because logos are now set on the Header screen), `inc/assets.php` (early `cic-js` class), `assets/fonts/**`, `assets/brand/logo-original-{384,576,768}.webp` (copied unchanged from the image pack).
- Dev: `dev/tools/build-theme-json.php`, `dev/tests/phase02/{design-test.php,browser-test.cjs,fixtures.php,options.php,auth-cookies.php}`.
- Outside the repo: fixed `C:\laragon\bin\wp-cli\wp.cmd` (see issues).

### Verification actually performed

1. **Server-side** `wp eval-file dev/tests/phase02/design-test.php`: **51/51 passed** (theme.json parity; spec §5 colours, fonts and type scale as defaults; contrast maths 4.73:1 and 4.48:1 match the spec; no failing pairs by default; colour/number/font/weight/type/preset validation incl. CSS-injection strings; inheritance by reference after a global change; explicit preset hex stays local; no font family in presets; global body font reaches the custom properties; overrides sanitized and scoped per block instance and per page; registered meta sanitizes on save; export→import lossless and invalid import rejected entirely; user Global Styles ignored; Font Library off; contact/layout sanitizers). Options snapshotted and restored.
2. **Browser** (headless Microsoft Edge via `puppeteer-core` 23.11.1 installed only in the session scratch folder, not in the project) `node dev/tests/phase02/browser-test.cjs`: **58/58 passed** on the final clean run. It saved settings through the real admin forms (Settings API + nonce) and measured computed styles:
   - Defaults: Fraunces headings, DM Sans body, local font files loaded (incl. Fraunces italic); no external requests for an anonymous visitor; all 9 preset buttons `#D92373`; Wedding 600 + italic; Birthday sky/mint.
   - Responsive: H1 40/44 px at 390, 64/70 at 1440, ≈48.64 at 768; body 16/26 → 18/28.
   - Global action colour → `#1F6FB2`: every preset button, header CTA, call panel and Wedding-section button changed; explicit teal button override stayed local. Same result inside the **block editor** canvas; blocks valid; panel shows "Override active" + reset; editor Reset previews the inherited colour.
   - Global heading colour changed while an element override existed: neighbour changed, override did not; after resetting the override it showed the **current** global colour.
   - Global body font → system sans: body text in every preset, nav and buttons changed; headings stayed Fraunces.
   - Admin Reset buttons remove values from storage; defaults return; local overrides untouched.
   - Global H1 36/72: applied at 390/1440; local heading size override stayed 30/50; neighbour H2 stayed 32/42; page-scope override on page B only.
   - Persistence: fresh browser session after `wp cache flush` + transient flush; anonymous page shows the new token.
   - Contrast: low-contrast action colour → warning on the admin screen and preview; saved value unchanged.
   - Validation via the real form: injected colour, out-of-range size and tampered font each rejected with a message; nothing stored; forged POST without nonce → 403.
   - Preset edit (Birthday accent) changed only Birthday; preset reset restored sky.
   - Header/footer: top row off, bottom row on (hidden on mobile), sticky off, call action on, footer layout "logo · contact", phone change → every `tel:` link updated.
   - Drawer at 390: dialog semantics, focus to Close, scroll lock, submenu disclosure, focus trap, Escape returns focus, ≥ 44 px targets; desktop submenu opens on keyboard focus; sticky header sits below the admin bar with matching `scroll-padding-top`.
   - All options restored exactly to their pre-test state (checked by snapshot comparison).
3. **Regression**: Phase 01 guard suite `guard-test.php phase02-regression` **69/69 passed** (with Phase 01 fixtures, then deleted). Core plugin deactivated: home, fixture page and `/shop/` return 200 with the theme's minimal header, fonts from theme.json, no PHP log entries; re-activated.
4. PHP lint clean on 8.5.1 and 8.2.29. No new debug-log entries from site code during any run.
5. Screenshots (git-ignored) in `dev/tests/output/phase02/`: `01–03` style preview at 1440/768/390 (full page, defaults), `04` preview after the global action change, `05` override fixture, `06` block editor with the override panel, `07` fixture at 390 with size overrides, `08` admin contrast warning, `09` admin presets, `10` header/footer after changes, `11` Header/Footer admin, `12` mobile drawer open, `13` Design System admin. Test log: `browser-test.log`.
6. Cleanup verified: 0 fixture posts, 0 products, the three options absent (approved defaults apply), test login sessions destroyed.

### Issues found and fixed during Phase 02

- **`wp.cmd` was broken since Phase 01** (`C:\laragonin\php…`: the `\b` was lost when written). Fixed and verified; the Git Bash wrapper was unaffected.
- Admin screens did not show "Settings saved." (WordPress files it under `general`). Fixed on both screens.
- Test harness: helper scripts declared `global $args`, hiding WP-CLI's local `$args`, so an early aborted run left test values behind. Found, cleaned (options deleted, 4 CLI test sessions removed, owner's session kept) and fixed; the final run proves exact restoration.

### Unresolved / observations

- Header/footer are functional shared components matched to the mockups' structure (plum top bar, logo left, centred menu, pill CTA; plum footer). The full screenshot comparison against the homepage mockups belongs to Phase 04.
- The original logo is a raster on a white background, so the footer shows it on a light plate. An approved reversed (white) logo can be uploaded on the Header screen when one exists; never recreated.
- Top-row text is an editable placeholder ("Southern California ice cream catering"); mockup minimum-order copy was not used.
- Menus link to routes that do not exist yet (`/treats/`, `/events/…`, `/about/`, `/service-areas/`, policies, `/get-a-quote/`); they 404 until later phases create them.
- Hosting page/CDN cache layer unknown; purge hooks are best-effort until hosting is chosen.
- Not tested: real touch devices and screen readers (keyboard/ARIA checked in headless Edge only); Safari/Firefox; Windows High Contrast; PHPCS (not installed). Editor checks covered the page editor, not the Site Editor (classic theme).
- Mobile drawer breakpoint is fixed at 1024 px (not an admin setting).

---

## Phase 01 log: Foundation and catalogue protection

Prompt: `handoff/cater-ice-cream/prompts/01_foundation_and_catalogue_protection.md`.

### Backups (taken before any change)

`C:\laragon\backup\catericecream\pre-phase01-20261019-151906\` (outside the web root): `catericecream.sql` (mysqldump, 12 tables, completion marker present), `wp-config.php`, `htaccess.txt`, `SHA256SUMS.txt`. Credentials were passed to mysqldump through a temporary options file that was deleted immediately; nothing was printed.

### Local setup changes (owner-authorised)

| Change | Result |
| --- | --- |
| Git | `git init -b main` in project root; allow-list `.gitignore` (excludes WP core, third-party plugins/themes, uploads, `wp-config*.php`, `.env`, keys, `*.sql`, backups, `*.log`, caches, `node_modules`, `vendor`, build output, handoff ZIPs, stray temp files). **No commit made.** |
| WP-CLI | 2.12.0 installed to `C:\laragon\bin\wp-cli\` (SHA-512 verified), wrappers pinned to PHP 8.5.1; project `wp-cli.yml` |
| `wp-config.php` | `WP_DEBUG=true`, `WP_ENVIRONMENT_TYPE=local`, `WP_DEBUG_LOG=C:/laragon/logs/catericecream/debug.log`, `WP_DEBUG_DISPLAY=false`, `display_errors=0`. No other lines touched |
| Timezone | `America/Los_Angeles` |
| Permalinks | `/%postname%/`; `.htaccess` rewrite block generated |
| WooCommerce | 11.2.1 installed and activated (free, wordpress.org). Created Shop/Cart/Checkout/My account pages. No gateways enabled |
| WooCommerce coming-soon | Set `woocommerce_coming_soon=no` (it hid /shop/ from anonymous visitors; it's a cosmetic launch gate, not purchase protection) |
| Theme | `cater-ice-cream` activated (shell only) |

### Files created

- `wp-content/mu-plugins/cic-catalog-guard.php`: standalone purchase guard (see design below)
- `wp-content/plugins/cater-ice-cream-core/`: `cater-ice-cream-core.php`, `uninstall.php`, `src/Autoloader.php`, `src/Plugin.php`, `src/Config.php`, `src/Database/Migrations.php`, `src/Admin/Dependencies.php`, `src/Admin/SiteHealth.php`
- `wp-content/themes/cater-ice-cream/`: `style.css`, `functions.php`, `theme.json`, `header.php`, `footer.php`, `index.php`, `woocommerce.php`, `inc/setup.php`, `inc/assets.php`, `inc/woocommerce.php`, `template-parts/content.php`, `assets/css/main.css`, `assets/js/main.js`
- `dev/.htaccess` (deny all), `dev/tests/phase01/fixtures.php`, `dev/tests/phase01/guard-test.php`
- `.gitignore`, `wp-cli.yml`; updated `CLAUDE.md`, `ENVIRONMENT.md`, this file

### Ownership decisions

| Component | Owns | Does not own |
| --- | --- | --- |
| MU guard `cic-catalog-guard.php` | All purchase prevention and commerce UI suppression. Loads before plugins, cannot be deactivated in wp-admin, no dependency on theme/core | Any content, design or business logic |
| Core plugin | Bootstrap (`CIC\` namespace, PSR-4 autoloader), dependency notices, config defaults (`CIC\Config`), versioned migrations (`CIC\Database\Migrations`, schema v1 = baseline only), Site Health guard check, HPOS/cart-checkout-blocks compatibility declaration. Later: events, product meta, design settings, locations, enquiries | Purchase prevention |
| Theme | Presentation: supports, menu locations (primary, secondary, footer, legal), assets (filemtime versioning), WooCommerce gallery support, minimal accessible templates, theme.json safe defaults (approved palette from spec §5, 1200 px content width) | Business logic, purchase prevention |

### Catalogue guard design

- **Fail closed:** catalogue mode unless `wp-config.php` defines `CIC_COMMERCE_MODE` as exactly `'sales'`. No admin/editor toggle, no filter override.
- **Request layer (`init`, earliest priority):** denies requests carrying WooCommerce cart/checkout/order-pay/account keys (`add-to-cart`, `update_cart`, `apply_coupon`, checkout nonces, `pay_for_order`, `order_again`, registration, etc.) on every web request, because WooCommerce form handlers don't check `is_admin()`. Covers front end, `admin-post.php`, admin-ajax and REST URLs. A GET-only request gets a 302 to the same URL minus the purchase parameters; POST/JSON gets a 403.
- **wc-ajax:** all endpoints denied (empty allowlist), via `$_REQUEST` and via the parsed query var (POST bodies included).
- **wc-api:** denied in query-string and `/wc-api/` path forms.
- **admin-ajax:** explicit shopping actions plus any `woocommerce_*` action with a `nopriv` handler. Priv-only admin actions (product/variation editing) are unaffected.
- **Store API:** `/wc/store[/vN]/…` denied before route matching (`rest_pre_dispatch`, case-insensitive, any version, `?rest_route=`) and again before callbacks for every request including batch sub-requests. Read-only `products*` GET is allowed only for users with `edit_products` (editor previews). Cart, checkout, order and batch are denied for everyone, admins included.
- **Store API dependency audit:** the PHP catalogue makes no public Store API calls, so denying anonymous reads is acceptable per spec §14. If a public read route is ever needed, add it to the allowlist after review.
- **Pages:** cart, checkout (incl. order-pay, order-received) and account pages 302 to the shop.
- **Defence in depth:** `is_purchasable`/`variation_is_purchasable` false, `add_to_cart_validation` false, checkout process throws, no payment gateways outside admin, customer registration forced off, order/customer emails disabled.
- **Presentation:** price HTML and add-to-cart removed from public output (admin keeps prices). Offer removed from product schema. Mini-cart/account/purchase blocks removed from block-hook injection and rendering. Cart/checkout/account shortcodes neutralised. Cart scripts dequeued. Cart/checkout/account pages excluded from core sitemaps. Info notice on product admin screens.
- **No fake zero prices.** The guard never touches price data.

### Verification actually performed

Harness: `wp eval-file dev/tests/phase01/guard-test.php <label>` sends real HTTPS requests to `https://catericecream.test` (anonymous unless noted) and asserts DB/mail/cookie side effects. Fixtures (`fixtures.php`): one **priced, in-stock** simple product and one variable product with two priced variations, marked `_cic_fixture=phase01`. These are worst-case products WooCommerce would normally sell. They were deleted after testing (0 products remain).

| Run | Configuration | Result |
| --- | --- | --- |
| 1 | `cater-ice-cream` theme + core active | **69/69 PASS** |
| 2 | **Theme switched to Twenty Twenty-Five** (block theme with WooCommerce block templates + hooked mini-cart) + core active | **69/69 PASS** |
| 3 | `cater-ice-cream` theme + **core plugin deactivated** | **69/69 PASS** |
| 4 | **WooCommerce deactivated** (guard + core active) | Home/pages 200, unknown routes behave, Store API URL still 403 from guard, core reports missing dependency, Site Health "good", **no PHP errors logged** |

Probes per run (exact paths):
- **A legacy:** `GET /?add-to-cart=<simple>`, `GET /product/…/?add-to-cart&quantity=3`, `GET /?add-to-cart=<variation>` (all 302, parameter stripped). `POST` product form, `POST` variable form with `variation_id`, `POST /wp-admin/admin-post.php add-to-cart`, `POST /cart/ update_cart`, `POST /checkout/ place_order`, `POST /my-account/ register` (all 403). `GET /wp-json/?add-to-cart` (JSON 403). `GET /checkout/order-pay/999999/?pay_for_order…` (302, stripped). `GET /?order_again` (302).
- **B wc-ajax:** `add_to_cart` (query, POST-body, variation), `checkout`, `update_order_review`, `get_refreshed_fragments`: all JSON 403.
- **C admin-ajax:** `woocommerce_add_to_cart`, `woocommerce_checkout`, `woocommerce_apply_coupon`, `woocommerce_get_variation`: all JSON 403. Control: `heartbeat` still 200.
- **D wc-api:** `?wc-api=WC_Gateway_Paypal` and `/wc-api/WC_Gateway_Paypal/`: 403.
- **E Store API:** `GET cart`, `POST cart/add-item` (simple, variation), unversioned `/wc/store/cart/add-item`, `?rest_route=`, upper-case route, `v2` route, `GET`/`POST checkout`, `POST order/{id}`, Store API `batch`, anonymous `GET products`: all JSON 403 `cic_catalog_mode`. Core `/batch/v1` wrapping add-item: 207 with inner 403.
- **F pages:** `/cart/`, `/checkout/`, `/my-account/`, `/checkout/order-received/…`, `/my-account/orders/`: 302 → `/shop/`.
- **G presentation:** simple, variable and shop pages render 200 with product title and **no** price, add-to-cart, variations form, mini-cart, cart scripts or `offers` in JSON-LD (verified on both themes; on TT5 the WooCommerce block template rendered with price/add-to-cart blocks removed). Control: `/wp-json/wp/v2/pages` 200. Sitemap query excludes cart/checkout/account. `is_purchasable()` false server-side.
- **H admin (authenticated):** temporary application password: `wc/v3` GET 200, PUT edit 200 **and persisted**, POST create 201, DELETE 200, Store API products read 200, Store API add-item **403 even for admin**. Temporary cookie session: Products list 200 with catalogue notice, product edit screen 200, priv `woocommerce_load_variations` AJAX 200, WooCommerce settings 200. App password and session token revoked afterwards (0 app passwords remain).
- **I side effects:** guest WooCommerce sessions 0→0, sessions containing cart items 0→0, order posts 0→0, HPOS orders 0→0, users 1→1, customer lookup 0→0, **Mailpit 1→1 (no email)**, **no WooCommerce session/cart cookies issued**, no new site PHP warnings/errors.
- **Data persistence across theme switch and core deactivation:** `cic_core_schema_version=1`, `cic_core_activated_version=0.1.0`, both fixtures and an admin-edited field unchanged after every switch.
- **Other:** all Phase 01 PHP files `php -l` clean on PHP 8.5.1 and 8.2.29. `theme.json` valid JSON. `dev/` returns 403 over HTTP. Mailpit capture verified with one `.test` message (that message is the "1" in the mail count).

### Issues found and fixed during Phase 01

1. Git Bash rewrote `/%postname%/` to `/C:/Program Files/Git/%postname%/` and saved it as the permalink structure. Corrected immediately; WP-CLI wrapper now sets `MSYS_NO_PATHCONV=1`.
2. First WooCommerce install attempt failed inside a timed-out command (output truncated, cause not captured); the retry succeeded in 51 s.
3. **Fatal error in core `/batch/v1`** when the guard returned `WP_Error` from `rest_pre_dispatch` (WP 7.1 core issue; see ENVIRONMENT.md §5). The guard now returns a 403 `WP_REST_Response` there. Re-tested: 207/inner 403, no fatal.
4. WooCommerce coming-soon mode hid `/shop/` from anonymous visitors. Disabled locally.
5. Test-harness bugs (eval-file scope; over-broad session assertion) fixed. The one WooCommerce session row (user #1, `customer` data only, no cart) was created by the authenticated admin checks, not by an anonymous bypass.

### Unresolved / observations

- With WooCommerce **deactivated**, old `/product/<slug>/` URLs still render product text through the generic template because stored rewrite rules persist. No purchasing is possible. Revisit route ownership in Phase 03 (shop base `/treats/`, product permalinks).
- WooCommerce's own Cart/Checkout/My account pages remain published (WooCommerce expects them); the guard redirects them publicly. Decide in Phase 08 whether to set them to draft/private.
- Shop slug is still `/shop/`; spec route is `/treats/` (Phase 03 permalink configuration).
- One WooCommerce session row for user #1 remains (benign, expires automatically).
- Not tested (out of scope / not installed): third-party payment gateways or WooCommerce extensions (none installed; re-audit guard coverage whenever an extension is added); PHPCS/WPCS standards (tools not installed); visual design (no layouts built by instruction).
- (Resolved 2026-10-19: first commit `7f2cbc3` made at the owner's request.)

---

## Phase 00 log (summary)

Read-only inspection of the environment and handoff. Created `ENVIRONMENT.md`, `BUILD_STATUS.md`, root `CLAUDE.md` (imports the handoff `CLAUDE.md`). No installs or DB changes.

## Confirmed architecture (spec v1.1)

| Concern | Decision | Status |
| --- | --- | --- |
| Theme | Custom hybrid PHP theme `cater-ice-cream` + `theme.json` | Shell (01); tokens, fonts, header/footer components (02) |
| Business logic | Plugin `cater-ice-cream-core` (`CIC\` namespace) | Bootstrap (01); Design System, overrides, header/footer/contact settings (02) |
| Purchase guard | `mu-plugins/cic-catalog-guard.php`, fail-closed | Built and verified (01) |
| Editing | Gutenberg core blocks/patterns + ACF Free + Settings API; no ACF Pro, page builder or paid licence | ACF Free 6.8.10 field groups (03) |
| Treats | 4 WooCommerce products, non-purchasable | Seeded; Dippin' Dots fully composed (03) |
| Events | `cic_event` CPT, archive `/events/` | Seeded; Birthday fully composed (03) |
| Pairings | `cic_product_events` table: single source of pairing copy, unique + indexed | Built (03) |
| Cities | Custom tables + virtual routes behind a location-repository adapter | **Pending owner's city code** |
| Enquiries | CF7 (3-step) + Flamingo as sole store | Phase 06 |
| Scheduler | Action Scheduler (bundled, 4.1.0) | Available |
| Mail (local) | Mailpit | Capture verified (01) |
| SEO | One adapter; none installed | Phase 05 decision |
| Design | Original raspberry Cater logo + original palette; lavender experiment not adopted | Design System built (02); defaults = spec §5 |

## Dependencies and pending inputs

| Input | Status | Blocks |
| --- | --- | --- |
| **Owner's city template + rewrite-rule code and schema** | **Pending.** Spec §9 tables are a local fallback only; no competing production schema. Phase 05 uses a fixture until supplied | Final city schema/routes |
| ACF (free) | **Installed (03)**: 6.8.10 | — |
| Contact Form 7, Flamingo | Not installed | Phase 06 |
| Final quote/contact form-field review | Pending | Phase 06 sign-off |
| Approved coverage dataset (LA County, Ventura, Santa Barbara, Compton exclusion, shared ZIPs) | Pending reconciliation | Coverage go-live |
| Reminder timing, assignee fallback | Pending; reminders stay disabled | Reminders |
| Yan / team account emails | Pending | Enquiry-access accounts |
| Hosting/staging, mail sender domain, deployment owner, backup destination, host PHP version | Pending | Phase 10 / launch |
| Licensed font files (Fraunces, DM Sans) + licences | **Done (02)**: OFL, self-hosted WOFF2 | — |
| Vector logo master | Raster only | Print only |
| Client review of AI photos, product options, ingredients, policy copy, About biography | Pending | Launch |
| Is DipN' Bananas a supplier brand? | Provisional brand term | Brand taxonomy |
| Approved reversed (white) logo for the footer | Not supplied; original on a light plate | Footer visual |

## Risks

1. Parent folder `C:\laragon\www` is also a Git repo; this project is nested inside it. Never run git for this project from the parent.
2. Shell PATH PHP (7.4) ≠ site PHP (8.5.1). Always use the `wp` wrapper or Laragon's PHP.
3. PHP 8.5.1 is bleeding-edge; host PHP unknown.
4. WP 7.1 core batch/`rest_post_dispatch` type issue (worked around in guard; could affect other plugins).
5. Any new WooCommerce extension or gateway may add purchase paths: re-run the guard suite and extend it.
6. City implementation arrives later; keep the repository adapter boundary strict.
7. Mockups contain obsolete minimum-order copy, typos and invented marks; follow DESIGN_REFERENCE_INDEX.
8. AI-recreated photos must not be presented as real clients, staff or inventory.

## Phase plan

| Phase | Deliverable | Gate |
| --- | --- | --- |
| 01 ✔ | Theme shell, core plugin, MU guard | Passed 2026-10-19 |
| 02 ✔ | Design System admin + style preview + header/footer controls | Passed 2026-10-19 |
| 03 ✔ | Products + Events models (ACF Free install) | Passed 2026-10-19 |
| 04 | Three-row header/footer + homepage | Approved structure; screenshots 390/768/1440 |
| 05 | Locations adapter, importer, search, virtual routes, SEO | ZIP cases, collisions, canonicals, noindex (fixture until city code arrives) |
| 06 | CF7/Flamingo quote wizard, lead admin, mail, reminders | Save-before-notify proven in Mailpit |
| — | Vertical-slice gate | Before Phase 07 |
| 07 | Remaining event + treat compositions | Screenshot comparison |
| 08 | Archives, hubs, About, Contact, policies, 404 | Navigation and forms complete |
| 09 | Visual, accessibility, performance review | Differences fixed or recorded |
| 10 | Release candidate + operational guide | Backup/rollback rehearsed on staging |

## Next phase

**Phase 04: Homepage and shared components** (`prompts/04_homepage_and_shared_components.md`), only when the owner asks. It should include the visual comparison of the shared header/footer and resolve or confirm the pending footer logo treatment and menu destinations. Phase 03 can be committed first if the owner wants a checkpoint.
