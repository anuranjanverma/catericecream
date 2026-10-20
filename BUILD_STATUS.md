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
| 05 Locations, import, search, routes and SEO | **Provisional: built and tested with local fixtures only; NOT launch-ready.** Production city/state integration deferred until the owner supplies the historical city code and approved coverage data | 2026-10-20 |
| 06 Quote flow, lead management, mail and reminders | **Pending.** Deferred by owner instruction to after Phase 07; full quote-flow integration and testing outstanding | |
| 07 Event and treat compositions | **Eight detail pages built and verified, awaiting owner review.** Built before Phase 06 by owner instruction; the vertical-slice/quote-flow prerequisite is **DEFERRED, not passed** | 2026-10-20 |
| 08 Archives and supporting pages | **Built and verified, awaiting owner review.** Contact submissions + full quote flow pending Phase 06 | 2026-10-20 |

Git (local only, no remote): `7f2cbc3` Phase 00–01 baseline; `ccc05e9` Phase 02; `d055da2` Phase 03; `70479ad` Phase 04 + visual correction pass; `78b50d8` homepage approval + header logo restored to initial Phase 04 size; `6f72409` Phase 05 (provisional). Each was committed at the owner's request after reviewing the staged set. Phase 07 is **not committed yet**.

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

- **Phase 05 is provisional.** It is tested with labelled local fixtures only and is **not launch-ready**. Production city/state integration is deferred until the owner supplies the historical city code and approved coverage data.
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
- **Deferred gate:** vertical-slice gate (complete quote flow) recorded as **deferred** by owner instruction.
- **City code:** owner's city code + approved coverage data (Phase 05 production integration).
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
| Historical city template + rewrite-rule function and schema | Audit, mapping report, legacy adapter implementing `LocationRepository` (via `cic_location_repository`), route compatibility decision | Provisional `DatabaseRepository` + `Routes` (`/service-areas/{state}/{city}/`) |
| Approved production ZIP/city dataset (LA County, client Ventura and Santa Barbara lists, Compton exclusions, shared/nonresidential ZIP decisions) | Import with `--dataset=production --apply-status` after review | Fixtures, local/development only |
| Reviewed city copy (per city) | City content records ticked "Content reviewed" | All cities noindex, inheriting the template |
| SEO plugin choice (if any) | A plugin adapter | Core adapter |
| Final quote form (Phase 06) | Server-side coverage validation at submission (`Coverage::blocks_quote`) | Client-side blocking + interim quote route |

**Mapping report (prepared for when the owner's code arrives):** no historical code has been supplied, so nothing has been mapped and no historical schema is assumed. The audit will compare its tables, columns, slugs and rewrite rules against the adapter contract above, then either:
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
| Cities | Custom tables + virtual routes behind a location-repository adapter | **Provisional (05):** spec §9 fallback tables (schema v3), `DatabaseRepository`, importer, virtual routes, private city content; local fixtures only. Owner's city code pending (legacy adapter via `cic_location_repository`) |
| Enquiries | CF7 (3-step) + Flamingo as sole store | Phase 06 |
| Scheduler | Action Scheduler (bundled, 4.1.0) | Available |
| Mail (local) | Mailpit | Capture verified (01) |
| SEO | One adapter; none installed | **Core adapter (05)** for location routes + core sitemap provider; stands down if an SEO plugin is activated |
| Design | Original raspberry Cater logo + original palette; lavender experiment not adopted | Design System built (02); defaults = spec §5 |

## Dependencies and pending inputs

| Input | Status | Blocks |
| --- | --- | --- |
| **Owner's city template + rewrite-rule code and schema** | **Pending.** Phase 05 built the provisional fallback behind the adapter (see the Phase 05 mapping notes); no historical schema assumed | Legacy adapter / final routes |
| ACF (free) | **Installed (03)**: 6.8.10 | — |
| Contact Form 7, Flamingo | Not installed | Phase 06 |
| Final quote/contact form-field review | Pending | Phase 06 sign-off |
| Approved coverage dataset (LA County, Ventura, Santa Barbara, Compton exclusion, shared ZIPs) | Pending reconciliation; import path ready (`--dataset=production --apply-status`) | Coverage go-live (fixtures are ignored outside local/dev) |
| Reviewed per-city copy | Pending | City indexing (all cities noindex until reviewed) |
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
| 04 ✔ | Three-row header/footer + homepage | Approved by owner 2026-10-20 |
| 05 (provisional) | Locations adapter, importer, search, virtual routes, SEO | Fixture tests passed 2026-10-20; **not launch-ready**: production integration deferred (city code + approved data) |
| 07 ✔ (before 06) | Event + treat compositions (8 detail pages) | Built and verified 2026-10-20; awaiting owner review |
| 06 | CF7/Flamingo quote wizard, lead admin, mail, reminders | Pending: save-before-notify proven in Mailpit |
| — | Vertical-slice gate (complete quote flow) | **Deferred by owner instruction** (not passed) until Phase 06 |
| 08 | Archives, hubs, About, Contact, policies, 404 | Navigation and forms complete |
| 09 | Visual, accessibility, performance review | Differences fixed or recorded |
| 10 | Release candidate + operational guide | Backup/rollback rehearsed on staging |

## Next phase

**Owner review of the Phase 07 detail pages** (`/events/{birthday,wedding,anniversary,corporate}/`, `/treats/{dippin-dots,dipn-bananas,classic-ice-cream,frozen-yogurt}/`, screenshots in `dev/tests/output/phase07/`).

Then, when asked, **Phase 06: Quote flow, lead management, mail and reminders** (`prompts/06_quote_flow_lead_management_mail_and_reminders.md`). It needs the owner's go-ahead to install CF7 + Flamingo, and closes the deferred quote-flow gate.

Phase 05 production integration waits for the owner's city code and approved coverage data.
