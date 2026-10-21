# Cater Ice Cream: release candidate 1 (rc1)

> **OUTDATED (2026-10-21):** the rc1 package (code commit `23935f5`) predates the refinements made on 2026-10-21 (location labels, MM/DD/YYYY date picker, Site Icon, event-field icon, California map previews, desktop compact sticky header). Do not deploy it; regenerate the release candidate with `dev/tools/release/release.sh` once the refinements are reviewed and committed. A notice file sits beside and inside the package (`OUTDATED-rc1.txt`, `OUTDATED.txt`).


This is a **release candidate for staging review**. It is **not a launch-ready build**: several business inputs and hosting checks are still outstanding (see `LAUNCH_CHECKLIST.md`). Nothing in this package deploys itself, changes DNS, enables search indexing, publishes draft policies, turns on reminders or sends email.

## Documents

| File | Purpose |
| --- | --- |
| `RELEASE_NOTES.md` | Exact commit, dependency versions, completed checks, known limitations |
| `RELEASE_PACKAGE.md` | What the package contains, what is excluded, how the database content and settings migrate |
| `DEPLOYMENT.md` | Step-by-step move from Laragon to a staging host (custom tables, media, configuration, URL replacement, rewrite rules, cron, mail) |
| `BACKUP_AND_ROLLBACK.md` | Backups, restore, rollback limits and the restore drill (always on a disposable copy) |
| `ADMIN_GUIDE.md` | Everyday editing: content, treats, events, Design System, header/footer, Service Areas, enquiries |
| `LAUNCH_CHECKLIST.md` | Remaining business inputs, technical setup and checks that must run on the hosting environment |

## Package layout (top level)

```
cater-ice-cream-rc1/
  README.md          this file
  VERSION.json       release id, Git commits, tested versions
  INVENTORY.txt      every file: path, size in bytes, SHA-256
  SHA256SUMS         checksums (sha256sum -c SHA256SUMS)
  code/wp-content/   theme, core plugin and the two must-use plugins
  media/uploads/     the media library files referenced by the database
  database/          sanitised SQL dump (no enquiries, contacts, sessions or credentials)
  docs/              these documents
```

Verify the package before use:

```bash
cd cater-ice-cream-rc1 && sha256sum -c SHA256SUMS
```

Treat the package as private: although it contains no credentials or enquiry data, the database holds unpublished drafts and site configuration.
