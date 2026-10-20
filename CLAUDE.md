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
- The owner's existing city template and rewrite-rule code will be supplied later. Record it as a dependency; do not implement a competing production schema. Spec §9 tables are a local fallback/fixture behind the location-repository adapter only.
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
- Quote context: only non-personal, validated keys travel (`CIC\Quote\Context` + `assets/js/context.js`); never put names, emails, phones, addresses, dietary notes or free text in URLs or storage. City/coverage lookups go only through `CIC\Locations\LocationRepository` (owner code pending); never claim coverage without approved data.
- Locations (Phase 05, provisional until the owner's city code is audited):
  - **Rules:** coverage rules live only in `CIC\Locations\Coverage` (exclusions win; shared ZIPs need the venue city; unknown is never outside_area). Location SQL lives only in `DatabaseRepository` / `CityStore` / `Importer` (prepared, configured prefix).
  - **Fixtures:** `data/fixtures/*.csv` are test data (`dataset=fixture`), counted only on local/development. Never present them as approved coverage. Load real data only through the importer with `--dataset=production` after owner review.
  - **Routes and cache:** virtual routes `/service-areas/{state}/{city}/`. Never add city×event×treat routes. Flush rewrites only by bumping `Routes::ROUTE_VERSION`. Every location write must call `Tables::bump_cache()`, and the cache version must never be restored or lowered.
  - **SEO:** location metadata comes only from the SEO adapter (`CIC\Seo`). City pages stay noindex until published + Index + reviewed content. Run `dev/tests/phase05/locations-test.php` (and the phase05 browser/admin tests for UI changes) after location, route, SEO or import changes.
- Run browser tests one at a time and make sure each closes its browser (memory).
- Tests must restore everything they change in `finally` (settings, statuses, fields, pairings, theme, plugins, fixtures) and close any browser on failure.
- Never print or commit credentials from `wp-config.php`.

## Status tracking

- `ENVIRONMENT.md`: verified versions and configuration. Update it whenever the stack changes.
- `BUILD_STATUS.md`: current phase, decisions, changed files, real verification output and pending inputs. Update it at the end of every phase.
