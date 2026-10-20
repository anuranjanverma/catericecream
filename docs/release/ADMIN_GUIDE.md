# Admin guide

This guide is for the people who run the website day to day. Everything here is done in the WordPress dashboard (`/wp-admin/`). Nothing requires editing code.

## Who can do what

| Role | Can |
| --- | --- |
| **Administrator** | Everything, including the Design System, Header/Footer & Contact, Enquiry settings, Service Areas and users |
| **Editor** | Pages, treats (products) and events. Editors cannot see enquiries or change global settings |
| **Enquiry manager** | Only **Enquiries**: read, assign, update, add notes, retry notifications, export. No content, settings, Flamingo contact book or Flamingo exports |
| Service Areas | Administrators (capability `manage_locations`) |

Create accounts in **Users → Add New**. The Yan and Web Karma accounts are waiting for their email addresses. When those arrive, create the accounts with the role **Enquiry manager**, or **Administrator** where the owner agrees. Use strong unique passwords, and remove accounts that are no longer needed.

## 1. Pages and general content

The site's pages are normal WordPress pages with fixed, labelled fields underneath the editor. These fields come from the free version of ACF, arranged in tabs. Fill a field to change that text or image; leave it empty to keep the approved default.

| Page | What you edit |
| --- | --- |
| **Home** (front page) | Hero, event and treat sections, FAQ, quote starter. The homepage design is owner-approved: change words and images, not the structure |
| **About**, **Contact** | Their own field tabs (headline, story, images, help text, FAQ) |
| **Get a Quote** | Page wording around the form. The form itself is managed in code (see section 7) |
| **Service Areas** | Hub page wording plus the **default city content** used by every city page (fields beginning "City template …") |
| **Events → Overview page** | Introduction, headings, steps and labels of the `/events/` page. The cards come from published events |
| **Privacy Policy**, **Website Terms** | **Drafts.** Publish only after the owner has approved the wording. Once published, the footer legal links and the form privacy link appear automatically |

Tips:
- Headings accept accent words: wrap them in `<em>…</em>`, for example `Plan your day. <em>We bring the treats.</em>`
- Multi-line headings use one line per row.
- Images: choose from the Media Library and always fill in the **alternative text** (describe the photo; leave it empty only for purely decorative images).
- Every save keeps a revision. Use **Revisions** in the editor sidebar to go back.

## 2. Treats (WooCommerce products)

**Products** in the menu are the treats (Dippin' Dots, Dip'n Bananas, Classic Ice Cream, Frozen Yogurt). The site is a **catalogue**: there are no prices, cart or checkout, and a separate safety plugin blocks purchasing on the server.

To edit a treat story:
1. **Products → All Products → (treat) → Edit.**
2. Change the title, short description, featured image and gallery images as needed.
3. Use the field tabs below (story, feelings and highlights, serving details, ingredient/dietary note, FAQs, card tagline and link label). Ingredient and dietary wording must be client-approved, so make no guarantees.
4. **Occasion pairings** box: one row per event. The headline and description you write here appear on **both** the treat page and the matching event page, so you only edit them in one place.
5. **Catalogue identity** (side box): the stable key used by quote links. Do not change it once the treat is live.
6. **Update.**

Adding a treat: **Add New**, fill the fields, set a key, and save as **Draft**. Drafts never appear in menus, cards or the quote form. Publish when ready, then check the treat page, the catalogue and the quote form.

Never enter a price or sale price, never enable "purchasable" features and never install payment gateways. Selling online would need a separate, tested release.

## 3. Events

**Events** holds Birthday, Wedding, Anniversary and Corporate.

1. **Events → (event) → Edit.**
2. **Composition** chooses the approved page design for that occasion (its own sections, order and colours). Keep the approved composition.
3. Content tabs: hero, story, plans, quote and FAQ wording, and images.
4. **Section controls:** each section has **show** (untick to hide), **order** (leave empty for the approved order, or enter a number to move it) and **preset** (leave on "Inherit page preset"). Empty order fields always mean "approved order".
5. **Paired treats** (side box) shows which treats are paired. Pairing text is edited on the treat (section 2).
6. **Update.**

Adding an event: **Add New**, choose a composition, fill the fields, then save as **Draft** and preview it. Publishing adds it to the Events page, the menus that list events and the quote form's occasion choices.

## 4. Global design (colours, fonts and sizes)

**Cater Ice Cream → Design System** is the single place for global design values. A change here updates every page, every event and treat, city pages and the quote form, without editing them one by one.

| Section | Contains |
| --- | --- |
| Brand colours / Base colours and text / Actions, hover and focus | Raspberry action colour, text, surfaces, link colour, focus ring |
| Occasion and treat accents | Colours that the occasion presets use (Birthday sky/mint/violet/raspberry, Wedding ivory/blush/champagne gold, …) |
| Typography | Heading and body fonts (from the bundled fonts), weights, accent style |
| Responsive scale / Layout / Components | Text sizes for mobile and desktop, spacing, radii, buttons |
| Presets | Per-occasion and per-treat looks that build on the global values |
| Contrast | Live warnings when a colour pair falls below the readable 4.5:1 |
| Export / import | Download the whole configuration, or import a saved one (validated, with confirmation) |

How to change a global colour or font:
1. Click **Download design configuration** (Export / import) so you can roll back.
2. Change the value. The field shows the approved default and whether it has been changed.
3. Check the **Contrast** panel: there must be no warnings for text colours.
4. **Save design system**, then open **Cater Ice Cream → Style preview ↗** and a few real pages (home, an event, a treat, the quote page) on phone and desktop.

Rules:
- Wedding champagne gold is **decorative only** (lines, rings, icons), never for reading text.
- Text links use the deeper raspberry for readability. Buttons use the main raspberry.
- To go back, use **Reset to inherited** on a field, or import the downloaded configuration.

### Overrides for one page, section or element

Use these sparingly. Most changes belong in the Design System.

- **Whole page:** in the page editor, the **Design overrides (this page only)** box. Set only the values you want different; empty means inherit.
- **One section or block:** select the block, then open **Cater style overrides** in the block sidebar. It shows what the block currently inherits; choose a value to override it. **Override active** marks a changed field.
- **Reset:** **Reset to inherited** for one value, or **Reset all overrides on this block**. After a reset the element follows the current global settings again, including any global changes made since.

## 5. Header, footer and contact details

**Cater Ice Cream → Header, Footer & Contact**:

| Tab / section | Controls |
| --- | --- |
| Header | Top row (enable, content), main row (logo, size: 190 px desktop / 136 px mobile approved), optional bottom row (uses the "Header bottom row" menu) |
| Mobile navigation | Drawer options |
| Footer | Columns, footer logo, text, menus, legal line |
| Contact & calls to action | Public business name, legal entity, **public phone**, public email, location, quote button label/link, call label, availability note, website label, social profile URLs (empty profiles are never shown) |

The public phone is used everywhere: header, footer, contact page and every `tel:` link. Change it once here.

The menus themselves are edited in **Appearance → Menus** (locations: Primary navigation, Header bottom row, Footer navigation, Footer second menu, Footer legal links).

The public business email is **not** where enquiries are sent. The notification recipient is set separately in Enquiry settings (section 7).

## 6. Service Areas (cities, ZIP codes and coverage)

**Service Areas** holds the cities, their ZIP codes and the coverage decisions. Approved production coverage has **not** been supplied yet, so staging has no cities. City pages return "not found" and ZIP checks answer "unknown" until reviewed data is imported.

Key ideas:
- **Publishing a city page does not mean you serve it.** Coverage is decided per ZIP and per city–ZIP link.
- New cities and ZIPs always start safe: city **Draft + Noindex**, ZIP **Unreviewed**, link **Inherit**.
- **Compton is excluded.** A ZIP shared with Compton cannot make Compton covered.
- Unknown or shared ZIPs ask the visitor for the venue city. They are never shown as "outside the area" by guesswork.
- A city appears in search engines only when it is **Published**, set to **Index** and has **reviewed** city content.

### Cities

- **Service Areas → Cities**: search and filter. Each row links to **Edit city**, **Edit content** (or **Create content**), **Preview** and **View**.
- **Add City**: name, state, county and slug. It is created as Draft + Noindex with no ZIPs.
- **Edit city**:
  - **City details**: name, slug and directory order. Changing the slug keeps the old address as a redirect. You can also add an **alias slug** that redirects once.
  - **Publication and search engines**: Draft or Published, and Noindex or Index.
  - **ZIP codes and coverage**, which has two separate things:
    - *ZIP coverage* affects **every** city linked to that ZIP.
    - *Decision for this city* applies to this city–ZIP link only.
  - **Add ZIP codes**: a check step shows other cities sharing the ZIP before you confirm.
  - **Remove** unlinks a ZIP from this city. The removal is remembered, so imports will not silently re-add it.

### City content

- City pages use the **default city content** from the Service Areas page. A city's own content record overrides only the fields you fill; empty fields keep inheriting (the editor shows the inherited text).
- **Edit content** opens the private city record (revisioned, never public by itself). Tick **reviewed** only when the owner has approved the text. **Preview** shows the real city page to signed-in managers.

### Importing coverage (CSV)

**Service Areas → Import**:
1. Choose the type: `cities`, `zips` or `city_zips`. The file must be a UTF-8 CSV with these columns:
   - `cities`: `state_code, county_name, city_name` (+ optional `city_slug, latitude, longitude, display_order, publish_status, index_status`)
   - `zips`: `zip, state_code` (+ optional `county_label, latitude, longitude, service_status`)
   - `city_zips`: `state_code, city_slug, zip` (+ optional `service_override, review_note`)
2. Dataset **Production** and a source reference (where the data came from, for example "Owner-approved LA County list 2026-11-02").
3. Run the **dry run** and read it: created, updated, unchanged, rejected, duplicates, and **conflicts with manual edits**. Manual decisions always win; imports never overwrite them.
4. Only for a reviewed coverage dataset, tick **Reviewed coverage**, which applies the `service_status` / `service_override` columns. Otherwise imports change geography only.
5. **Apply.** ZIP leading zeros are kept, ZIP+4 is shortened to 5 digits, and 3–4 digit values are rejected rather than guessed. Re-importing the same file creates no duplicates.

The same import exists for developers: `wp cic locations import file.csv --type=zips --dataset=production --source="…"` (dry run), then add `--apply`.

**Service Areas → Log** shows who changed what and recent imports. The bundled *fixture* files are local test data: the importer refuses them on staging and production.

## 7. Enquiries

Quote requests and contact messages arrive through the two Contact Form 7 forms and are stored once in Flamingo. **Enquiries** in the menu is the place to work them.

### Daily work

1. **Enquiries → All enquiries**: filter by status, type (quote or contact) and assignee, and search. The **Notifications** column shows whether the operator and customer emails went out.
2. Open an enquiry. You will see:
   - the request (event, date, guests, location and the coverage-check result, venue, treats, notes);
   - customer details, and the **Source** (first and latest page);
   - **Workflow**: status, assignee, next follow-up;
   - **Private notes**;
   - **Notifications**, with any last error.
3. Update the **Status** as the enquiry progresses:

   | Status | Meaning |
   | --- | --- |
   | New | Not yet handled |
   | Contacted | First reply sent |
   | Quote sent | Quote with the customer |
   | Booked | Closed: confirmed by staff (the website never confirms bookings itself) |
   | Lost | Closed: did not proceed |
   | Spam | Closed: junk. No acknowledgement is ever sent for spam |

4. **Assign** it to a team member with enquiry access, set the **Next follow-up** date (Pacific time) and add **private notes**. Customers never see notes.
5. **Closing a lead** means setting Booked, Lost or Spam. Closing cancels any reminder.

### When an email failed

The enquiry is always saved first, so a mail failure never loses it.
- The hourly maintenance job retries failed notifications automatically.
- To retry straight away, open the enquiry and click **Retry notifications**. Each email is sent at most once, even if retries overlap.
- On staging, mail is blocked on purpose, so "failed" there is expected.

### Export

**Export CSV** on the list downloads the filtered enquiries, excluding spam. The file is protected against spreadsheet formula injection. Treat exports as personal data: store them securely and delete them when no longer needed.

### Privacy requests

- **Tools → Export Personal Data**: enter the customer's email address. The export includes their enquiries with workflow data.
- **Tools → Erase Personal Data**: Flamingo's eraser deletes their messages. The workflow data and coordination row go with them.

Retention:
- Flamingo moves spam to the trash after 30 days, and WordPress empties the trash after 30 days.
- Real enquiries are **kept until someone deletes them**. The owner should decide a retention period (and state it in the privacy policy), then delete older enquiries periodically.
- Uninstalling the plugins does **not** delete enquiries.

### Enquiry settings (administrators)

**Enquiries → Settings**:

| Setting | Notes |
| --- | --- |
| Operator notification recipient | Initially `anuranjan.verma25@gmail.com`. This is where new-enquiry notifications go, and it is separate from the public business email |
| Sender name / Sender email | **Pending production setup.** Empty uses the WordPress default. Set them only to a verified sending domain |
| Operator subject, acknowledgement subjects and texts | Editable. Placeholders such as `{name}`, `{summary}`, `{phone}`, `{type}`, `{id}`, `{event}`, `{location}` are listed on the screen. Acknowledgements must never promise a booking |
| Customer acknowledgement | On or off |
| Forms | The CF7 quote and contact forms (#357 and #358). Do not change them unless a developer asks |
| Internal follow-up reminders | **Off.** Enable only when the owner has chosen the timing: set a reminder time (HH:MM, Pacific), the number of days before the follow-up date, and an optional fallback recipient. Reminders go to staff only, never to customers |

The form fields themselves (questions, options, required fields) are defined in code and are **provisional pending the owner's review**. Ask a developer to change them; do not edit the CF7 form markup in **Contact → Contact Forms**. The CF7 "Mail" tabs are unused because the site sends its own emails. "Contact form 1" is an unused default.

## 8. Routine checks

| When | Check |
| --- | --- |
| Daily (enquiry days) | **Enquiries**: new items, follow-ups due, any failed notifications |
| Weekly | **Tools → Site Health**: no critical issues. Glance at the site on a phone |
| After any design change | Contrast panel clear; check home, one event, one treat and the quote page |
| After a coverage import | Dry-run report saved; spot-check 3 ZIPs with the homepage search |
| Monthly | Backups exist and a recent one has been test-restored on a disposable copy (`BACKUP_AND_ROLLBACK.md`). Plugin updates are applied on staging first |

## 9. Please do not

- Delete or rename `wp-content/mu-plugins/cic-catalog-guard.php`, or add `CIC_COMMERCE_MODE` to `wp-config.php`.
- Add prices, payment gateways or "minimum order" text.
- Install page builders, ACF Pro, SEO or SMTP plugins without a developer review.
- Publish the draft policies, enable reminders or tick "Index" for cities without the owner's approval.
- Import the fixture files, or present them as real coverage.
- Copy a staging database onto the live site.
