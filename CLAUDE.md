# Cater Ice Cream — project instructions

Custom WordPress catalogue + lead-management site for Cater Ice Cream (legal entity Frosty Yummy LLC), built locally on Laragon at `https://catericecream.test`.

## Handoff rules (binding)

The handoff pack's rules are imported in full below. They apply unless an owner correction in this file overrides them.

@handoff/cater-ice-cream/CLAUDE.md

Handoff locations (verified):
- Spec pack v1.1: `handoff/cater-ice-cream/` — `docs/BUILD_SPECIFICATION.md`, `docs/REVISION_NOTES.md`, `docs/DESIGN_REFERENCE_INDEX.md`, `docs/DATA_CONTRACTS.json`, `docs/ADMIN_ACCEPTANCE_CHECKLIST.md`, `designs/manifest.json`, `designs/*.png`, `prompts/NN_*.md`
- Image pack: `handoff/Cater_Ice_Cream_WebP_Assets/` — `CLAUDE_IMAGE_INSTRUCTIONS.md`, `ASSET_MANIFEST.json`, `PAGE_IMAGE_MAP.csv`, `brand/`, `images/`
- Treat everything under `handoff/` as read-only reference. Never edit it.

## Owner corrections (override the handoff where they conflict)

- The approved mockups are the visual baseline. Preserve their sections, section order, layouts and occasion-specific styling.
- Use the ORIGINAL approved logo (`handoff/Cater_Ice_Cream_WebP_Assets/brand/logo-original-*.webp`, `designs/00_logo.png`) and the original palette (spec §5). The later lavender logo experiment was NOT adopted. Never use or recreate it.
- The image pack supplies photographs only; it does not authorize a redesign. Place images per `ASSET_MANIFEST.json` placements/slots.
- Locations (owner decision, 2026-10-20): the existing Service Areas implementation (spec §9 tables, `DatabaseRepository`, virtual routes, city content records) is permanent. The historical city template/rewrite code is NO LONGER a dependency and overrides the handoff rule that it will be supplied. Approved production coverage data is still pending (separate item).
- Work one phase prompt at a time and only when asked. Do not start the next phase automatically.
- Do not install plugins, activate themes, or change the database or `wp-config.php` without the owner's go-ahead for that phase.
- The owner is learning Claude Code: explain each meaningful step briefly while working.
- Homepage APPROVED by the owner (2026-10-20, commit `70479ad` plus the logo adjustment). Do not change its sections, typography, colours or layout without a new owner request. The header logo defaults are 190 px desktop / 136 px mobile (initial Phase 04 values, editable in Header settings); the footer logo is separate.

## Local environment (details in ENVIRONMENT.md)

- Web server runs PHP 8.5.1 (Laragon). The `php` on PATH is XAMPP 7.4 — do NOT use it. For CLI work use `C:\laragon\bin\php\php-8.5.1-nts-Win32-vs17-x64\php.exe`.
- MySQL 8.4.3, database `catericecream`, prefix `wp_` (always use `$wpdb->prefix`).
- Mail is captured by Mailpit (web UI http://localhost:8025). Use `.test` recipients locally; never send real email.
- WP-CLI 2.12: from Git Bash run `/c/laragon/bin/wp-cli/wp <command>` (pinned to PHP 8.5, path conversion disabled); from cmd/PowerShell use `C:\laragon\bin\wp-cli\wp.cmd`. Project `wp-cli.yml` sets the URL.
- In Git Bash, arguments starting with `/` get rewritten to Windows paths unless `MSYS_NO_PATHCONV=1` is set (the wrapper does this).
- Git: project repo at this folder (branch `main`), allow-list `.gitignore`. Commit only when the owner asks. The parent `C:\laragon\www` is a separate repo; never run git commands there for this project.
- Debug log: `C:\laragon\logs\catericecream\debug.log`. Ignore lines tagged `wp-cli.phar` (WP-CLI's own PHP 8.5 deprecations).
- Back up the DB and `wp-config.php` to `C:\laragon\backup\catericecream\<label>-<timestamp>\` before schema or config changes.
- Purchase prevention lives ONLY in `wp-content/mu-plugins/cic-catalog-guard.php`. Never move it into the theme or core plugin. Re-run `dev/tests/phase01/guard-test.php` after any WooCommerce, guard or REST change.
- Dev scripts in `dev/` are CLI-only (blocked over HTTP by `dev/.htaccess`, and each exits unless `WP_CLI`).
- Design values come only from the Design System (`CIC\Design\Schema` allowlist → theme.json filter + preset CSS). Never hardcode hex colours, fonts or page palettes in theme CSS or templates; use `--cic-color-*` roles, `--wp--preset--*` and `--wp--custom--cic--*`. After changing Schema defaults, run `wp eval-file dev/tools/build-theme-json.php` so theme.json stays in parity.
- Re-run `dev/tests/phase02/design-test.php` (and the browser test when the UI changes) after design-system, header/footer or override changes.
- Treats are WooCommerce products; events are `cic_event`. Pairing copy lives ONLY in `{prefix}cic_product_events` and is written ONLY through `CIC\Content\Pairings`. Never duplicate pairing text in post meta.
- Structured fields are ACF Free local field groups (`CIC\Content\Fields`), free field types only (no Repeater/Flexible/Gallery/Options/Blocks). Templates read data through `CIC\Content\ViewModel`, never ACF functions.
- `wp cic seed-catalog` is safe to re-run: it never overwrites edits or recreates removed records/pairings. Re-run `dev/tests/phase03/catalog-test.php` after catalogue, pairing, route or seed changes.
- Quote context: only non-personal, validated keys travel (`CIC\Quote\Context` + `assets/js/context.js`); never put names, emails, phones, addresses, dietary notes or free text in URLs or storage. City/coverage lookups go only through `CIC\Locations\LocationRepository`; never claim coverage without approved data.
- Locations (Phase 05 + Service Areas admin; permanent implementation, approved coverage data pending):
  - **Rules:** coverage rules live only in `CIC\Locations\Coverage` (exclusions win; shared ZIPs need the venue city; unknown is never outside_area). Location SQL lives only in `DatabaseRepository` / `CityStore` / `Importer` (prepared, configured prefix). Admin wording for statuses lives in `CIC\Locations\Labels` (labels only, stored values unchanged).
  - **Manual edits:** cities, ZIPs and links created in the admin never approve coverage (cities Draft + Noindex, ZIPs `unreviewed`, links `inherit`). Manual coverage decisions stamp `manual_at` (schema v4) and removed links are remembered (`cic_location_unlinks`); the importer reports these as conflicts and never overwrites them. Production cities cannot link fixture ZIPs. Run `dev/tests/service-areas/admin-test.php` (and `admin-browser-test.cjs` for UI changes) after Service Areas admin changes.
  - **Fixtures:** `data/fixtures/*.csv` are test data (`dataset=fixture`), counted only on local/development. Never present them as approved coverage. Load real data only through the importer with `--dataset=production` after owner review.
  - **Routes and cache:** virtual routes `/service-areas/{state}/{city}/`. Never add city×event×treat routes. Flush rewrites only by bumping `Routes::ROUTE_VERSION`. Every location write must call `Tables::bump_cache()`, and the cache version must never be restored or lowered.
  - **SEO:** location metadata comes only from the SEO adapter (`CIC\Seo`). City pages stay noindex until published + Index + reviewed content. Run `dev/tests/phase05/locations-test.php` (and the phase05 browser/admin tests for UI changes) after location, route, SEO or import changes.
- Build sequence (owner, 2026-10-20): Phase 05 is not launch-ready until approved production coverage data is loaded (fixtures only so far); Phase 07 ran before Phase 06; Phase 06 (quote flow) is now built and tested end to end locally, which covers the previously deferred vertical-slice/quote-flow gate for owner review.
- Detail pages (Phase 07):
  - **Compositions:** each event layout and treat composition has its own approved section list (`Fields::EVENT_LAYOUT_SECTIONS` / `TREAT_LAYOUT_SECTIONS`) and section variants. Never flatten them into one reordered template. Section order fields stay empty by default (empty = approved order).
  - **Quote actions:** go only through the context helper and the shared non-personal starter (`template-parts/components/quote-starter.php`). No second form system: the only submission path is the Phase 06 CF7 quote form (prefilled from the validated context).
  - **Tests:** run `dev/tests/phase07/details-test.php` (and the phase07 browser/admin tests for UI changes) after detail-page, pairing or seed changes.
- Enquiries (Phase 06):
  - **Architecture:** CF7 is the only submission transport and Flamingo the only enquiry store. Never add a `cic_lead` type or a CIC submission endpoint. Form markup, field specs and custom form tags live in code (`CIC\Enquiries\Forms`); the CF7 form IDs live in Enquiry settings (never hardcode them). Field definitions are provisional pending owner review.
  - **Pipeline:** `CIC\Enquiries\Pipeline` stores first (atomic submission-key claim in `{prefix}cic_submissions` → `wpcf7_flamingo_submit` → workflow meta on the Flamingo message → receipt), then notifies. CF7 mail is skipped; `Notifier` is the single mail owner, with atomic per-channel claims, failure → saved enquiry + retry. Never send an acknowledgement for spam or an unsaved request.
  - **Validation and privacy:** all values are validated server-side (`Validator`) with coverage only through the location repository; fixture coverage is labelled and never production. No personal data in URLs (receipts are random), analytics (`dataLayer` gets keys only), logs (error codes only) or the coordination table. Guard inputs are stripped before storage.
  - **Access:** `manage_cic_enquiries` (administrators + the "Enquiry manager" role). Settings are `manage_options` only. Do not create Yan/Web Karma accounts until their emails are supplied. Reminders stay off until timing is configured.
  - **Mail locally:** `wp-content/mu-plugins/cic-local-mail.php` routes mail to Mailpit (local only). Tests use synthetic `@example.test` addresses and temporarily switch the operator recipient; never test with real addresses.
  - **Tests:** run `dev/tests/phase06/enquiry-test.php`, `submit-test.cjs` and `browser-test.cjs` (sequentially) after enquiry, form, quote/contact page or coverage changes.
- Accessibility and performance (Phase 09):
  - Text links use the `link` role (default `action_hover`, ≥4.5:1 on every surface token); never use decorative roles (e.g. Wedding gold) for reading text, only for lines, rings and icons.
  - Interactive elements need a ≥44 px touch target; extend compact links with padding + equal negative margin (see `main.css` §10b) instead of changing approved layouts.
  - ACF field keys are derived from names: a field name reused in several groups must have the same type everywhere (`dev/tests/phase09/review-test.php` R1).
  - No decorative animation may run longer than 5 s; reduced motion disables motion. Keep WooCommerce front-end/gallery scripts out of catalogue mode (guard script list + theme).
  - Run `dev/tests/phase09/review-test.php`, `interaction-test.cjs` and `audit.cjs` (one width at a time) after visual, CSS, template or asset changes. Passing automated checks is not an accessibility-compliance claim.
- Release and operations (Phase 10):
  - **Docs:** release documentation lives in `docs/release/` (release notes, package/data migration, deployment, backup/rollback, admin guide, launch checklist). Update it with each release candidate; never call the site launch-ready while `LAUNCH_CHECKLIST.md` §A items are open.
  - **Packages and rehearsals:** build packages with `dev/tools/release/release.sh` from an exact commit (`git archive`), never from the working tree. Packages, logs and disposable installs live under `C:\laragonelease\catericecream\`, outside the web root. Restore drills and staging rehearsals run only on disposable `cic_*` databases/installs; the tools refuse otherwise. Never import a package or staging database over a site with real enquiries or edits; later releases are code-only (migrations, non-destructive seeds).
  - **Staging mail:** `cic-staging-mail-guard.php` must stay in `mu-plugins`; staging uses `WP_ENVIRONMENT_TYPE=staging` (never `local` on a host).
- Run browser tests one at a time and make sure each closes its browser (memory).
- Tests must restore everything they change in `finally` (settings, statuses, fields, pairings, theme, plugins, fixtures) and close any browser on failure.
- Never print or commit credentials from `wp-config.php`.

## Status tracking

- `ENVIRONMENT.md`: verified versions and configuration. Update it whenever the stack changes.
- `BUILD_STATUS.md`: current phase, decisions, changed files, real verification output and pending inputs. Update it at the end of every phase.
