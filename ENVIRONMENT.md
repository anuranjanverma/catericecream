# Cater Ice Cream — Environment

First inspected 2026-10-19 (Phase 00, read-only). Updated 2026-10-19 after Phase 01 local setup and Phase 02.
Credentials are deliberately not recorded here. They live only in `wp-config.php`.

Legend: **[V]** verified by command output · **[A]** assumption / not yet verified.

## 1 Locations

| Item | Value |
| --- | --- |
| WordPress root | `C:\laragon\www\catericecream` [V] |
| Local URL | `https://catericecream.test` (also `http://`) [V] |
| `siteurl` / `home` | `https://catericecream.test`; `wp-config.php` derives `WP_HOME`/`WP_SITEURL` from the request host except under WP-CLI [V] |
| Handoff spec pack | `handoff/cater-ice-cream/` (v1.1), identical to `Cater-Ice-Cream-Build-Pack.zip` (48/48 CRC) [V] |
| Image pack | `handoff/Cater_Ice_Cream_WebP_Assets/`, identical to `Cater-Ice-Cream-WebP-Images.zip` (410/410 CRC); 57 masters, 125 placements [V] |
| Stray files | `handoff/cater-ice-cream/designs/.10_…`, `.12_…`, `.17_…` partial-extraction temp files; git-ignored, not deleted [V] |
| Backups | `C:\laragon\backup\catericecream\pre-phase01-20261019-151906\` and `pre-phase02-20261019-191021\` (DB dump, `wp-config.php`, `.htaccess`, SHA256SUMS) [V]. `wp db export` needs Laragon's MySQL `bin` folder on PATH for that command [V] |
| Debug log | `C:\laragon\logs\catericecream\debug.log` (outside web root) [V] |

## 2 Stack (local, Laragon)

| Component | Version / setting |
| --- | --- |
| OS | Windows 11 Home 10.0.26200 [V] |
| Web server | Apache 2.4.62 on 80/443, vhost `auto.catericecream.test.conf`, `AllowOverride All` [V]. nginx 1.27.3 also runs on 8080/8443 (not primary) [V] |
| PHP (website) | **8.5.1 NTS** via `mod_fcgid`, `C:\laragon\bin\php\php-8.5.1-nts-Win32-vs17-x64` [V] |
| PHP on shell PATH | **7.4.33 (XAMPP)**, which does not match the site. Use Laragon 8.5.1 or the `wp` wrapper [V] |
| Other PHP builds | 8.2.29, 8.3.26, 8.4.14 [V]. All Phase 01 PHP files lint clean on 8.5.1 and 8.2.29 [V] |
| PHP extensions (8.5.1) | curl, exif, fileinfo, gd, intl, mbstring, mysqli, pdo_mysql, openssl, sodium, xml*, zip, OPcache. No imagick [V] |
| Database | MySQL 8.4.3, DB `catericecream`, `utf8mb4` / `utf8mb4_0900_ai_ci`, strict `sql_mode`, prefix `wp_` [V] |
| Mail | PHP `sendmail_path` → Mailpit 1.22.3 (SMTP 127.0.0.1:1025, UI http://localhost:8025). **Capture verified**: a `wp_mail()` to `phase01-capture-check@example.test` arrived in Mailpit only [V] |

## 3 WordPress state (after Phase 05)

| Item | Value |
| --- | --- |
| WordPress | 7.1.3, `db_version` 61833 [V] |
| Environment | `WP_ENVIRONMENT_TYPE=local`; `WP_DEBUG=true`, `WP_DEBUG_DISPLAY=false`, `display_errors=0`, log outside web root [V] |
| Active theme | **Cater Ice Cream 0.2.0** (tokens, self-hosted fonts, header/footer components) [V] |
| Plugins | **WooCommerce 11.2.1** (active), **Cater Ice Cream Core 0.4.0** (active), **Advanced Custom Fields (free) 6.8.10** (active, no Pro), Akismet / Hello Dolly (inactive) [V] |
| MU plugins | **cic-catalog-guard 1.0.0** [V] |
| Not installed yet | Contact Form 7, Flamingo, SEO plugin [V] |
| Permalinks | `/%postname%/`; WordPress rewrite block written to `.htaccess` [V] |
| Timezone | `America/Los_Angeles` [V] |
| WooCommerce | Pages created: Shop (/shop/), Cart, Checkout, My account (published; the guard redirects the last three). No payment gateways enabled. Currency USD, base US:CA. Order storage: posts authoritative (`woocommerce_custom_orders_table_enabled=no`; `wp_wc_orders` table exists). Built-in `product_brand` taxonomy present. Action Scheduler 4.1.0 bundled. Coming-soon mode **set to `no`** locally [V] |
| Front page (Phase 04) | Static front page "Home" (`show_on_front=page`); interim quote route page "Get a Quote" at `/get-a-quote/`. Service Areas page `/service-areas/` (Phase 05). 26 imported masters. REST `POST /cic/v1/coverage` and `GET /cic/v1/locations` through the provisional `DatabaseRepository` [V] |
| Locations (Phase 05) | Schema v3 tables `wp_cic_cities`, `wp_cic_zips`, `wp_cic_city_zips`, `wp_cic_city_content`, `wp_cic_city_slugs`; capability `manage_locations` (administrators). Loaded data is **local fixtures only** (20 cities, 60 ZIPs, 61 mappings; `dataset=fixture`), counted because `wp_get_environment_type()` = `local`. Virtual routes `/service-areas/{state}/{city}/` (rewrite version `cic_location_routes_version=1`). `blog_public=0` locally, so core sitemaps are off unless tests switch it on temporarily [V] |
| Catalogue (Phase 03) | Products: 4 showcase treats (no prices) at `/treats/{slug}/`; Shop page `/treats/` ("Our Treats"); `woocommerce_permalinks.product_base=/treats`. Events CPT `cic_event`: 4 seeds at `/events/{slug}/`, archive `/events/`. Table `wp_cic_product_events` (7 pairings, UNIQUE product_id+event_id). 20 media attachments imported from the WebP pack (`cic_media_map`, `_cic_asset_id`). Seed log `cic_seed_log`. WooCommerce `product_brand` terms: Dippin' Dots, DipN' Bananas [V] |
| Core plugin data | `cic_core_schema_version=3` (Phase 05), `cic_core_activated_version=0.1.0` (activation hook not re-run on update; harmless). `cic_design_settings` holds the Phase 04 heading tuning (weight 800, tracking −0.01em, H1/H2 sizes); `cic_site_layout` and `cic_core_settings` are **absent**, so their defaults apply (header logo 190/136 px); location options `cic_location_audit`, `cic_location_imports`, `cic_locations_cache_version`; `cic_design_version` is a cache counter [V] |
| Menus | Primary navigation (13 items), Footer navigation (4), Footer legal links (2), seeded by `wp cic seed-navigation`. Locations: primary, secondary, footer, footer_secondary, legal [V] |
| Fonts | Fraunces (roman 600–700, italic 600) + DM Sans (400–700), latin WOFF2, OFL, self-hosted in the theme; `@font-face` printed by core from theme.json [V] |
| Products | 0 (Phase 01 fixtures created and deleted) [V] |
| Search visibility | Discouraged (`blog_public=0`), so core XML sitemaps are disabled locally [V] |
| Cron | WP-Cron (no `DISABLE_WP_CRON`) plus Action Scheduler [V] |

## 4 Tooling

| Tool | Status |
| --- | --- |
| Git | 2.35.3. Project repo at `C:\laragon\www\catericecream` (branch `main`); first commit `7f2cbc3`; no remote. Allow-list `.gitignore`. Note: the parent `C:\laragon\www` is also a (commit-less) repo; this project is a nested repo inside it [V] |
| WP-CLI | **2.12.0** at `C:\laragon\bin\wp-cli\wp-cli.phar` (SHA-512 verified). Wrappers: `C:\laragon\bin\wp-cli\wp` (Git Bash) and `wp.cmd` (cmd/PowerShell; broken in Phase 01, fixed and verified in Phase 02), pinned to PHP 8.5.1. In `eval-file` scripts the positional arguments are the local `$args` (never declare `global $args`). Project `wp-cli.yml` sets URL and `mod_rewrite` [V] |
| WP-CLI quirks | PHP 8.5 deprecations from WP-CLI's own vendor code (filtered from the bash wrapper's stderr; they still appear in the debug log tagged `wp-cli.phar`). Git Bash path conversion must be off (`MSYS_NO_PATHCONV=1`, set in wrapper) or arguments like `/%postname%/` are corrupted [V] |
| Node.js / npm | v22.21.0 / 10.8.2 [V] |
| Composer | 2.3.5 (runs on PATH PHP 7.4) [V] |
| Coding-standards tools | PHPCS/WPCS **not installed**; no automated lint beyond `php -l` [V] |
| Browsers | Microsoft Edge and Google Chrome installed [V]. Browser tests drive local Edge with `puppeteer-core` 23.11.1, which is **not** a project dependency (installed in a temporary folder; point `NODE_PATH` at any install) [V] |

## 5 Compatibility notes

- WooCommerce 11.2.1 ran on PHP 8.5.1 / WP 7.1.3 with no site PHP warnings in the debug log during Phase 01 tests. One early `_load_textdomain_just_in_time` notice for the `woocommerce` domain was logged during CLI activation [V].
- **WP 7.1 core issue found:** in `/batch/v1`, core passes `rest_pre_dispatch` results to `rest_post_dispatch` without converting `WP_Error`, and the new core filter `_wp_connectors_rest_settings_dispatch()` requires `WP_REST_Response`, which causes a fatal error. The guard returns a response object to avoid it. Any future plugin returning `WP_Error` from `rest_pre_dispatch` would trigger the same core fatal in batch requests [V].
- Host PHP version unknown. Code targets PHP ≥ 8.1 (headers say so) [A].
- Custom tables should use `$wpdb->get_charset_collate()` rather than the MySQL-8-only collation [A].
- No imagick: WebP sub-sizes depend on GD (check at image import) [A].
