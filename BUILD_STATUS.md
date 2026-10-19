# Cater Ice Cream — Build Status

Spec: `handoff/cater-ice-cream/` v1.1 (BUILD_SPECIFICATION.md, REVISION_NOTES.md) plus image pack `handoff/Cater_Ice_Cream_WebP_Assets/`.
Environment facts: see `ENVIRONMENT.md`.

## Current phase

| Phase | Status | Date |
| --- | --- | --- |
| 00 Environment and plan | Complete (documentation only) | 2026-10-19 |
| 01 Foundation and catalogue protection | **Complete: gate passed** (see verification) | 2026-10-19 |
| 02 Design System admin and style preview | Not started. Awaiting owner instruction | |

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
- The repo has no commits yet; awaiting the owner's go-ahead to make the first commit.

---

## Phase 00 log (summary)

Read-only inspection of the environment and handoff. Created `ENVIRONMENT.md`, `BUILD_STATUS.md`, root `CLAUDE.md` (imports the handoff `CLAUDE.md`). No installs or DB changes.

## Confirmed architecture (spec v1.1)

| Concern | Decision | Status |
| --- | --- | --- |
| Theme | Custom hybrid PHP theme `cater-ice-cream` + `theme.json` | Shell built (01) |
| Business logic | Plugin `cater-ice-cream-core` (`CIC\` namespace) | Bootstrap built (01) |
| Purchase guard | `mu-plugins/cic-catalog-guard.php`, fail-closed | Built and verified (01) |
| Editing | Gutenberg core blocks/patterns + ACF Free + Settings API; no ACF Pro, page builder or paid licence | ACF not yet installed |
| Treats | 4 WooCommerce products, non-purchasable | Phase 03 |
| Events | `cic_event` CPT, archive `/events/` | Phase 03 |
| Cities | Custom tables + virtual routes behind a location-repository adapter | **Pending owner's city code** |
| Enquiries | CF7 (3-step) + Flamingo as sole store | Phase 06 |
| Scheduler | Action Scheduler (bundled, 4.1.0) | Available |
| Mail (local) | Mailpit | Capture verified (01) |
| SEO | One adapter; none installed | Phase 05 decision |
| Design | Original raspberry Cater logo + original palette; lavender experiment not adopted | theme.json palette = spec §5 |

## Dependencies and pending inputs

| Input | Status | Blocks |
| --- | --- | --- |
| **Owner's city template + rewrite-rule code and schema** | **Pending.** Spec §9 tables are a local fallback only; no competing production schema. Phase 05 uses a fixture until supplied | Final city schema/routes |
| ACF (free) | Not installed | Phase 03 |
| Contact Form 7, Flamingo | Not installed | Phase 06 |
| Final quote/contact form-field review | Pending | Phase 06 sign-off |
| Approved coverage dataset (LA County, Ventura, Santa Barbara, Compton exclusion, shared ZIPs) | Pending reconciliation | Coverage go-live |
| Reminder timing, assignee fallback | Pending; reminders stay disabled | Reminders |
| Yan / team account emails | Pending | Enquiry-access accounts |
| Hosting/staging, mail sender domain, deployment owner, backup destination, host PHP version | Pending | Phase 10 / launch |
| Licensed font files (Fraunces, DM Sans) + licences | To source | Phase 02 |
| Vector logo master | Raster only | Print only |
| Client review of AI photos, product options, ingredients, policy copy, About biography | Pending | Launch |

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
| 02 | Design System admin + style preview | Global change propagates; overrides scoped; reset works |
| 03 | Products + Events models (ACF Free install) | 4 + 4 seeded and editable; pairing index consistent; `/treats/` permalinks |
| 04 | Three-row header/footer + homepage | Approved structure; screenshots 390/768/1440 |
| 05 | Locations adapter, importer, search, virtual routes, SEO | ZIP cases, collisions, canonicals, noindex (fixture until city code arrives) |
| 06 | CF7/Flamingo quote wizard, lead admin, mail, reminders | Save-before-notify proven in Mailpit |
| — | Vertical-slice gate | Before Phase 07 |
| 07 | Remaining event + treat compositions | Screenshot comparison |
| 08 | Archives, hubs, About, Contact, policies, 404 | Navigation and forms complete |
| 09 | Visual, accessibility, performance review | Differences fixed or recorded |
| 10 | Release candidate + operational guide | Backup/rollback rehearsed on staging |

## Next phase

**Phase 02: Design System admin and style preview** (`prompts/02_design_system_and_admin_controls.md`). Needs: decision on sourcing Fraunces / DM Sans font files (OFL, self-hosted). No new plugin installs expected.
