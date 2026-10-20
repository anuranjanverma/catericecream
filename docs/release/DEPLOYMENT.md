# Deployment: Laragon → staging (rc1)

This guide moves release candidate rc1 from the local Laragon build to a **staging** host. It was rehearsed end to end on a disposable local copy (see "Rehearsal evidence"). It has **not** been run on a real host: the steps that depend on the host are marked **[host]**.

Production deployment is a separate, later instruction (see the end of this file). Do not point the production domain's DNS at staging, do not enable search-engine indexing, do not publish draft policies, do not enable reminders and do not configure real outgoing mail while following this guide.

Placeholders used below:

- `STAGING_URL`: for example `https://staging.catericecream.com` (no trailing slash)
- `STAGING_HOST`: the same without the scheme, for example `staging.catericecream.com`
- `SITE`: the WordPress root on the host, for example `/var/www/staging`
- `PKG`: the unpacked `cater-ice-cream-rc1` folder on the host, kept **outside** the web root

## 0. Host requirements [host]

| Need | Value |
| --- | --- |
| PHP | 8.5.x is the tested version (plugin minimum 8.1, untested below 8.5). Extensions: curl, gd (with WebP), intl, mbstring, mysqli, openssl, sodium, xml, zip, fileinfo, exif. `memory_limit` 256M or more |
| Database | MySQL 8.x (tested 8.4.3). MariaDB 10.6+ should work (the dump uses only `utf8mb4_unicode_520_ci`, InnoDB) but is untested |
| Web server | Apache with `mod_rewrite` and `AllowOverride FileInfo`, or Nginx with a `try_files` rule (step 7) |
| HTTPS | A valid certificate for `STAGING_HOST` |
| Access | SSH with WP-CLI 2.12+, and the ability to add a system cron job |
| Privacy | HTTP basic authentication (or IP allow-list) on the whole staging site, in addition to WordPress's "discourage search engines" |
| Disk | About 300 MB for WordPress, plugins and media, plus backups |

## 1. Verify and unpack the package

```bash
sha256sum -c cater-ice-cream-rc1.zip.sha256        # on the machine holding the zip
unzip cater-ice-cream-rc1.zip -d /home/deploy/      # PKG=/home/deploy/cater-ice-cream-rc1 (outside the web root)
cd "$PKG" && sha256sum -c SHA256SUMS                # every line must say OK
cat VERSION.json                                    # confirm code_commit 23935f5…
```

## 2. Database and user [host]

Create an empty database (utf8mb4) and a dedicated user with privileges on that database only. Keep the credentials in the host's secret store, never in Git or in this package.

## 3. WordPress core and configuration [host]

```bash
cd "$SITE"
wp core download --version=7.1.3 --locale=en_US
wp config create --dbname=DB_NAME --dbuser=DB_USER --prompt=dbpass --dbhost=DB_HOST --dbprefix=wp_ --dbcharset=utf8mb4
wp config set WP_ENVIRONMENT_TYPE staging
wp config set WP_DEBUG true --raw
wp config set WP_DEBUG_LOG '/home/deploy/logs/staging-debug.log'   # outside the web root
wp config set WP_DEBUG_DISPLAY false --raw
wp config set DISABLE_WP_CRON true --raw     # a real server cron runs jobs (step 9)
wp config set DISALLOW_FILE_EDIT true --raw
```

- `wp config create` generates fresh salts. Never copy the local salts.
- **Never** define `CIC_COMMERCE_MODE`. Its absence keeps catalogue mode, and only the value `sales` would disable the purchase guard.
- Optional, only with the owner's approval of a sandbox inbox: `wp config set CIC_STAGING_MAIL_TO 'capture@example.test'`. Without it, staging refuses all mail.
- `WP_ENVIRONMENT_TYPE` must be `staging`. This activates the staging mail guard and ignores fixture data. Do not use `local` on a host: the local Mailpit plugin is not shipped, and fixtures would count.

## 4. Code, plugins and media

```bash
# Project code from the package (exact commit 23935f5)
cp -r "$PKG/code/wp-content/themes/cater-ice-cream"        "$SITE/wp-content/themes/"
cp -r "$PKG/code/wp-content/plugins/cater-ice-cream-core"  "$SITE/wp-content/plugins/"
mkdir -p "$SITE/wp-content/mu-plugins"
cp "$PKG/code/wp-content/mu-plugins/"*.php                 "$SITE/wp-content/mu-plugins/"

# Third-party plugins at the tested versions (activation comes from the imported database)
wp plugin install woocommerce --version=11.2.1
wp plugin install advanced-custom-fields --version=6.8.10
wp plugin install contact-form-7 --version=6.2.2
wp plugin install flamingo --version=2.6.4

# Media library files (paths must stay wp-content/uploads/2026/10/…)
cp -r "$PKG/media/uploads/." "$SITE/wp-content/uploads/"
```

Set ownership and permissions to the host's standard (for example, directories 755 and files 644, with uploads writable by the PHP user). Do **not** install Akismet, Hello Dolly, an SEO plugin, a cache plugin or an SMTP plugin as part of this release.

## 5. Import the database

```bash
cd "$SITE"
wp db import "$PKG/database/cater-ice-cream-rc1.sql"
```

This creates all 59 tables, including the custom ones:

- `wp_cic_product_events`: 16 pairings.
- `wp_cic_cities`, `wp_cic_zips`, `wp_cic_city_zips`, `wp_cic_city_content`, `wp_cic_city_slugs`: empty, waiting for approved coverage.
- `wp_cic_submissions`: empty.

`RELEASE_PACKAGE.md` lists what the dump contains. If the host forces a different table prefix, stop. The release was built and tested with `wp_`, and changing it needs its own rehearsal.

## 6. URLs, administrator and caches

Run each replacement with `--dry-run` first and read the report, then run it for real:

```bash
# 1) Plain URLs (content, options, serialized settings, GUIDs)
wp search-replace 'https://catericecream.test' 'STAGING_URL' --all-tables-with-prefix --precise --report-changed-only
# 2) JSON-escaped URLs (block attributes and JSON meta; 0 changes is normal for rc1)
wp search-replace 'https:\/\/catericecream.test' 'https:\/\/STAGING_HOST' --all-tables-with-prefix --precise --report-changed-only
# 3) Bare host (CF7 form sender lines such as wordpress@catericecream.test; unused, but keep them clean)
wp search-replace 'catericecream.test' 'STAGING_HOST' --all-tables-with-prefix --precise --report-changed-only
```

In the local rehearsal the three passes made 208, 0 and 8 replacements. Afterwards, no `catericecream.test` reference remained in options, posts or meta.

Then set a real administrator. The shipped account has the placeholder email `admin@example.invalid` and a random, unknown password:

```bash
wp user list --fields=ID,user_login,user_email,roles
wp user update 1 --user_email='OWNER-APPROVED-ADDRESS' --prompt=user_pass --skip-email
wp option update admin_email 'OWNER-APPROVED-ADDRESS'
wp cache flush && wp transient delete --all
```

Use an address the owner supplies. Do **not** create Yan or Web Karma accounts until their email addresses are supplied. When they are, create them with the role **Enquiry manager** (or Administrator where agreed).

## 7. Rewrite rules (pretty permalinks and virtual city routes)

The site uses `/%postname%/` permalinks plus virtual Service Areas routes (`/service-areas/{state}/{city}/`). Both need only the standard WordPress front-controller rule.

**Apache [host]:** let WordPress write `.htaccess`:

```bash
wp rewrite flush --hard     # needs apache_modules: [mod_rewrite] in wp-cli.yml, or write the block below by hand
```

```apache
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
```

**Nginx [host]:**

```nginx
location / { try_files $uri $uri/ /index.php?$args; }
# Private upload folders (Apache uses the plugins' own .htaccess files; Nginx needs explicit rules)
location ^~ /wp-content/uploads/wpcf7_uploads/     { deny all; }
location ^~ /wp-content/uploads/woocommerce_uploads/ { deny all; }
location ~* /(dev|handoff)/ { deny all; }
```

Then run `wp rewrite flush` (soft flush). Future route changes flush themselves when the core plugin bumps `Routes::ROUTE_VERSION`. Never flush on every request.

## 8. Mail on staging

- The staging mail guard (`mu-plugins/cic-staging-mail-guard.php`) refuses every `wp_mail()` call while `WP_ENVIRONMENT_TYPE` is `staging`. Enquiries are still saved; their notification state shows *failed* with retries, exactly as during a mail outage. That is expected on staging.
- Check it:

  ```bash
  wp eval 'var_dump( wp_mail( "check@example.test", "staging check", "test" ) );'   # must print bool(false)
  ```

- Do not install an SMTP plugin or configure a sender on staging. Production mail (verified sender domain, SPF/DKIM/DMARC, delivery tests) is a launch item.

## 9. Scheduled jobs (real cron) [host]

`DISABLE_WP_CRON` is on, so add a system cron job for the PHP user. Every 5 minutes is enough:

```cron
*/5 * * * * cd /var/www/staging && /usr/local/bin/wp cron event run --due-now --quiet >> /home/deploy/logs/cron.log 2>&1
```

This runs WordPress cron, which drives WooCommerce's Action Scheduler queue. That queue includes the hourly enquiry maintenance (`cic_enquiry_maintenance`: frees interrupted sends, retries failed notifications, cleans stale coordination rows) and, once enabled, reminders (`cic_enquiry_reminder`).

Check:

```bash
wp cron event list --fields=hook,next_run_relative | head
wp cic enquiries status          # expects: forms #357/#358, Reminders: off, Scheduler: Action Scheduler
wp action-scheduler action list --hook=cic_enquiry_maintenance --status=pending --format=count   # 1
```

## 10. Search engines and access

- Keep **Settings → Reading → Discourage search engines** ticked (`blog_public` = 0, shipped that way). The site then sends `noindex` and `robots.txt` disallows crawling. The core sitemap is off.
- Keep HTTP basic authentication on the whole staging site.
- Do not change DNS for `catericecream.com`.

## 11. Verify

Run these checks and keep the output with the deployment record.

1. **Automated deployment checks.** Copy `dev/tools/release/verify-deploy.php` from the repository to a folder outside the web root on the host, then run:

   ```bash
   wp eval-file /home/deploy/tools/verify-deploy.php 'STAGING_URL' users-configured
   ```

   Expect "All deployment checks passed". The `users-configured` argument allows the real administrator email set in step 6. Delete the file afterwards.
2. **HTTP smoke checks** from a developer machine (add basic-auth credentials with a `~/.netrc` or by editing `curl` options):

   ```bash
   bash dev/tools/release/smoke.sh 'STAGING_URL' <a product ID>
   ```

   Expect "SMOKE OK". City pages return 404 until approved coverage is imported. That is correct.
3. **Catalogue guard suite** (optional, needs the dev folder on the host): `wp eval-file dev/tests/phase01/fixtures.php create`, `guard-test.php staging`, `fixtures.php delete`.
4. **Manual checks [host]:** see "Checks that must run on the hosting environment" in `LAUNCH_CHECKLIST.md`.

## Rehearsal evidence (local, disposable)

Run on 2026-10-20 with `dev/tools/release/release.sh` (stages `site`, `deploy-drill`, `restore-drill`). It used a separate WordPress install under `C:\laragon\release\catericecream\work\site`, the database `cic_drill`, environment `staging`, and PHP's built-in server at `http://127.0.0.1:8090` in place of Apache/Nginx.

- Steps 4–7 and 9 were followed with the package contents. Plugins were copied at the pinned versions instead of downloaded, because the rehearsal ran offline.
- Results: deployment checks 44/44, smoke checks 33/33, catalogue guard 58/58 (anonymous), restore drill PASS. The disposable install and databases were removed afterwards.
- Not covered by the rehearsal: a real web server's rewrite configuration, HTTPS, basic auth, file permissions, a system cron, host PHP/MySQL versions and host mail behaviour.

## Updating staging later (code-only releases)

1. Back up first (`BACKUP_AND_ROLLBACK.md`).
2. Replace `wp-content/themes/cater-ice-cream`, `wp-content/plugins/cater-ice-cream-core` and the two MU plugins with the new package's `code/` folders. Keep `uploads` and the database.
3. Visit wp-admin once (or run `wp eval 'do_action( "admin_init" );' --user=1`) so versioned migrations run. Check with `wp option get cic_core_schema_version`.
4. Run `wp cic seed-catalog` only if the release notes ask for it. It never overwrites edits.
5. Re-run step 11.

## Production (not part of this release)

Production needs a separate, explicit instruction and the launch inputs in `LAUNCH_CHECKLIST.md`. The differences from staging are:

- `WP_ENVIRONMENT_TYPE` set to `production`, which leaves the staging mail guard inactive.
- A verified sender and SMTP configured.
- Search-engine visibility turned on deliberately, at launch only.
- Approved policies published, approved coverage imported, real accounts created, reminders configured if wanted.
- Backups and monitoring in place before go-live.
