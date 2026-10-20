# Release notes: Cater Ice Cream rc1

**Status:** release candidate for **staging review**. **Not launch-ready**: the outstanding items below must be resolved and the hosting checks must run on the real host first.

Prepared 2026-10-20 on the local Laragon build. Nothing has been deployed. DNS, payments, indexing, policies, reminders and production mail are untouched.

## Exact source

| Item | Value |
| --- | --- |
| Release candidate code commit | `23935f5` (`23935f56ab18862e211068a0b9aa19b0791a85eb`), "Phase 10: release candidate code and release tooling" |
| Previous functional commit | `776a635`, "Phase 09: visual, accessibility and performance review". The only runtime change after it is the new staging mail guard (inactive locally) |
| Documentation | The Phase 10 documentation commit follows `23935f5`. The package's `VERSION.json` records both full hashes (`code_commit`, `docs_commit`) |
| Branch / remote | `main`, local only. Nothing has been pushed |
| Package | `C:\laragon\release\catericecream\cater-ice-cream-rc1\` and `cater-ice-cream-rc1.zip` (+ `.zip.sha256`), outside the web root |

The package code is produced with `git archive 23935f5`, so it matches the commit exactly and never includes uncommitted files.

## Components and versions (tested together locally)

| Component | Version | Source on the host |
| --- | --- | --- |
| WordPress | 7.1.3 | `wp core download --version=7.1.3` |
| PHP | 8.5.1 (web server and CLI); plugin header requires ≥ 8.1 | Host. Only 8.5.1 has been tested |
| MySQL | 8.4.3, `utf8mb4` / `utf8mb4_unicode_520_ci`, prefix `wp_` | Host. MariaDB has not been tested |
| WooCommerce | 11.2.1 | wordpress.org, pinned |
| Advanced Custom Fields (free) | 6.8.10 | wordpress.org, pinned. ACF Pro is not used |
| Contact Form 7 | 6.2.2 | wordpress.org, pinned |
| Flamingo | 2.6.4 | wordpress.org, pinned |
| Theme `cater-ice-cream` | 0.2.0 | package `code/` |
| Plugin `cater-ice-cream-core` | 0.7.0, database schema v5 | package `code/` |
| MU plugin `cic-catalog-guard` | 1.0.0 | package `code/` (must be present on every environment) |
| MU plugin `cic-staging-mail-guard` | 1.0.0 (new in rc1) | package `code/` (acts only when the environment type is `staging`) |
| Fonts | Fraunces and DM Sans, self-hosted WOFF2, SIL OFL 1.1 (licence files bundled in the theme) | package |

Not part of the release: Akismet and Hello Dolly (inactive locally), the default Twenty Twenty-* themes, and the local-only `cic-local-mail.php` (Mailpit routing). No paid plugin, page builder, SMS service, external CRM or analytics script is included.

## What is in this release (phases 00–09)

- **Catalogue mode** with server-side purchase prevention in a standalone MU plugin. There are no prices, carts, checkout or online orders.
- **Design System** admin: global colours, typography, scale and presets, with sparse page, section and element overrides that reset to inherited values. There is also a three-row header, a footer, and contact and call-to-action settings.
- **Content:** four treats (WooCommerce products) and four events (custom post type), each with its own approved composition. Pairings live in one table, structured fields are ACF Free, and all images are editable.
- **Homepage (owner-approved), archives, About, Contact, Get a Quote, policy template and 404.**
- **Service Areas:** custom location tables, CSV importer (dry run first), admin city/ZIP management, virtual city routes, SEO adapter and location sitemap. The data is fixtures only and is not shipped (see limitations).
- **Enquiries:** a Contact Form 7 quote form with three steps and a contact form, stored once in Flamingo before any notification. Operator and customer emails are retried after failure. Staff work enquiries through status, assignee, follow-up, private notes and CSV export, with a scoped "Enquiry manager" role. Optional internal reminders are **off**.
- **Phase 09 accessibility and performance fixes** (link contrast, touch targets, motion limits, unused WooCommerce scripts removed, image sizes).
- **New in rc1:** `cic-staging-mail-guard.php`. On a site whose `WP_ENVIRONMENT_TYPE` is `staging`, every `wp_mail()` call is refused. Optionally, all mail can be redirected to one owner-approved capture inbox (`CIC_STAGING_MAIL_TO`). Staging therefore cannot email the live operator recipient or customers.

## Completed checks

These were run by the developer, not by an independent tester. Each figure is the actual result of the last run.

### On the local working site (code `776a635` / `23935f5`)

| Suite | Result |
| --- | --- |
| Phase 01 catalogue guard (all purchase paths, sessions, orders, mail, REST, batch) | 69/69, re-run for rc1 |
| Phase 02 design system | 51/51 server, 58/58 browser |
| Phase 03 catalogue / seed / pairings | 46/46, admin edit 22/22 |
| Phase 04 homepage and shared components | 25/25, browser 22/22, admin fields 6/6 |
| Phase 05 locations, import, routes, SEO | 55/55, browser 24/24, admin 12/12 |
| Phase 06 enquiries (store-then-notify, retries, failures, privacy, roles) | 49/49 server (re-run for rc1), submissions 27/27, browser 35/35 |
| Phase 07 detail pages | 23/23, browser 13/13, admin 7/7 |
| Phase 08 archives and supporting pages | 24/24, browser 11/11 |
| Service Areas admin | 42/42, browser 21/21 |
| Phase 09 review | 8/8 (re-run for rc1 after a test correction, see below) |
| Phase 09 interaction | 7/7 |
| Phase 09 audit at 390/768/1440 px, 18 page types | axe-core 0 violations; no horizontal overflow; CLS ≤ 0.08; no touch targets under 44 px; 45 internal links OK (only the deliberate 404 test URL fails) |

### Release rehearsal on a disposable copy (Phase 10)

The rehearsal used a separate WordPress install at `C:\laragon\release\catericecream\work\site`, databases `cic_rc_build` and `cic_drill`, environment type `staging`, served by PHP's built-in server at `http://127.0.0.1:8090`. It followed `DEPLOYMENT.md` and used only the package.

| Check | Result |
| --- | --- |
| Backup of the working site (database, `wp-config.php`, `.htaccess`, project files, uploads) | `C:\laragon\backup\catericecream\pre-phase10-20261020-231042\`, SHA256SUMS verified |
| Sanitised package database | Removed 2 Flamingo contacts, 33 WooCommerce sessions, 284 finished jobs, 195 transients, 10 stored keys/salts and all fixture coverage (20 cities, 60 ZIPs, 61 links). The administrator account and the admin email in 5 settings were neutralised |
| Package hygiene | No `wp-config`, logs, `.env`, keys, local mail plugin, `node_modules`, git files, backups, tests, handoff, form uploads or WooCommerce logs. Exactly one SQL file. No credential definitions |
| Deployment checks (`verify-deploy.php`) | **44/44.** URL fully replaced; staging environment; search engines discouraged; pinned plugin versions active; schema v5; 4 treats, 4 events, 16 pairings; 58 media records all with files; menus assigned; policies still drafts; reminders off; no enquiries, users' real emails or orders shipped; no public enquiry REST routes; no fixture rows; Service Areas rewrite rules present; `wp_mail()` refused |
| HTTP smoke checks (`smoke.sh`) | **33/33.** 16 public pages return 200 with no local URL. 404 for unknown pages and for a city with no approved coverage. Robots `noindex` and `robots.txt` disallow on staging; core sitemap off. Quote and contact forms render. Add-to-cart URL, Store API add-item and checkout are refused. No Flamingo REST namespace or collection. Fonts and media served |
| Catalogue guard suite against the deployed copy | **58/58** (anonymous probes; the 11 authenticated probes ran locally, 69/69) |
| Restore drill | **PASS.** A synthetic enquiry with workflow metadata and coordination row was backed up. Then came a simulated incident: enquiry deleted, a pairing deleted, design settings changed, a media file deleted and a broken theme deployed (HTTP 500). Database and files were restored. The enquiry fingerprint (message, 26 meta rows including all `_cic_*` workflow keys, coordination row), pairings, products, design settings and media file were byte-identical, and the home page returned 200. Afterwards: deployment checks 44/44, smoke 33/33 |
| Working site untouched by the rehearsal | Table checksums before and after: only `wp_options`, `wp_actionscheduler_actions` and `wp_actionscheduler_logs` changed, from the site's own scheduled jobs (hourly enquiry maintenance and WooCommerce housekeeping ran at 23:14). No content, location, user or enquiry table changed |

A test correction was made during the rc1 re-runs. Phase 09 R2 failed because the homepage FAQ heading is now stored with a browser line break (`\r\n`) after an admin save; the rendered heading is correct (two lines). The check now accepts `\n` or `\r\n`. This is not a product change.

## Known limitations and outstanding items

These are carried forward explicitly. The site must not be described as launch-ready while any of them is open.

1. **Approved production ZIP coverage.** Only labelled local fixtures exist, and they are deliberately **not** shipped. On staging, the Service Areas hub and state pages render, but every city page returns 404 and ZIP checks report "unknown" until the owner's reviewed data is imported.
2. **Final form-field review and policy wording.** The quote/contact fields are provisional implementation defaults. The Privacy Policy and Website Terms are drafts; their footer and form links appear only once published.
3. **Approved imagery and product details.** The photographs are recreated from mockups (AI-assisted) and need client review. Product options, ingredients and dietary wording need client confirmation. There is no founder/staff photo. A reversed (white) footer logo is optional and not supplied.
4. **Production email sender and delivery verification.** No sender name/email is configured (WordPress default). SPF/DKIM/DMARC for the sending domain and real delivery tests are pending.
5. **User access and reminder timing.** Yan and Web Karma accounts are not created (emails not supplied). Reminders are off until a timing is chosen.
6. **Analytics/privacy configuration.** No analytics or tracking is installed. Events are ready for a `dataLayer`, and the privacy policy must reflect whatever is chosen.
7. **Hosting, scheduled jobs and backups.** There is no staging or production host yet. Server cron, PHP/MySQL versions, HTTPS, caching, a backup destination and restore rehearsal on the real host are pending.
8. **Remaining real-device and accessibility checks.** Not tested: screen readers (NVDA/JAWS/VoiceOver), Safari and Firefox, real phones and tablets, Windows high contrast / forced colours, 200–400 % zoom and text spacing, slow networks and field performance, and a production server. Automated axe checks find only part of WCAG issues, so **there is no accessibility-compliance claim**.

## Licences, media and map data

- **Code:** theme, core plugin and MU plugins are GPL-2.0-or-later (WordPress-compatible). The third-party plugins keep their own GPL licences.
- **Fonts:** Fraunces and DM Sans, SIL Open Font License 1.1. The licence text sits beside each font in `themes/cater-ice-cream/assets/fonts/` and the source/checksums are in `FONTS.md`. Self-hosted, so nothing loads from Google at runtime. Bundling is allowed; selling the fonts on their own is not.
- **Logo and mascot:** the client's original approved raster logo and mascot (plus resized copies of the same artwork). No redrawn logo.
- **Photographs:** from the client-supplied image pack (`Cater_Ice_Cream_WebP_Assets`). These are recreations of the approved mockups (AI-assisted), not original photo shoots. The client must confirm the right to use them commercially, or replace them with licensed or real photography, before launch (outstanding item 3). Each attachment's description records its provenance.
- **Map:** the California map is a simplified local SVG outline. It uses no third-party map service, tiles or API key. There is one point per county: the average position of that county's **enabled** ZIPs that have coordinates. Points are never service boundaries, radii or customer addresses. The production ZIP CSV should include reviewed latitude/longitude. A ZIP without coordinates still works for coverage checks; it just doesn't contribute to the map. With no enabled ZIPs, as on staging today, the map shows no points. The fixture coordinates were rounded approximations and are not shipped. The map cannot confirm dates, venue access or coverage on its own.

## Mail, cron and enquiry retention (summary)

- **Mail:** the core plugin is the only sender (CF7 mail is skipped). Each enquiry is saved first, then one operator notification and one customer acknowledgement are sent (no acknowledgement for spam). A failed email leaves the saved enquiry plus a retry. On local, mail goes to Mailpit. On staging it is refused by the staging mail guard. Production needs a verified sender and SMTP (outstanding item 4).
- **Cron:** WordPress cron plus WooCommerce's Action Scheduler. The hourly `cic_enquiry_maintenance` job frees interrupted sends, retries notifications and cleans stale coordination rows. `cic_enquiry_reminder` jobs exist only when reminders are on. Hosts must run a real server cron (`DEPLOYMENT.md` §9).
- **Retention and export:** enquiries stay in Flamingo until deleted; there is no automatic expiry (the owner chooses a period, outstanding items 2 and 6). Flamingo moves spam to the trash after 30 days and WordPress empties the trash after 30 days. Staff export enquiries from **Enquiries → Export CSV** (filtered, spam excluded, formula-safe). Personal-data export and erasure go through **Tools → Export / Erase Personal Data**. Plugin deactivation or uninstall deletes no data.

Technical notes:

- Only PHP 8.5.1 and MySQL 8.4.3 have been tested. A MariaDB or older PHP host needs its own test pass; see `DEPLOYMENT.md` for the collation note.
- City template/rewrite code from the historical site is no longer a dependency (owner decision, 2026-10-20).
- The CF7 default "Contact form 1" exists but is unused. The CF7 form "Mail" tabs are unused because the core plugin sends all mail.
- The SEO adapter is WordPress core. If an SEO plugin is installed later, Site Health reports that a plugin adapter is required.
- PHPCS has not been run.
