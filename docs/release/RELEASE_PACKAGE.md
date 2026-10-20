# Release package and data migration (rc1)

## Where it is and how it was built

- Folder: `C:\laragon\release\catericecream\cater-ice-cream-rc1\`
- Archive: `C:\laragon\release\catericecream\cater-ice-cream-rc1.zip` with `cater-ice-cream-rc1.zip.sha256`
- Build logs: `C:\laragon\release\catericecream\logs\` (`backup.log`, `build-db.log`, `assemble.log`, `deploy-drill.log`, `smoke*.log`, `guard-drill.log`, `restore-drill.log`, `checksum-after.log`)
- The package lives **outside the web root** on purpose. Never copy it into `C:\laragon\www` or a public web folder: it contains a database dump.

Built with `dev/tools/release/release.sh` (developer tool, not shipped):

| Stage | What it does |
| --- | --- |
| `backup` | Dumps the working database (read-only) and copies `wp-config.php`, `.htaccess`, the project code and uploads to `C:\laragon\backup\catericecream\pre-phase10-<timestamp>\` with SHA256SUMS |
| `site` | Creates a disposable WordPress install outside the web root with its own `wp-config.php` (database `cic_*`, environment `staging`, fresh salts) |
| `build-db` | Imports the backup into the disposable database `cic_rc_build`, runs `sanitize.php`, exports the package SQL, drops `cic_rc_build` |
| `assemble` | `git archive` of the code commit, docs from the docs commit, media, SQL, `VERSION.json`, `INVENTORY.txt`, `SHA256SUMS`, hygiene checks, zip |

## Contents

| Path | Files | Size | Notes |
| --- | --- | --- | --- |
| `code/wp-content/themes/cater-ice-cream/` | 112 | 1.0 MB | Theme: templates, CSS/JS, bundled fonts with OFL licences, approved logo and mascot WebP files, `theme.json` |
| `code/wp-content/plugins/cater-ice-cream-core/` | 76 | 0.8 MB | Core plugin (`src/`, admin assets, style-preview template, `uninstall.php`). `data/fixtures/` holds the local test CSVs; the importer refuses them on staging and production |
| `code/wp-content/mu-plugins/cic-catalog-guard.php` | 1 | | Purchase prevention. Required on every environment |
| `code/wp-content/mu-plugins/cic-staging-mail-guard.php` | 1 | | Mail block on `staging` only |
| `media/uploads/` | 407 | 16.3 MB | `2026/10/`: the 58 media-library originals plus the WordPress-generated sizes; 7 WooCommerce placeholder images |
| `database/cater-ice-cream-rc1.sql` | 1 | 0.8 MB | Sanitised full dump, prefix `wp_`, `utf8mb4_unicode_520_ci` |
| `docs/` | 7 | | These documents |
| `README.md`, `VERSION.json`, `INVENTORY.txt`, `SHA256SUMS` | 4 | | Package metadata |

`INVENTORY.txt` lists every file with its size in bytes and SHA-256, one per line. `SHA256SUMS` covers the same files; `INVENTORY.txt` and `SHA256SUMS` exclude themselves.

## Deliberately excluded

| Excluded | Why |
| --- | --- |
| `wp-config.php`, `.htaccess`, `.env`, keys, salts | Credentials and per-environment configuration. Each host writes its own |
| WordPress core and the four third-party plugins | Installed on the host from wordpress.org at the pinned versions (see `RELEASE_NOTES.md`). This avoids redistributing vendor code and keeps updates normal |
| `cic-local-mail.php` | Local Mailpit routing only |
| Flamingo messages and contacts, `wp_cic_submissions` rows | Private enquiry data. Production enquiries are never overwritten by a release |
| WooCommerce sessions, rate limits, finished scheduled jobs, logs, transients | Runtime data |
| `uploads/wpcf7_uploads/`, `uploads/wc-logs/`, `uploads/woocommerce_uploads/` | Form uploads and logs. The plugins recreate these protected folders |
| Local fixture coverage (cities, ZIPs, links, import history) | Test data, ignored outside local/development and never approved coverage |
| `dev/` (tests, release tools), `handoff/`, `BUILD_STATUS.md`, `ENVIRONMENT.md`, `CLAUDE.md` | Developer material |
| Local backups (`C:\laragon\backup\…`) | Contain the unsanitised database and `wp-config.php` |

## How database content and settings migrate

Almost everything an editor sees lives in the database, so the code alone is not enough. The approach differs by situation.

### A. First staging install (this release): import the sanitised database

The package SQL is a full copy of the local database with private and local-only data removed. Importing it brings:

| Data | Where it lives | Notes |
| --- | --- | --- |
| Pages, treats (products), events, menus, media records | `wp_posts`, `wp_postmeta`, `wp_terms*` | Includes revisions and the two draft policies (stay drafts) |
| Structured fields (ACF Free) | `wp_postmeta` | Field definitions are in code (`CIC\Content\Fields`); there are no ACF field-group posts |
| Product–event pairings | `wp_cic_product_events` (16 rows) | The only store of pairing copy |
| Design System | `wp_options`: `cic_design_settings`, `cic_design_version` | Page overrides are post meta; block overrides are block attributes |
| Header/footer layout, contact data | `cic_site_layout`, `cic_core_settings` (code defaults apply while unsaved) | |
| Events overview page | `cic_events_overview` (written by Events → Overview page; code defaults apply while unsaved) | |
| Enquiry settings | `cic_enquiry_settings` | Recipient = specification default; forms #357/#358; reminders off; sender empty |
| CF7 forms | `wpcf7_contact_form` posts #357 (quote), #358 (contact), #354 (unused default) | Markup is generated from code (`CIC\Enquiries\Forms`) |
| Seed and media logs | `cic_seed_log`, `cic_media_map` | Let `wp cic seed-catalog` / `import-media` stay non-destructive on re-runs |
| Location tables | `wp_cic_cities`, `wp_cic_zips`, `wp_cic_city_zips`, `wp_cic_city_content`, `wp_cic_city_slugs` | Shipped **empty** (fixtures removed); the schema is present |
| Enquiry coordination | `wp_cic_submissions` | Shipped empty |
| Schema/route versions | `cic_core_schema_version` = 5, `cic_location_routes_version`, `cic_locations_cache_version` (bumped) | Migrations run automatically on admin requests when the code is newer |
| WordPress/WooCommerce settings | `wp_options` | `blog_public` = 0 (search engines discouraged), permalinks `/%postname%/`, timezone `America/Los_Angeles`, front page = Home |
| Administrator account | `wp_users` (1 account) | Email set to `admin@example.invalid` and password randomised. Set real details on the host straight after import (`DEPLOYMENT.md` step 6) |

Custom tables are ordinary tables in the dump (`CREATE TABLE` plus data), so `wp db import` creates them. If they are ever missing, activating or visiting wp-admin with the core plugin active recreates them (versioned, non-destructive `dbDelta`).

### B. Later releases to a site that has live data: code only, never the database

Once staging or production holds real enquiries or edited content, **do not import a package database over it**. Instead:

1. Deploy only `code/` (theme, core plugin, MU plugins) and new media where needed.
2. Database changes arrive as **versioned migrations** in the core plugin (`CIC\Database\Migrations`, `TARGET_VERSION`). They run once on the next admin request, are idempotent and never drop data.
3. New default content arrives through **non-destructive commands**: `wp cic seed-catalog` (never overwrites edits or recreates deleted records), `wp cic seed-navigation` (only empty menu locations), `wp cic enquiries install` (creates forms only if missing).
4. Approved configuration changes (for example a Design System change) are moved deliberately: **Design System → Export / import**, or by re-entering the setting on the target. Never by copying `wp_options`.
5. Coverage data enters only through **Service Areas → Import** (dry run, then apply) with `dataset = production`.

### C. Production

Use route A for the very first production install only if production starts empty, and only after the owner has approved content and coverage on staging. Afterwards, always use route B. A staging database must never be pushed over live enquiries or edited content.
