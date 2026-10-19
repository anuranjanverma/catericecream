# Cater Ice Cream WordPress Developer Handoff and Build Specification

Version 1.1 | 18 October 2026 | Prepared by Web Karma for Anuranjan Verma and the implementation team

Build catericecream.com as a custom WordPress catering website with WooCommerce products, an Events custom post type, database driven locations and a three step enquiry flow. The initial release is a catalogue and lead management website. Online purchasing is a later, separately tested release.

The written requirements in this pack override outdated copy, artificial imagery and inconsistent controls in the visual mockups. Use the mockups for composition and atmosphere, then build real text, forms, images and accessible components. No single prompt can guarantee an exact result. The staged workflow and verifiable acceptance criteria are how we control the outcome.

## 1 Project decisions

| Area | Build decision |
| --- | --- |
| Public identity | Cater Ice Cream. Frosty Yummy LLC is the legal entity, not the logo name. |
| Platform | WordPress and WooCommerce, with a custom hybrid PHP theme and native WordPress editing. |
| Editor | Gutenberg for prose and repeatable editorial sections; ACF Free for fixed structured fields. Native blocks only where core blocks cannot meet the interaction requirement. No ACF Pro or paid dependencies. No Elementor, Divi, Bricks or other page builder in this baseline. |
| Treats | Real WooCommerce product records. Four initial showcase products, all non purchasable. |
| Events | Public cic_event CPT with Birthday, Wedding, Anniversary and Corporate entries. |
| Locations | Indexed custom database tables and virtual city routes. Do not create thousands of WordPress Pages. |
| Design controls | One Design System admin panel controls global colours, typography, sizes and spacing. Presets and sparse local overrides inherit from it. |
| Enquiries | One CF7 form presented in three steps, with Flamingo as the authoritative enquiry store before notification attempts. No reservation or immediate price calculation. |
| Business contact | Yan Kriv, (818) 726-9700, ceo.frostyyummy@gmail.com. Public business location is Los Angeles, California. |
| Coverage | Selected ZIP codes in Los Angeles, Ventura and Santa Barbara counties. Compton is excluded; shared ZIP cases need careful review. |
| Public pricing | Do not show a minimum order amount, prices, cart, checkout, account or payment controls. Do not implement an unpublished minimum as a form rejection rule. |
| Development | Local or staging first, version controlled. Deployment and real customer communications require their own explicit instruction. |

The page builder choice is a recommendation for this build, not a limitation of Claude Code. Claude can write a custom theme directly. Native editing plus structured content provides admin control without making the approved designs dependent on a third party builder licence.

## 2 Ownership and code structure

Use three separately installable components. WordPress guidance recommends keeping custom post types in a plugin so the content survives a theme change. [WP1]

- **cater-ice-cream theme:** PHP templates, semantic components, CSS, editor presentation, local fonts and animation assets. Include theme.json defaults for editor parity and design presets.
- **cater-ice-cream-core plugin:** Events, product metadata, structured blocks and fields, Design System settings, locations, imports, routing services, CF7 integration and Flamingo workflow metadata. Do not register a duplicate cic_lead CPT or custom quote/contact submission endpoint.
- **cic-catalog-guard.php MU plugin:** A small standalone purchase guard that defaults to catalogue mode even if the theme or core plugin is deactivated. Do not put essential purchase prevention only in the theme.

Suggested directories are theme/inc, theme/template-parts, theme/assets, core/src/Admin, core/src/Content, core/src/Design, core/src/Locations, core/src/Quotes, core/src/SEO and core/src/Integrations. Use a CIC PHP namespace or consistent cic_ prefix. Avoid one enormous functions.php file.

Use WooCommerce CRUD for product data and supported WordPress APIs for posts, settings, capabilities, REST and scheduling. Do not modify WordPress or WooCommerce core. Inspect installed versions and compatibility before choosing APIs; record the tested WordPress, WooCommerce, PHP, database and build tool versions in ENVIRONMENT.md.

Do not introduce React on the public website just to render normal pages. React may be used for native editor controls where appropriate. Use lightweight JavaScript modules for the menu, galleries, quote wizard, location search and motion. Reuse an existing SEO or mail integration where suitable instead of installing overlapping plugins.

## 3 Routes and page ownership

| Route | Owner and template |
| --- | --- |
| / | WordPress front page with homepage composition |
| /treats/ | WooCommerce shop archive presented as Our Treats |
| /treats/dippin-dots/ | WooCommerce product with treat story template |
| /treats/dipn-bananas/ | WooCommerce product with chocolate banana composition |
| /treats/classic-ice-cream/ | WooCommerce product with classic composition |
| /treats/frozen-yogurt/ | WooCommerce product with yogurt composition |
| /events/ | cic_event archive with editable archive introduction |
| /events/birthday/ | Birthday event with colourful composition |
| /events/wedding/ | Wedding event with premium ivory and gold composition |
| /events/anniversary/ | Anniversary event with approved intimate composition |
| /events/corporate/ | Corporate event with approved professional composition |
| /service-areas/ | Service Areas hub Page |
| /service-areas/california/ | Database state hub using the location renderer |
| /service-areas/california/pasadena/ | Virtual city route resolved from the database |
| /about/ and /contact/ | Standard Pages with dedicated editable compositions |
| /get-a-quote/ | Three step quote Page |
| /quote-request-received/ | Generic success state with no customer information in URL |
| /privacy-policy/ and /website-terms/ | Standard Pages using the readable policy template |
| Unrecognised routes | Real 404 response using the branded 404 template |

Do not create duplicate Pages for the WooCommerce shop or Events archive. Configure product permalinks once and test collisions. Do not create duplicate brand landing pages for the initial four treats; the Dippin' Dots product is its launch brand destination. Taxonomy archives must not compete with those destinations.

Event card Explore buttons open event detail pages. Explicit Plan or Quote actions carry context into /get-a-quote/. City links open city pages. Normal page visits do not silently select an event, treat or city.

## 4 Global design controls

Create a clear admin menu named Cater Ice Cream with a Design System screen. Use the Settings API with capability checks, nonces, validation and export/import of the approved configuration. Make global palette, typography and preset settings the only editable source of design values. Do not also expose a conflicting theme options panel or unrestricted raw Global Styles editing.

theme.json contains safe defaults. The core design service resolves saved settings into an allowlisted token object, updates editor presets through supported theme.json hooks and emits matching CSS variables for the frontend. WordPress documents both theme.json styling and the wp_theme_json_data_theme filter. Verify support against the installed version. [WP2, WP3]

| Admin group | Controls |
| --- | --- |
| Brand | Logo variants, favicon, base colours, text colour, primary and secondary actions, hover and focus states |
| Typography | Heading and body font families, weights, sizes, line heights and optional letter spacing for named roles |
| Responsive scale | Mobile and desktop limits for H1 through H4, body, captions, labels and buttons |
| Layout | Content width, section width, spacing scale, section padding, card radius and button radius |
| Presets | Default brand, Birthday, Wedding, Anniversary, Corporate and each treat theme |
| Components | Shared header, footer, buttons, fields, cards, call panels, quote starters and accordion styles |

Resolve central defaults and saved global tokens first, then explicit sparse page, section and element overrides. Context presets reference global tokens rather than copy their values. Global heading/body fonts and shared colour roles must change across every template, including all occasion pages. Birthday and Wedding accents are centrally editable named tokens, not hidden page literals. A global action-colour change updates every CTA unless an editor deliberately creates a visible local override. An empty override means inherit, not an empty CSS value. All override fields need a visible Reset to inherited action and an indicator that an override is active.

Expose safe colour roles and bounded numeric size controls, including mobile and desktop values. Do not accept arbitrary CSS, JavaScript or unrestricted style strings. A section can select another named preset or override a few roles without freezing every inherited value. A page override must be scoped to that page; two instances of a block can differ without affecting one another.

Global theme settings also supply shared CTA labels, phone, email, navigation and recurring explanatory text. Contact detail changes must update every call panel and tel link. Override scope is separate from content editing; changing a hero title does not require rebuilding a layout.

Saving a global design setting must invalidate its generated stylesheet and relevant page or CDN caches, including virtual city pages. Product, event and coverage edits must invalidate their own related query and lookup caches. Test the visible change through the configured cache layer, not only in an uncached editor preview.

## 5 Tokens and approved visual contexts

| Token | Initial value and use |
| --- | --- |
| brand raspberry | #DE2879 for logo artwork, large accents and decoration |
| action primary | #D92373 with white button text; hover #BC1F64 |
| text primary | #503564 deep plum |
| strawberry | #F3A2BD |
| lavender | #B9A0DD |
| surface soft | #F3EFF9 |
| surface base | #FFFFFF |
| birthday accents | Sky #63C9EB, mint #8CDCBF and violet #7652B8; pale surfaces derived separately |
| wedding accents | Ivory #FFFDF8, champagne gold #B29458 and blush #F5E7E8 |

These are configurable starting values, not literals to repeat in page CSS. Reference semantic variables such as --cic-color-action and --cic-font-heading. Keep logo artwork unchanged when the website palette changes unless a replacement logo is deliberately uploaded.

Use Fraunces 700 for base headings and DM Sans 400, 500 and 700 for body and interface. Start with H1 64/70 px desktop and 40/44 mobile; H2 42/48 and 32/38; H3 28/34 and 24/30; body 18/28 and 16/26. Use fluid interpolation with bounded values. Wedding may vary heading weight and italic treatment while inheriting the global heading family. A different family requires a deliberate visible local override, never an automatically seeded font-family lock.

Self host approved licensed fonts and provide fallbacks. Prevent font uploads from accepting arbitrary executable files. Include font source and licence information in the implementation repository.

The base raspberry and white pair is approximately 4.48:1; do not use it for normal size text requiring 4.5:1. White on action pink is approximately 4.73:1. Gold is decorative, not the default reading text. Recheck contrast when an administrator changes a colour. The style preview should warn about failing text combinations without silently changing saved brand values.

Birthday and Wedding are different compositions, not identical sections with replacement colours. Anniversary and Corporate are also final reference choices. Treat pages explain feeling and occasion pairing. Their theme boards suggest styling; they do not promise supplied decor, custom packaging or available flavours.

## 6 Content editing and reusable sections

All visible copy, imagery, alt text, internal links, phone labels, FAQs, hero controls and section visibility must be editable. Use native Pages and Gutenberg for ordinary prose. Use controlled Gutenberg patterns/core blocks for repeatable editorial content and ACF Free for fixed fields. Register only the small number of native dynamic blocks needed for forms, location lookup and record listings. Do not use ACF Blocks, Repeater, Flexible Content, Gallery or ACF Options Pages. Use WooCommerce galleries and native Settings API screens instead. Do not hardcode the only version of a title or a list of treats into a PHP template.

Provide section types for hero, photo collage, emotional value points, brand or treat listings, occasion pairings, theme inspiration, editorial story, process, FAQ, call panel, location finder, county directory, map and quote starter. Each has an enabled flag, ordered position, content fields and optional style overrides. Restrict choices enough to preserve the approved design; operators can edit content and move supported sections without constructing every page from scratch.

Supply sensible layout presets with different section order and photo treatment. The wedding botanical and ring motifs and birthday balloons and confetti are theme assets, not screenshots. Build real SVGs with consistent stroke and accessible handling. Decorative SVGs are hidden from assistive technology. Provide reduced motion and no animation that delays input or obscures text.

Use Media Library attachment IDs, responsive images and editable crops. Generated product images, brand marks, fictional branded cups and staff photos are visual references, not proof of real assets or actual service equipment. Replace them with approved material before launch. Do not invent founder photographs, reviews, statistics, availability, local offices or dietary guarantees.

## 7 WooCommerce product records

Seed four catalogue products with stable cic_key values: dippin-dots, dipn-bananas, classic-ice-cream and frozen-yogurt. Keep normal WooCommerce title, excerpt, description, slug, featured image and gallery authoritative. Give each a cic_catalog_role of showcase. Prices may be empty; never set a fake zero price to make an item appear purchasable.

| Field group | Structured data |
| --- | --- |
| Identity | Stable key, brand or generic type, sort order, active catalogue flag and selected composition preset |
| Hero | Eyebrow, emotional headline, introduction, CTA label and media attachment references |
| Feeling | Ordered qualities with icon, title and meaningful explanation |
| Occasion pairings | event_id, feeling headline, explanation, image and internal event link |
| Theme inspiration | Title, image, description and palette references; no implied stock guarantee |
| Product detail | What it is, texture, options to discuss, ingredient note approved by client and FAQs |
| Relationships | Featured or related product IDs, related events and optional location overrides |
| Presentation | Sparse page and section style overrides, visibility and order |

Store pairing descriptions once and maintain an indexed cic_product_events join table with unique product_id plus event_id. Reverse event-page lookup uses this index, not LIKE queries against serialized postmeta. Saving or deleting a pairing updates its index through one service. Use the same description on both pages, with an optional event-page introduction. Do not maintain contradictory descriptions in two places. Validate related IDs and avoid duplicate pairings for one event on one product.

Use WooCommerce's built in brand taxonomy if present and useful after the version audit; do not register a second product_brand taxonomy over it. Generic types must not falsely receive a supplier brand. Do not expose duplicate, empty taxonomy archives just to fill navigation.

Future selling will need real fulfilment units, SKU, weight, packaging, stock, taxes and shipping rules. A showcase family is not automatically a saleable portion. Add separate sellable pack products linked to the showcase via a validated relationship, or deliberately migrate a family into a variable product when the real fulfilment model is approved. Preserve the catalogue landing URL and stable identity.

## 8 Event custom post type

Register cic_event in the core plugin with public archive /events/, REST enabled for editing, revisions and the standard title, content and featured image supports. Registered structured meta needs explicit types, sanitizers, schemas and edit capabilities. [WP1, WP4]

The four seeds are Birthday, Wedding, Anniversary and Corporate. The editor selects a layout preset and edits hero copy, photos, emotional points, occasion-specific story, planning prompts, FAQ, featured treat relationships and the final quote starter. Treats can work across events; pairings guide the mood rather than restricting what a customer may ask about.

The Events archive has editable introduction, card copy, card crops and ordering. It uses a two-column desktop grid and a single-column mobile stack. Its fourth card is Anniversary. A separate text link allows other event enquiries without inventing another specialist service page.

The occasion is known on an event quote action. Avoid asking the visitor to select it again unless they want to change it in the shared quote flow. Event pages do not represent dated inventory or availability calendars.

## 9 City database and imports

Use WordPress's configured table prefix, not a hardcoded wp_. Keep location data separate from WordPress Pages. The existing Flowers and Party Characters pattern is the intended operating model; their production schema and code have not been supplied with this pack. The owner will supply a custom city template and rewrite-rule function later. Audit and reuse compatible parts through the adapter boundary; do not finalize a competing schema or routes before that audit. Use the following schema only as a local fallback contract. Do not access another production database or reuse credentials by assumption.

| Table | Required columns and indexes |
| --- | --- |
| cic_cities | id PK; country_code; state_code; county_name; city_name; city_slug; timezone; nullable latitude and longitude; publish_status; index_status; display_order; updated_at. Unique country plus state plus slug. Index state, county and name. |
| cic_zips | zip CHAR(5) PK; state_code; county_label; nullable centroid; service_status; source_reference; reviewed_at; updated_at. Allowed statuses unreviewed, enabled, disabled, manual_review. |
| cic_city_zips | city_id plus zip composite PK; service_override inherit, enabled, disabled or manual_review; internal review note. Index zip for reverse lookup. |
| cic_city_content | Provisional fallback: city_id PK; editorial_post_id unique; updated_at. Editorial content lives in private cic_city_editorial WordPress records with revisions, structured fields and authenticated preview. Existing supplied city implementation must be audited before adopting this fallback. |
| cic_product_events | product_id plus event_id composite PK; indexed event_id for reverse lookup; validated pairing content reference. |
| cic_request_keys | hashed submission key PK; flamingo_message_id nullable during claim; state; created_at; expires_at. Atomic claims coordinate one stored enquiry and notification workflow, with stale-claim recovery. |

Use logical referential checks and deletion restrictions. Add schema versions, repeatable migrations and WordPress-compatible dbDelta creation routines. Never run destructive database changes on every page load. Back up before migrations and keep data on normal plugin deactivation. [WP5]

The location repository exposes resolve_city, search_locations, list_counties, list_cities, get_city_content, evaluate_coverage and get_map_points. A legacy database adapter must satisfy that contract. The renderer, form and search must not each contain their own unrelated SQL or coverage rules.

Imports need CSV validation, UTF-8 handling, a dry run, duplicate report, deterministic upsert, source tracking and counts for created, updated, unchanged and rejected rows. Preserve leading ZIP zeros. Normalize ZIP+4 to the first five digits after validating its structure. Do not infer service from every ZIP present in a geographic reference PDF. Geography and approved coverage are different datasets.

Reconcile Los Angeles County, the client-supplied Ventura and Santa Barbara lists, excluded Compton locations and nonresidential or shared ZIPs. A shared ZIP cannot be globally enabled merely because it also names another city. Mark ambiguous mappings for manual review; ask for venue city or address rather than falsely confirming coverage. Source documents can be old; import review must occur before publication.

## 10 Coverage search and virtual city pages

Coverage evaluation uses one shared service on both frontend and server. Explicit reviewed city or ZIP exclusions deny service and cannot be overridden by an enabled association. Conflicting or shared mappings require disambiguation. Otherwise an approved city-ZIP mapping or enabled unambiguous ZIP is covered. Unknown geography is unknown, not outside_area; malformed input is invalid. A city containing only partially served ZIPs asks for the event ZIP instead of claiming whole-city coverage.

For a confirmed outside_area result, show: Sorry, we do not currently serve this area. Please choose another event location. Block quote progression and final quote submission until corrected; keep other answers intact. General Contact and call options remain available. Unknown/manual_review results ask for ZIP and venue city or clarification; never mislabel these as confirmed exclusions or allow them to bypass quote coverage validation. A covered result indicates service geography only, not event-date availability.

The location API returns candidate city label, stable key, canonical URL, coverage status and a human explanation. Support covered, outside_area, manual_review, unknown and invalid input. Limit autocomplete results, debounce requests, paginate directories and use indexed prefix or normalized lookups. Never load a national city database into the browser.

City and state pages resolve through explicit rewrite rules. Return real 404 for unknown states or slugs; redirect only recorded aliases to their canonical URL. Avoid rewrite flush on normal requests. City pages combine global sections, state or county defaults and city-specific overrides. Do not create city by event by treat Cartesian route combinations.

Show a California map on the desktop hub to the right of the search and below it on mobile. Plot approved ZIP centroids with clustering or county grouping. Points are approximate locations, not coverage polygons or a guarantee of service at a venue. The lookup database is authoritative. Do not reverse engineer boundaries from a decorative mockup, draw a radius and call it coverage, or place customer event addresses on a public map.

Use a lightweight licensed outline or local map dataset. A third-party live map is optional and needs an agreed provider and key; no unapproved paid account dependency. Provide a text county and city directory that works without the map.

## 11 City content and search indexing

City content needs a distinct title, introduction, relevant event context, selected treat introductions, factual planning details, FAQ, nearby approved cities and a call panel. Empty city overrides inherit useful template content but remain noindex until reviewed. A city status controls whether it is publicly published separately from whether it is eligible for indexing.

Use one SEO adapter for the installed SEO plugin, or a core WordPress integration if none is chosen. City canonical, title, description, Open Graph, breadcrumb and sitemap URL must all resolve to the same virtual city. Do not output competing SEO tags from both theme and plugin. Only approved, indexable canonical city URLs enter sitemaps. Draft, noindex, invalid and search-parameter URLs stay out.

Do not mass publish near-duplicate city pages with only the name changed. Google describes doorway abuse as pages targeting similar queries and funneling users onward without adequate value. Our publish controls and reviewable unique city content are technical safeguards, not promises of rankings. [G1]

Use actual Organization or service business information and breadcrumbs. A listed service area is not an office address. Do not fabricate city branches, local telephone numbers, reviews or venue endorsements. On catalogue products, do not emit zero-priced Offer or in-stock sales assertions just because WooCommerce is installed. Product rich-result eligibility is not a launch guarantee.

City administration must support searchable records, edit/preview, draft/publish, independent index status, revision recovery, slug history and one-hop redirects. Use a private editorial record only if the supplied implementation lacks an equivalent reliable workflow; keep public cities as virtual routes. One SEO adapter owns metadata. Update caches after content, coverage, slug and design changes. Flush rewrite rules only on deliberate activation/versioned route changes, never on each request. Approved editorial records must remain recoverable through backups and revisions.

## 12 Quote context and step behaviour

Explicit selections accumulate across pages. A homepage search supplies location, event and date. A city page supplies city. A Plan action on an event supplies event. A treat Plan action supplies product. The most recent explicit choice wins for the same field; unrelated choices are preserved. Merely viewing a page does not change the selection.

Use validated nonpersonal URL context or sessionStorage for event, product references, city or ZIP, date and estimated guests. Never place name, email, phone, dietary notes, address or free text in a query string or analytics. Do not persist contact or dietary text in localStorage. Keep unsubmitted sensitive/free text in memory unless a separately approved draft feature is added.

Example context shape is /get-a-quote/?event=wedding&city=pasadena-ca&treat=dippin-dots&date=2027-06-20. Resolve the keys against published records and validate dates. Invalid parameters do not become trusted field values. URL keys are identifiers, not authorisation.

| Step | Fields and behaviour |
| --- | --- |
| Your event | Required event type, location and approximate guest count. Date required unless Date not set yet is selected. Venue or address, serving start and duration optional. Permit an Other occasion. |
| Your treats | Multiple active product selections, or Not sure yet. Selecting Not sure clears product selections and vice versa. Optional flavour preferences, theme or feeling and dietary notes. |
| Review and send | Name and email required. Phone optional unless Phone is the chosen contact method. Show editable summary of event, location, date, guests and treats. Additional notes optional. |
| After submission | Confirm saved request, explain team review and availability discussion, and provide call and browsing options. Do not imply a reservation, payment or completed booking. |

Prepopulate step 1 and show remaining missing fields rather than making the user retype everything. Only advance automatically when all required information for that step is valid and the transition is clear. Direct access to a later step returns to the earliest incomplete step. Back, keyboard navigation and browser history preserve in-session answers.

Use California local dates and America/Los_Angeles event time semantics, including daylight saving time. A date-only value must not shift because JavaScript converts it through UTC. Do not disable dates using invented availability. Validate past dates consistently and offer a helpful correction.

## 13 CF7, Flamingo and lead workflow

Use Contact Form 7 for both quote and general Contact submissions. CF7 owns its supported submission transport, field validation and spam checks; do not register competing /cic/v1/quotes or /cic/v1/contact endpoints. A versioned normalized payload is an internal integration contract, not a second public API. Form IDs and routing settings are stored by environment in admin; never hardcode production IDs into templates.

Flamingo is the only authoritative stored enquiry record. Do not also create cic_lead records. The core plugin adds namespaced metadata and controlled workflow views/actions referencing the Flamingo message ID: enquiry type, normalized event/treat/location context, source, status, assignee, private notes, next follow-up and notification state. Use supported APIs/hooks after inspecting installed CF7 and Flamingo versions; do not modify vendor files or depend on an assumed hook order.

Before enabling notifications, prove in a focused integration test that an accepted enquiry is saved once and the resulting Flamingo ID is available before sending operator/customer messages. If stock storage and mail sequencing cannot satisfy this, implement a thin documented storage/notification adapter using supported APIs. Keep CF7 as the sole submission path and Flamingo as the sole store. A storage failure must not report saved success or send acknowledgements. Check both required dependencies and fail gracefully rather than silently accepting unsaved enquiries.

Validate all fields server-side, including published identifiers, covered location, date/date_unknown, numeric counts, contact preference and text lengths. Map dynamic event/treat options through CF7-supported field validation; changing only browser HTML is insufficient. Use an atomic submission-key claim to avoid duplicate Flamingo records or messages on retries. Never deduplicate distinct enquiries merely because their email address matches. Resolve labels on the server. Keep spam unacknowledged and separate from accepted leads.

Provide new, contacted, quote_sent, booked, lost and spam statuses, notes, assignment and follow-up controls attached to the same Flamingo message. Both Web Karma and Yan receive separate user accounts with an explicit manage_cic_enquiries capability and compatible Flamingo access. Ordinary editors have no lead access. Deleting/trashing a message cancels follow-ups; retention and export/erasure cover workflow metadata too. Restrict Flamingo contact-book and exports appropriately; do not grant full Administrator solely for enquiry access.

Initial operator recipient is anuranjan.verma25@gmail.com, editable in admin. This is a notification recipient, not an authenticated sender address or a WordPress login identity. Public business email remains ceo.frostyyummy@gmail.com. Configure a verified domain sender and customer Reply-To at deployment. Customer acknowledgement, operator notification, templates and recipient settings are editable. Choose one mail owner for each notification: do not send both CF7 Mail/Mail (2) and a custom duplicate. Save first; email failure leaves one enquiry with recoverable delivery state.

Use one scheduled-job runner, reusing installed Action Scheduler where compatible; production needs a working server cron trigger and delivery monitoring. Reminders go to internal operators only. Timing, assignee fallback, recipients and timezone are admin settings. Reminder timing is pending; reminders remain disabled until configured. Deduplicate/reschedule safely and cancel closed/spam/deleted-message jobs. No automatic customer follow-up campaign, SMS, external CRM or commission accounting is included.

Use mail capture and .test recipients on local/staging even though the configured production recipient is recorded here. ACF Free, CF7, Flamingo and the core plugin must require no paid licence. Provide capability-checked, nonce-protected actions and exports, CSV formula protection, approved retention and privacy handling. No public lead REST, search, sitemap or export route.

## 13A Multi-step form acceptance

Render one CF7 form grouped into Your event, Your treats and Review and send. JavaScript progressively enhances it; without JavaScript the complete form remains usable. Validate each step before advancing and all fields on the server. Final errors open the relevant step and focus the field/error summary, including controls previously hidden. Back preserves values. Prevent hidden required controls from trapping browser validation. Test keyboard, network retries, dependency failure and mail failure. Existing field definitions are provisional pending owner review; do not describe them as newly approved or invent extra mandatory fields.

## 14 Catalogue mode and future selling

Catalogue mode is a server-side capability decision, not a CSS-only presentation setting. Keep real WooCommerce products editable while preventing public purchase paths, including those absent from the theme. WooCommerce has separate Cart and Checkout Store APIs, so hiding rendered buttons is insufficient. [WC1, WC2]

The MU guard defaults to catalogue mode when configuration is missing. In that mode, all products and variations are non purchasable; add-to-cart attempts are rejected; legacy cart, checkout, order-pay and account shopping entry points are removed or safely redirected. Do not redirect REST requests to an HTML page: return a consistent JSON error before mutation.

Inspect frontend dependencies before restricting Store API reads. Deny public cart/checkout mutation and checkout-initializing routes before side effects. This PHP catalogue uses no Store API by default, so broader denial is acceptable only after documenting that dependency audit. If a read-only discovery route is later needed, explicitly allowlist it after review. Do not block authenticated WooCommerce administrative wc/v3 product management or unrelated WordPress REST routes. Block legacy wc-ajax add_to_cart and checkout processing before they mutate state. Retain server-side purchasability and validation guards as defence in depth.

Audit all current installed WooCommerce entry paths and extensions. Store API batching, alternative route versions, variation requests, direct form POST, add-to-cart query parameters and order-pay links must not bypass the guard. Do not rely on an absent price alone. Do not create a cart, draft checkout order, payment attempt or email when an anonymous user probes checkout. A controlled admin can still maintain products.

Hide price HTML, quantity selectors, cart fragments, mini cart, account links, purchase blocks, commerce emails and sales schema from the public catalogue experience. Remove unnecessary commerce assets from catalogue pages after testing dependencies. Do not uninstall WooCommerce or rewrite its data tables to achieve this.

Future activation requires an explicit controlled change, real sellable SKUs, fulfilment decisions, taxes, shipping, payment setup, policies and a purchase test suite. Showcase products remain non purchasable unless deliberately converted. The switch is not a casual design setting for an editor to toggle.

## 15 API and admin responsibilities

| Surface | Access and contract |
| --- | --- |
| GET /cic/v1/locations | Public limited search; validated query; no personal data or internal review notes |
| POST /cic/v1/coverage | Public limited postcode and city check; status plus explanation, never a date reservation |
| CF7 submission transport | Quote and Contact forms only; supported CF7 integration with coverage validation, spam protection, Flamingo storage and retry control; no parallel CIC submission route |
| Design settings | Admin capability, nonce, allowlist, preview and export/import |
| City import and editing | Specific manage_locations capability; preview and audited updates |
| Leads and exports | Specific manage_cic_enquiries capability; authenticated access and minimal data exposure |
| Commerce mode | Restricted configuration or deploy permission; fail closed |

Anonymous WordPress nonces are not an anti-spam system or proof of identity. Use layered request limits, honeypot, timing and payload checks, optional reviewed CAPTCHA, and safe acknowledgement limits to prevent email abuse. Handle cached or expired form tokens without losing entered data. Protect admin operations using both permissions and nonces.

Use prepared SQL, escaped output, safe redirects, allowlisted URLs and bounded inputs. Sanitize SVGs or bundle reviewed static icons; do not allow arbitrary public SVG uploads. API error messages must not reveal credentials, table structure or personal information. Credentials remain in environment or supported secure configuration, not source code or the handoff pack.

## 16 Components and responsive acceptance

Implement real header and footer menus editable in WordPress. The default navigation is Our Treats, Events, How It Works, About and Service Areas, followed by Get a Quote. How It Works can link to the homepage anchor. Quote pages may use the approved reduced header. Global navigation does not change accidentally between mockups.

Provide a single Header and Footer admin area using native settings and WordPress menus, not ACF Pro Options Pages. These settings apply to standard pages, products, events, archives and virtual cities.

| Shared area | Editable controls |
| --- | --- |
| Top header row | Enabled state, announcement/contact links, social links, semantic colours and desktop/mobile visibility |
| Main header row | Desktop/mobile logos, native primary menu, global call action and quote CTA, configurable sticky state |
| Bottom header row | Independent enabled state, secondary native menu, colours and desktop/mobile visibility |
| Mobile navigation | Accessible toggle/drawer, submenus, close/Escape/focus return and call/quote actions |
| Footer | Logo, intro, supported column layouts, native menus, contact/social links, policy links, copyright and row visibility |

Use the approved navigation as initial content. Sticky header is configurable and enabled as a provisional default; avoid obscuring anchors, focused fields or the WordPress admin toolbar. Test scroll offsets and header height at each breakpoint. Footer copyright year may update automatically. Separate public contact fields from operator notification recipients. Do not seed empty social URLs or display invented profiles. Exact top/bottom bar copy may use editable placeholders on staging until supplied.

Desktop Events uses two cards per row; mobile stacks one per row. Treat catalogue rows alternate imagery on desktop and use a consistent reading order on mobile. Full explanatory paragraphs must remain readable. Quote fields stack on mobile, the stepper uses compact labels and every action remains reachable without a fixed overlay hiding fields.

Maintain the bright Birthday sky, mint and lilac world; the Wedding ivory, champagne and romantic serif world; the final Anniversary and Corporate moods; and distinct treat stories. On desktop city and hub maps can sit on the right; mobile maps follow search and city controls. County directories become accordions. All content remains available without hover.

Keyboard navigation, visible focus, labels, error summary, field-level errors, semantic headings, accessible accordions, useful alt text and minimum 44 px touch targets are acceptance requirements. Announce quote step changes and focus the new step heading or first invalid field appropriately. Reduced motion disables nonessential animation. Confetti is brief, decorative and never appears over the form or wedding page.

Use responsive image sizes and local font subsets. Prioritise the hero asset and avoid lazy-loading the actual LCP image. Lazy-load below-fold images and defer optional map interaction. Load WooCommerce cart and checkout scripts only when genuinely needed in a future commerce mode. Do not preload every image or font.

## 17 Tracking and lead attribution

Track meaningful interactions through one analytics installation: hero_plan_started, quote_step_view, quote_step_completed, quote_validation_error, quote_submitted, call_click, location_search and coverage_result. Use event keys, treat keys, template type and reviewed coverage status as nonpersonal parameters. quote_submitted fires only after the server confirms one persisted Flamingo enquiry, once per accepted submission key. CF7 wpcf7submit fires for multiple outcomes and is not a conversion signal; wpcf7mailsent proves a mail outcome, not our storage result. Do not expose message IDs as unauthenticated record access tokens.

Record first and latest permitted source context for the lead, including originating template, city key, event and treat selection. Capture marketing click identifiers only under the approved tracking and privacy configuration. Do not send name, email, phone, venue address, free text or dietary details to analytics, pixels or URL parameters.

Do not measure every form view as a lead, or every refresh of the confirmation page as a conversion. UTM links must not create indexable duplicates. Staging analytics and live customer emails are disabled by default. Call tracking numbers must not replace the verified number without a deliberate business configuration.

## 18 Build sequence and acceptance gates

The prompt pack implements the following sequence. Use one phase at a time, or the supplied controlled sequence prompt. Keep BUILD_STATUS.md current and record real tests, not claimed success without output.

| Phase | Deliverable and required gate |
| --- | --- |
| 00 | Read pack and inspect environment; document versions, existing code, routes and dependency decisions |
| 01 | Theme, core plugin and MU guard; activation works, data persists on theme switch, anonymous purchase paths fail |
| 02 | Design System admin and style preview; a global change propagates, local overrides remain scoped and reset correctly |
| 03 | Product and Events models; seed four of each, edit without source changes, relationships render consistently |
| 04 | Global three-row header/footer and homepage components; approved structure, context starter and mobile navigation |
| 05 | Location schema, importer, search and virtual routes; exact ZIP cases, collisions, canonicals and noindex states verified |
| 06 | Quote wizard, lead admin and notifications; retry, failure, privacy and follow-up cases pass in mail capture |
| 07 | Four event and four treat detail compositions; correct moods and real editable content, screenshot comparisons |
| 08 | Catalogue archive, Events overview, hubs, About, Contact, policies and 404; complete navigation and forms |
| 09 | Cross-device visual, accessibility and performance review; reported differences corrected or explicitly recorded |
| 10 | Release candidate and operational guide; backup and rollback rehearsed on staging, no automatic deployment |

Before building the remaining page catalogue, complete a vertical slice using one product, one event, one city, global settings/header/footer and the full CF7-Flamingo quote flow. Phase 03 establishes the four record identities; fully compose only one product/event until this gate passes. Phase 07 expands the approved slice to remaining compositions. If city source code is pending, use a local adapter fixture and mark historical integration unverified.

Start visual verification with the homepage before mass building pages. At 390, 768 and 1440 px, compare the same viewport and representative full-page screenshots to the approved references. Compare layout, spacing, hierarchy, colour roles, imagery proportions, page mood and interaction states. Correct approved copy and known image defects before treating a visual difference as an error.

## 19 Required test cases

- Changing the primary action colour updates every inheriting CTA, including virtual cities and the quote wizard, without editing their records. A deliberate local override changes only its own element; resetting restores inheritance.
- Changing global heading/body families, scales and shared colours updates all occasion, product, city and form templates. Central Wedding gold/Birthday accent token edits propagate without page edits. Only deliberate visible overrides remain local and reset correctly.
- All four products are editable WooCommerce records. Catalogue mode blocks query, form, AJAX, variation, REST, batch and checkout entry paths even after a theme switch. Product administration still works. No new customer order is created during bypass tests.
- Imports preserve ZIP leading zeros, reject malformed values, deduplicate, preview changes and can be repeated without duplication. Unknown and shared ZIPs do not become confirmed coverage.
- Pasadena resolves once to its canonical route. Invalid city returns 404. A draft is not published. Noindex cities are absent from sitemaps. Query searches are not new landing pages.
- Homepage, city, event and product actions preserve explicit selections. Normal browsing does not silently change them. An invalid product key is rejected or cleared with a helpful message.
- Date not set yet works; a date-only value does not shift across timezones. Other event works. Not sure yet and product selections are mutually exclusive. Phone preference requires a valid phone.
- Duplicate clicks and retries produce one lead and one acknowledgement. Validation failure preserves values. Mail failure leaves one stored Flamingo enquiry with retry state. Refreshing confirmation does not fire another lead conversion.
- Public REST, search, sitemap, source, cache and exports reveal no lead data. Dietary and contact information do not enter analytics or URLs. Staging sends no real messages.
- Contact messages save and notify through the approved mail configuration. Both displayed call number and tel URI match the global admin contact value.

## 20 Launch inputs and operational handoff

Development can proceed with reviewed fixtures. Launch needs the approved ZIP dataset and shared-code decisions, real event and product photos, licensed logo and font assets, client-reviewed product options and ingredients, approved policy copy, sender-domain mail setup, reminder settings, privacy and tracking configuration, hosting access and a backup destination.

The client supplied the Los Angeles County ZIP reference at https://file.lacounty.gov/SDSInter/lac/1031552_MasterZipCodes.pdf and separate Ventura and Santa Barbara lists in email screenshots. Treat those as source inputs to reconcile, not an automatically enabled coverage feed. Do not import unrelated customer invoice details from the screenshots into seed data.

The commercial service minimum has not been replaced by this design decision. It is simply not displayed or used as an undisclosed automated rejection rule. Staff can discuss commercial requirements during manual quoting. Do not invent prices or operational terms in seed content.

The operational handoff must show an editor how to change global styles, use and reset an override, add an event, edit a product story, review city content, import coverage safely, manage an enquiry, retry a failed notification and close a lead. Include uninstall and data retention behaviour, cron and mail checks, and recovery instructions.

The supplied instructions describe a build, not a live implementation. Do not claim that hosting, DNS, mail, databases, plugins or tests have been configured until there is direct evidence from the actual environment. Record unresolved environment inputs without blocking independent local work.

## 20A Confirmed inputs and pending release configuration

No paid plugin licence is available or required. Confirmed form stack is CF7 plus Flamingo. Both Yan and Web Karma need controlled enquiry access. Initial notification recipient is anuranjan.verma25@gmail.com and is editable in admin. Global fonts and colour tokens propagate everywhere unless explicitly overridden. Header/footer controls follow section 16. Product offerings follow approved mockups, subject to factual client asset/option review. Confirmed unsupported areas receive a service-area error and cannot submit a quote for that location. Catalogue-only launch is confirmed.

Pending inputs: actual city template/rewrite function and schema, final form-field review, approved coverage reconciliation, hosting/staging details, verified mail sender, reminder timing, account emails for Yan/team, deployment owner and production assets/policies. Existing form fields are implementation defaults until reviewed. Do not treat unknown inputs as permission to invent business rules.

Deploy version-controlled code and specifically approved configuration changes. Do not push a staging database over live enquiries or edited content. Seed commands must upsert stable identities without overwriting human edits; reset requires a separate deliberate action. Database migrations are versioned and non-destructive by default. Document backup, rollback limits and restoration of geography, editorial content, Flamingo messages and workflow metadata.

## 21 Design reference precedence

See designs/manifest.json and DESIGN_REFERENCE_INDEX.md. Birthday, Wedding, Anniversary and Corporate occasion designs are final reference selections. The final logo is the raspberry Cater version. Use the updated Events overview with Anniversary.

Homepage desktop and the original homepage mobile sheet contain obsolete minimum-order copy; remove it and include Anniversary in the event selection and enquiry choices. All mockups are raster concept images. Correct misspellings, unsupported registered trademark symbols, inconsistent navigation, artificial packaging and example contact placeholders. The earlier catalogue mockup contains a mislabeled Frozen Yogurt detail heading; use Let's discuss. Do not use Yan's business number as a customer's phone-field placeholder.

Mobile boards are representative layouts, not complete specifications of every scroll section or every wizard state. Derive missing breakpoints from accessible responsive components and test the entire page. Never deliver a mockup PNG as the actual page or as an image containing all headings and controls.

## 22 Source notes

The technical choices above combine project requirements with the following primary documentation, checked on 18 October 2026. Read the installed source where APIs differ; this is not a guarantee of compatibility with an uninspected environment.

- WP1 WordPress Registering Custom Post Types https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/
- WP2 WordPress Global Settings and Styles https://developer.wordpress.org/themes/global-settings-and-styles/
- WP3 WordPress theme JSON data filter https://developer.wordpress.org/reference/hooks/wp_theme_json_data_theme/
- WP4 WordPress register post meta https://developer.wordpress.org/reference/functions/register_post_meta/
- WP5 WordPress Creating Tables with Plugins https://developer.wordpress.org/plugins/creating-tables-with-plugins/
- WC1 WooCommerce Cart API https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/cart/
- WC2 WooCommerce Checkout API https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/checkout/
- WC3 WooCommerce Product Code Reference https://woocommerce.github.io/code-reference/classes/WC-Product.html
- CC1 Claude Code Best Practices https://code.claude.com/docs/en/best-practices
- CC2 Claude Code Project Memory https://code.claude.com/docs/en/memory
- CF1 Contact Form 7 message storage https://contactform7.com/save-submitted-messages-with-flamingo/
- CF2 Contact Form 7 DOM events https://contactform7.com/dom-events/
- ACF1 ACF Pro features (excluded from baseline) https://www.advancedcustomfields.com/pro/
- G1 Google Search Spam Policies https://developers.google.com/search/docs/essentials/spam-policies

The original Cater Ice Cream Brand Guide supplies base colour and typography values. Later accepted occasion designs and the instructions in this pack extend it. Historical Flowers and Party Characters materials are not a schema contract; adapt actual supplied code rather than assuming it matches a remembered project.
