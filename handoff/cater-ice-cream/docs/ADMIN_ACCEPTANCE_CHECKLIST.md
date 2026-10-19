# Admin and functional acceptance checklist

Use a local or staging copy with mail capture. Record evidence for each applicable item in BUILD_STATUS.md. This checklist specifies expected behaviour; it is not a report of tests already performed.

## Global editing
- Change the global action colour and verify homepage, all event/treat pages, archive, city, hub, contact and wizard buttons.
- Change the global body font and heading scale, reload and verify frontend/editor parity at mobile and desktop.
- Change the Birthday preset without editing Birthday content; Wedding must retain its own preset.
- Override one hero heading's colour and mobile size. Verify neighbouring headings and other pages do not change.
- Reset the override and confirm current inherited settings return, including global changes made since the override.
- Change the global phone and verify visible values and tel links in all templates.

## Content
- Edit a product introduction, gallery, feeling and event pairing without changing code.
- Edit that same event page and verify the product pairing description is consistent.
- Add a new draft product/event; it must not appear in public controls. Publish it and confirm expected lists update.
- Change event section order/visibility using supported controls and confirm the approved preset remains available.
- Switch themes and verify all product, event, lead and location data remains.

## Locations
- Dry-run a reviewed CSV and inspect inserts, updates, duplicates and rejected rows before applying.
- Reimport the same CSV and verify idempotent results and no unexpected coverage activation.
- Test ZIP leading zero, ZIP+4, invalid postcode, unknown location, covered location and shared/excluded mapping.
- Verify Compton exclusion cannot be bypassed by a shared ZIP or an alias.
- Confirm a city page uses its custom content and inherits any blank fields.
- Confirm invalid routes return 404, aliases redirect once, noindex cities stay out of sitemaps and only canonical city URLs are listed.
- Confirm map points reflect reviewed data and cannot confirm dates or venue access.

## Forms and lead operations
- Start on homepage/city/event/product and verify the correct explicit context arrives at the wizard.
- Browse unrelated pages without clicking a Plan action and verify selections are not overwritten.
- Test unknown date, Other occasion, multiple treats, Not sure yet, keyboard Back and validation failure.
- Test timezone conversion near midnight and daylight-saving dates without changing the selected day.
- Submit twice, retry after network interruption and reload success; one lead and acknowledgement must result.
- Simulate mail failure and recover via the queue without creating another lead.
- View, assign, note, reschedule, close and export a lead using the proper role; a normal editor without lead capability must be denied.
- Confirm reminders are cancelled for closed/spam/deleted leads and do not duplicate after cron retries.
- Verify general Contact messages use the same safe storage and delivery foundation.

## Catalogue protection
- Confirm no price, cart, checkout, account shopping controls or public minimum-order text.
- Attempt direct add-to-cart URL, form POST, legacy AJAX, variation purchase, cart/checkout Store API, API batch and order-pay routes.
- Count orders before/after; no new draft/customer order or payment attempt may be created.
- Repeat with a different theme and with the core plugin deactivated; the standalone guard must remain effective.
- Confirm authenticated product administration still works.
- Check schema and public APIs do not announce a fabricated free sale or availability.

## Responsive and accessibility
- Compare actual 390, 768 and 1440 px screenshots with the correct reference and written corrections.
- Test the full page below the mobile reference crop, not only the hero.
- Test labels, keyboard navigation, focus visibility, accordion announcements, error summary and step focus.
- Confirm normal text contrast after any palette change, minimum touch targets and no input covered by a sticky element.
- Check reduced motion, decorative SVG semantics, photo alt text and no content requiring hover.

## Before release
- Replace illustrative assets, fabricated packaging and map artwork with approved production equivalents.
- Confirm policy content, coverage decisions, real product options and ingredients with the business owner.
- Configure mail sender, actual cron, retention and approved tracking; keep secrets out of the repository.
- Disable staging indexing and live notifications appropriately; verify production toggles deliberately on deployment.
- Back up and test restore. Prepare the release candidate and deployment instructions; deployment remains a separate action.

## Version 1.1 mandatory checks
- Confirm no ACF Pro/paid plugin API is required.
- Edit all three header rows and footer globally; test mobile drawer, sticky anchors and disabled-row spacing.
- Change global heading family/action colour across all occasion and virtual city templates; reset explicit overrides.
- CF7 saves exactly one Flamingo enquiry; no duplicate cic_lead record or competing submission route.
- Prove storage completes before notifications; simulate storage failure and saved-message/mail failure.
- Both operator roles can manage authorised enquiries; unauthorised users cannot access Flamingo messages/contact-book/export.
- Change notification recipient from admin; public business contact stays unchanged.
- Outside-area requests fail frontend and server checks; unknown/shared locations request clarification without false exclusion.
- Dynamic CF7 options validate published event/product identifiers server-side. Hidden-step errors focus correctly; no-JS form works.
- Indexed reverse product-event lookup; city preview/revisions and slug redirects work.
- Complete vertical slice before expanding all compositions.
- Release/seed replay preserves live enquiries and human edits; reminders stay off until configured.
