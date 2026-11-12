# CaterIceCream.com

WordPress site for **catericecream.com** (Cater Ice Cream / Frosty Yummy LLC), plus the form-to-Google-Sheets
lead connector.

- **Production:** https://www.catericecream.com/
- **Hosting:** SiteGround (SSH port 18765, PHP 8.5)
- **WordPress:** custom theme `cater-ice-cream`, custom plugin `cater-ice-cream-core`, must-use plugins
- **Forms:** Contact Form 7 → Flamingo (the enquiry store) → Google Sheet lead tracker

This repository contains **custom code only**. WordPress core, third-party plugins and uploads are managed
on the server and are not versioned here.

## Structure

```
wp-content/
  themes/cater-ice-cream/          # site theme (templates, CSS, JS, brand assets)
  plugins/cater-ice-cream-core/    # business logic
    src/Enquiries/                 # CF7/Flamingo pipeline, workflow, admin, receipts
    src/Sheets/                    # Google Sheets export (this integration)
    src/Locations/                 # service-area data and pages
    src/Content/ Design/ Seo/ ...  # content, design system, SEO
  mu-plugins/                      # catalog guard, local mail guard
tools/deploy.bat                   # deploy custom code to production over SSH
```

## Google Sheets export

Every submitted quote request and contact message is appended as one row to the **Leads** tab of the
lead tracker. Saved quote progress ("incomplete") and spam are never exported. Each enquiry is exported
exactly once — an export ledger keyed by the Flamingo message id makes repeated runs idempotent.

- **Settings:** WP Admin → Enquiries → Google Sheet
- **Credentials:** a Google service-account JSON key, stored **outside the web root** at
  `../secure/google-service-account.json` (or define `CIC_SHEETS_CREDENTIALS` in `wp-config.php`).
  The key is never stored in the database or this repository.
- **Share the Sheet** with the service-account email as an **Editor**.
- **Columns:** the connector fills only what the website knows (lead id, dates, contact, event, location,
  treats, notes). Workflow columns — status/assignee/follow-up/quotes/payments — are left for the team.

### Running the export

| Trigger | When |
| --- | --- |
| WP-Cron (`cic_sheets_sync`) | every 5 minutes while the site has traffic |
| New enquiry | single event ~1 minute after submission |
| External scheduler | GET the token endpoint every 5 minutes |

Token endpoint (printed by `wp cic sheets token`):

```
https://www.catericecream.com/wp-json/cic/v1/sheets/sync?token=<token>
```

### WP-CLI

```bash
wp cic sheets install     # create the ledger table / run migrations
wp cic sheets status      # configuration, credentials, counts, last error
wp cic sheets sync        # run the export now
wp cic sheets test        # verify credentials and spreadsheet access
wp cic sheets reset --yes # forget exported ids (re-export on next run)
wp cic sheets token       # print the endpoint URL
```

## Deployment

Production is deployed over SSH; there is no staging pipeline. Back up first, then run:

```bat
tools\deploy.bat
```

The script streams the theme, the core plugin and `mu-plugins` to the server with `tar` over SSH.
After deploying a change that touches the schema or the plugin:

```bash
wp cic sheets install
wp cic sheets status
```

Secrets (`wp-config.php`, the service-account key, SSH keys) are never committed — see `.gitignore`.

## Requirements

- PHP 8.1+ (production runs 8.5), WordPress 6.6+
- Plugins: Contact Form 7, Flamingo, Advanced Custom Fields, WooCommerce, Yoast SEO, UpdraftPlus