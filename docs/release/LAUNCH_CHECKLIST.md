# Launch checklist

Status on 2026-10-20: **not launch-ready.** rc1 is a staging release candidate. Every unticked item below is open. Tick an item only with evidence, and record who supplied or verified it and when.

## A. Business inputs (owner / client decisions)

These cannot be completed by development. Each one blocks launch.

- [ ] **Approved production ZIP coverage.** A reviewed dataset of cities, ZIPs and city–ZIP decisions, reconciled from the LA County master ZIP list and the Ventura and Santa Barbara lists, including the shared-ZIP decisions. Compton stays excluded. Owner approval of which city pages to publish and index, and review of their city content.
- [ ] **Final form-field review.** Quote and contact fields, options, required fields and help text (currently provisional defaults).
- [ ] **Policy wording.** Approved Privacy Policy and Website Terms, covering the enquiry data kept, the retention period, mail processing and any analytics. Publishing them makes the footer and form links appear.
- [ ] **Approved imagery.** Real event and product photos, or explicit approval of the current AI-assisted recreations. Optionally a founder/staff photo and a reversed (white) footer logo.
- [ ] **Product details.** Confirmed product options, flavours, ingredient and dietary wording, and serving details for all four treats. No guarantees and no prices.
- [ ] **Production email sender.** The sending domain and address (for example `hello@catericecream.com`) and who controls its DNS.
- [ ] **User access.** Email addresses for Yan and Web Karma (and anyone else), with their roles (Enquiry manager or Administrator).
- [ ] **Reminder timing.** Whether internal follow-up reminders are wanted. If so: time of day, days before the follow-up date and a fallback recipient.
- [ ] **Operator recipient confirmation.** Confirm `anuranjan.verma25@gmail.com` stays the notification recipient, or supply the replacement.
- [ ] **Analytics / privacy configuration.** Whether to use analytics at all, which tool, consent approach and retention. No tracking is installed today.
- [ ] **Enquiry retention period.** How long enquiries are kept before deletion. Not automated today.
- [ ] **Hosting and deployment owner.** Who provides and pays for hosting, who deploys, who holds DNS and backups.
- [ ] **Go-live approval.** Explicit owner sign-off on staging before any production deployment or DNS change.

## B. Technical setup (development / hosting, after the inputs exist)

- [ ] Staging host provisioned to `DEPLOYMENT.md` §0: PHP 8.5, MySQL 8, HTTPS, basic auth, SSH + WP-CLI.
- [ ] rc1 deployed to staging per `DEPLOYMENT.md`; real administrator set; `verify-deploy.php` and `smoke.sh` pass on the host.
- [ ] Approved coverage imported through **Service Areas → Import** (dry run reviewed, then apply, `dataset = production`); city pages published and indexed only as approved; location sitemap checked.
- [ ] Form-field changes from the review implemented, tested (Phase 06 suites) and released as a new candidate.
- [ ] Approved policies entered and published (owner action), with footer/form links verified.
- [ ] Approved images and product details entered through the admin.
- [ ] Production mail: sender name/email set in **Enquiries → Settings**; SMTP or a transactional mail service configured on production; SPF, DKIM and DMARC published for the sending domain.
- [ ] Accounts created for the supplied addresses with least-privilege roles. Editors confirmed unable to open Enquiries.
- [ ] Reminders configured only if the owner chose a timing.
- [ ] Analytics added only as approved (one installation, non-personal events only), and the privacy policy updated to match.
- [ ] Server cron every 5 minutes (`DEPLOYMENT.md` §9) on staging and production.
- [ ] Backups scheduled (daily database, weekly files, off-server copy) with retention agreed.
- [ ] Production `wp-config.php`: `WP_ENVIRONMENT_TYPE = production`, `WP_DEBUG_DISPLAY = false`, debug log outside the web root, `DISALLOW_FILE_EDIT = true`, no `CIC_COMMERCE_MODE`, fresh salts.
- [ ] Production search visibility switched on **only at launch**, deliberately (Settings → Reading).
- [ ] Uptime monitoring and a contact for mail-delivery problems.
- [ ] PHPCS / code-standards pass (not run yet), or a recorded decision to skip it.

## C. Checks that must run on the hosting environment

These cannot be proven locally. Run them on staging, and repeat the marked ones on production at launch.

- [ ] Host PHP and MySQL/MariaDB versions recorded. Full test suites re-run, or at least `verify-deploy.php`, `smoke.sh` and the guard suite, if the versions differ from PHP 8.5.1 / MySQL 8.4.3.
- [ ] Pretty permalinks and virtual city routes through the real web server (Apache `.htaccess` or Nginx `try_files`): a city URL, a state hub, an alias redirect (single 301), an unknown city (404).
- [ ] Private folders not reachable over HTTP: `/wp-content/uploads/wpcf7_uploads/` and `/woocommerce_uploads/`, the debug log, and `dev/` / `handoff/` if ever uploaded.
- [ ] HTTPS everywhere, no mixed content; basic auth active on staging.
- [ ] **Real cron:** `wp cron event list` shows jobs running on time. `cic_enquiry_maintenance` runs hourly. A failed notification is retried by the job (staging: confirm the retry happens and is refused by the mail guard).
- [ ] **Mail (production):** a quote and a contact submission from synthetic test addresses the owner controls. The operator notification and customer acknowledgement are delivered (not spam-foldered), the headers pass SPF/DKIM/DMARC, the sender is the verified address, and there is exactly one of each.
- [ ] **Staging sends no mail:** `wp_mail()` returns false, and no message reaches the operator recipient.
- [ ] **Catalogue protection on the host:** the guard suite passes. No order rows before or after; add-to-cart, Store API and checkout paths refused; no prices or cart UI.
- [ ] **Enquiry flow end to end on the host:** submit, stored once in Flamingo, visible in **Enquiries**; duplicate submit gives one enquiry; status, assign, note and export work; an Enquiry manager account can do this and an Editor cannot.
- [ ] **Indexing:** staging sends `noindex` and `robots.txt` disallows. Production, at launch only: indexable pages, sitemap with only approved city URLs, canonical tags, noindex for draft/unreviewed cities.
- [ ] **Backup and restore drill on the host** (`BACKUP_AND_ROLLBACK.md`), restoring to a separate staging copy, never over the live site. Result recorded.
- [ ] **Performance on the real host:** TTFB and LCP measured on a phone-like profile and slow network, after any server or CDN cache is configured. The local figures (LCP 0.49–1.20 s) do not transfer.
- [ ] **Caching:** if the host adds a page cache, confirm it never caches the quote/contact confirmation, wp-admin, or responses with cookies. Exclude `/get-a-quote/` submissions and REST.
- [ ] **Remaining real-device and accessibility checks:**
  - screen readers (NVDA + Firefox/Chrome, VoiceOver on iPhone and macOS Safari);
  - Safari and Firefox desktop;
  - real phones and tablets with touch;
  - Windows high contrast / forced colours;
  - 200 % and 400 % zoom with text-spacing overrides;
  - keyboard-only quote submission;
  - colour-vision simulation.

  Automated checks found 0 axe violations locally, but that is **not** an accessibility-compliance claim.

## D. Already verified locally (evidence in `RELEASE_NOTES.md` and `BUILD_STATUS.md`)

- [x] All phase suites pass on the local build (Phases 01–09, Service Areas).
- [x] Package built from an exact commit (`23935f5`), sanitised, with an inventory and checksums; hygiene checks pass.
- [x] Deployment rehearsal on a disposable staging-mode copy: 44/44 deployment checks, 33/33 HTTP smoke checks, 58/58 anonymous guard probes.
- [x] Restore drill on a disposable copy: enquiry with workflow metadata, pairings, design settings and media restored identically.
- [x] Staging mail guard blocks `wp_mail()` in staging mode and is inactive locally.
- [x] Policies still drafts, reminders off, no fixture coverage in the package, search engines discouraged.
