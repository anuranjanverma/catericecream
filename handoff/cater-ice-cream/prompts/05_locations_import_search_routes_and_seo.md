# Prompt 05 Locations import search routes and SEO

Implement sections 9 to 11. Create versioned tables using the configured prefix, a repository/adapter boundary, safe CSV import with dry run and deterministic upsert, reviewed coverage states and city content overrides. Import only reviewed fixtures initially; geography rows do not become served locations automatically. Preserve ZIP strings and handle shared city/ZIP mappings and Compton exclusions.

Build indexed autocomplete, coverage evaluation, county directories, state hub and virtual city routes. Use the approved Pasadena and Service Areas designs. Search returns covered, outside_area, manual_review, unknown or invalid without claiming a date is available. Unknown and shared ZIPs must not be falsely confirmed. The actual source data defines points; decorative mockup maps are not boundary datasets.

Integrate canonical, metadata, breadcrumbs, noindex and city sitemap behaviour through one SEO adapter for the installed plugin or core provider. Resolve normal canonical city routes, aliases, unknown slugs and unpublished rows. No city-event-treat permutation routes. Test 404 status, duplicate imports, prepared lookup queries, prefix indexes, sitemap exclusion and correct cache invalidation. Produce a mapping report if adapting historical code; do not invent its schema.

## Version 1.1 acceptance additions

Audit owner-supplied city template/rewrite function when available; use provisional local adapters meanwhile. Implement city preview/revisions/slug redirects using existing workflow or private editorial records. Explicit exclusions win. Confirmed outside-area errors block quote progression and server acceptance; unknown/shared locations require clarification. Never flush rewrite rules on every request.
