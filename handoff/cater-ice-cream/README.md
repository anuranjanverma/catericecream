Version 1.1: Read docs/REVISION_NOTES.md and the revised specification before implementation.

# Cater Ice Cream implementation handoff

This is a developer specification and Claude Code starter pack, not an installed WordPress theme or plugin.

## Start here

1. Extract this folder to `handoff/cater-ice-cream/` inside the development repository, or keep it alongside the repository and tell Claude its actual path.
2. Merge `CLAUDE.md` into the repository's existing project instructions. Keep existing useful rules and resolve conflicts rather than overwriting them.
3. Read `docs/BUILD_SPECIFICATION.md` and `docs/DESIGN_REFERENCE_INDEX.md`. The DOCX is a human-readable edition of the specification; Markdown is the source for Claude.
4. Open Claude Code from the actual development repository. Give it `prompts/00_environment_and_plan.md` first.
5. Run phases 01 through 10 in order. Each prompt defines its outputs and verification. Use the controlled sequence prompt only if you want Claude to continue through multiple phases independently on local or staging.

## First message to Claude Code

Read the Cater Ice Cream handoff at `handoff/cater-ice-cream/`. Merge its CLAUDE.md rules with the existing project instructions without losing unrelated rules. Read the build specification, reference index and manifest. Then execute only `prompts/00_environment_and_plan.md`. Inspect the actual environment and report the plan and dependencies. Do not deploy, install paid tools or send real emails.

## What is included

- Developer specification in Markdown and Word format.
- Concise project rules for CLAUDE.md.
- Numbered phase prompts, a controlled sequence prompt and a visual correction prompt.
- Named PNG visual references and a manifest recording their precedence and known corrections.
- A visual reference index, admin acceptance checklist and sample data contracts.

## Important reference corrections

The older homepage and mobile homepage sheets contain obsolete minimum-order text. Remove all public pricing/minimum-order references during implementation. Add Anniversary to homepage event choices. Birthday, Wedding, Anniversary and Corporate occasion compositions are final selections. The updated Events overview is the current reference.

Generated images are composition guides. They contain illustrative people, equipment, products, packaging and map graphics. Replace them with approved assets and actual geographic data before launch. Do not copy text typos, unsupported registered marks, fictional customer details or decorative map coordinates.

The policy board specifies layout only. Legal wording, operational policies, production coverage, shared ZIP decisions and real product availability are launch inputs. No production city database credentials or historical project schema are included; use the documented adapter boundary when actual code is supplied.

## Review workflow

Do not ask Claude for a perfect whole website in one instruction. Review the design system and homepage first. Then build the data and quote flow, apply the approved occasion/treat compositions, and verify the actual pages on desktop and mobile. Keep real evidence and remaining work in BUILD_STATUS.md.

Use the source links in the specification to confirm APIs for the installed versions. A passing screenshot alone does not prove that form storage, admin settings, coverage or catalogue protections work.
