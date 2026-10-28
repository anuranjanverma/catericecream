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
| 04 Homepage and shared components | **Approved by the owner (2026-10-20)**, header logo size restored to the initial Phase 04 values (https://catericecream.test/) | 2026-10-20 |
| 05 Locations, import, search, routes and SEO | **Permanent implementation (owner decision 2026-10-20); tested with local fixtures only, NOT launch-ready** until approved production coverage data is loaded. The historical city code is no longer a dependency | 2026-10-20 |
| 06 Quote flow, lead management, mail and reminders | **Built and verified locally, awaiting owner review.** CF7 + Flamingo installed; quote + Contact forms, store-then-notify, lead admin, reminders (off until timing is set). Production sender, account emails, final field review pending | 2026-10-20 |
| 07 Event and treat compositions | **Eight detail pages built and verified, awaiting owner review.** Built before Phase 06 by owner instruction; the vertical-slice/quote-flow prerequisite is **DEFERRED, not passed** | 2026-10-20 |
| 08 Archives and supporting pages | **Built and verified, awaiting owner review.** Contact submissions + full quote flow pending Phase 06 | 2026-10-20 |
| Service Areas admin enhancement | **Built and verified, awaiting owner review** (Add City, ZIP add/assign/remove, readable coverage controls, navigation, inheritance display, import conflict protection) | 2026-10-20 |
| 09 Visual, accessibility and performance review | **Review done; 9 defects fixed and re-verified; awaiting owner review.** Automated + manual checks recorded below. Not an accessibility-compliance or launch-readiness claim | 2026-10-20 |
| 10 Release candidate and operational guide | **rc1 prepared locally (release preparation only). NOT launch-ready.** Package, staging rehearsal and restore drill done on disposable copies; nothing deployed. Outstanding business inputs and host checks listed in `docs/release/LAUNCH_CHECKLIST.md` | 2026-10-20 |
| Refinements (owner request) | **Built and verified locally, awaiting owner review; committed locally (2026-10-21, not pushed).** Location labels, MM/DD/YYYY date picker, Site Icon, event-field icon, California map previews. The rc1 package is **outdated** until regenerated | 2026-10-21 |
| Desktop compact sticky header (owner request) | **Built and verified locally, awaiting owner review; committed locally (2026-10-21, not pushed).** Desktop (≥1024 px) only; mobile/tablet unchanged. rc1 package still outdated | 2026-10-21 |
| Client updates (2026-10-22) | **Built and verified locally, awaiting owner review; NOT committed, not deployed.** Graduation Parties + Proms, central six-event order, phone 424-235-4942, contact name Alex Frost, copy + American English, draft Kosher FAQ. Logo NOT changed (input needed). rc1 outdated | 2026-10-22 |
| Cities We Serve prefooter (owner request) | **Built and verified locally, awaiting owner review; NOT committed, not deployed.** Dynamic ranked city links above the footer (all public pages except Get a Quote), settings in Header, Footer & Contact | 2026-10-23 |
| Two-step quote form with saved progress (owner request) | **Built and verified locally, awaiting owner review; NOT committed, not deployed.** Step 1 details & favorites → draft on Continue → Step 2 event → "Request My Quote" completes the same Flamingo inquiry; optional details after submission. Retention period and privacy wording pending | 2026-10-23 |
| Header, quote steps and mobile CTA (owner request) | **Built and verified locally, awaiting owner review; NOT committed, not deployed.** Balanced step indicator, "Not sure yet" default, fixed mobile "Get a Quote" bar, About out of the header menu, desktop phone beside the quote button (menu/settings via a reviewed migration) | 2026-10-24 |
| Search dropdowns, hero spacing and authentic treat media (owner request) | **Built and verified locally, awaiting owner review; NOT committed, not deployed.** Dropdowns no longer hidden by the next section; homepage hero fitted to the new headline; real wedding photo and videos on Dippin' Dots, storage photo and dipping clip on DipN' Bananas (media imported by a reviewed migration) | 2026-10-24 |
| Google Analytics 4 (owner request) | **Built and verified locally (production simulated), awaiting owner review; NOT committed, not deployed.** GA4 `G-NXRRDRV5C2` once in `<head>`, only on catericecream.com / www.catericecream.com in production, never for logged-in administrators; admin-editable (Cater Ice Cream → Analytics); settings via a reviewed add-only migration. No form events | 2026-10-25 |
| SEO foundation (owner request) | **Built and verified locally (production simulated), awaiting owner review; NOT committed, not deployed.** Yoast metadata for all marketing pages, Organization schema, utility/parameter noindex, clean sitemaps with location lastmod, city indexing needs approved coverage. Live Reading setting still needed. Details: `docs/release/SEO_2026-10-28.md` | 2026-10-28 |
| QA fixes (owner request) | **Built and verified locally, awaiting owner review; NOT committed, not deployed.** Brand CaterIceCream.com, quote step spacing, form failure handling (live cause pending evidence), indexing/canonical (live Reading setting needed), Yoast location SEO + sitemap, 90224 exclusion, privacy draft + automatic links, Contact card, alt text; graduation image needed. Details: `docs/release/QA_FIXES_2026-10-26.md` | 2026-10-26 |

Git (local only, no remote): `7f2cbc3` Phase 00–01 baseline; `ccc05e9` Phase 02; `d055da2` Phase 03; `70479ad` Phase 04 + visual correction pass; `78b50d8` homepage approval + header logo restored to initial Phase 04 size; `6f72409` Phase 05; `215515a` Phase 07; `27005ca` Phase 08. Each was committed at the owner's request after reviewing the staged set. `800e07b` Service Areas admin enhancement; `1823bc2` Phase 06; `776a635` Phase 09; `23935f5` Phase 10 release candidate code (staging mail guard + release tooling, **rc1 code commit**); Phase 10 documentation commit follows it (both hashes recorded in the package `VERSION.json`). Nothing pushed.

---

## SEO foundation (owner request, 2026-10-28)

**Not committed, not deployed.** Backup before the local settings migration: `C:\laragon\backup\catericecream\seo-20261028-20261028-231725\` (database, `wp-config.php`, SHA256SUMS, `seo-report.json`). Full write-up (audit, page mapping with final titles/descriptions, indexing and sitemap rules, location SEO, deployment, rollback, backlog): `docs/release/SEO_2026-10-28.md`.

**Summary**
- Code (core plugin `src/Seo/{Service,YoastAdapter,LocationSitemap}.php`, `src/Locations/DatabaseRepository.php`, `src/Locations/Admin/LocationsPage.php`): quote/contact query variants and Woo utility pages noindex; Woo utility + duplicate shop page out of the sitemap; Organization fields from Header, Footer & Contact; no SearchAction; Service → #organization + mainEntityOfPage; event WebPage name/description = title/description; city indexability also needs ≥1 enabled production ZIP (`is_served()`, shown in the city screen checklist); locations sitemap + index entry with `lastmod`.
- Settings/metadata (`dev/tools/content/seo-20261028.php`, dry run + report, add-only; 44 changes locally): separator "|", Organization + mascot Site Icon logo, author/date archives off, category/tag/product taxonomy archives noindex, Events archive title/description, default social image, titles/descriptions for Home, Treats, Service Areas, About, Contact, Get a Quote, four treats and four events, sample post to Draft.
- Found, not changed: GA4 receives no conversion events (`dataLayer` object pushes without GTM); no contact or phone-click event. Service Areas and California directory overlap. Prefooter links to noindex cities. All in the backlog.

**Verification (automated):** seo-test 19/19 (new), qa-test 17/17 (I4 updated: receipt URLs now noindex), phase05 49/55 (same 6 known fixture-data failures), service-areas admin 40/42 (known B7, H1) + browser 21/21, cities 18/18, phase07 23/23, phase03 46/46, analytics 15/15, client-update 35/35, nav-quote 38/38, media 27/27. Not run: guard test (no Woo/guard/REST change; needs fixtures), phase09 audit (no visual change).

**Not tested:** anything on the live site; Rich Results Test / Search Console (no access); `wp yoast index` (refuses on non-production by design).

---

## QA fixes (owner request, 2026-10-26)

**Not committed, not deployed.** Backup: `C:\laragon\backup\catericecream\pre-qa-fixes-20261026-115200\` (database, `wp-config.php`, `.htaccess`, SHA256SUMS, brand migration reports). Full write-up (causes, files, deployment, live settings, live checks for the form failure, rollback, tests and test records): `docs/release/QA_FIXES_2026-10-26.md`.

**Summary**
1. **Brand → CaterIceCream.com:** code defaults + `dev/tools/content/brand-name-20261026.php` (65 changes locally; rerun 0). Logos already correct (unchanged). Inquiries, CF7 forms, slugs and URLs untouched.
2. **Quote step headings:** gap 6 px → 16 px (phone) / 20 px (≥640 px) via `--cic-space`.
3. **Form failures:** not reproduced locally (all suites pass). Fixed three confirmed client defects in `quote.js`: unrelated rejected promises no longer trigger the error; an answered-but-not-JSON request (e.g. security page) shows "didn’t go through … call us"; a replayed receipt after a lost response now records its conversion once. Live checks listed in the write-up §4.
4. **Indexing:** reproduced with "Discourage search engines" (live setting to change). Non-production sites are now always noindex (`Seo\Service::protected_environment()`).
5. **Location SEO:** new `Seo\YoastAdapter` (titles, descriptions, canonical, robots, OG/Twitter, schema pieces inside Yoast’s graph, `cic-locations-sitemap.xml`); state route now uses its own title/canonical (pre-existing bug); city default title includes the state and a default description. Cities stay noindex until their content is reviewed (existing rule; none reviewed yet).
6. **90224:** production exclusion via the Importer (`compton-90224-20261026.php`); owner to confirm (and decide on 90223).
7. **Privacy:** no approved policy; editable draft saved (still draft) by `privacy-draft-20261026.php`; footer/form links appear automatically once published.
8. **Contact card:** two real steps.
9. **Graduation image:** none available; slot and size specified in the write-up.
10. **Alt text:** 57 concise descriptions by asset id (`alt-text-20261026.php/.json`).

**Verification (automated):** qa-test 17/17, QA browser 19/19, quote-flow 34/34, phase06 browser 35/35 (enquiry 46/49, submit 25/27: known fixture-data failures), phase05 49/55 (known fixture-data failures; SEO checks updated), client-update 35/35, nav-quote 38/38, media 27/27, header 30/30, refinements 46/46, phase08 24/24 + 11/11, phase07 23/23, phase02 51/51 + 58/58, phase03 46/46, phase04 25/25, cities 18/18, analytics 15/15, phase09 review 7/8 (R8 known) + interaction 7/7, audit 390/1440 axe 0. Tests updated: quote-flow V1 (wait for the message), phase05 L6/L7 (Yoast adapter + environment guard), client-update N1 (no canonical while noindex).

**Not tested:** anything on the live site, real devices, Safari/iOS, Firefox, screen readers.

---

## Google Analytics 4 (owner request, 2026-10-25)

**Not committed, not deployed.** Deployment steps: `docs/release/CHANGESET_2026-10-22_23.md` (group 7). No database backup was taken before the local settings migration: it only added one new option (`cic_analytics`), which `wp option delete cic_analytics` removes.

**Inspection before building (what was found)**
- **Code:** no Google tag, gtag, GTM container, Measurement ID or consent code in the theme, the core plugin or the MU plugins.
- **Plugins:** active ACF, core plugin, CF7, Flamingo, UpdraftPlus, WooCommerce, Yoast SEO 28.7 (Akismet inactive). None of them outputs a Google tag. No Site Kit, GTM4WP, MonsterInsights, header-script plugin or consent plugin is installed. `woocommerce_analytics_enabled` is WooCommerce's internal admin reports, not Google Analytics.
- **Database:** no `googletagmanager`, `gtag(` or `GTM-` in options, post content or post meta.
- **No central tracking settings existed**, so the integration is a new core-plugin module (as requested).
- **Existing dataLayer use:** `quote.js` already pushes keys-only quote events (`quote_step_view`, `quote_step_completed`, `quote_validation_error`, `coverage_result`, `quote_submitted`) to `window.dataLayer` when it exists. They are unchanged. Once the tag is live, these objects sit in the dataLayer; they are not gtag commands, and no event command was added.
- **Receipts in URLs:** the quote/contact confirmation uses `?received=` / `?sent=` (random, non-personal, expiring receipts). GA4 would otherwise record them through the page load, history-change page views (default enhanced measurement) and the next page's referrer.

**What was built**
- `CIC\Analytics\Service` (core plugin): prints the tag once (`wp_head`, priority 10, after enqueued head scripts so a future consent plugin's defaults come first). Conditions, all required:
  - enabled + valid Measurement ID (`G-` pattern);
  - `wp_get_environment_type() === 'production'`;
  - both the site address host and the requested host are `catericecream.com` or `www.catericecream.com`;
  - not a logged-in administrator (`manage_options` or super admin);
  - a public front-end page (not wp-admin, AJAX, REST, cron, CLI, feed, preview, Customizer, embed).
- **Snippet:** exactly the approved snippet, plus ONE line between `gtag('js')` and `gtag('config')` that sets `page_location` and `page_referrer` with `received`/`sent` and the `#fragment` removed. `window.dataLayer = window.dataLayer || []` keeps any existing dataLayer; no consent state is set, so existing/future consent controls stay in charge.
- `CIC\Analytics\Settings`: **Cater Ice Cream → Analytics** (administrators only): on/off, Measurement ID (validated; junk keeps the previous value), live status line ("Off on this site: the environment is local"), and a warning when an active plugin can add its own Google tag (Site Kit, GTM4WP, MonsterInsights, ExactMetrics, header-script plugins, Google for WooCommerce, ...). Option `cic_analytics`, code defaults off/empty (the ID is not hardcoded).
- **Migration** `dev/tools/content/analytics-20261025.php [dry-run] [replace-id]`: duplicate-plugin pre-flight, then adds `{enabled: 1, measurement_id: G-NXRRDRV5C2}` only when the option does not exist; an existing value (another ID or "off") is reported and kept (`replace-id` replaces only the ID). Local run: dry run → apply → rerun "already done".

**Files**
- New: `wp-content/plugins/cater-ice-cream-core/src/Analytics/{Service,Settings}.php`; `dev/tools/content/analytics-20261025.php`; `dev/tests/analytics/{analytics-test.php,browser-test.cjs}`.
- Changed: `wp-content/plugins/cater-ice-cream-core/src/Plugin.php` (registers the service); docs (`BUILD_STATUS.md`, `CLAUDE.md`, `docs/release/{CHANGESET_2026-10-22_23,ADMIN_GUIDE}.md`).

**Verification actually performed (automated, 2026-10-25)**
- `dev/tests/analytics/analytics-test.php`: **15/15.**
  - Rules: 12 environment/host combinations (only production on the two hosts is eligible; staging, development, local, `.test`, localhost, staging host, IP and look-alike hosts are not); off / invalid ID; sanitising (a found bug was fixed: tag-stripping turned junk input into an empty ID); snippet = approved snippet + one redaction line, no consent command.
  - This local site, real HTTP: **no** tag, GTM or ID on 12 pages (home, both treats, two events, Get a Quote with and without a receipt, Contact, About, Service Areas, a city page, a 404).
  - Simulated production (temporary MU plugin + per-run header, removed afterwards): **exactly one** installation inside `<head>` on the same 12 pages; one on `www`; **none** for staging, development, a `.test` request host, a staging host, localhost or a wrong token; none on `wp-login.php`, REST and `/feed/`; **none for a logged-in administrator** (admin bar present).
  - The admin screen renders for an administrator with the saved ID and the local status.
- `dev/tests/analytics/browser-test.cjs` (Edge, gtag.js answered by a local stub, nothing sent to Google): **16/16.** Local: no tag element, no `gtag`, no Google request. Simulated production: one tag element in `<head>`; a pre-existing dataLayer entry kept; gtag commands exactly js → set → config; clean `page_location` (receipt and fragment dropped, context key kept) for `?received=` and `?sent=`; `page_referrer` without the receipt; the existing quote pushes still arrive, carry keys only and add no gtag command; staging / `.test` host: no tag; page height and headline box identical with and without the tag; no console errors.
- Regression: phase02 design 51/51; phase09 review 7/8 (R8 = the known production location data, unchanged); debug log has no new entries.

**Not tested / not run**
- `--real-gtag` mode of the browser test (loads Google's real library with every hit blocked) was **not run**, because it contacts Google from the `.test` site; so what Google's own library does with the existing quote dataLayer objects is not verified here.
- No real production request, Realtime report or Tag Assistant check (possible only after deployment); phase01 guard test not run (needs fixtures; no WooCommerce/REST change).

**Owner decisions / GA property settings (outside the code)**
- Privacy policy: mention Google Analytics before or at launch (the policy is still a draft). Consent banner: none exists; decide whether one is wanted (the tag respects one added later).
- Recommended in GA4 Admin: Data streams → Enhanced measurement → turn **off "Form interactions"** (otherwise GA sends automatic form_start/form_submit events; conversions are planned separately); Redact data → query parameters `received`, `sent` (second layer) and keep email redaction on; review Google signals and data retention.

---

## Search dropdowns, homepage hero and authentic treat media (owner request, 2026-10-24)

**Not committed, not deployed.** Checkpoint before the media import: `C:\laragon\backup\catericecream\pre-treat-media-*\` (database, `wp-config.php`, `.htaccess`, SHA256SUMS). Deployment steps: `docs/release/CHANGESET_2026-10-22_23.md` (group 6).

**1. Search dropdowns behind the next section (cause and fix)**
- **Cause:** every composition section's content layer is `position: relative; z-index: 1` (`compositions.css`: `.cic-comp > .cic-container`, so content sits above decorative balloons), and each one is a separate stacking context. The location suggestions (`z-index: 20`) and the date picker are trapped inside their section's layer, and the next section's layer (also `z-index: 1`, later in the page) paints over them; at 1440 px it covered 4 of 6 home-hero suggestions. `home.css` intended `z-index: 3` for the hero search wrapper, but the more specific composition rule overrode it. No `overflow` clipping was involved (sections clip only horizontally) and no decorative overlay intercepted clicks.
- **Fix:** `.cic-comp:has(.cic-ac-list:not([hidden]), .cic-dp:not([hidden])) { z-index: 2; }`. Only the section that shows an open popover is lifted, one step above the following sections and below the sticky header (50) and the mobile quote bar (40). It applies to every template using the shared search/starter fields; nothing changes while no popover is open.

**2. Homepage hero after the headline change**
- **Cause:** the new headline "Your Special Occasion. / Our Special Treat." is 11.25 em per line (the approved copy was about 6.6 em). At the 72 px desktop size each line wrapped (4 lines), the text column grew taller than the photo, and the search card's intentional 2.5 rem overlap landed on the intro ("parties…" was hidden at 1440 px).
- **Fix (`home.css` ≥ 900 px, `section-hero.php`):**
  - The text column keeps the 2.5 rem overlap band free, so the intro can never sit under the card whatever the copy.
  - The headline shrinks just enough for each line to fit its column. The longest line's character count comes from the template, using about 0.53 em per character of the display face.
  - It never exceeds the Design System H1 size or goes below its mobile size (44 px); the line height keeps the token ratio.
- **Results:** two lines at 1440 (52 px) and 1280 (48 px); three or four lines at 1024–1100 and 900 px (44 px). Phones and tablets are unchanged (four lines at 44 / 54 px, already clear of the card).

**3/4. Authentic treat media**
- **Supplied files found:** all five, in `handoff/Cater_Ice_Cream_WebP_Assets/treats/` (none missing).
- **Measurements:** the wedding photo is 1600×1066 and the storage photo 1200×1600. The videos are H.264/AAC MP4:
  - serving: 45.4 s, coded 1024×576 with a 90° rotation, so 576×1024 portrait;
  - birthday: 24.1 s, 576×1024;
  - banana dipping: 99.9 s, coded 224×128 with a 90° rotation, so 128×224 portrait (14 MB).
- **New "Photos and videos" section** (`media`) in the shared split composition after Product detail:
  - Fields: ACF Free (image, file, textarea), heading, intro, 4 slots of photo/poster + optional video + caption. `Fields::MEDIA_SLOTS`, `ViewModel::media()`, `template-parts/treat/section-media.php`, `compositions.css` media block.
  - It renders only when filled in, so Classic Ice Cream and Frozen Yogurt keep their compositions.
- **Video player:**
  - Native `<video controls playsinline preload="none">` with a WebP poster from the same footage, width/height and aspect ratio reserved, named by its caption; no autoplay, muted or loop.
  - Only one video plays at a time (`main.js`).
  - Low-resolution clips (< 360 px wide) are capped at 1.5× their size.
- **Placements:**
  - **Dippin' Dots:** the wedding setup photo replaces the AI macro in Product detail (the section about serving arrangements and staffed service; half-width). Photos and videos: serving video first (largest), birthday video second.
  - **DipN' Bananas:** "Behind the scenes", with the storage photo first and the dipping clip as a small secondary player. No banana assets on the Dippin' Dots page and vice versa.
- **Build and import:**
  - `dev/tools/content/build-treat-media.cjs` (local, headless Edge): photos to WebP at their own size; the wedding photo is cropped to leave out the sign with the couple's names and date; posters from real frames at the video size, rotation applied by the browser; videos copied unchanged; SHA256SUMS.
  - Package: `C:\laragon\release\catericecream\media-20261024-treats\`.
  - `dev/tools/content/treat-media-20261024.php [dry-run] [source=…]` imports by stable asset key (`_cic_asset_id`):
    - it checks checksums and never duplicates on reruns;
    - it stores the rotated display size (`_cic_video_display`) and links posters as video thumbnails;
    - it fills fields only while empty or still seeded.
  - Local run: dry run, import (#1031–#1038), re-run "already done"; one attachment per key.

**Verification (actual results)**

| Check | Result |
| --- | --- |
| `dev/tests/media/browser-test.cjs` (new) | **27/27**, covering:<br>• **dropdowns:** suggestions never covered on 6 templates (home hero, service-areas hub, city hero, event starter, two treat starters) × 1440/768/390; lowest visible option selectable by mouse and touch; keyboard ArrowDown+Enter; date pickers fully clickable; only the section with an open popover is lifted, then restored;<br>• **hero:** intro clear of the card at 1440/1280/1100/1024/900/768/390, two lines at 1440/1280, never below 44 px, no overflow;<br>• **media:**<br>&nbsp;&nbsp;– wedding photo in Product detail; section order; video order, posters and attributes; portrait 9:16;<br>&nbsp;&nbsp;– no video bytes while loading or scrolling; pressing play plays the serving video (decoded 576×1024), and only it is fetched; the second video pauses the first; focus + Space plays;<br>&nbsp;&nbsp;– axe 0;<br>&nbsp;&nbsp;– bananas: storage photo first, not the hero; no cross-mixing; banana clip ≤ 192 px and plays at 128×224;<br>&nbsp;&nbsp;– 390 px media fit, captions ≥ 13 px;<br>&nbsp;&nbsp;– Classic/Yogurt unchanged; no JS errors. |
| Updated / re-run suites | Phase 04 browser 22/22, home 25/25 (E2 now counts image-pack masters only: still 57); header 30/30; nav-quote 38/38; refinements 46/46; Phase 07 server 23/23 (split sections now include media), browser 13/13, admin 7/7; Phase 03 catalog 46/46 (File added to the ACF Free type list); Phase 02 design 51/51; Phase 09 review 7/8 (R8 = production location data, as before) |
| Phase 09 | interaction **7/7**; audit 390 px and 1440 px, 20 pages each: axe-core 0, no overflow, no small targets, max CLS 0.058 / 0.048; treat pages ≈1.0–1.3 MB on load with no video bytes; only the deliberate 404 link fails |
| Admin | Treat page composition → Photos and videos shows the heading, intro, posters, video files (descriptive titles) and captions, all editable; Product detail shows the wedding photo |
| Manual visual review | Screenshots of both media sections and the Dippin' Dots detail at 1440/390 and the hero at seven widths. Found and noted: blank hero insets in some headless first-paint screenshots, which the images repaint correctly on reload (a headless Edge artifact, not a page fault) |

Not tested: real phones, iOS Safari (playsinline and rotation are standard there, but not exercised), Firefox, screen readers, real network speeds. The videos have no caption/subtitle track (none supplied; they show service and decor rather than speech, but a track is needed if spoken content matters).

**Owner decisions:**
- confirm publishing permission for the couple and for the children (serving clip; the birthday clip shows a child's first name on the cake and the banner);
- keep or undo the crop of the wedding photo's name sign;
- supply a higher-quality banana clip.

---

## Header, quote steps and mobile CTA (owner request, 2026-10-24)

**Not committed, not deployed.** Checkpoint before the menu/settings change: `C:\laragon\backup\catericecream\pre-header-nav-20261024-194934\` (database, `wp-config.php`, `.htaccess`, SHA256SUMS). Deployment steps: `docs/release/CHANGESET_2026-10-22_23.md` (group 5).

**What changed**
- **Step indicator** (`quote.css` progress block; `Forms::steps_tag()`; `quote.js` `show()`):
  - Layout is [Step 1] — flexible connector — [Step 2]. Step 1 keeps its natural width; Step 2 takes the rest and draws the connector before its button, so the gaps on both sides of the line are equal (`--cic-qf-step-gap`, 12 px, 8 px on phones).
  - Circles, labels and line share one centre line. Long labels wrap on phones (max 7.5rem, 13 px) instead of overflowing.
  - States are unchanged (current / completed / upcoming). The current step keeps `aria-current="step"`; a completed step now also reads "(completed)" to screen readers.
- **Default treat** (`quote.js`, `quote.css`):
  - "Not sure yet — help me choose" is selected whenever no valid treat is chosen: on a fresh form, and again when the last treat is removed. It also cannot be unticked into "nothing selected".
  - An incoming treat (link from a treat page, session context) or a restored choice wins. Invalid incoming keys are ignored.
  - This is page state only: nothing is sent before Step 1 Continue, and the draft and submission carry exactly the visible choice.
  - Selected look: 2 px border, soft surface and a ✓ box, matching the tiles.
  - Without JavaScript the old single-page fallback is unchanged (an explicit choice is still required).
- **Mobile "Get a Quote" bar** (`template-parts/footer/mobile-quote.php`, `cic_theme_mobile_cta_url()`, `main.css` §10d, `main.js`):
  - Shown below 600 px only (the same breakpoint that hides the header quote button, so there is never a second bar), on every public page except Get a Quote (form and success state).
  - A normal link to the Get a Quote page permalink. The bar is the last element of the page with `position: sticky; bottom: 0`, so it reserves exactly its own height at the end of the page and never covers the footer. Its bottom padding adds `env(safe-area-inset-bottom)`.
  - Hidden instantly while the menu drawer or any open dialog (e.g. the date picker) is on screen, or while a form field has focus. It is also hidden in print.
  - Setting: Header, Footer & Contact → Mobile navigation → "Phones: fixed Get a Quote button" (`mobile.sticky_quote`, default on, so no migration is needed).
- **About out of the header menu:** removed from the menu assigned to the header (local item #32) by `dev/tools/content/header-nav-20261024.php`. The About page, its URL and the footer link (#287) are unchanged. The fresh-install menu plan (`Design\Cli::navigation_plan()`) matches.
- **Desktop phone beside the quote button** (`site-header.php`, `main.css` §8):
  - The existing main-row call action (`main.show_call`) is now "Desktop: phone number beside the quote button": icon plus number, desktop only, immediately before "Get a Quote". It reads the central `business_phone` setting; the accessible name is "Call 424-235-4942".
  - Default on in code; the saved value is turned on by the migration.
  - From 1200 px the number is shown. Between 1024 and 1199 px the menu, number and button do not fit on one line beside the approved 190 px logo (measured shortfall 70 px at 1024), so the same link is a 44 px round phone icon there.
  - Mobile and tablet headers are unchanged: menu button, and the call action in the drawer.
- **Also fixed:**
  - With the owner's Media Library logo, WordPress core gave the header logo `fetchpriority="high"`, competing with the hero photo (phase04 D4). The logo now uses `fetchpriority="auto"`.
  - Test corrections for the owner's logo (phase02 H4), the shorter menu (phase02 T14.5, phase04 N1, phase08 B7/P6.2), the treat default (phase06 U1), and fixed pauses replaced by waits in refinements L7/L9 and phase04 E1 (the local server takes 1–1.7 s per REST request).

**Migration (menu and settings only):** `wp eval-file dev/tools/content/header-nav-20261024.php [dry-run] [update-phone]`.
- **Actions:**
  - removes top-level header-menu items that point to the About page;
  - turns on `main.show_call` if it is saved as off;
  - changes `business_phone` only if it still holds the previous number (818) 726-9700 (any other number is reported and kept unless `update-phone` is given).
- **Properties:** WordPress APIs only (any prefix); idempotent.
- **Local results:**
  - dry run listed the 2 changes;
  - the apply run removed #32 and turned the phone on;
  - the re-run reported "already done";
  - the old-number and unknown-number branches were checked with the value restored afterwards.

**Verification (actual results)**

| Check | Result |
| --- | --- |
| `dev/tests/nav-quote/browser-test.cjs` (new) | **38/38**, covering:<br>• step indicator geometry at 1440/768/390 (equal gaps, one centre line, no overflow or clipping, ≥ 13 px labels);<br>• Step 1 / Step 2 / back states with `aria-current` and "(completed)";<br>• fresh default, treat ↔ "Not sure yet" switching, last treat restores the default, no request before Continue;<br>• draft = "Not sure yet", then two treats autosaved to the same draft, submitted record and summary = the two visible treats;<br>• incoming treat kept, invalid one ignored;<br>• About gone from the desktop menu and drawer (order kept), About page 200 + footer link;<br>• phone before the button with the exact `tel:` URL and name;<br>• no text wrapping or overlap at 1024/1100/1199/1200/1280/1440 and in the compact header (1440, 1024);<br>• icon-only form below 1200; dropdowns; 768/390 headers unchanged;<br>• mobile bar: pinned, correct link, ≥ 44 px, rests below the footer, keyboard reachable, hidden while typing, drawer or date picker, shown on event/treat/contact/service-area pages, absent on Get a Quote, absent at 768/1440, works without JavaScript;<br>• axe-core 0 in the header and the bar; no JS errors; cleanup done. |
| Updated suites | quote flow **34/34**; Phase 06 browser **35/35**; header **30/30**; client update **35/35**; refinements **46/46**; Phase 04 browser **22/22**; Phase 08 browser **11/11**; Phase 02 browser **58/58**; Phase 09 interaction **7/7** (first run caught a real issue, fixed: the bar slid away over 0.2 s, so a focused Contact field was briefly covered at 390 px; hiding is now instant) |
| Server-side | Phase 02 design **51/51**; Phase 04 home **25/25**; Phase 08 pages **24/24**; Phase 03 catalog **46/46**; Cities **18/18**; Phase 06 server 46/49 and submission 25/27, Phase 09 review 7/8 (the same production-location-data cases as before: B1, C2, C5, S4, S12, R8) |
| Phase 09 audit | 390 px and 1440 px, 20 pages each: axe-core 0, no overflow, no small targets, max CLS 0.081 / 0.048; only the deliberate 404 link fails |
| Manual visual review | Screenshots of the step indicator (320/390/600/768/1440, both steps), header (1024/1200/1440 and compact) and the bar (390 top and footer) inspected. Fixed: "Your event" wrapped needlessly at 390 px; the header wrapped at 1024–1179 px with the full number |

Not tested: real iPhones (the safe-area inset only takes effect where the browser reports one; with the current viewport meta, Safari keeps content out of the home-indicator area itself), Safari, Firefox, screen readers, the 768 px full audit, the WordPress toolbar on phones with the bar (the toolbar is at the top). The guard test was not run (no WooCommerce, guard or REST change in this task; its fixtures are not created).

**Found during this task (not changed):** Yoast SEO 28.7 was installed and activated on 2026-10-24 (20:43) outside this task. By design, the site's own SEO adapter (`CIC\Seo\Service::adapter()`) steps aside when an SEO plugin is active. Yoast now outputs titles, robots, schema and sitemaps. Local pages show no canonical or meta description and are noindex because search engines are discouraged locally. Yoast does not apply the city-page review rule (noindex until published + Index + reviewed content), the location sitemap or event Service schema. Owner decision needed before deployment (see Outstanding decisions in the changeset).

---

## Two-step quote form with saved progress (owner request, 2026-10-23)

Deployment files, storage, admin and notification behavior: `docs/release/CHANGESET_2026-10-22_23.md`. **Not committed, not deployed.** Checkpoint: `C:\laragon\backup\catericecream\pre-quote-drafts-20261023-223648\` (database, `wp-config.php`, `.htaccess`, SHA256SUMS).

**What changed**
- `CIC\Enquiries\Drafts` (new):
  - Draft-only REST routes `cic/v1/quote-draft` (Step 1 Continue: validate Step 1 on the server, then create or update) and `cic/v1/quote-draft/save` (autosave), protected by same-origin + `X-CIC-Draft`.
  - Access: HttpOnly/Secure/SameSite=Strict cookie `cic_qd` (only an HMAC is stored) or the in-page client key; numeric ids never authorize.
  - Allowlisted, length-limited fields; honeypot + signed render time + per-IP limit for creation, per-draft save limit.
  - Idempotent creation through a `cic_submissions` row (`form = draft`, channels `skipped`, no Flamingo id).
  - Revision claims (atomic conditional UPDATE) under a per-draft `GET_LOCK` (name includes the prefix).
  - `complete()` builds exactly the arguments CF7's Flamingo module would store and saves them on the same message; then `_cic_stage = submitted`, revision raised to the maximum.
  - Optional details (notes, theme, dietary) for 24 hours.
  - "At a glance" summary only for the owning browser.
  - Retention cleanup only when `draft_retention_days` is set.
- `Pipeline`: completes the visitor's incomplete draft instead of storing a new message; the response adds the glance summary and the optional flag; `cic_draft_key` is stripped from stored data. `Workflow::init(…, keep_status)` / `set_context()`: a completed draft keeps a status the team set. `Store`: draft-key rows deleted after a day (orphan rule skips them); `receipt_flamingo()`. `Notifier` maintenance runs the (opt-in) draft cleanup. `Settings`: `draft_retention_days` (0 = not set).
- `Forms`:
  - two-step markup;
  - six occasion tiles in the central order plus "Something else" as a secondary chip;
  - venue, start time and duration as a compact secondary row;
  - consent line ("By continuing, you agree that we may contact you about your event.") with the Privacy Policy link once published;
  - "Request My Quote";
  - hidden `cic_draft_key`;
  - notes, theme, dietary and flavours removed from the main steps; the `[cic_summary]` tag removed.
- `quote.js`:
  - nothing is sent before Continue;
  - Continue saves Step 1 (button disabled, error with "Try again", retries reuse the key);
  - autosave of text fields on blur and selections after 400 ms, with full snapshots, strictly increasing revisions, one request at a time, quiet retries and no saving messages in the steps;
  - coverage pre-check moved to "Request My Quote";
  - autosave stops on submission;
  - confirmation fills "Your event at a glance" and autosaves the optional details with discreet saving / saved / retry feedback.
- Confirmation component: glance box and optional section (server-rendered on reload only for the owning browser). Admin: Incomplete / Submitted pills, Completion filter, Completion box, last activity, no New count for drafts, CSV `stage`. CSS: `quote.css` two-step block.
- Draft Privacy Policy: flagged "Quote requests and saved progress" section added by `dev/tools/content/privacy-quote-drafts.php` (still a draft; wording and retention need approval).

**Verification (actual results)**

| Check | Result |
| --- | --- |
| `dev/tests/quote-flow/browser-test.cjs` (new) | **34/34**, covering:<br>• Step 1 layout (fields, heading, consent, no optional fields, no capture notice; four treats on one desktop row; name/email side by side);<br>• typing sends and stores nothing;<br>• invalid email: client error, and the server also rejects (422);<br>• a failed Continue shows an error with "Try again", then exactly one Incomplete draft;<br>• HttpOnly/Secure/Strict cookie, nothing in the URL or storage;<br>• six occasions in order on one row, no clipped labels; field order and secondary row; link values kept;<br>• autosave updates the same draft with the newest value and no per-keystroke requests; no saving messages; an older save is ignored;<br>• Back keeps values and edits update the same draft;<br>• another browser is rejected (no cookie, guessed key, missing header, forged cookie with the real id);<br>• three simultaneous Continue requests produce one inquiry;<br>• an excluded/ambiguous ZIP stops submission;<br>• the final submission completes the same inquiry with the latest values;<br>• "Your event at a glance" shows contact, event and treats (no empty rows, no booking claims);<br>• exactly one operator email and one acknowledgement (Mailpit, synthetic addresses);<br>• a late autosave cannot revert or change the submission;<br>• optional details saved to the same inquiry with no new mail, and status/assignee untouched;<br>• reload shows the summary only to this browser, not in another browser;<br>• admin: Incomplete pill, last activity, filter, Completion box (never "abandoned"), then Submitted with the optional details;<br>• 390 px: no overflow, stacked contact fields, two treats per row, occasions in two rows of three;<br>• axe-core: no violations in Step 2 or the confirmation; no JS errors; cleanup done. |
| `dev/tests/phase06/browser-test.cjs` (updated to two steps) | **35/35**: entry points, Step 1 errors, treat rules, phone preference, Continue, Other occasion / date not set, Compton blocked, unknown ZIP asks for the venue city, Back/history, double-click → one enquiry, saved context, analytics without personal data or draft key, refresh, keyboard, network failure + retry → one enquiry, mobile, no-JS (both steps on one page; server errors; saved once), Contact form, mail failure, admin list/retry/workflow/export/permissions, store-before-notify |
| Other suites | Phase 06 server 46/49 (B1, C2, C5 = production location data, as before); submit 25/27 (S4, S12, same cause); refinements **46/46** (updated to the two steps: location labels, date picker, touch, icons, map, one complete submission); client update **35/35** (logo widths now read from the settings: owner set 135 px mobile; the acknowledgement keeps the owner's edited sign-off); header **30/30** (baseline reads the configured logo widths); Phase 09 interaction 7/7; audit at 1440 px: 20 pages, axe 0, no overflow, no small targets |
| Manual visual review | Step 1, Step 2 and the confirmation at 1440 and 390 px. Fixed: double divider under "Not sure yet"; misaligned secondary labels; truncated duration placeholder; an empty "Serving" row (CSS `display:grid` overrode `[hidden]`); per-field "(optional)" in the optional section |

Not tested: Safari, Firefox and real devices, screen readers, real email delivery, the 390/768 px full audits for this change (1440 run only), behavior with cookies fully blocked (covered by design through the in-page key, not exercised in a browser).

**Outstanding decisions**: the retention period for incomplete inquiries; approval of the privacy wording and publication of the policy (the form shows the Privacy Policy link only once published); confirmation of the production location data loaded on 2026-10-21.

---

## Client update log (2026-10-22)

Content handoff notes, outstanding inputs and editing steps: `docs/content/CLIENT_UPDATE_2026-10-22.md`. Photo inventory: `docs/content/PHOTO_INVENTORY.md`. **Not committed, not deployed.** Checkpoint: database + `wp-config.php` + `.htaccess` backup `C:\laragon\backup\catericecream\pre-client-updates-20261022-104440\` (SHA256SUMS) and git tag `checkpoint-pre-client-updates` (= `cda2878`).

**What changed (code)**
- Central event order = each event's `menu_order` (Order field). `Catalog::selectable_events()` already used it; added `Catalog::event_rank()` (treat-page occasions sorted by it in `ViewModel::treat()`), `Catalog::event_icon()` (+ `cic_event_icon` field, defaults `EVENT_ICONS`) used by the quote tiles and treat occasion badges, and a theme `wp_nav_menu_objects` filter (`inc/setup.php`) that re-sorts menu links to events into the central order and hides links to unpublished events. The pairing "Order on this treat" number no longer affects the public treat page (relabelled).
- New compositions `graduation` (hero-split + grad-cap/stars/confetti, values, starter, Birthday treat cards, stories, planning band, quote panel) and `prom` (Wedding hero card with starlight line, ribbon, feature with stars, brand treats, gallery with star corners, planning band, framed quote panel) in `Fields::EVENT_LAYOUTS`/`EVENT_LAYOUT_SECTIONS` and `single-cic_event.php`; motifs `grad-cap`, `stars`, `starlight` and icons `grad-cap`, `diploma`, `mirror-ball` (+ `party`, `clock` in the admin icon list); CSS in `details.css`/`compositions.css`. Design System presets `graduation` and `prom` (existing tokens only; gold decorative only).
- Six-card grids: homepage `.cic-event-cards--5/--6`, Service Areas `.cic-occasions--n5/--n6`, Frozen Yogurt `.cic-occ-cards--n5/--n6` → 3 per row at desktop.
- Event SEO through the existing SEO service: `cic_seo_title` / `cic_seo_description` fields (fallback title/excerpt), canonical, breadcrumbs and a Service schema with `ViewModel::provider()` (name, legalName, url, telephone; also used by city pages).
- Contact: `Config` defaults phone `424-235-4942`, `business_contact` `Alex Frost` (now a public, editable field; `Contact::get()['contact_name']`); `{contact_name}` token in About "Meet" and Contact call title. Enquiry settings: new optional `reply_to` for customer acknowledgements, `{contact_name}` placeholder, new acknowledgement defaults; operator subject/recipient unchanged.
- Homepage FAQ slots: `cic_faq_{i}_draft` (hidden when on; `ViewModel::faqs()`) and `cic_faq_{i}_note` (internal).
- Customer-facing strings: American English, "inquiry", friendlier confirmations ("Thanks! We received your quote request."), "check" instead of "tick".
- `SeedData`: Graduation Parties + Proms (order 30/40; Corporate 50, Anniversary 60), 8 new pairings, copy and spelling parity for fresh installs.
- `dev/tools/content/client-update-20261022.php` (CLI, idempotent, conditional): seeder run, menu_order, copy rewrites only where the old seeded text is unchanged, spelling, pairings via `Pairings::fill_seeded()`, alt text, Kosher draft, acknowledgement texts equal to old defaults, menu links, stored CF7 markup copy. Policy drafts and historical enquiries untouched.

**Database changes (local)**: events #763 Graduation Parties and #765 Proms; Corporate/Anniversary order 50/60; 8 pairings; 2 primary-menu links; copy/spelling in page, event, treat and pairing fields; 20 alt texts; homepage FAQ 4 (draft); acknowledgement subjects/bodies.

**Verification (actual results)**

| Check | Result |
| --- | --- |
| `dev/tests/client-update/browser-test.cjs` (new) | **35/35**: order in header dropdown, homepage cards (3 per row), hero search (+Other), footer, Events page, quote tiles (+Other last), three treat pages, Service Areas, a city page (O1–O9); both new pages at 390/768/1440: one H1, distinct title/description/canonical, own composition and motifs, every quote action preselects the event, no overflow or broken images (N1–N4); Anniversary works (N5); all `tel:` = `+14242354942`, Alex Frost on About/Contact with Frosty Yummy LLC kept, logo 190/136 px (C1–C4); a Graduation starter → quote form with Graduation preselected, submitted once to Flamingo (event key `graduation`), one operator notification to the configured (synthetic) recipient and one acknowledgement in Mailpit with "We got your quote request", "Event: Graduation", Alex Frost sign-off and the phone; no Reply-To on the acknowledgement, operator mail replies to the customer (Q1–Q6); no JS errors. Test records and mail removed |
| Content tool | dry run, real run, then re-runs: no further changes (idempotent) |
| HTML spot checks | 19 key pages 200, no new PHP log lines; visible text of those pages has no "Yan", old number or British spellings; no "$500"/minimum text; Kosher question not public; both new pages in the `cic_event` sitemap provider (sitemaps stay off while `blog_public=0`) |
| Updated expectations (event count 4→6, pairings 16→24, phone, presets 8→10, new copy) | Phase 03 server 46/46 + admin 22/22; Phase 04 server 25/25, browser **22/22** (E1 passed this time), admin fields **6/6** (test now waits for the save promise: it could close the browser mid-save); Phase 07 server 23/23 + admin 7/7 + browser 13/13; Phase 08 server 24/24 + browser 11/11; Phase 02 server 51/51 + browser 58/58 |
| Unchanged suites | guard 69/69 (fixtures created and deleted); header 30/30; Phase 09 interaction 7/7; Phase 09 audit at 390, 768 and 1440 px (now 20 pages incl. the new two): axe-core 0 violations, no overflow, no undersized targets, CLS ≤ 0.064 (390 px; 0.057 at 768, 0.047 at 1440), local LCP 0.52–2.15 s (indicative only) |
| Manual visual review | Screenshots of both new pages at 390 and 1440 (`dev/tests/output/client-update/`). Found and fixed: the Graduation starter strip overlapped the value row (negative margin meant for Birthday's hero) and the grad-cap motif overlapped the eyebrow on mobile; the Prom feature inset duplicated the Dippin' Dots photo |

**Logos applied (owner instruction, later on 2026-10-22):** `dev/tools/brand-logos.php` imported the supplied `logo-main.webp` (#870) and `logo-transparent.webp` (#871) into the Media Library (SHA-256 identical to the supplied files) and assigned them through the central layout settings (header logo; footer logo; footer plate off). `main.css`: logo images keep the approved 2:1 box (`aspect-ratio`, `object-fit: contain`) and the footer logo box keeps the plate padding without the plate. Measured before/after at 1440/768/390 and compact: header 145/118/118 px, logo 190×95 / 136×68 / 136×68 / compact 140×70, footer logo 144×72 in 168×88, footer heights 229/227/434 px: identical. Footer box transparent. Screenshots `dev/tests/output/client-update/after-*`. Header test 30/30 and client-update test 35/35 rerun after the change. Favicon, emails and other images untouched.

**Cities We Serve prefooter (owner request, 2026-10-22/23):** one shared component above the footer on every public page except Get a Quote.
- Data: `DatabaseRepository::served_city_ranking()`, one query for all eligible city–ZIP rows (city published + index status "index", city/link/ZIP production, supported state), each relationship passed through the existing `Coverage::effective()`; a city counts its DISTINCT ZIPs whose effective status is "enabled" (so globally disabled ZIPs such as the Compton exclusions, disabled links and manual-review/unreviewed links never count; a shared ZIP counts once per city whose own link is served). Sorted by count desc, then name; cached with `Tables::cached()` under the location cache version (bumped by every admin/import write). New `wp cic locations flush-cache` for direct database imports. `ViewModel::served_cities()` passes only labels ("Los Angeles, CA") and canonical URLs (`DatabaseRepository::url()`); counts are never output.
- Settings: Header, Footer & Contact → **Cities We Serve** (`Layout` section `cities`: enabled, heading, count 4–60 default 20, link label); empty heading/label fall back to the defaults.
- Markup/CSS: `<section aria-labelledby>` + `<h2>` + `<ul>` of normal links + "View all service areas" → /service-areas/; footer tokens (same palette, width), hairline divider, wrapping on desktop, two columns at phone width, ≥44 px targets, underline + outline focus, no images/cards/motion (`main.css` §10a).
- Note: "indexable" here = the city's **Index** setting (as requested). The repository's stricter `is_indexable()` (also requires reviewed city content, used for the city pages' robots tag and sitemap) is currently false for all 202 cities because no city content has been reviewed yet, so those linked city pages still render `noindex`.
- Also found: on 2026-10-23 the owner saved Header, Footer & Contact with new logos #874 `logo-colour-400` / #875 `logo-white-800` (footer width 190, mobile 135) from a form opened before the new tab existed, which saved the section as off with blank texts. The owner's logo choices were kept; only the `cities` section was reset to its defaults (on).
- Checks: `dev/tests/cities/cities-test.php` **18/18** (ranking equals an independent SQL DISTINCT count for all 202 cities, order included; temporary draft/noindex/fixture/unserved cities excluded; Compton ZIPs + disabled + manual-review links do not inflate a count; cached until `flush-cache`; an admin override (`CityStore::set_override`) refreshes it; labels/canonical URLs resolve to the city route; homepage HTML 20 links, before the footer, no numbers; absent on Get a Quote, present on event/treat/Service Areas/city pages; settings heading/count/label/disable; test rows removed, settings restored). `dev/tests/cities/browser-test.cjs` **14/14** (390/768/1440: 20 links, no overflow, ≥44 px targets, footer width/background, divider, no images/animation; two columns at 390, wrapping at 768/1440; visible keyboard focus; link contrast 10.32:1; axe-core 0 violations in the section; footer content, logo file and geometry identical with the section on and off; settings restored). Screenshots `dev/tests/output/cities/`.

**Failing checks NOT caused by this update (open)**: the local database now holds **378 production ZIP codes** (sources "LA County PDF 2022-02-25; client approved 2026-10-21", "Client supplied county ZIP list; approved 2026-10-21", "Client Compton exclusion 2026-10-21"; all written 2026-10-21 18:32, before this update and already in this update's pre-change backup). This load is not recorded elsewhere in this file, which still says "fixtures only". Tests written for the fixtures now fail: Phase 05 locations 48/55, Phase 06 enquiry server 46/49 (B1, C2, C5), submit 25/27 (S4, S12), browser 10/16 (U4 onward: 90220 is now excluded, so the scripted shared-ZIP path stops), refinements 39/41 (L11, Q0), Phase 09 review 7/8 (R8), Service Areas admin 40/42 (B7, H1). Not changed here; the owner should confirm this data is approved, then the location tests and docs need updating to production data.

Not tested: real browsers other than headless Edge, real devices, screen readers, sending real email, the supplied logo in place (preview only).

---

## Desktop compact sticky header log (owner request, 2026-10-21)

Desktop only, at the existing 1024 px navigation breakpoint. Mobile and tablet headers, the drawer and their transitions are unchanged. No page-specific reduced header exists in the codebase (the quote page uses the shared header via `get_header()`), so the behaviour applies consistently on every page, quote included. Committed locally on 2026-10-21 (not pushed).

**What changed**
- Settings: the central header configuration (`CIC\Site\Layout`, Cater Ice Cream → Header, Footer & Contact → Main row) gains `compact` ("Desktop: compact the sticky header while scrolling down", default on) and `logo_width_compact` (96–260 px, default 140). Both are rendered automatically by the existing settings screen; the theme fallback was updated too. `site-header.php` outputs `data-cic-compact` only when the header is sticky and compact is on, plus `--cic-logo-w-compact`.
- Behaviour (`assets/js/main.js`):
  - At the top: the full header, unchanged (145 px; top row 34 px; logo 190×95).
  - Scrolling down past 80 px with at least 12 px of travel in one direction: class `is-compact`. The top row collapses (max-height/opacity, then `visibility:hidden`, which also takes its links out of the keyboard order), the main row goes to min-height 56 px with 4 px padding, and the logo shrinks to the compact width (header ≈ 78 px).
  - Scrolling up 12 px, or reaching the top, restores it. Direction changes restart the travel count (no flicker).
  - It stays expanded while any primary dropdown is shown (hover, focus or toggle) or `:focus-visible` focus is inside the header. Keyboard focus entering the header, or a dropdown toggle, restores it.
- No content jump: on desktop the header box keeps its measured full height in both states and only its rows are painted. The freed band shows the page, and pointer events pass through it.
  - The first version let the box shrink. Re-expanding then shifted the page, browser scroll anchoring re-compacted the header and a wrong height was stored. Found by test D5 and fixed by holding the height.
  - The height is measured from the rows' natural heights after they settle (400 ms after a restore, resize or breakpoint change; ResizeObserver for late fonts and wrapping). Until then no fixed height applies, so nothing is clipped. Test R2 found a measurement taken mid-transition after widening; fixed.
- Offsets:
  - `--cic-header-offset` stays the full header height plus the admin toolbar. Anchors, scroll padding and focused fields use it, so a target is never hidden when the header re-expands on an upward jump.
  - New `--cic-header-visible` is the painted height. `.cic-jump` (Our Treats) and `.cic-policy-toc` use it, so they sit snugly under the compact header. On mobile and tablet both values are equal, so nothing changes there.
- Motion: 0.25 s transitions only under `prefers-reduced-motion: no-preference`; with reduced motion the state changes instantly.
- Breakpoint: crossing 1024 px removes `is-compact`, `--cic-header-full` and `--cic-top-h`. Rows hidden in the admin keep `display:none` (`cic-hide-desktop`), so restoring cannot reveal them. With the sticky setting off, the attribute is not rendered and scrolling is normal.

**Verification (actual results)**

| Check | Result |
| --- | --- |
| `dev/tests/header/browser-test.cjs` (new; settings changes restored, admin session destroyed) | **30/30**: top unchanged (D1); compact on scroll down with links and CTA ≥44 px (D2); no content jump, no overflow (D3); offsets (D4); restore on scroll up and at the top (D5–D6); ±6 px jitter never toggles, rapid changes do not oscillate (F1–F2); open dropdown holds, closing allows compacting (K1–K2); keyboard focus restores and holds, visible focus (K3–K5); collapsed top-row link cannot be focused (A1); anchor jumps down and up never hidden, jump bar snug (N1–N3); admin toolbar (W1); reduced motion instant (M1); resize across 1024 px resets and restores (R1–R2); 390 and 768 px headers identical to the baseline at the top, scrolling down and up, drawer open/Escape unchanged (U1–U2); sticky off, compact off and a desktop-hidden top row all honoured (S1–S3); quote page at 1024 px (Q1); no JS errors |
| Geometry before/after (390, 768, 1440 px × home, quote, Our Treats; top and scrolled) | 390/768: identical in every state. 1440: identical at the top; scrolled = intended compact state (top 34→0 px, main row 111→78 px, logo 190×95→140×70) |
| Regressions | Phase 02 design 51/51 + browser 58/58; Phase 04 server 25/25, browser **21/22** (see below), admin fields 6/6; Phase 08 24/24 + browser 11/11; Phase 09 review 8/8, interaction 7/7 |
| Phase 09 audit at 1440 px (not run in the previous task) | 18 pages: axe-core 0 violations, no horizontal overflow, CLS ≤ 0.055 (including scrolling), no commerce scripts, 45 links (only the deliberate 404). Local LCP was slower this run (1.3–2.6 s vs 0.6–1.2 s in Phase 09); not investigated, local and indicative only |

- **Open, not caused by this change:** Phase 04 browser E1 failed twice. In the block editor, the homepage field save did not persist during the test, and the test restored the value afterwards. The header script and markup do not load in wp-admin. **UpdraftPlus 2.23.14.1** was installed and activated by the owner at 02:24 (confirmed by the owner); since then the admin bar shows a "Live" badge and UpdraftPlus produces "textdomain loaded too early" notices in the debug log. Not investigated further; it needs a look before relying on editor saves.
- Not tested: real browsers other than headless Edge (Safari/Firefox), trackpad momentum scrolling and real touch-and-mouse hybrids, screen readers.

Docs: admin guide (Header settings: compact header), `CLAUDE.md` (rules), rc1 outdated notices updated.

---

## Refinements log (owner request, 2026-10-21): location labels, date picker, Site Icon, event icon, map previews

Approved layouts, palette, typography, the 190/136 px header logo and the form/business logic were preserved. Existing components were extended, not replaced. Checkpoint before changes: database + `wp-config.php` + `.htaccess` backup `C:\laragon\backup\catericecream\pre-refinements-20261021-000014\` (SHA256SUMS) and git tag `checkpoint-pre-refinements` (= `9d00fd3`). Committed locally on 2026-10-21 at the owner's request (not pushed). Nothing deployed.

**1. Consistent location display**
- Cause: starters, the quote form and the server prefill wrote the canonical key (`los-angeles-ca`) into visible fields.
- Contexts, URLs and storage still carry only canonical keys (city key or ZIP). Fields and summaries show the repository label:
  - Server: `CIC\Locations\Service::display_label()` is used by `Forms::value()` for the quote-form prefill.
  - New read-only `GET /cic/v1/location?key=` returns the label for a stored key (rate-limited; '' when unknown).
  - Client (`context.js`): `fillLocation()`, `labelFor()` and `setLabel()`. Labels are kept in sessionStorage only, never in URLs.
- Unknown keys leave the field empty; no label is ever rebuilt from a slug. The validator's fallback for unknown cities now uses the visitor's own wording instead of title-casing the slug.
- A chosen suggestion marks the field with its key. Editing the text clears the key until the starter's coverage check (or the quote form's step-1 check) validates the new text. Shared-ZIP clarification and exclusions are unchanged.
- The quote form writes validated step-1 choices to the context and keeps the address bar in step (`history.replaceState` with keys only), so reload, Back/Forward and direct links show the current choice.
- ZIPs display as the ZIP. A city plus event ZIP displays as "Los Angeles, CA · 90012" in the summary and "Los Angeles, CA 90012" on the confirmation and in the saved enquiry.

**2. Date picker** (native `type=date` cannot guarantee MM/DD/YYYY: its display follows the browser locale)
- New theme-owned `assets/js/datepicker.js` (no third-party code, so no licence to retain) plus `main.css` §10c, using Design System tokens only. It applies to every event-date field: the quote form and all starters.
- The original input keeps its name and the canonical Y-m-d value (hidden); a visible text field shows MM/DD/YYYY.
- Calendar dates use year/month/day parts and UTC arithmetic only; "today" and the latest date come from the server (America/Los_Angeles).
- Opening and keys:
  - It opens on a click or tap anywhere in the date control (label and icon included), or with Down arrow / Alt+Down. Typing never opens it.
  - Manual entry accepts MM/DD/YYYY, M/D/YYYY or MMDDYYYY.
  - Labelled previous/next month and year buttons; arrows, Home/End, PageUp/PageDown (Shift for years), Enter/Space; Escape returns focus to the field.
  - Out-of-range days are announced as unavailable; day targets are 44 px; a touch tap moves focus into the calendar.
  - A note states that choosing a date does not reserve it.
- Without JavaScript the native field remains (Phase 06 no-JS check passes). "Date not set yet", context persistence, CF7 submission and server validation are unchanged.
- User-facing date labels are now MM/DD/YYYY: review summary, confirmation, saved `date_label`, emails (`Validator::date_label`).

**3. Site Icon:** WordPress core Site Icon from a crop of the installed approved mascot.
- Crop: dome, face and tray (x 70–570, y 14–364); no wordmark or hand. Placed on a white 512 px square and saved as `assets/brand/site-icon-512.png` (PNG; the WebP rule covers photographs).
- Registered by `dev/tools/site-icon.php` exactly as Settings → General does: source attachment #645 plus site-icon attachment #646, with sizes 32/180/192/270. Both are tagged `_cic_brand_asset`, not the image-pack `_cic_asset_id`.
- Output: one `icon` 32, one `icon` 192, one `apple-touch-icon` 180 and one TileImage 270; no other declarations. `/favicon.ico` → 302 to the 32 px PNG.
- Replaceable in Settings → General → Site Icon.

**4. Event field icon:** new `party` (party-popper) line icon for event-type fields: homepage hero search, quote starters, the review "Event type" row and the confirmation chip. Date fields keep `calendar`. Icons stay `aria-hidden` and the labels remain.

**5. California map**
- `ca-map.php` + new `assets/js/map.js` (enqueued on Service Areas, About and Contact) + `locations.css`. Layout, outline and point positions are unchanged.
- Pins drop/fade once, staggered (110 ms), when the map first enters the viewport. The classes are removed afterwards, so nothing keeps running; there is no animation with reduced motion or without IntersectionObserver.
- Each county (pin + label) becomes a button (JS only) with invisible hit areas.
- Hover (mouse) or focus shows a compact preview. Click, tap, Enter or Space keep it open, and keyboard activation moves focus into it.
- Escape, the close button, a tap outside, leaving the county, or Tab out of the card closes it, with focus returned to the county.
- The card is appended to the page end (so later sections cannot cover it), placed inside the viewport and below the sticky header.
- Preview content:
  - county name;
  - the existing **Service Areas page county slot** fields: "line under the name" and "photo" (approved county photos `county-los-angeles`, `county-ventura`, `county-santa-barbara`; alt "" because the name is shown);
  - "Approximate county reference point, not a service boundary. N ZIP codes here are marked as served…";
  - a link to that county's directory.
- The photo loads only when the card opens, in a fixed 16:9 box. A county without a slot or photo gets a text-only card. **No missing county asset:** all three mapped counties have approved photos.
- `ViewModel::map_points()` replaces three duplicated loops. Field instructions now mention the map preview.

**Verification (actual results)**

| Check | Result |
| --- | --- |
| `dev/tests/refinements/browser-test.cjs` (new, sequential, synthetic data, restored) | **46/46**. Location labels, edits after a choice, Compton exclusion, shared-ZIP and partly served clarification, a changed location kept after reload, direct links and an older stored key (L1–L12); calendar at 390/768/1440 via the icon area, Escape/focus return, keyboard, manual entry, invalid date, date-not-set, touch, starter → quote (D1–D9); icons (I1–I3); favicon (F1–F2); map at 390/768/1440: entrance once, hover/tap, keyboard, touch dismissal, reduced motion, text-only card (M1–M6); one complete quote submission saved **once** in Flamingo (also after refreshing the confirmation) with label "Los Angeles, CA 90012", key `los-angeles-ca`, date label MM/DD/YYYY, and one operator + one customer message in Mailpit (Q0–Q4); no JS errors |
| Server suites | Phase 01 guard **69/69**, 02 design **51/51**, 03 **46/46**, 04 **25/25**, 05 **55/55**, 06 **49/49**, 07 **23/23**, 08 **24/24**, 09 review **8/8**, Service Areas **42/42** |
| Browser suites | 02 **58/58**, 03 admin **22/22**, 04 **22/22** + admin fields **6/6**, 05 **24/24** + admin **12/12**, 06 browser **35/35** + submissions **27/27**, 07 **13/13** + admin **7/7**, 08 **11/11**, 09 interaction **7/7**, Service Areas browser **21/21** |
| Phase 09 audit | **390 px and 768 px only** (18 pages each): axe-core 0 violations, no horizontal overflow, CLS ≤ 0.080 / 0.024, no commerce scripts, 45 links (only the deliberate 404 fails). **1440 px was not run**: Claude Code stopped the background job because the machine ran low on memory, and it was not restarted |
| Touch targets | At 390 px the audit flagged the new map counties on the small About (260 px) and Contact (200 px) maps: 68×43, 58×40 and 52×33 px bounding boxes. The label hit areas were then made taller (20 → 44 map units, extending into the empty ocean). **That change has not been re-measured** (the audit was stopped). Even enlarged, the real tap area on the Contact map is about 39×29 px, because the approved 200 px map leaves only about 41 px between labels. It meets the WCAG 2.5.8 24 px minimum but not this project's 44 px rule. Owner decision needed: accept, or enlarge those two maps |

Test-expectation updates (behaviour intentionally changed):
- Phase 04 H3/H4/P2–P4 and Phase 06 E1/E4: the visible location label, the hidden canonical date, the typed MM/DD/YYYY and the canonical city key in URLs.
- Phase 06 A3: allows the read-only label route.
- Phase 06 B5: expects MM/DD/YYYY.

Not tested:
- the 1440 px audit;
- re-measuring the enlarged map targets;
- screen readers, Safari/Firefox, real devices;
- the email body layout (only delivery counts were checked).

**State:** test enquiries, contacts, rows and Mailpit messages removed by the test helpers; county photo meta restored; recipient unchanged; reminders off; policies drafts; fixtures unchanged. One enquiry (#644, 2026-10-20 23:34 local, the owner's own address) was submitted through the site outside these tests and was **left untouched**.

**Release package:** rc1 (`23935f5`) is **outdated**: notices `C:\laragon\release\catericecream\OUTDATED-rc1.txt` and `cater-ice-cream-rc1\OUTDATED.txt`, plus banners in `docs/release/README.md` and `RELEASE_NOTES.md`. Regenerate after review and commit.

**Docs:** `docs/release/ADMIN_GUIDE.md` (Site Icon, map previews and county fields, location/date display) and `CLAUDE.md` (refinement rules).

---

## Phase 10 log: Release candidate and operational guide (rc1)

Prompt: `prompts/10_release_candidate_and_operational_guide.md` (+ spec §18–§20A, `docs/ADMIN_ACCEPTANCE_CHECKLIST.md` "Before release"). Owner authorisation: **local release preparation only**. No deployment, DNS, indexing, policy publication, reminders or external email. Approved designs and functionality preserved: the only runtime addition is a staging-only mail guard, inactive locally.

**Phase 09 commit:** reviewed (diff matched the Phase 09 log, PHP lint clean, no secrets in the new tests) and committed locally as `776a635`, excluding secrets, logs, backups and test output.

**Decisions**
- **No staging host exists**, so the staging rehearsal and restore drill ran on **disposable local copies**: a separate WordPress install at `C:\laragon\release\catericecream\work\site` (outside the web root), databases `cic_rc_build` / `cic_drill`, `WP_ENVIRONMENT_TYPE=staging`, served by PHP's built-in server at `http://127.0.0.1:8090`. The working site and database were only read. Host-dependent steps are marked **[host]** in the guides.
- **Staging mail guard** (`wp-content/mu-plugins/cic-staging-mail-guard.php`, new): when the environment type is `staging`, `wp_mail()` is refused (optional single capture inbox via `CIC_STAGING_MAIL_TO`). Reason: spec §17/§19 require that staging sends no real messages, and a staging copy carries the live operator recipient. Inactive on local (verified: no `pre_wp_mail` filter) and production.
- **Package database is sanitised**, not raw: Flamingo messages/contacts, coordination rows, WooCommerce sessions, finished jobs, transients, stored keys/salts and **fixture coverage** removed; the administrator email/password and the admin email in settings neutralised. Fixture data is never shipped (ignored outside local anyway).
- **Third-party code is not redistributed:** the host installs WordPress 7.1.3 and the four plugins at pinned versions from wordpress.org.
- **Later releases are code-only:** versioned migrations, non-destructive seeds, Design System export/import, and coverage only through the importer. A package database must never be imported over a site with real enquiries or edits.
- The rc1 code commit is `23935f5`; the package code comes from `git archive` of that commit, never from the working tree.

**Files added/changed**
- New: `wp-content/mu-plugins/cic-staging-mail-guard.php`; `dev/tools/release/` (`release.sh` stages backup/site/build-db/assemble/deploy-drill/restore-drill/teardown/checksum-after, `make-disposable-config.php`, `sanitize.php`, `verify-deploy.php`, `smoke.sh`, `enquiry-drill.php`, `router.php`; all refuse to write unless on a disposable `cic_*` copy); `docs/release/` (`README.md`, `RELEASE_NOTES.md`, `RELEASE_PACKAGE.md`, `DEPLOYMENT.md`, `BACKUP_AND_ROLLBACK.md`, `ADMIN_GUIDE.md`, `LAUNCH_CHECKLIST.md`).
- Changed: `.gitignore` (allows `docs/` and the staging mail guard); `dev/tests/phase09/review-test.php` (R2 accepts `\r\n`, see below); `BUILD_STATUS.md`, `ENVIRONMENT.md`, `CLAUDE.md`.

**Release package (outside the web root)**
- `C:\laragon\release\catericecream\cater-ice-cream-rc1\` + `cater-ice-cream-rc1.zip` (+ `.sha256`). Contents: theme (112 files), core plugin (76), 2 MU plugins, media (407 files, 16.3 MB: the 58 originals + generated sizes + WooCommerce placeholders), sanitised SQL (59 tables, InnoDB, `utf8mb4_unicode_520_ci` only, 0.8 MB), docs, `VERSION.json`, `INVENTORY.txt` (path, bytes, SHA-256 per file), `SHA256SUMS`.
- Hygiene checks (automated, all PASS): no `wp-config`, logs, `.env`, keys, `cic-local-mail.php`, `node_modules`, git files, backups, `dev/`/tests/handoff, `wpcf7_uploads`/`wc-logs`, credential definitions; exactly one SQL file. Email addresses left in the SQL: `admin@example.invalid` (placeholder), the specification's operator recipient (`cic_enquiry_settings`), `wordpress@catericecream.test` (CF7 sender lines, replaced by the bare-host pass at deployment), and WordPress/CF7 sample defaults (`wapuu@wordpress.example`, `login@example.com`).
- Build logs: `C:\laragon\release\catericecream\logs\`.

**Verification (actual results)**

| Check | Where | Result |
| --- | --- | --- |
| Backup of the working site | `C:\laragon\backup\catericecream\pre-phase10-20261020-231042\` (DB dump, `wp-config.php`, `.htaccess`, project files + uploads tar, SHA256SUMS) | Done; checksums recorded |
| Sanitiser on the disposable build DB | `cic_rc_build` (dropped afterwards) | 2 Flamingo contacts, 33 sessions, 284 finished jobs, 195 transients, 10 stored keys/salts, 20 fixture cities / 60 ZIPs / 61 links, 2 location-history options removed; 1 account and 5 settings neutralised; 0 Flamingo / 0 fixture rows remaining |
| Deployment rehearsal (`deploy-drill`: package code/media, DB import, 3 search-replace passes = 208 + 0 + 8 replacements, rewrite flush) | `cic_drill`, staging mode | `verify-deploy.php` **44/44** |
| HTTP smoke checks (`smoke.sh`) | `http://127.0.0.1:8090` | **33/33** (first run 32/33: the check flagged the `"flamingo-spam"` post-status name in REST schema enums, which is not data; the check was narrowed to Flamingo namespaces/routes and a `/wp/v2/flamingo_inbound` 404 probe was added) |
| Catalogue guard suite against the deployed copy | `guard-test.php staging-drill anon-only` | **58/58**; its fixture products removed |
| Restore drill (`restore-drill`) | disposable copy | **PASS**: synthetic enquiry (26 meta rows incl. all `_cic_*` workflow keys + coordination row), pairings, products, design settings and a media file identical after a simulated incident (enquiry deleted, pairing deleted, design changed, media deleted, broken theme → HTTP 500) and restore (files, then DB); home 200; afterwards verify 44/44, smoke 33/33. First run: the design-change step matched 0 rows (stored settings lacked the targeted hex), so the step was rewritten and the whole drill re-run; the reported result is the second run |
| Working DB untouched | table checksums before/after | Only `wp_options`, `wp_actionscheduler_actions`, `wp_actionscheduler_logs` changed: the site's own hourly enquiry maintenance + WooCommerce housekeeping ran at 23:14. No content, location, user or enquiry table changed |
| Local regressions after adding the MU plugin | working site | Phase 01 guard **69/69**, Phase 06 server **49/49**, Phase 09 review **8/8** (after the R2 correction) |
| PHP logs | local + disposable | No new local entries besides WP-CLI's own deprecations; disposable log holds only the deliberate drill fatal |

- **Test correction:** Phase 09 R2 failed in the re-run because the homepage FAQ heading is now stored with `\r\n`. A browser admin save (Phase 04 admin-fields regression, after the Phase 09 repair) submits textarea line breaks as CRLF. The rendered heading is correct (two `cic-line` spans, checked in the HTML). R2 now accepts `\n` or `\r\n`. No product change.
- **Not tested / host-dependent:** a real web server's rewrites, HTTPS, basic auth, permissions, system cron, host PHP/MySQL (or MariaDB) versions, host mail and deliverability (SPF/DKIM/DMARC), host backup tooling and a restore on the host, performance on the host, and everything in Phase 09's "not tested" list (screen readers, Safari/Firefox, real devices, forced colours, zoom/text spacing, field performance). PHPCS has not been run.

**Outstanding (carried explicitly; the site is not launch-ready while any is open):** approved production ZIP coverage; final form-field review and policy wording; approved imagery and product details; production email sender and delivery verification; user access (Yan / Web Karma emails) and reminder timing; analytics/privacy configuration; hosting, scheduled jobs and backups; remaining real-device and accessibility checks. Details: `docs/release/LAUNCH_CHECKLIST.md`.

**Clean state after Phase 10:** disposable install and databases `cic_drill` / `cic_rc_build` removed; PHP built-in server stopped; working site unchanged apart from the inactive MU plugin; 0 enquiries/submission rows/test users/orders; recipient, reminders (off), policies (drafts) and fixture coverage unchanged.

---

## Phase 09 log: Visual, accessibility and performance review

Prompt: `prompts/09_visual_accessibility_and_performance_review.md` (+ spec §16, §18, `docs/ADMIN_ACCEPTANCE_CHECKLIST.md`). Approved homepage, the 190/136 px header logo and the page designs were preserved; only reproducible defects were fixed. Policies stay drafts, coverage stays fixtures only, reminders stay off, mail is local capture only.

**How it was checked**
- *Automated:* `dev/tests/phase09/audit.cjs` — 18 page types (home, Events, 4 events, Our Treats, 4 treats, Service Areas, state hub, city, About, Contact, Get a Quote, 404) × 390/768/1440 px: axe-core 4.10.2 (WCAG 2.0/2.1 A + AA rule tags), horizontal overflow, CLS (including scrolling), LCP element/time/loading attribute, broken images, alt attributes, heading structure, duplicate ids, touch targets (390 px), page weight by type, commerce scripts/controls, then all 45 internal links found. `dev/tests/phase09/interaction-test.cjs` — fields never covered by sticky/fixed elements, visible focus on every Tab stop, reduced motion, no endless motion, keyboard accordions, image sizing. `dev/tests/phase09/review-test.php` — regressions for the fixes and the "do not change" constraints. Plus every earlier suite (functional, catalogue, coverage, quote/contact, permissions).
- *Manual inspection* (screenshots read against the reference boards): mobile first screens of all page types (homepage, Events, Birthday, Wedding, Anniversary, Corporate, Our Treats, a treat, Service Areas, city, About, Contact), tablet first screens (homepage, Corporate, Our Treats, quote), the mobile drawer with an open submenu, the desktop dropdown, the tablet footer, the homepage FAQ, and the focused date field. Earlier phases' 1440 px comparisons were not repeated except where code changed.
- *Conditions:* local Laragon (Apache + PHP 8.5.1), no network throttling, headless Edge, cache disabled per page, warm-up request first. Timings are indicative only; they are not field or Lighthouse scores.

**Defects found and fixed (all re-verified)**

| # | Defect (evidence) | Fix |
| --- | --- | --- |
| 1 | Text links `#D92373` on tinted surfaces 3.8–4.45:1 (axe colour-contrast on 16 page types) | Design System `link` role default → existing `action_hover` token `#BC1F64` (4.85–5.97:1 on every surface). Buttons keep `#D92373`; a colour saved in the Design System admin still wins |
| 2 | Wedding ruled eyebrow in champagne gold 2.84:1 on ivory | Eyebrow text uses the heading role like every other eyebrow; gold kept for the rule line (gold is "decorative only" in the Design System) |
| 3 | Wedding plan-step numerals in gold 2.4:1 | Digit uses the heading role; gold ring kept |
| 4 | Unused WooCommerce scripts on every page (woocommerce, BlockUI, js-cookie, Sourcebuster, order attribution — the last also set tracking cookies without an approved tracking configuration) | Added to the guard's catalogue-mode script list (survives theme switches) |
| 5 | Treat pages loaded WooCommerce gallery zoom/flexslider/photoswipe + single-product + jQuery and hidden lightbox markup, none used by the custom compositions | Theme gallery supports removed, `wc-single-product` dequeued (product gallery images stay editable) |
| 6 | Touch targets under 44 px at 390 px: county city links (21), text links (26), breadcrumbs (17), footer phone (32), hero search inputs (28), pairing/nearby/phone/jump links (17–26), catalogue jump pills (40) | Hit area extended with padding + equal negative margin (visible layout unchanged); jump pills `min-height` 40 → 44 px |
| 7 | Decorative mascot downloaded at 640 px / 113 KB for 46–88 px uses | 160/320 px resampled copies of the same approved artwork + `srcset`/`sizes` (4.6 / 10.8 KB) |
| 8 | Homepage FAQ heading read "A few things youmight be wondering." — ACF key `field_cic_faq_heading` was a textarea on the homepage but a single-line text field on events/treats, so admin saves stripped the line break | All three definitions are multi-line now; the stored value was repaired (it still held the mangled seed text) |
| 9 | Wedding botanical motif swayed endlessly (and the line trace ran 6 s) for visitors without a reduced-motion preference (WCAG 2.2.2) | Two 2.5 s passes then still; trace 4.5 s |

**Investigated, not defects:** homepage LCP of 1.7 s was the first navigation in a fresh browser (TTFB 1.49 s first vs 0.30 s warm, reproduced on another page; audit now warms up). "Missing" focus rings on homepage cards and search fields were `:focus-within` rings on their wrappers; date/time fields were confirmed by manual inspection (3 px plum outline). Event/occasion card title links are stretched over the whole card. The 404 page's footer logo is lazy but not the LCP. The header logo's light box is the owner-approved original raster.

**Final results**
- Audit (final runs): axe **0 violations** on all 18 page types at 390, 768 and 1440 px; no horizontal overflow; CLS ≤ 0.08 everywhere (largest: Events at 390, 0.080); every LCP image eager with `fetchpriority=high` where it is an image; no broken images, no missing alt attributes, no duplicate ids, one `h1` per page, no heading-level skips; no small touch targets left at 390 px (the visually hidden skip link excluded); no commerce scripts or controls; 45 internal links checked, only the deliberate 404 test URL fails. Local LCP 0.49–0.86 s (390) and 0.58–1.20 s (1440; 1.2 s is the 404 page). Page weight 486 KB (quote) to 1.86 MB (homepage, mostly approved photography); fonts: two local WOFF2 files (~74 KB).
- `interaction-test.cjs` **7/7**; `review-test.php` **8/8**.
- Regressions: Phase 01 guard **69/69** (re-run because the guard changed), 02 design **51/51** + browser **58/58**, 03 **46/46** + admin edit **22/22**, 04 **25/25** + browser **22/22** + admin fields **6/6**, 05 **55/55** + browser **24/24** + admin **12/12**, 06 server **49/49** + submissions **27/27** + browser **35/35**, 07 **23/23** + browser **13/13** + admin **7/7**, 08 **24/24** + browser **11/11**, Service Areas **42/42** + browser **21/21**. One expectation update: Phase 04 H4 now checks the prefilled Phase 06 form instead of the interim summary list.
- Clean state: no test enquiries, contacts, users, submission rows, scheduled jobs, fixture products or orders left; operator recipient unchanged; no new PHP log entries; no test browsers open.

**Admin acceptance checklist (evidence):** global action colour / fonts / heading scale / preset changes and override reset — Phase 02 browser T3–T8, T12; phone propagation — T13.3; header rows, mobile drawer, sticky anchors — T13–T14; content edits, pairings, new records, theme switch — Phases 03/07 + guard suite; locations dry run, idempotent re-import, ZIP cases, Compton exclusion, 404/redirect/noindex — Phase 05 + Service Areas suites; forms and lead operations — Phase 06 suites; catalogue protection (all purchase paths, theme switch, core plugin off, no orders) — guard 69/69; responsive and accessibility — this phase.

**Not tested:** screen readers (NVDA/JAWS/VoiceOver), Safari and Firefox, real phones/tablets and touch input, Windows high-contrast / forced colours, 200–400 % zoom and text-spacing overrides, colour-vision simulation, slow networks or field performance (no Lighthouse/CrUX), a production server, PHPCS. Automated axe checks find only part of WCAG issues: **this is not an accessibility-compliance statement and not a launch-readiness claim.**

**Remaining launch dependencies**
- Approved production coverage data (only labelled fixtures today) and the owner's review of city content.
- Verified production mail sender/domain, Yan / Web Karma account emails, reminder timing.
- Owner review of the provisional quote/contact form fields; approved privacy policy and website terms (both drafts; the footer legal links and the form privacy link appear only once they are published).
- Real event/product photography and client review of the AI images, product options and ingredients; an approved reversed (white) footer logo if wanted.
- Analytics/privacy configuration (no analytics installed; events are ready for `dataLayer`).
- Hosting/staging, working server cron + delivery monitoring, backup destination and restore rehearsal (Phase 10).
- Manual accessibility testing with assistive technology and real devices, and field performance measurement on the production host.

---

## Phase 06 log: Quote flow, lead management, mail and reminders

Prompt: `prompts/06_quote_flow_lead_management_mail_and_reminders.md` (spec §12, §13, §13A, §15, §17, §19, §20A). References: `designs/17_quote_step_1.png`, `18_quote_later_steps.png`, `16_contact.png`, `21_mobile_detail_and_quote.png`.

Backup: `C:\laragon\backup\catericecream\pre-phase06-20261020-155924\` (DB, `wp-config.php`, `.htaccess`). Installed with the owner's authorisation: **Contact Form 7 6.2.2** and **Flamingo 2.6.4** (free). Core plugin 0.6.0 → **0.7.0**; schema **v5**.

**Why a thin adapter.** In these versions CF7 sends mail first and Flamingo stores afterwards (on `wpcf7_submit`, after the visitor's result is fixed); Flamingo also stores spam. That cannot guarantee "saved before notified" or turn a storage failure into an error. So, using supported hooks only (no vendor edits):

| Step | What happens |
| --- | --- |
| `wpcf7_validate` | Every field validated server-side (`CIC\Enquiries\Validator`): published event/treat keys, LA-local date or "Date not set yet", guests 1–10000, contact preference, phone rule, text limits; location through the existing `LocationRepository` + `Coverage` (excluded → blocked; shared/unknown → asks for the missing venue city or event ZIP, then accepted for team review). Expired form token → "press Send again" with a fresh token. |
| `wpcf7_spam` | Honeypot, signed render token (too fast / forged), 5 accepted per 10 minutes per hashed IP (`Guard`). Spam is never stored or acknowledged. |
| `wpcf7_before_send_mail` | Atomic claim of the submission key (`{prefix}cic_submissions`, UNIQUE key hash): a stored key replays the same receipt; a concurrent duplicate is told to wait. Then **one** Flamingo record via CF7's own `wpcf7_flamingo_submit`, workflow meta on that Flamingo message, receipt, and only then notifications. No Flamingo id → key released, request aborted, nothing sent. |
| Mail | CF7 mail skipped. `Notifier` is the single mail owner: operator notification (Reply-To customer) + customer acknowledgement (max 3/day per address). Each channel claimed atomically; failure keeps the enquiry, records a code only and schedules a retry (Action Scheduler); admin "Retry notifications". |
| Response | `{cic: accepted, receipt, summary}` for the script; no-JS submissions redirect (303) to `?received=<receipt>`. Receipts are random and show only labels; they expire after a day. |

**What was built**

| Area | Details |
| --- | --- |
| Quote page (`/get-a-quote/`) | Boards 17/18: hero with the original mascot, form card, sidebar (planning steps, Need a hand? call box, photo), What happens next, confirmation state. Global header and the approved 190/136 px logo unchanged. Text editable on the page (Get a Quote composition fields). |
| Quote form | One CF7 form in three steps. Event tiles from published events (new optional **Short label** field on events: Birthday, Wedding, Anniversary, Corporate) + Other; date + "Date not set yet"; guests; city/ZIP with autocomplete and coverage pre-check; venue, serving time and duration (optional). Treat cards from published products, multiple OR "Not sure yet" (exclusive). Flavours/theme/dietary (optional). Name, email, phone (required only for Phone preference), notes. Editable "at a glance" summary. |
| Enhancement (`assets/js/quote.js`) | Steps with Back and browser history (#step-n); earliest incomplete step; Enter continues; inline errors + focused error summary that opens the right step (including hidden-field errors); double-submit protection; network-failure recovery; confirmation in place; analytics events with keys only. Without JavaScript the whole form works on one page. |
| Prefill | Incoming selections from homepage, event, treat and city entry points (URL context and session context), validated on the server. First/latest source page recorded (template + key only). |
| Contact (`/contact/`) | The CF7 Contact form (topic, name, email, phone optional, message) in the approved message panel, same foundation; confirmation state. |
| Enquiries admin | **Enquiries** menu (`manage_cic_enquiries`): list with filters (status, type, assignee, search), notification state, CSV export (nonce, formula-protected, spam excluded); detail with request, coverage (fixture-labelled), customer, source, status (new, contacted, quote_sent, booked, lost, spam), assignee, next follow-up, private notes, notification Retry and reminder state. **Enquiries → Settings** (administrators): operator recipient (initially anuranjan.verma25@gmail.com, separate from the public email), sender (pending), acknowledgement templates, form IDs, reminders. |
| Access | Administrators get `manage_cic_enquiries`; new **Enquiry manager** role (read + that capability only; WooCommerce's admin redirect is lifted for it). Editors have no access. Flamingo's own screens stay administrator-only. No accounts created (emails not supplied). |
| Reminders | Internal only, one Action Scheduler job per enquiry (rescheduled safely, cancelled for booked/lost/spam, trash or delete), **off until a reminder time is set**. Hourly maintenance job: unstick, retry, retention cleanup. |
| Privacy | Workflow data is meta on the Flamingo message (Flamingo's eraser removes it); an exporter covers enquiries + workflow. No IP or user agent stored; guard fields stripped; no personal data in URLs, analytics, logs or the coordination table. |
| Local mail | `wp-content/mu-plugins/cic-local-mail.php`: on this Windows setup `mail()` fails inside php-cgi, so local WordPress mail goes to Mailpit over SMTP. Local environment only; Mailpit has no relay. |

**Changed / new files:** `src/Enquiries/{Service, Settings, Store, Forms, Validator, Guard, Pipeline, Notifier, Workflow, Roles, Privacy, Cli}.php`, `src/Enquiries/Admin/EnquiriesPage.php`, `src/Database/Migrations.php` (v5), `src/Content/{Fields, SeedData, Seeder, ViewModel, Catalog}.php`, `src/Plugin.php`, theme `page-get-a-quote.php`, `page-contact.php`, `template-parts/components/enquiry-received.php`, `assets/css/quote.css`, `assets/js/quote.js`, `assets/js/context.js` (source attribution), `inc/content.php` (icons check/dots/clock, enqueue, source page), MU `cic-local-mail.php`, `.gitignore`, tests in `dev/tests/phase06/` (+ test-only observer fixture, copied into mu-plugins only during tests).

**Verification (real runs, synthetic @example.test addresses, Mailpit capture)**
- `dev/tests/phase06/enquiry-test.php` **49/49**: architecture (no `cic_lead`, no CIC submission route, CF7 mail skipped, one three-step form), validation incl. DST dates and malformed keys, fixture coverage (excluded / shared / unknown), atomic claims, abuse controls, settings, workflow + reminder scheduling/cancellation, roles, CSV safety, privacy surfaces.
- `dev/tests/phase06/submit-test.cjs` **27/27** through CF7's real transport: stored once **before** every notification (observer), one operator mail + one acknowledgement, retries and 6 concurrent duplicates → one enquiry/one mail each, separate enquiries from the same email, validation, malformed ids, excluded/shared/unknown locations, spam paths, expired token, mail failure → saved + recovered by retry without duplicates, Contact, receipt refresh, no-JS redirect and re-render, rate limit, no leakage (REST/search/sitemap), Flamingo-missing fallback.
- `dev/tests/phase06/browser-test.cjs` **35/35**: homepage/event/treat/city entry points, step validation, Other, date unknown, coverage clarification, treat exclusivity, summary, phone rule, Back + history, double-click, analytics (one `quote_submitted`, no personal data), confirmation refresh, keyboard, network failure, 390 px, no-JS, Contact, mail failure + admin Retry, workflow save, export, unauthorised access (editor, visitor) and the Enquiry manager role.
- Regressions: Phase 01 guard **69/69**, 02 design **51/51** + browser **58/58**, 03 **46/46**, 04 **25/25**, 05 **55/55** + browser **24/24** + admin **12/12**, 07 **23/23** + browser **13/13** + admin **7/7**, 08 **24/24** + browser **11/11**, Service Areas **42/42** + browser **21/21**. Expectation updates: Phase 07 D5.2/B5/B9 and Phase 08 P5.3/B4 now assert the real Phase 06 form instead of "Phase 06 pending".
- Clean state: 0 test enquiries, contacts, users, rows or scheduled jobs left; operator recipient restored to anuranjan.verma25@gmail.com; test mail deleted from Mailpit; no new PHP log entries (the only log lines are WordPress's own update checks, which cannot reach WordPress.org).
- Screenshots: `dev/tests/output/phase06/p6-*.png` (git-ignored).

**Found and fixed during testing:** reminder jobs could not be unscheduled (argument mismatch); Flamingo stored the guard fields; CF7's client validation cleared custom-field errors; texturized titles showed HTML entities in summaries; Enquiry managers were redirected out of wp-admin by WooCommerce; local web-request mail failed (environment, see above).

**Not tested:** screen readers, Safari/Firefox, real touch devices, PHPCS, a production server cron, real mail delivery (deliberately: local capture only).

**Admin instructions**
- *Read an enquiry:* Enquiries → All enquiries → open a row. "TEST DATA" means the coverage check used local fixtures; confirm service yourself.
- *Work it:* set Status, Assignee and Next follow-up, add a private note, Save. Notes never reach the customer.
- *Failed email:* the enquiry is already saved. Open it and press **Retry notifications** (or run `wp cic enquiries retry <id>`). Only unsent messages go out.
- *Export:* Enquiries → Export CSV (respects the filters; spam excluded unless filtered).
- *Settings (administrators):* Enquiries → Settings: change the operator recipient, acknowledgement texts and, at deployment, the verified sender. To turn on reminders set a time (HH:MM) and days before, then tick Enable.
- *Accounts for Yan / Web Karma:* when their emails are supplied, create each user with the **Enquiry manager** role.
- *Spam:* use Status → Spam (or Flamingo's Spam action); reminders are cancelled.

**Remaining launch dependencies (Phase 06):** verified production sender/domain and Reply-To setup, Yan/Web Karma account emails, reminder timing and assignee fallback, owner review of the provisional form fields and copy, approved privacy policy (the form shows its link once published), working server cron + delivery monitoring on the host, approved production coverage data (fixtures only today), analytics/privacy configuration (no analytics installed; events are ready for `dataLayer`).

---

## Service Areas admin enhancement (owner request, 2026-10-20)

**Owner decision:** the existing Service Areas implementation (spec §9 tables, `DatabaseRepository`, virtual routes, city content records) is **permanent**. The historical city template/rewrite code is **no longer a dependency**. The outdated admin notice about waiting for it was removed. Approved production coverage data remains a separate pending item.

Backup before the schema change: `C:\laragon\backup\catericecream\pre-service-areas-20261020-151230\` (DB dump + `wp-config.php`). Core plugin 0.5.0 → **0.6.0**.

**What was reused (nothing duplicated):** the same tables, `CityStore` writes (validation, audit log, `Tables::bump_cache()`), `Coverage` rules (unchanged), the importer, city content records (`cic_city_editorial`), the existing preview and the `manage_locations` capability with nonce checks on every action.

| Area | What changed |
| --- | --- |
| Schema v4 | One nullable column `manual_at` on `wp_cic_zips` and `wp_cic_city_zips` (dbDelta, no new tables). It records when a coverage decision was made by hand. |
| Add City | "Add City" button on Cities. Fields: city name, state, county (suggests existing counties), auto-generated editable slug, optional coordinates (both or neither), dataset (Fixture is offered on local/dev only). New cities are always **Draft + Noindex** with no ZIPs, so coverage stays "Not confirmed". Refused: duplicate name + county, a slug used by another city, a slug recorded as another city's alias or previous slug, invalid slug/state/coordinates. |
| ZIPs in the city editor | One ZIP or a pasted list (commas, spaces, new lines). Leading zeros kept; ZIP+4 shortened; 3–4 digit values flagged as a lost leading zero; non-California prefixes refused. **Check step first (nothing saved)**: new ZIP / link existing ZIP / already linked / cannot add. Existing shared ZIPs list the other cities and their decisions and explain the scope. New ZIP records start **Not reviewed yet**; new links **Use ZIP coverage**. A production city cannot link fixture ZIPs. |
| Remove a ZIP | "Remove from this city" deletes only that relationship, after a confirmation. The ZIP record and its links to other cities stay. The removal is remembered for imports. |
| Readable coverage controls | `CIC\Locations\Labels` maps internal values to labels with explanations (stored values unchanged). The city editor separates four things: **ZIP coverage — affects every linked city**; **Decision for this city — this relationship only**; **Page publication**; **Search-engine indexing** (with a ✓/✗ checklist of the three requirements). A "Result here" column shows the effective outcome. Changing ZIP coverage on a shared ZIP shows an inline warning naming the other cities, and the save message states how many cities a ZIP-wide change applies to versus "this city only". Exclusion precedence and the shared-ZIP clarification are unchanged. |
| Navigation | Cities rows: **Edit city · Edit/Create content · Preview · View**, plus ZIP count and content status. City editor: action bar with the same actions, and status cards. **Preview now works for every city**: cities without a content record get an authenticated, nonce-protected, noindex city-level preview showing the inherited template. |
| Inherited content | In the city content editor every empty override field shows **"Inherited from the city template: …"** (tokens such as `{city}` resolved; images as thumbnails). Filled fields show the template value and a **Reset to inherited** button, which empties the field; saving then inherits again. Nothing is copied into saved overrides. |
| Data protection | Fixture/production labels unchanged; manual entries take the city's dataset and never relabel fixture data. Imports: a manual ZIP status, a manual city decision or note, and a manually removed link are reported as **conflicts** in the dry run and the applied result (CLI too) and are **left unchanged**. Other geography changes on the same row still apply. |

**Changed files:** `src/Database/Migrations.php` (v4), `src/Locations/{CityStore, Importer, Cli, Editorial, Routes, Labels (new), Tables, DatabaseRepository, LocationRepository, Service}.php`, `src/Locations/Admin/LocationsPage.php`, `src/Content/ViewModel.php` (city-level preview flag), `cater-ice-cream-core.php` (0.6.0), `data/fixtures/README.md`, theme comment in `template-parts/home/section-coverage.php`, `CLAUDE.md`, tests in `dev/tests/service-areas/`. No frontend template, CSS or design value changed.

**Verification (temporary fixture cities/ZIPs; everything restored in `finally`)**
- `dev/tests/service-areas/admin-test.php`: **42/42**. Covers creating a draft city, duplicates, slug/alias collisions, invalid input, ZIP parsing, linking a new and a shared ZIP, no duplicates, the fixture/production guard, city-only vs ZIP-wide changes, exclusion precedence, removal without damaging other cities, import conflicts, slug redirects, inheritance + reset, directory/search/sitemap cache updates, and permissions.
- `dev/tests/service-areas/admin-browser-test.cjs`: **21/21**, through the real screens. Includes the logged-out, bad-nonce and editor-role (no `manage_locations`) requests, all rejected with nothing created or removed.
- Regressions: Phase 01 guard **69/69**, Phase 02 design **51/51**, Phase 03 **46/46**, Phase 04 **25/25**, Phase 05 server **55/55**, browser **24/24**, admin **12/12**, Phase 07 **23/23**, Phase 08 **24/24**.
- Clean state: 0 test cities/users/links left, 61 original fixture mappings intact, no new PHP log entries, no test browsers open.
- Walkthrough screenshots: `dev/tests/output/service-areas/sa-*.png` (git-ignored).

**Not tested:** screen readers, Safari/Firefox, PHPCS.

---

## Phase 08 log: Archives and supporting pages

Prompt: `prompts/08_archives_and_supporting_pages.md`. References 03, 08, 14, 16, 19, 20 and their asset-map rows. Backup: `C:\laragonackup\catericecream\pre-phase08-*`.

**Pages**

| Page | URL | What was built |
| --- | --- | --- |
| Our Treats | https://catericecream.test/treats/ | Hero, sticky jump links, one detailed row per published treat (live product query; product "Our Treats row" fields), "Not sure" panel, non-personal quote starter |
| Events overview | https://catericecream.test/events/ | All four occasions from the live event query, "something else" link (quote with Other occasion), featured treats, steps, starter. Text editable at Events → Overview page (Settings API; the archive has no duplicate Page) |
| About | https://catericecream.test/about/ | Verified facts only; business details come from the contact settings; "YK" placeholder graphic until a real portrait is supplied |
| Contact | https://catericecream.test/contact/ | Call and email cards (configured values), quote alternative, FAQ. The message form is **pending Phase 06**: no alternative form and no success message |
| Policies | `/privacy-policy/`, `/website-terms/` | Policy template (headings outline, "Content pending approval" label, contact from settings). Both pages stay **drafts** (real 404 publicly); menu links to unpublished pages are hidden |
| 404 | any unknown URL | Branded template; real 404 status; noindex |

**Other changes**
- WordPress's default "Sample Page" set to draft.
- About and Contact added to the footer menu.
- Core plugin: new fields; `EventsOverview`; `ViewModel` methods for catalogue, events overview, about, contact and policy; seeder now supports draft status and page template.
- Theme: `pages.css`, page/archive/policy/404 templates, icons and county motifs.

**Verification**
- `dev/tests/phase08/pages-test.php`: **24/24**. Covers statuses, dynamic queries (temporary records), jump links, quote links, configured tel/mailto, no form or fake success, verified facts, 20 links, menus, copy rules, editability, policy outline, seeder idempotence and cleanup.
- `dev/tests/phase08/browser-test.cjs`: **11/11**. Covers no overflow at 390/768/1440, jump links, starter blocking and handoff, Explore, FAQ by keyboard, the mobile menu, admin edits (Overview page and product row), and the policy draft preview while still publicly 404.
- Screenshots: `dev/tests/output/phase08/`.

**Regressions after Phase 08** (one suite at a time):

| Phase | Server | Browser / admin |
| --- | --- | --- |
| 01 guard | **69/69** | — |
| 02 | **51/51** | **58/58** |
| 03 | **46/46** | **22/22** |
| 04 | **25/25** | **22/22**, admin fields **6/6** |
| 05 | **55/55** | **24/24**, admin **12/12** |
| 07 | **23/23** | **13/13**, admin **7/7** |

- **Expectation update:** Phase 04 E2 now expects 57 masters (Phase 08 imported the events hero photo).
- **Test residue found and removed:** an empty draft city-content record for Los Angeles (post 295, no field values) left by an earlier test run. The snapshot/restore had preserved it because it predated the snapshot.
- **Clean state:** no new PHP notices; no test browsers left; only the owner's session remains.

## Build-sequence change (owner instruction, 2026-10-20)

- **Phase 05 is not launch-ready.** It is tested with labelled local fixtures only. Update (owner, later on 2026-10-20): the implementation is permanent and the historical city code is no longer needed; only approved production coverage data is outstanding.
  - The Phase 05 work is preserved unchanged.
  - Fixture rows count only on local/development sites and are visibly labelled as test data.
  - No fixture location is presented as confirmed production coverage.
- **Phase 07 now runs before Phase 06.** This explicitly overrides the v1.1 prerequisite that the vertical slice, including the complete CF7–Flamingo quote flow, passes before page expansion.
  - That prerequisite is **DEFERRED, not passed**.
  - Full quote-flow integration and testing remain **pending Phase 06**.

## Phase 07 log: Event and treat compositions

Prompt: `handoff/cater-ice-cream/prompts/07_event_and_treat_compositions.md`. References read for each page:

| Page | Reference |
| --- | --- |
| Birthday | `04_birthday_final.png` |
| Wedding | `05_wedding_final.png` |
| Anniversary | `06_anniversary_final.png` |
| Corporate | `07_corporate_final.png` |
| Dippin' Dots | `09_treat_dippin_dots.png` |
| DipN' Bananas | `10_treat_dipn_bananas.png` |
| Classic Ice Cream | `11_treat_classic.png` |
| Frozen Yogurt | `12_treat_yogurt.png` |

Also read: `21_mobile_detail_and_quote.png`, plus each page's rows in `PAGE_IMAGE_MAP.csv` / `ASSET_MANIFEST.json`.

**URLs:** `/events/{birthday,wedding,anniversary,corporate}/` and `/treats/{dippin-dots,dipn-bananas,classic-ice-cream,frozen-yogurt}/`.

Stopped after these eight pages for review. Phase 06 and Phase 08 have not started. The homepage, including the restored 190/136 px header logo, is unchanged, and the shared header and footer are reused.

### Backup
`C:\laragon\backup\catericecream\pre-phase07-20261020-103351\` (DB dump, `wp-config.php`, `.htaccess`, SHA256SUMS), taken before the content seed. No schema change in this phase.

### Decisions

| Area | Decision |
| --- | --- |
| Distinct compositions | Each event composition has its own approved section list and order (`Fields::EVENT_LAYOUT_SECTIONS`), plus section variants where the references differ (`single-cic_event.php`), so pages are **not** one reordered template:<br>• Birthday: hero, starter, treats, stories, planning, quote (Phase 03 composition kept).<br>• Wedding: hero (photo + ivory card + rings), ribbon, sweet-moment story, treats, wide photo band, planning, quote panel.<br>• Anniversary: hero (split + hearts), values, treats on a lavender band, story, planning, quote + FAQ.<br>• Corporate: the same order as Anniversary with professional styling (no hearts; strokes and a people-line motif; lavender FAQ card).<br>Treats get a **Composition** field (`Fields::TREAT_LAYOUTS`):<br>• **split**: 09 Dippin' Dots and 10 DipN' Bananas (the references share it; DD has no FAQ in 09, Bananas has its FAQ band).<br>• **classic**: centred hero card between photos, alternating occasion rows, FAQ + starter side by side.<br>• **yogurt**: fresh hero, mood boards **before** occasions, vertical occasion cards with icon badges, intro + starter + FAQ trio. |
| Section order safety | Section "order" fields no longer default to a number. Empty means the approved order of the page's composition, so an admin save can't freeze a generic order (verified A3/A6). |
| Editable content | New ACF Free fields (free types only):<br>• events: ribbon phrases, three value points, story eyebrow/heading/text/photo/inset/three points, wide photo + caption, treats eyebrow;<br>• treats: composition, second/third hero photo, theme icon, FAQ intro.<br>Everything else uses the Phase 03 fields, WooCommerce product fields, the Events CPT and the single pairing table. |
| Content | Copy from the mockups, corrected per the handoff: no prices or minimums, no ® marks, no stock/flavour/dietary guarantees (a cautious allergen disclaimer only), no health claims on Frozen Yogurt, and theme boards labelled "Styling inspiration only". Seeded non-destructively. |
| Pairings | 9 new pairings, so every treat has all four occasions (**16 total**). The three existing seed rows gained their mapped photos only because they had none. Classic × Birthday copy was refreshed only because it still exactly equalled the previous seed text (`Pairings::fill_seeded`). Editor changes are never overwritten. |
| Media | 30 more masters imported idempotently from the asset map (**56 total**, each once). The approved original mascot (`brand/mascot-original.webp`) is bundled with the theme for the "Your treat" pill and the treat FAQ, like the logo. |
| Styling | `assets/css/details.css`: Design System roles/tokens only (no hex).<br>• Occasion moods come from the existing presets (`wedding` ivory/blush/champagne gold, `anniversary` strawberry, `corporate` lavender, `banana`/`classic`/`yogurt`).<br>• Wedding accent words use the bundled Fraunces italic.<br>• The Classic card headline uses the Design System **H2** scale tokens (11 shows a smaller card headline); every other heading keeps the central H1/H2.<br>• `fetchpriority="auto"` on eager non-LCP images stops WordPress promoting a second high-priority image. |
| Motifs | `cic_theme_motif()`: real inline SVG (rings, gold wave, botanical sprigs, double heart, sparkle, strokes, people line), `aria-hidden`, coloured by the preset. Wedding gets a delicate line-trace and sway animation, disabled under `prefers-reduced-motion`. Confetti stays on Birthday only. 9 new line icons (banana, spoon, cup, handshake, building, rings, briefcase, cake, two cups). |
| Quote actions | Every Plan/Get-a-quote link carries the explicit selection (`/get-a-quote/?event=…` or `?treat=…`) through the existing context helper.<br>• One shared **non-personal starter** component (`template-parts/components/quote-starter.php`) on every detail page, with the event or treat fixed: events collect venue city/ZIP, date and guests; treats collect location, event type and date.<br>• It validates, blocks a confirmed outside-area location with the exact message, and lands on the interim quote route, which only shows the choices.<br>• **No second form system, no stored submission, no success message.** Names, emails and phones wait for the CF7 form in **Phase 06**.<br>• The Birthday starter strip now uses the same helper (GET, validated keys) instead of a bare POST. |
| Fix carried from Phase 05 | The autocomplete list styles moved from `locations.css` to `main.css`, so the homepage and detail-page location inputs show a styled list. |

### Files
- **Core plugin (0.5.0):** `src/Content/Fields.php` (layouts, section lists, new fields, icons, order-field default), `src/Content/ViewModel.php` (layout-aware `sections()`, event/treat data), `src/Content/SeedData.php` (event and treat content, pairings, media pages), `src/Content/Seeder.php` + `src/Content/Pairings.php` (`fill_seeded`), `cater-ice-cream-core.php`.
- **Theme:**
  - new `assets/css/details.css`, `assets/brand/mascot-original.webp`;
  - new `template-parts/components/quote-starter.php`;
  - new `template-parts/event/section-{hero-wedding,hero-split,ribbon,values,feature,treats-brands,gallery,planning-band,quote-panel}.php`;
  - new `template-parts/treat/section-{hero-classic,hero-yogurt,occasions-rows,occasions-cards,quote-faq,quote-trio}.php` and `template-parts/treat/your-treat.php`;
  - changed `single-cic_event.php`, `template-parts/treat/{single,section-quote,section-faq,section-themes}.php`, `template-parts/event/section-starter.php`, `inc/content.php` (icons, motifs, image priority, enqueue), `inc/template-tags.php` (mascot), `assets/css/main.css`, `assets/css/locations.css`.
- **Tests:**
  - new `dev/tests/phase07/{details-test.php,browser-test.cjs,admin-details-test.cjs,state.php}`;
  - expectations updated where Phase 07 intentionally changed facts: phase03 `catalog-test.php` D1 (16 pairings) and `admin-edit-test.cjs` S5 (starter via the context helper, non-personal keys only); phase04 `home-test.php` E2 (56 masters).

### Verification actually performed
1. **`dev/tests/phase07/details-test.php`: 23/23.**
   - Every page's section set and order (view model and rendered HTML).
   - Composition-specific markup; confetti only on Birthday and rings only on Wedding; presets.
   - One h1; images with width/height/alt; ≤ 1 high-priority image; no embedded mockups; motifs aria-hidden.
   - All 20 unique content links internal and 200; each treat links all four occasions and each new event all four treats.
   - Quote links and starters carry the event/treat with no personal fields; the quote route shows the selections without any success claim; no form-plugin output.
   - No minimum/price/®/guarantee copy; no yogurt health claims; theme boards labelled as inspiration.
   - 16 pairings, with shared copy on the treat and event pages; no add-to-cart, price or Offer schema, and products non-purchasable; seeder re-run changes nothing; compositions are record settings.
   - The first run found two real issues (core added a second high-priority image on 3 pages, fixed) and three test-expectation errors (corrected).
2. **`dev/tests/phase07/browser-test.cjs`: 13/13** (headless Edge, one browser, closed).
   - No overflow on 8 pages × 390/768/1440.
   - Mobile reading order (Wedding photo then card as in 21; Classic card then scoops; split heroes text then photo); mobile occasion cards two per row.
   - Wedding motion runs and stops under reduced motion (confetti too).
   - Wedding CTA → `/get-a-quote/?event=wedding`, no success text; Bananas CTA carries the treat while keeping the earlier explicit occasion.
   - Corporate starter blocks 90224 with the exact message and keeps the guests answer, then continues with only event/location/guests; Yogurt starter carries treat + occasion + location; Birthday strip uses the helper.
   - **One Design System change (primary action colour) restyled the CTA on all 8 pages without editing them**, every heading inherits the global family, and the restore returned the original colour.
3. **`dev/tests/phase07/admin-details-test.cjs`: 7/7.**
   - Wedding in the block editor: ribbon and story heading saved, shown on the page, approved section order kept.
   - Classic on the product screen: FAQ question and theme title saved and shown, order kept.
   - Restore confirmed.
4. **Regressions, one suite at a time:** Phase 01 guard **69/69** (fixtures created and deleted: catalogue protection intact); Phase 02 server **51/51** and browser **58/58**; Phase 03 server **46/46** and admin **22/22**; Phase 04 server **25/25**, browser **22/22**, admin fields **6/6**; Phase 05 server **55/55**, browser **24/24**, admin **12/12**.
5. **Hygiene:** no PHP notices or warnings in the debug log; no hex colours in the new CSS; every test browser closed; only the owner's WordPress session remains; all test edits restored.
6. **Evidence** (`dev/tests/output/phase07/`, git-ignored; unscaled true widths):
   - `{page}-{390,768,1440}.png` full pages and `-viewport.png` first views for all 8 pages (e.g. wedding-390 390×4566, wedding-1440 1440×3888);
   - 100 `section-{page}-{390,1440}-*.png`;
   - `admin-wedding-fields.png`, `admin-classic-fields.png`.

### Remaining differences from the references (for review)
- **Quote panels:** the mockups show name, email and phone fields. Until the Phase 06 CF7 form exists, the panels collect only non-personal choices and hand over to `/get-a-quote/`.
- **Heading sizes:** the central Design System sizes (approved with the homepage) make some headlines wrap differently from the raster mockups (e.g. "Good teams deserve" breaks after "deserve"). The Classic card headline uses the H2 scale to fit its card.
- **Small icon differences:** the line icons are the bundled set, not pixel copies of each mockup glyph.
- **Botanical/gold line art:** drawn as simple SVG motifs, not traced from the AI images.
- **Not tested:** screen readers, Safari/Firefox, real touch devices, PHPCS.

### Pending
- **Phase 06:** CF7 + Flamingo quote flow (contact fields, storage before notification, Mailpit). This needs the owner's go-ahead to install CF7/Flamingo. Full quote-flow integration and testing from every detail page are **pending**.
- **Quote-flow gate:** built and tested end to end in Phase 06 (see the Phase 06 log); awaiting owner review.
- **Coverage data:** approved production coverage data (the historical city code is no longer needed).
- **Client review:** AI photos, product options/ingredients, and whether DipN' Bananas is a supplier brand.

---

## Phase 05 log: Locations, import, search, routes and SEO

Prompt: `handoff/cater-ice-cream/prompts/05_locations_import_search_routes_and_seo.md` (spec §3, §9–11, §15, §19). References: `designs/13_city_pasadena.png`, `designs/15_service_areas.png`, the Service Areas column of `designs/20_mobile_supporting.png`.

**URLs:**
- hub: https://catericecream.test/service-areas/
- state hub: https://catericecream.test/service-areas/california/
- city: https://catericecream.test/service-areas/california/pasadena/

Stopped here for review. Phase 06 has not started.

> **Everything location-specific you see locally is FIXTURE data, not approved coverage.** `DATA_CONTRACTS.json` has `approved_production_zip_rows: []`. The owner's historical city code is still pending.

### Backup and authorised changes
- Backup before the schema change: `C:\laragon\backup\catericecream\pre-phase05-20261020-090256\` (DB dump, `wp-config.php`, `.htaccess`, SHA256SUMS).
- Schema v3 (migration `migrate_to_3`, dbDelta, configured prefix): `wp_cic_cities`, `wp_cic_zips`, `wp_cic_city_zips`, `wp_cic_city_content`, `wp_cic_city_slugs`.
- Capability `manage_locations` granted to administrators (editors do not have it).
- Core plugin 0.3.0 → **0.4.0**.
- Seed re-run (non-destructive) created the **Service Areas** page (`/service-areas/`, key `service-areas`) with its fields. It also imported 3 masters: `county-los-angeles`, `county-ventura`, `county-santa-barbara` (26 in total).
- Local fixtures imported with `wp cic locations import-fixtures` (20 cities, 60 ZIPs, 61 mappings). A second run reported 0 created, 0 updated, everything unchanged.

### Decisions

| Area | Decision |
| --- | --- |
| Adapter boundary | `CIC\Locations\LocationRepository` now carries the spec §9 contract: `resolve_city`, `search_locations`, `list_counties`, `list_cities`, `get_city_content`, `evaluate_coverage`, `get_map_points` (+ `is_connected`). Selection order: owner adapter from the `cic_location_repository` filter → provisional `DatabaseRepository` (once v3 tables exist) → `PendingRepository`. Templates, REST and search contain no SQL or coverage rules of their own. |
| Fallback schema | Spec §9 columns plus `dataset` (fixture/production), `source_reference`, `county_slug`, `name_search` (ASCII search key) and `cic_city_slugs` (aliases + slug history). Indexes: unique country+state+slug, `name_prefix` (state, name_search; verified by EXPLAIN), state+county+order, reverse `zip` on mappings, unique state+slug history. ZIP is `CHAR(5)`. Marked **provisional**; not a competing production schema. |
| Coverage rules | One pure rule set, `CIC\Locations\Coverage`, shared by REST, pages and the CLI (and Phase 06 through `blocks_quote()`):<br>1. Malformed input → `invalid`; geography we don't hold → `unknown` (never `outside_area`).<br>2. Explicit reviewed exclusions win: a disabled ZIP or disabled city–ZIP mapping can't be re-enabled by another association.<br>3. A shared ZIP whose communities have different decisions → `manual_review` (asks for the venue city). With the venue city, the mapping decides.<br>4. Approved mapping or enabled unambiguous ZIP → `covered`.<br>5. A partially served city → `manual_review` asking for the event ZIP.<br>Covered means geography only, never a date. The outside-area copy is exactly: "Sorry, we do not currently serve this area. Please choose another event location." |
| Fixture guard | Every row records its dataset. `Tables::fixtures_allowed()` counts fixture rows only when `wp_get_environment_type()` is local/development. On staging/production they are ignored (so results become unknown, with no cities) and fixture imports are refused. Fixture-backed pages and results show a visible "Local test data" note; admin rows carry a **FIXTURE** badge. |
| Imports | `CIC\Locations\Importer`:<br>• UTF-8 only (BOM stripped), 5 MB / 20,000-row limits; dry run first.<br>• Deterministic upsert by natural key, applied in one transaction, in key order.<br>• Counts created / updated / unchanged / rejected, plus duplicates.<br>• ZIPs stay strings: ZIP+4 is validated and shortened, and 3–4 digit values are **rejected** as a probable lost leading zero (never padded).<br>• Geography never becomes coverage: statuses and overrides apply only for reviewed coverage files (`--apply-status`), and new ZIPs start `unreviewed`.<br>• City publish/index states are set only on creation (draft/noindex by default).<br>• Fixture rows can never overwrite production rows.<br>• Mappings to unknown cities or ZIPs are rejected.<br>• Every import is logged (hash, counts, mode). |
| Fixtures | `wp-content/plugins/cater-ice-cream-core/data/fixtures/*.csv` + README. Cities follow the county lists in mockup 15; coordinates are rounded approximations. Service states are **invented test scenarios** exercising each rule:<br>• Pasadena fully served; Glendale and Los Angeles partial.<br>• 90220 shared with excluded Compton; 90221 disabled ZIP beats an enabled mapping; Compton excluded (draft, not listed).<br>• 90021/90810 manual review; 91208/93023/93108 unreviewed.<br>Source tag: `fixture:local-dev-2026-10-20`. |
| Routes | Explicit rewrite rules `^service-areas/{state}/` and `^service-areas/{state}/{city}/`, flushed once per `ROUTE_VERSION` (never on normal requests; verified). `/service-areas/` stays a Page.<br>• Unknown state/city, unpublished cities, extra segments and city×event×treat paths → real 404.<br>• Recorded aliases, previous slugs, wrong case and missing trailing slash → **one** 301 to the canonical URL.<br>• The main posts query is skipped for virtual routes. |
| City content | Private, revisioned `cic_city_editorial` records (ACF Free fields; not public, not queryable, not in REST/search/sitemaps; capabilities mapped to `manage_locations`), linked through `cic_city_content`.<br>• Empty fields inherit the **City page template** tab on the Service Areas page, with tokens `{city}`, `{state}`, `{state_code}`, `{county}`.<br>• **Indexable only when** the city is Published + Index **and** its content record is published and ticked "Content reviewed". Otherwise noindex (doorway safeguard).<br>• Authenticated preview at the virtual URL (nonce + capability; noindex, `X-Robots-Tag`, no-cache, preview banner). WordPress hides its own Preview button for non-public types, so the City box offers "Preview city page". |
| SEO | No SEO plugin is installed, so `CIC\Seo\CoreAdapter` is the single adapter for location routes: title, meta description, one canonical (core `rel_canonical` removed there), robots via `wp_robots` (the site-wide "discourage search engines" still applies), Open Graph, BreadcrumbList + Service JSON-LD (provider = global business, areaServed = the city; no address, reviews or ratings).<br>• Query-string variants are noindex with the clean canonical.<br>• Core sitemap provider `wp-sitemap-locations-1.xml` lists the state hub + indexable cities only.<br>• If Yoast, Rank Math, SEOPress, AIOSEO or TSF becomes active, the core adapter stands down and Site Health asks for a plugin adapter (filter `cic_seo_adapter`). |
| Cache | Location reads are cached by a monotonically increasing version (`cic_locations_cache_version`), bumped on every city, ZIP, mapping, slug, import or editorial save, plus an action `cic_locations_changed` for a future page cache. |
| Public API | `GET /cic/v1/locations?q=` (2–60 chars, ≤ 10 results, rate-limited 90/min per hashed IP, no notes, reasons or ids) and `POST /cic/v1/coverage` (`location` + optional venue `city`; 30/min). Both send `Cache-Control: no-store`. |
| Frontend | Accessible ARIA combobox autocomplete (250 ms debounce, ≥ 2 chars, ≤ 6 options, Abort on retype) on every location input, homepage included.<br>• Area checks clear stale results while checking, show status-specific messages, and link covered results to the city page and the quote.<br>• Starter forms ask the coverage service before continuing: a confirmed outside_area **blocks** with the exact error (other answers kept); unknown/manual review continue to the quote for clarification. |
| Homepage connection | Composition unchanged (approved). The county lists now come from the repository (published cities only, linked to their pages; Compton never appears), and the hero/area inputs gained autocomplete. The editor's plain city list is used only when no location data is connected. |
| Design | `assets/css/locations.css` uses Design System tokens and preset roles only (no hex), building on `home.css`.<br>• Call panels are plum (`action-secondary` + `on-action`), as in references 13/15.<br>• California map: the local simplified outline with real points projected from the reviewed ZIP centroids, grouped by county and labelled in a staggered row (approximate points, never boundaries).<br>• The city "location preview" is decorative and captioned "Illustrative location preview". |

### Files
- **Core plugin:**
  - new `src/Locations/{Tables,Coverage,DatabaseRepository,CityStore,Importer,Editorial,Routes,Cli}.php`, `src/Locations/Admin/LocationsPage.php`, `src/Seo/{Adapter,CoreAdapter,LocationSitemap,Service}.php`, `data/fixtures/{cities,zips,city-zips}.fixture.csv` + `README.md`;
  - changed `src/Locations/{LocationRepository,PendingRepository,Service}.php`, `src/Database/Migrations.php` (v3), `src/Content/{Fields,ViewModel,Seeder,SeedData}.php`, `src/Quote/Context.php` (ZIP+4, "City, ST"), `src/Plugin.php`, `cater-ice-cream-core.php` (0.4.0).
- **Theme:**
  - new `page-service-areas.php`, `location-hub.php`, `location-city.php`, `template-parts/location/{hub,hub-hero,hub-counties,hub-steps,hub-call,hub-occasions,hub-faq,hub-start,city-hero,city-plans,city-treats,city-call,city-occasions,city-check,city-faq,city-nearby}.php`, `template-parts/components/{area-check,ca-map,call-panel,occasion-cards}.php`, `assets/css/locations.css`;
  - changed `assets/js/context.js`, `inc/content.php` (icons home/sundae/phone/chevron, enqueue, endpoints, messages), `inc/setup.php` (Service Areas menu state on virtual routes), `template-parts/components/hero-search.php` (optional fixed city), `template-parts/home/section-coverage.php` (linked cities).
- **Tests:**
  - new `dev/tests/phase05/{locations-test.php,browser-test.cjs,admin-locations-test.cjs,state.php}`;
  - expectations updated: phase03 `catalog-test.php` A2 (schema = migration target), phase04 `home-test.php` B1/B2/C6/E2 and `browser-test.cjs` A1 (they asserted the pre-Phase-05 "pending, no cities, 23 masters" state).

### Admin (Service Areas menu, `manage_locations`)
- **Cities:** search, county/status filters, pagination, coverage and indexability per city, fixture badges.
- **City editor:** name, slug (old slug kept as a redirect), county, publish and search-engine status (separate), order, approximate coordinates, aliases, and per-ZIP decisions (ZIP status for all cities + this city's override + internal note, shared-ZIP count). "Create city content" / "Edit city content" / "Preview city page".
- **City content:** the private revisioned records.
- **Import:** upload → dry-run report (counts, rejections with line numbers, planned changes) → "Apply this import".
- **Log:** audit trail + recent imports.
- **WP-CLI:** `wp cic locations import <file> --type --dataset --source [--apply-status] [--apply]`, `import-fixtures [--dry-run]`, `status`, `check <zip|city> [--city=]`.

### Verification actually performed
1. **`dev/tests/phase05/locations-test.php`: 55/55, run twice in a row.**
   - Schema/indexes (incl. EXPLAIN on the prefix lookup) and a 26-case coverage matrix.
   - Exact outside copy and actions.
   - Import safety (dry run writes nothing; `02108` keeps its zero; ZIP+4 shortened; 4-digit rejected as a lost zero; duplicates reported; repeat import unchanged; geography never changes a reviewed status; fixture can't overwrite production; invalid UTF-8, missing columns and missing source refused; BOM and accents; referential rejections).
   - Fixture guard; routes over HTTP (200/301/404, alias and slug-history one-hop, unpublished 404, forged preview 404).
   - SEO (single canonical, titles, noindex until reviewed, indexable after review, JSON-LD without address or ratings, OG, query variants noindex).
   - Sitemap (hub + reviewed city only; removed again after un-review).
   - Cache invalidation; injection/wildcard queries return nothing; a static scan of SQL interpolation.
   - REST field privacy and limits; homepage links (no Compton); no price/minimum copy; global tel only; rewrite rules unchanged across requests; editorial privacy/revisions/capabilities; cleanup.
2. **`dev/tests/phase05/browser-test.cjs`: 24/24** (headless Edge, one browser, closed).
   - No overflow at 390/768/1440 for hub/state/city.
   - Combobox semantics and keyboard selection.
   - Covered / outside (no quote link) / shared / unknown / partial / invalid results.
   - Starter blocked on an excluded ZIP with answers kept; covered continues with only validated context; sessionStorage keys allow-listed.
   - City breadcrumbs, menu state, fixture notice and section order; nearby cities.
   - City ZIP check sends the venue city; hero starter carries the city key; the quote panel never prefills a city key into the ZIP field, carries ZIP+guests, and blocks an excluded ZIP.
   - Mobile accordions, hidden tabs, map below the search.
   - Homepage links, autocomplete and unchanged section order; browsing never selects a location.
3. **`dev/tests/phase05/admin-locations-test.cjs`: 12/12.**
   - Real screens: list with badges and search; city editor; slug change → one-hop 301 → restored.
   - City content created; override saved; preview shows banner, override, noindex and X-Robots-Tag; the public doesn't see the override.
   - CSV upload → dry run (writes nothing) → apply (draft/noindex city, dataset and source recorded, 404 publicly) → log entries; cleanup verified.
4. **Regressions on the final code, one suite at a time:** Phase 01 guard **69/69** (fixtures created and deleted); Phase 02 server **51/51** and browser **58/58**; Phase 03 server **46/46** and admin browser **22/22**; Phase 04 server **25/25**, browser **22/22**, admin fields **6/6**.
5. **CLI coverage matrix** (`wp cic locations check …`) matched the rules for every fixture scenario.
6. **Hygiene:**
   - No PHP notices, warnings or fatals in the debug log after the one fix below.
   - No hex colours in new theme CSS or templates.
   - Every test browser closed; only the owner's WordPress session remains (two test sessions leaked by early admin-test crashes were destroyed, and the test now creates its session inside `try`).
   - Location tables restored to the fixture baseline after every test.
7. **Evidence** (`dev/tests/output/phase05/`, git-ignored; unscaled true-width captures):
   - full pages and first views `{hub,state,city}-{390,768,1440}[-viewport].png` (e.g. hub-390 390×6396, city-1440 1440×4307);
   - 30 `section-{hub,city}-{390,1440}-*.png`;
   - `autocomplete-1440.png`, `area-outside-1440.png`, `starter-blocked-1440.png`;
   - `admin-{cities,city-edit,city-content,preview,import-dry-run,log}.png`.

### Issues found and fixed during Phase 05
- **Every page returned 500 for a few minutes:** `pre_handle_404` receives `WP_Query`, not `WP`. Fixed; the homepage and all routes were re-verified.
- **Test helper reused cached results:** restoring the cache-version option reused version numbers and served stale cached reads. The version now only increases; the snapshot helper no longer restores it.
- **Stale area-check answers:** a result could briefly show the previous answer. The check now clears the result and sets `aria-busy` while checking.
- **No Preview button for city content:** WordPress hides it for non-public types, so an explicit "Preview city page" link was added.
- **Map labels overlapped:** staggered labels with leader lines.
- **City hero:** the intro sat under the search card. The card no longer overlaps on the city hero.
- **Call panel colour:** it used the strawberry call-panel token; it now uses plum (`action-secondary`) as in the references.
- **"Explore all treats" card:** it had no photo; it now uses the homepage "more treats" photo.

### Remaining differences from the references (for review)
- **Hero headline length:** the hub ("A sweet celebration, closer to home.") and city ("Ice cream catering in Pasadena.") headlines wrap onto three lines at 1440 px because they use the central Design System H1 (44→72 px, weight 800) approved with the homepage. The mockups show two lines. Not changed locally, by rule.
- **City quote panel:** it shows only non-personal fields (event, date, guests, venue city, event ZIP) and hands off to `/get-a-quote/`. Name/email/phone wait for the shared CF7 form in Phase 06; the mockup shows them inline.
- **Hub map:** the California outline with data-driven county points (a simplified local outline), not the mockup's zoomed decorative illustration. The city map is decorative and labelled "Illustrative location preview".
- **County lists:** they show fixture cities matching the mockup's names, while all real coverage is pending. Nearby areas are computed from data (Pasadena shows Altadena · Glendale · Los Angeles; the mockup showed Burbank).
- **Hub hint text:** rewritten to "Not sure if we serve your area? Start with your event location." so it doesn't name cities before coverage is approved.
- **Not tested:** screen readers, Safari/Firefox, real touch devices, PHPCS.

### Dependencies that need the owner's city code or final dataset
| Item | Needed | Until then |
| --- | --- | --- |
| ~~Historical city template + rewrite-rule function and schema~~ | **No longer needed** (owner decision 2026-10-20: the current implementation is permanent) | — |
| Approved production ZIP/city dataset (LA County, client Ventura and Santa Barbara lists, Compton exclusions, shared/nonresidential ZIP decisions) | Import with `--dataset=production --apply-status` after review | Fixtures, local/development only |
| Reviewed city copy (per city) | City content records ticked "Content reviewed" | All cities noindex, inheriting the template |
| SEO plugin choice (if any) | A plugin adapter | Core adapter |
| Final quote form (Phase 06) | Server-side coverage validation at submission (`Coverage::blocks_quote`) | Client-side blocking + interim quote route |

**Mapping report (superseded 2026-10-20; kept for the record):** no historical code has been supplied, so nothing has been mapped and no historical schema is assumed. The audit will compare its tables, columns, slugs and rewrite rules against the adapter contract above, then either:
- implement a legacy adapter, if the owner's schema satisfies the contract; or
- migrate approved rows through the importer (production dataset).

Any route difference will be handled with one-hop redirects recorded in `cic_city_slugs`.

---

## Phase 04 log: Homepage and shared components

Prompt: `handoff/cater-ice-cream/prompts/04_homepage_and_shared_components.md`. **Homepage URL: https://catericecream.test/** (static front page "Home"). Stopped here for the owner's visual review.

### Changes

- Phase 03 committed first: `d055da2` (local only, staged set reviewed; no secrets, logs, backups, fixtures, uploads or third-party plugins).
- Seeder (non-destructive, re-runnable) created the **Home** page (set as the static front page only because the site still showed latest posts) and the **Get a Quote** page, filled the homepage fields, card taglines and the missing event featured images, and imported **3 more masters** for the `home` manifest page (`wedding-couple`, `corporate-friends`, `anniversary-couple`). Total imported masters: 23, each once.
- No new plugins. Logo, palette and Design System defaults unchanged.

### Decisions

| Concern | Implementation |
| --- | --- |
| Homepage composition | `front-page.php` renders, in approved order: hero (eyebrow, two-line headline, main photo + two insets on a lavender swoosh) with the overlapping hero search; value points; treats (two large brand cards + compact cards + More Frozen Treats); events band (wave); How It Works process strip (anchor `#how-it-works`); FAQ split; service-area block with illustrative map; closing call panel; then the shared footer. Mobile follows reference 02 (text, search card, photo, values …). Corrections applied: **Anniversary added** as a fourth event card, **no minimum-order or price copy** (search note and FAQ rewritten), global menu used as-is (includes About per spec). |
| Editing | ACF Free field group "Homepage composition" (front page only, fixed slots, show/order/preset per section): hero copy and three photos, search labels/CTA/note, value points, treats/events headings, process steps + CTA, 6 FAQ slots, coverage copy/counties/cities/caption, closing CTA (+ optional phone). Treat and event cards come from published records (menu order); treats gained "Catalogue card tagline" and "link label" fields. |
| Shared components | Reusable partials: `components/hero-search.php` (quote starter), `components/process.php`; existing call panel, FAQ accordion, cards and header/footer reused. Header three rows, mobile drawer and footer from Phase 02 apply to every template (verified). |
| Quote context (central helper) | `CIC\Quote\Context` (server) + `assets/js/context.js` (browser). Only non-personal fields: event key (published events or `other`), treat keys (published, active), location (5-digit ZIP or city key), date (Los Angeles calendar date, today to +3 years, no UTC conversion), guests. Invalid values are dropped. Explicit actions (search submit, quote links) merge into sessionStorage; the newest explicit value wins per field, and viewing pages never changes it. The search prefills from stored choices. No personal fields are accepted. |
| Quote route | `/get-a-quote/` (interim template `page-get-a-quote.php`) shows the server-validated selections and the call/email options plus the availability note. It does not claim availability. Phase 06 replaces it with the CF7 three-step flow. |
| Location dependency (isolated) | `CIC\Locations\LocationRepository` interface + `PendingRepository`, selectable through the `cic_location_repository` filter for the owner's code. `POST /cic/v1/coverage` (validated, rate-limited, `no-store`) returns `unknown` ("we'll confirm with your quote") for valid input and `invalid` for malformed input, and **never `covered` or `outside_area`** until real data is connected. The handoff's approved ZIP list is empty, so the homepage directory shows only the spec-confirmed counties. City lists are editable fields left empty, and become accordions on mobile and links once city pages exist. The California map is a simplified outline labelled "Illustrative service-area preview", not a boundary. No competing city schema. |
| Images | All homepage photos are imported attachments (manifest slots) rendered with `wp_get_attachment_image` (width/height, srcset, sizes). Only the hero photo is `fetchpriority="high"` + eager (the header logo no longer claims high priority); the insets are eager; everything below the fold lazy-loads. |
| Fixes to shared parts | In-page anchor menu items (How It Works) are no longer marked as the current page; header logo no longer competes for high fetch priority. |

### Verification actually performed

1. `wp eval-file dev/tests/phase04/home-test.php`: **25/25**:
   - context validation (valid kept, bogus/past/personal dropped, LA dates, ZIP leading zeros, draft events rejected, URL builder);
   - coverage adapter (unknown/invalid, POST-only, not connected);
   - homepage data (front page, section order, Anniversary, search fields, all images resolved, no served cities);
   - rendered rules (no minimum/price text, one `h1`, every image with width/height/srcset/sizes, single high-priority image, tel links, anchor not "current", search GET carries only location/event/date);
   - seed re-run changes nothing; 23 masters, each once.
2. `node dev/tests/phase04/browser-test.cjs` (one headless Edge, sequential, closed in `finally`): **22/22**:
   - hero handoff: invalid input stays with an announced error and focus; a valid search lands on `/get-a-quote/?event=anniversary&location=pasadena&date=…` with only those 3 params, and the page shows the validated summary;
   - viewing pages doesn't change the context; a treat quote action adds the treat and keeps the rest; a newer event choice wins; returning home prefills the search, and past dates are blocked;
   - area check gives the honest "can't confirm online yet" answer with a quote link; malformed input is flagged;
   - menu items; How It Works lands below the sticky header;
   - first Tab reaches the skip link; keyboard reaches the search and shows a 3 px focus ring; every tel link matches the displayed number; mobile drawer opens with focus on Close and Escape returns focus;
   - one top-row edit appeared on home, treat, event, quote and catalogue pages;
   - a homepage field edited in the block editor appeared publicly;
   - no horizontal overflow at 390/768/1440; approved section order.

   Edited state restored; no browser processes left.
3. Regression on the final code, run one suite at a time: Phase 01 guard **69/69**; Phase 02 server **51/51** and browser **58/58**; Phase 03 server **46/46** (one media-count assertion updated from "exactly 20" to "no duplicates" because Phase 04 legitimately added 3 masters) and browser **22/22** (high-priority image expectation updated to 1). No PHP notices/warnings in the debug log during the final runs.
4. Visual comparison with 01/02 at 1440, 768 and 390 px. Corrected along the way:
   - lazy-image capture timing (screenshots now wait for decode);
   - "How It Works" wrongly highlighted;
   - mobile hero order (search card before the photo, as in 02);
   - DipN' Bananas title overflowing at 390 and 768 (brand cards stack until 1000 px);
   - oversized coverage map;
   - two high-priority images.
5. Screenshots (git-ignored) in `dev/tests/output/phase04/`: `home-{390,768,1440}.png` (full page), `home-{390,768,1440}-viewport.png` (first view), `admin-home-fields.png`.

### Phase 04 visual correction pass (2026-10-20)

Requested by the owner after the first review. Sections, order, navigation, the Anniversary card and the removal of minimum-order wording are unchanged. Logo artwork unchanged (size only).

**1. Typography**
- **Loaded file, checked directly** (WOFF2 tables decoded with Node's built-in Brotli):
  - `fraunces-roman-latin.woff2` is variable with a **single `wght` axis, 100–900**, and no `opsz`/`SOFT`/`WONK` axes (Google strips unrequested axes; the outlines are Fraunces's default display master).
  - `fraunces-italic-latin.woff2` is static (600).
  - `dmsans-roman-latin.woff2` is variable, `wght` 100–1000.
- **Defect found and fixed:** `@font-face` declared Fraunces as `600 700`, so 800/900 silently rendered as 700. Now declared `100 900` (same file, no new download) in `theme.json`. The Design System allowlist accepts Fraunces 600/700/800/900. Fraunces is still the heading font; nothing was replaced or transformed.
- **Comparison:** `dev/tests/output/phase04/typography-comparison.png` (reference crop beside Fraunces 700/800/900, tracking 0 and −0.02 em).
  - The reference's stroke weight sits between 800 and 900.
  - **Remaining discrepancy (cannot be fixed within the rules):** the reference glyphs are noticeably **condensed**, with narrow letters and a taller x-height (≈1.3–1.4× the height for the same line width). Fraunces has no width axis, and the shipped file has no optical-size/soft axes. Matching that character would need CSS distortion or a different font, neither of which is allowed. The reference's condensed headings cannot be reproduced with the specified Fraunces.
- **Central tuning, saved in the Design System** (owner-level setting, resettable to the spec defaults with the Reset buttons):
  - heading weight **800** (spec default 700; the Wedding preset keeps 600);
  - heading letter-spacing **−0.01 em**;
  - H1 **44/46 px mobile → 72/76 px desktop** (spec 40/44 → 64/70);
  - H2 desktop **44 px** (spec 42).

  The homepage's local H1 size override was removed, so sizes come only from the Design System.

**2. Header and hero**
- **Logo:** default width 190 → **250 px desktop**, 136 → **168 px mobile** (Header settings defaults; still editable 120–320 / 96–220).
- **Desktop hero:**
  - larger main photo with a more organic silhouette;
  - framed insets overlapping its right edge;
  - an asymmetric lavender blob, a soft lavender ring and a raspberry spark (decorative, `aria-hidden`).
- **Mobile order** kept: headline, intro, search form, main photo.

**3. Treat cards**
- **Brand cards are one composition:** each photo fills the right of the card and dissolves into the card tint through a mask gradient, so the text sits on the same surface (no rectangular halves).
- **Small screens:** the photo is narrower, so link text stays on the tint.
- **Small cards on mobile:** label left, arrow aligned right; the whole card is the link target (≥ 44 px).

**4. Section rhythm**
- Less empty space around the event cards.
- Waves are now asymmetric and differ per band (events top/bottom, service areas, closing CTA) instead of one symmetric curve.
- The mobile process steps have their subtle connecting line again.

**5. Pending (unchanged, not invented)**
- Footer white/reversed logo: still pending; the original logo sits on a light plate. No replacement was drawn.
- County city lists and coverage integration: still pending the owner's city code and approved ZIP data.

**6. Admin editing verification** (`dev/tests/phase04/admin-fields-test.cjs`, **6/6**)
- The expanded "Homepage composition" panel shows nine section tabs (capture: `admin-fields-hero-tab.png`, `admin-fields-treats-tab.png`).
- Through that panel, the test changed:
  - the **Treats heading** (with accent markup);
  - the **hero main photo**, picked in the real Media Library modal (`admin-media-modal.png`);
  - the **search CTA label**.
- Each change was saved in the block editor and confirmed on the public homepage, then the originals were restored and re-confirmed.
- The empty Gutenberg canvas was not used as evidence; homepage content lives in the field panel.
- Fixed while checking: field help text showed "Wrap accent words in …." because ACF rendered the `<em>` example as markup. The tags are now shown literally.

**7. Evidence and checks actually performed**
- Unscaled screenshots at true CSS widths: `home-390.png` (390×6960), `home-768.png` (768×5597), `home-1440.png` (1440×3903), the matching `-viewport.png` first views, and 16 readable section captures (`section-{390,1440}-{section}.png`).
- Phase 04 browser **22/22**, admin fields **6/6**, server **25/25**.
- Regressions: Phase 01 guard **69/69**; Phase 02 server **51/51**, browser **58/58**; Phase 03 server **46/46**, browser **22/22**.
- Three test expectations were updated because they hardcoded values this pass deliberately changed: weight 900 is now bundled; the logo default is 250; the H2/H1 scale is read from the saved Design System. No product defects.
- Browser checks ran one at a time, each closing its browser (no Edge processes left). Fixtures cleaned up, only the owner's session remains, and no PHP notices/warnings were logged.

**Remaining differences for review**
- Condensed heading character (font limitation above).
- Heading weight/size tuned above the written spec defaults (owner may reset).
- Placeholders as listed in the Phase 04 log (city lists, illustrative map, top-row text, footer logo plate, AI photos).

### Owner approval and header logo adjustment (2026-10-20)

- **The owner approved the homepage** as built after the visual correction pass. Its sections, order, typography (Design System tuning included), colours and layout are frozen; no further visual correction pass.
- **One adjustment:** the HEADER logo returns to its initial Phase 04 size. From Git history (`git diff d055da2 70479ad`), the values before the correction pass were **190 px desktop / 136 px mobile**; the pass had raised them to 250 / 168. Only those values were restored, in the three places that hold them:
  - `src/Site/Layout.php` (admin control defaults);
  - `inc/template-tags.php` (theme fallbacks);
  - `assets/css/main.css` (CSS variable fallbacks).

  Nothing else from the correction pass was reverted. The size stays editable under Header settings (desktop 120–320, mobile 96–220). No saved `cic_site_layout` option exists, so the defaults apply. The footer logo (its own `logo_width` 168, rendered at 144 px) is unchanged.
- **Verified:** headless Edge, one browser, closed afterwards. The header logo measured **136 px at 390 and 768 px** and **190 px at 1440 px**, the footer logo was unchanged and there was no horizontal overflow. Captures: `dev/tests/output/phase04/header-logo-{390,768,1440}.png`. Phase 02 server **51/51** and browser **58/58**, Phase 04 server **25/25**.

### Remaining differences from the references (for the owner's visual review)

- Layout matches; differences that remain are deliberate corrections or placeholders:
  - Four event cards instead of three (Anniversary correction).
  - FAQ and search note rewritten without minimum-order text.
  - The main menu includes About (spec), which the mockup omits.
  - The mobile eyebrow uses the same editable text as desktop ("For the moments worth celebrating"; the mobile mockup shows "Southern California catering").
- **Placeholder content (not layout defects):**
  - County city lists are empty until the approved ZIP/city data and the owner's city code arrive.
  - The map is illustrative.
  - Top-row text is a placeholder.
  - The footer shows the original logo on a light plate (approved reversed logo pending).
  - Photos are AI recreations awaiting client review.
- **Menu destinations still missing:** `/about/`, `/service-areas/` and the policy pages (later phases). `/get-a-quote/` now exists as the interim route.
- **Not tested:** screen readers, Safari/Firefox, real touch devices; PHPCS (not installed).

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
| Cities | Custom tables + virtual routes behind a location-repository adapter | **Permanent (05 + Service Areas admin):** spec §9 tables (schema v4), `DatabaseRepository`, importer with manual-edit conflict protection, Service Areas admin (Add City, ZIP management), virtual routes, private city content; local fixtures only until approved coverage data arrives |
| Enquiries | CF7 (3-step) + Flamingo as sole store | Phase 06 |
| Scheduler | Action Scheduler (bundled, 4.1.0) | Available |
| Mail (local) | Mailpit | Capture verified (01) |
| SEO | One adapter; none installed | **Core adapter (05)** for location routes + core sitemap provider; stands down if an SEO plugin is activated |
| Design | Original raspberry Cater logo + original palette; lavender experiment not adopted | Design System built (02); defaults = spec §5 |

## Dependencies and pending inputs

| Input | Status | Blocks |
| --- | --- | --- |
| Owner's historical city template + rewrite-rule code | **No longer a dependency** (owner decision 2026-10-20) | — |
| ACF (free) | **Installed (03)**: 6.8.10 | — |
| Contact Form 7, Flamingo | **Installed (06)**: CF7 6.2.2, Flamingo 2.6.4 (free) | — |
| Final quote/contact form-field review | Pending (fields are implementation defaults, provisional) | Form sign-off |
| Approved coverage dataset (LA County, Ventura, Santa Barbara, Compton exclusion, shared ZIPs) | Pending reconciliation; import path ready (`--dataset=production --apply-status`) | Coverage go-live (fixtures are ignored outside local/dev) |
| Reviewed per-city copy | Pending | City indexing (all cities noindex until reviewed) |
| Reminder timing, assignee fallback | Pending; controls built (Enquiries → Settings), reminders stay disabled | Reminders |
| Yan / Web Karma account emails | Pending; the "Enquiry manager" role is ready, no accounts created | Enquiry-access accounts |
| Verified production mail sender / domain | Pending; sender fields empty (WordPress default) | Live notifications |
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
6. Keep the location repository adapter boundary strict (all location reads through `LocationRepository`, writes through `CityStore`/`Importer`).
7. Mockups contain obsolete minimum-order copy, typos and invented marks; follow DESIGN_REFERENCE_INDEX.
8. AI-recreated photos must not be presented as real clients, staff or inventory.

## Phase plan

| Phase | Deliverable | Gate |
| --- | --- | --- |
| 01 ✔ | Theme shell, core plugin, MU guard | Passed 2026-10-19 |
| 02 ✔ | Design System admin + style preview + header/footer controls | Passed 2026-10-19 |
| 03 ✔ | Products + Events models (ACF Free install) | Passed 2026-10-19 |
| 04 ✔ | Three-row header/footer + homepage | Approved by owner 2026-10-20 |
| 05 | Locations adapter, importer, search, virtual routes, SEO + Service Areas admin | Fixture tests passed 2026-10-20; implementation permanent; **not launch-ready** until approved coverage data is loaded |
| 08 ✔ | Archives, hubs, About, Contact, policies, 404 | Built and verified 2026-10-20; awaiting owner review |
| 07 ✔ (before 06) | Event + treat compositions (8 detail pages) | Built and verified 2026-10-20; awaiting owner review |
| 06 | CF7/Flamingo quote wizard, lead admin, mail, reminders | Built and verified 2026-10-20 (save-before-notify proven, Mailpit); awaiting owner review |
| — | Vertical-slice gate (complete quote flow) | Built and tested in Phase 06; owner review pending |
| 09 | Visual, accessibility, performance review | Done 2026-10-20: defects fixed and re-verified, remaining items recorded; owner review pending |
| 10 | Release candidate + operational guide | rc1 prepared 2026-10-20: package, deployment and restore rehearsed on disposable local copies (no staging host exists yet); host rehearsal and launch inputs outstanding |

## Next phase

**Owner review** of the 2026-10-21 refinements (uncommitted), rc1 (`docs/release/`, now outdated), Phases 06–09 and the Service Areas admin; supply of the business inputs in `docs/release/LAUNCH_CHECKLIST.md` §A. After the refinements are approved and committed, regenerate the release candidate. Staging deployment, production deployment, DNS, indexing, policy publication, reminders and real mail each need a separate explicit instruction.
