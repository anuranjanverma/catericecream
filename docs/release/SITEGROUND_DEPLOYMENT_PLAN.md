# Central SiteGround deployment system: design brief

Status: **plan only, nothing built** (2026-11-12). To continue as a **separate project** serving every application hosted on SiteGround. Cater Ice Cream (this repository) is the first application and the reference case.

Goal: code is pushed to GitHub; a pipeline deploys it to **staging**, verifies it, and after explicit approval deploys the **same commit** to **live**. Customer data on live (enquiries, orders, users) is never overwritten.

---

## 1. Core rule: code moves forward, databases never do

- A deployment moves **code** (plus new media files) from Git to the host. It never imports a database onto live.
- Content and settings changes ship as **migration scripts** that run on the target: dry run first, idempotent (a second run says "already done"), never overwrite values edited in wp-admin, and find records by stable keys, never by ID or URL.
- The **live database is the source of truth**. Staging is refreshed *from* live (SiteGround's staging copy), never the other way round.
- **Do not use SiteGround "Push to Live" with the database**, and do not try to exclude tables: in WordPress, private data (e.g. Flamingo enquiries) lives in `wp_posts`/`wp_postmeta` next to pages and products, so it cannot be separated by table. A database push would erase everything received on live since the staging copy was made.

## 2. Example: where Cater Ice Cream enquiries live (why table exclusion fails)

| Location | Contents | Shared with site content? |
| --- | --- | --- |
| `wp_posts`, type `flamingo_inbound` | One row per enquiry (incl. incomplete Step 1 drafts) | **Yes** |
| `wp_postmeta` of those posts | Form answers (`_field_your_name`, `_field_phone`, `_field_notes`, `_field_dietary`, …) and workflow (`_cic_status`, `_cic_stage`, `_cic_activity`, draft token hash) | **Yes** |
| `wp_posts`, type `flamingo_contact` | Flamingo address book | **Yes** |
| `wp_terms` / `wp_term_relationships` | Channel (`flamingo_inbound_channel`) | **Yes** |
| `wp_cic_submissions` | Idempotency and notification state (no personal data) | No (own table) |
| `wp_actionscheduler_*` | Pending notification retries/reminders referencing enquiry IDs | Shared with WooCommerce |
| `wp_options` → `cic_enquiry_settings` | Enquiry settings (not enquiries) | Settings |

Other apps will have their own equivalents (WooCommerce orders, form plugins, users). Each app's config (section 5) must list them under `protected_data` for documentation and for the staging sanitiser.

## 3. Architecture

```
Developer ── git push ──► GitHub (one repo per app)
                             │
                             ▼
          GitHub Actions: app workflow calls the CENTRAL reusable workflow
                             │
   1 checks ─► 2 package ─► 3 staging deploy ─► 4 staging verify ─► [manual approval] ─► 5 live deploy ─► 6 live verify
                                   │                                                       │
                                   └──── SSH (port 18765) + rsync + WP-CLI on SiteGround ──┘
                             ▲
   MCP server (local, for Claude Code): plan / trigger / status / approve-request / logs / rollback
```

### Central repository (e.g. `siteground-deploy`)

- **Reusable workflow** `.github/workflows/deploy.yml` (`workflow_call`), used by every app.
- **Deploy scripts** (bash; run on the runner, act on the host over SSH): `preflight`, `backup`, `upload`, `migrate`, `verify`, `rollback`, `record`.
- **Remote helpers** copied to a tools folder outside the web root on the host (e.g. `~/deploy-tools/`), e.g. a verify script and the sanitiser.
- **MCP server** (Node/TypeScript, stdio) wrapping the GitHub API and read-only SSH status.
- Documentation and an app onboarding checklist.

### Each application repository

- Its code, its migration scripts and a small `deploy.config.yml` (section 5).
- A 10-line workflow that calls the central workflow with the app's settings.

## 4. Pipeline stages

1. **Checks** (on every push / PR): PHP lint for the app's PHP version, coding standards, and app-defined tests that can run in CI. Tests that need a full WordPress + database run on staging in stage 4.
2. **Package**: `git archive` of the exact commit, limited to `deploy_paths`; checksum file + `VERSION.json` (commit, date, migrations list). The same artifact goes to staging and live.
3. **Staging deploy**: backup → upload (rsync to a new release folder, then switch) → migrations (dry run, then apply) → cache flush.
4. **Staging verify**: app smoke checks (HTTP status of key URLs, redirects, no PHP errors in the log, mail guard active, noindex on staging), plus the app's server-side tests if configured.
5. **Live deploy**: only via GitHub **Environment `production`** with **required reviewer** approval, only for a commit that passed staging. Same steps as staging, with a fresh live backup first.
6. **Live verify**: the same checks with production expectations (indexing allowed where intended, analytics on, real mail sender). On failure: automatic rollback of code, then alert.

Every run writes a deployment record (commit, environment, migrations + reports, verify results, backup id) as a workflow artifact and to `~/deploy-log/` on the host.

## 5. Per-app config (`deploy.config.yml`, draft)

```yaml
app: cater-ice-cream
php: "8.x"                         # must match the SiteGround site
environments:
  staging:
    ssh_host_secret: SG_STAGING_HOST   # GitHub Environment secrets, never in the repo
    ssh_user_secret: SG_STAGING_USER
    path: www/staging1.example.com/public_html
    url: https://staging1.example.com
  production:
    ssh_host_secret: SG_LIVE_HOST
    ssh_user_secret: SG_LIVE_USER
    path: www/example.com/public_html
    url: https://www.example.com
deploy_paths:                      # only these are uploaded; everything else on the host is untouched
  - wp-content/themes/cater-ice-cream
  - wp-content/plugins/cater-ice-cream-core
  - wp-content/mu-plugins/cic-catalog-guard.php
  - wp-content/mu-plugins/cic-staging-mail-guard.php
never_touch:                       # explicit guard list
  - wp-config.php
  - wp-content/uploads
  - wp-content/plugins/*           # third-party plugins are managed on the site, except deploy_paths
migrations:                        # run in order, each with dry-run first; idempotent
  - wp eval-file dev/tools/content/url-slugs-20261112.php
after_deploy:
  - wp cic locations flush-cache
  - wp cache flush
verify:
  - wp eval-file ~/deploy-tools/verify-deploy.php {url}
  - http: { "/treats/dippin-dots/": 301, "/treats/dippin-dots-catering/": 200 }
protected_data:                    # documentation + staging sanitiser input; never exported/imported
  - "wp_posts: post_type in (flamingo_inbound, flamingo_contact) + their postmeta/terms"
  - wp_cic_submissions
staging:
  environment_type: staging        # WP_ENVIRONMENT_TYPE; activates the mail guard, noindex, no analytics
  sanitize_after_refresh: true     # pending decision
```

## 6. SiteGround specifics

- **No public API** for Site Tools staging (create / refresh / push). Staging refreshes from live are done in Site Tools by a person; the pipeline only deploys code.
- **SSH**: port **18765**, key-based. Create a dedicated deploy key per site in Site Tools → Devs → SSH Keys Manager; store the private key, host and user as **GitHub Environment secrets** (staging and production separately). To verify early: that SiteGround accepts SSH from GitHub-hosted runner IPs (otherwise use a self-hosted runner).
- **WP-CLI** is preinstalled; check the version and PHP CLI version per site.
- **SiteGround Optimizer / Dynamic Cache**: flush after deploy (`wp sg purge` if the plugin is active, otherwise Site Tools cache purge).
- **Staging copies live's `wp-config.php`**: after every staging refresh, set `WP_ENVIRONMENT_TYPE` to `staging` again (and any staging-only constants), or the staging site will behave like live (mail!).
- Backups: SiteGround's daily backups are not enough for a deploy rollback; the pipeline takes its own backup (`wp db export` + code) before every deploy and keeps the last N.

## 7. MCP server (Claude Code integration)

Tools (draft): `apps_list`, `deploy_plan(app, commit)` (diff since last deploy, pending migrations), `deploy_trigger(app, env, commit)`, `deploy_status(app, run)`, `deploy_logs(app, run)`, `approval_request(app, run)` (live approval stays a human click in GitHub), `rollback(app, env, backup_id)`, `site_status(app, env)` (read-only SSH: versions, PHP, last deploy, disk).

Rules: no secrets in the MCP server (it uses the user's GitHub token and SSH agent); live deploys always need the GitHub approval; every tool call is logged.

## 8. Safety rules (all apps)

1. Never import, push or replace a database on live. Never run search-replace across live tables as part of a deploy.
2. Backup before every deploy, both environments; rollback restores code first, database only with an explicit decision (it would also roll back new customer data).
3. Same artifact (commit) for staging and live; live only after staging passed.
4. Migrations: dry run + report before apply; idempotent; never overwrite admin edits.
5. Staging holds real personal data: mail guard on, noindex, basic auth if possible, optional sanitising, no analytics.
6. Secrets only in GitHub Environment secrets / the local SSH agent; never in repos, logs or MCP output.
7. Third-party plugins/themes are updated on the site (or as a separate, tested change), not silently by the code pipeline.

## 9. Build phases (proposed)

1. Central repo skeleton + reusable workflow with checks and packaging (no host access).
2. SSH deploy + backup + rollback to **staging** for Cater Ice Cream; verify stage.
3. Migrations runner + deployment records.
4. Production environment with approval; first live code-only deploy of Cater Ice Cream.
5. MCP server.
6. Onboarding of the other SiteGround apps (config per app).

## 10. Open questions

1. SSH access per site (host, user, port 18765); deploy keys created?
2. Staging type per app: SiteGround staging copy or separate site? Is `WP_ENVIRONMENT_TYPE=staging` set there?
3. Is Cater Ice Cream live already receiving real enquiries? (Then the first deployment must also be code-only.)
4. PHP versions on SiteGround per site (Cater Ice Cream was tested on PHP 8.5).
5. GitHub: Cater Ice Cream uses the personal account repo `anuranjanverma/catericecream` (private, 2026-11-13). Still open: account/organisation for the central `siteground-deploy` repo and who approves live deploys.
6. Staging sanitising after refresh: yes/no.
7. Which other applications, and their stacks (all WordPress?).

## 11. Cater Ice Cream: notes for onboarding

- Existing tooling to reuse: `dev/tools/release/` (`release.sh`, `verify-deploy.php`, `smoke.sh`, `sanitize.php`), `docs/release/DEPLOYMENT.md` ("Updating staging later (code-only releases)"), `BACKUP_AND_ROLLBACK.md`, changesets listing migrations per deployment group.
- Pending migrations for the next deploy include `dev/tools/content/url-slugs-20261112.php` (`docs/release/URL_SLUGS_2026-11-12.md`).
- Repo is on GitHub since 2026-11-13: `https://github.com/anuranjanverma/catericecream` (private, `main`); the allow-list `.gitignore` keeps WordPress core, uploads and secrets out (history checked before the first push: no `wp-config.php`, `.env`, SQL dumps or keys).
- Mu-plugins: `cic-catalog-guard.php` and `cic-staging-mail-guard.php` must ship; `cic-local-mail.php` must never ship.
