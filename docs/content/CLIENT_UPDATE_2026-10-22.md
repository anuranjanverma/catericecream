# Client update, 2026-10-22: content handoff notes

Local build only. Nothing is deployed, committed or sent externally. The rc1 release package is outdated (it predates this update).

## What changed

| Area | Result | Where it is edited |
| --- | --- | --- |
| Event offerings | **Graduation Parties** (`/events/graduation-parties/`) and **Proms** (`/events/proms/`) added as separate Events records with their own compositions, design presets, copy, FAQs, search title/description and pairings with all four treats. Anniversary kept (same design, content and URL) | Events → (event) → Edit |
| Event order | One central order for the whole site: Birthdays 10, Weddings 20, Graduation Parties 30, Proms 40, Corporate Events 50, Anniversaries 60. Used by the header dropdown, homepage cards, Events page, Service Areas and city occasion cards, hero search and quote-form choices ("Other" last) and treat-page occasion recommendations. Menu links to events are sorted automatically | Each event's **Order** (Page Attributes box) |
| Footer | The footer has a single "Events" link to the overview (no per-event links), so there is no separate footer order to maintain | Appearance → Menus |
| Public phone | **424-235-4942**, every link `tel:+14242354942` (header, footer, call panels, Contact, About, structured data, acknowledgement emails) | Cater Ice Cream → Header, Footer & Contact |
| Public contact name | **Alex Frost** (client's public business name). About: "Say hello to Alex Frost."; Contact: "Call Alex Frost"; acknowledgement emails signed "Alex Frost / Cater Ice Cream". Legal company stays **Frosty Yummy LLC** (footer, About byline, schema `legalName`). WordPress accounts, historical enquiries and internal settings unchanged | Same screen (new "Public contact name" field); pages use `{contact_name}` |
| Internal recipient | Unchanged. Public email, operator recipient, sender and a new optional Reply-To for customer emails are four separate settings | Enquiries → Settings |
| Kosher FAQ | Saved on the homepage FAQ ("A few things you might be wondering") as question 4, **Draft (not shown)**, with an internal note | Home → FAQ tab |
| Copy | About rewritten around confirmed facts; homepage, Our Treats, Service Areas, city template, Get a Quote, Contact, confirmations and acknowledgement emails made plainer and friendlier; event lists now name all six occasions. Homepage structure and design unchanged (approved) | Page field tabs; Enquiries → Settings |
| American English | 59 content fields, 8 pairing texts, 20 image alt texts (which also lost leftover "no text/no logos" generation notes), form labels, validation and network messages, confirmations and the privacy-export label. Brand names, technical identifiers (for example the form field `flavours`), database names and URLs unchanged. Internal admin screens keep "Enquiries" | — |
| Logos | **Applied after owner confirmation:** `logo-main.webp` = header only (desktop/mobile/compact), `logo-transparent.webp` = footer only (light plate off so the white artwork stays transparent). Imported into the Media Library (#870, #871; byte-identical copies), so nothing depends on the handoff folder. Displayed sizes and header/footer heights unchanged (190×95, 136×68, 140×70 compact; footer 144 px in the 168×88 area). Favicon and emails unchanged | Header, Footer & Contact → logos |

Re-running `wp eval-file dev/tools/content/client-update-20261022.php` is safe: it only changes text that still matches the old seeded wording and reports anything an editor changed.

## Graduation Parties and Proms

- **Graduation Parties**: split hero with a graduation-cap line, small stars and confetti; three audience points (graduate, parents and families, school organizers); the quote starter strip; Birthday-style treat cards; two stories (backyard parties, school celebrations); planning steps; quote panel with four FAQs.
- **Proms**: the Wedding-style hero card with a starlight sparkle line and restrained gold accents; ribbon; "The dessert stop" story with theme/staffing/schedule points; brand treat cards; a wide evening photo band; planning steps; framed quote panel with four FAQs.
- No prices, minimums, school affiliations, booking history, availability or operational promises. FAQs say a date is held only once confirmed and that service depends on the ZIP code.
- Every quote button and starter on these pages preselects the event (`event=graduation` / `event=prom`).

## Outstanding inputs (needed from the client)

1. **Company email.** The public email stays `ceo.frostyyummy@gmail.com` until a company-domain mailbox is supplied and working. Then: change Public email (website), and decide the verified sender email and the Reply-To in Enquiry settings. Do not publish an address that does not exist yet.
2. **Real photos.** No real client photos have been supplied; every photo is a temporary AI recreation. Graduation and prom pages especially need real photos (high-school graduation and prom settings). Full list with page, placement, subject, aspect ratio and sizes: `docs/content/PHOTO_INVENTORY.md`. Photos of people need publishing permission (and the school's permission for school events).
3. **DipN' Bananas Kosher certification.** Confirm the certification of the DipN' Bananas products actually supplied. Dippin' Dots publishes certification information at https://www.dippindots.com/about/quality-social-responsibility/ . When confirmed: Home → FAQ → question 4 → switch **Draft** off → Update. Do not extend the statement to other treats, toppings, serving setups or the catering service, and do not add certification symbols without approved source assets.
4. **Logo (resolved later on 2026-10-22: the owner confirmed and supplied the finished files; applied, see the table above).** Original note: the approved logo is raster-only (WebP up to 768 px) with no editable source, and its "ICE CREAM" lettering has no letter "O" to reuse, so ".COM" cannot be added faithfully without redrawing letters. During this update two files appeared in the handoff folder (`handoff/Cater_Ice_Cream_WebP_Assets/logos/logo-main.webp`, 353×150, and `logo-transparent.webp`, an all-white version). They read **"ICECREAM.COM"** (no space; the request says "ICE CREAM.COM"), and the color file is below the 380 px width needed for a sharp 190 px header on high-density screens. They were **not applied**. Preview screenshots: `dev/tests/output/client-update/logo-preview-*.png`. Needed: confirmation that this is the approved artwork and the intended wording, ideally as an SVG (or a PNG/WebP at least 1200 px wide, transparent background). To apply: Header, Footer & Contact → main row → Logo (desktop and mobile) and footer logo. The favicon stays the mascot.
5. **About page facts (optional, would make it more personal):** when and how the business started; Alex's role and a short welcome in Alex's own words (and whether a portrait may be shown); the size or makeup of the serving team; what a typical setup looks like (cart, table, freezer, how long service lasts, what the host provides); how far ahead people usually book; favorite or most-requested treats; any approved photos of real events. Nothing of this was invented.
6. **Anniversary label.** The event title stays "Anniversaries" (approved content). The client's list wrote "Anniversary"; change the title if the singular is wanted.

## Not changed on purpose

Homepage sections, layout, typography and colors; palette and darker lettering; draft Privacy Policy and Website Terms (legal wording awaiting review); reminders (off); indexing (local/staging stays noindex; the new pages are in the sitemap provider for when indexing is enabled); CF7, Flamingo and the enquiry workflow.
