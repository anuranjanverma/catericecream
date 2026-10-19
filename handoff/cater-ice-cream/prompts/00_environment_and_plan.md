# Prompt 00 Environment and plan

Act as the senior WordPress engineer for Cater Ice Cream. Read the handoff CLAUDE.md, docs/BUILD_SPECIFICATION.md, docs/DESIGN_REFERENCE_INDEX.md and designs/manifest.json. Locate the actual WordPress root, repository and local or staging environment. Inspect existing changes, WordPress/WooCommerce/PHP/database versions, active plugins, theme, permalinks, SEO, mail and scheduled-job configuration. Do not reveal credentials.

Do not start the implementation yet. Create ENVIRONMENT.md and BUILD_STATUS.md with the existing state, architectural decisions, file ownership, risks, dependencies and a phase plan matching this pack. Confirm custom hybrid PHP theme, companion plugin, standalone MU purchase guard, native editing, Woo products, Events CPT and database city routes. Explain any genuine incompatibility. Do not choose a page builder or paid dependency automatically.

Inspect whether an existing city repository or schema is actually supplied. If absent, plan the documented local tables and adapter contract; do not connect to another site by assumption. Identify which launch inputs are still needed while allowing independent local development. Return the plan, confirmed versions and next phase, without claiming any unrun test. No live deployment, paid account setup or external messages.

## Version 1.1 acceptance additions

Confirm free-only dependencies, CF7/Flamingo versions and their storage/mail hooks. Record city-template code as pending if absent; no competing production schema. Record unresolved form fields, hosting, reminder timing and deployment owner.
