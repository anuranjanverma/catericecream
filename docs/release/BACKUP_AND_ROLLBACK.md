# Backup, restore and rollback

## Golden rules

1. **Back up before every deployment, migration, import or settings change that matters.** Take both the database and the files.
2. **Rehearse restores only on a separate, disposable installation with its own database.** Never restore over the working or live site to "test" a backup.
3. **Never push a staging database over production** or over any site that holds real enquiries or edited content.
4. Keep backups **outside the web root**, encrypted at rest when off-site, and treat them as confidential. They contain enquiries (personal data) and `wp-config.php` credentials.
5. Keep `wp-config.php` (database credentials, **salts**) with the backup set but stored securely. Enquiry submission keys are hashed with the site salts. Restoring with different salts is safe for stored enquiries, but in-flight retry protection for submissions made in the last few minutes is lost.

## What a complete backup contains

| Part | How | Why |
| --- | --- | --- |
| Database (all tables with the site prefix) | `wp db export backup.sql --single-transaction` | Content, settings, Design System, pairings, location tables, Flamingo enquiries and their `_cic_*` workflow meta, `wp_cic_submissions`, scheduled jobs |
| `wp-content/uploads/` | archive the folder | Media files. Includes the private `wpcf7_uploads/` folder |
| Project code | `wp-content/themes/cater-ice-cream`, `wp-content/plugins/cater-ice-cream-core`, `wp-content/mu-plugins/` | Also reproducible from Git and the release package; back it up anyway for a quick rollback |
| `wp-config.php`, `.htaccess` (or the Nginx site config) | copy | Per-environment configuration and secrets |
| Checksums | `sha256sum * > SHA256SUMS` | Proves the backup is complete and unchanged |

Third-party plugins and WordPress core can be reinstalled at the pinned versions (`RELEASE_NOTES.md`). Including them in a file backup makes restores faster.

### Example (host) [host]

```bash
TS=$(date +%Y%m%d-%H%M%S); B=/home/deploy/backups/$TS; mkdir -p "$B"
cd /var/www/staging
wp db export "$B/db.sql" --single-transaction
tar -czf "$B/files.tar.gz" wp-content/uploads wp-content/themes/cater-ice-cream wp-content/plugins/cater-ice-cream-core wp-content/mu-plugins
cp wp-config.php .htaccess "$B/" 2>/dev/null
( cd "$B" && sha256sum * > SHA256SUMS )
```

On the host, schedule a daily database backup and a weekly file backup, with copies to a separate destination (off the server). Retention follows the owner's policy and the privacy policy. **Backup destination and schedule are pending** (no host yet).

### Locally (Laragon)

Phase backups live in `C:\laragon\backup\catericecream\<label>-<timestamp>\`. The rc1 backup is `pre-phase10-20261020-231042\` (database dump, `wp-config.php`, `.htaccess`, project files and uploads as `project-files.tar`, SHA256SUMS). Never copy local backups into the release package or a web folder.

## Restore (full site)

1. Put the site into maintenance (for example a host maintenance page) so no new enquiries arrive mid-restore.
2. Verify the backup: `cd backup && sha256sum -c SHA256SUMS`.
3. Take a fresh backup of the current state first, so the restore can itself be undone.
4. Restore the files: extract the archive over `wp-content/` (theme, core plugin, MU plugins, uploads).
5. Restore the database: `wp db import db.sql`. The dump contains `DROP TABLE IF EXISTS`, so the tables are replaced.
6. If the domain differs from the backup's domain, run the three `search-replace` passes from `DEPLOYMENT.md` step 6.
7. Run `wp rewrite flush`, `wp cache flush` and `wp transient delete --all`.
8. Verify with `verify-deploy.php` and `smoke.sh` (`DEPLOYMENT.md` step 11), then `wp cic enquiries status` and a look at **Enquiries** in wp-admin.
9. Lift maintenance.

## Restoring only part of the data

| Need | Approach |
| --- | --- |
| One enquiry deleted by mistake | Flamingo's trash first (*Flamingo → Inbound Messages → Trash*). If it was permanently deleted, restore the full backup into a **disposable** copy, export that message's `wp_posts` and `wp_postmeta` rows (including all `_cic_*` keys) and its `wp_cic_submissions` row, and import them into the live database by hand. Use a developer and check IDs first |
| Location data damaged by an import | Restore the five `wp_cic_*` location tables from a backup taken just before the import, then bump the cache: `wp eval 'CIC\Locations\Tables::bump_cache();'`. Never lower `cic_locations_cache_version` |
| Design System change regretted | Design System has **Export / import**. Export before big changes; import the saved file to roll back. Otherwise reset the individual fields |
| Page content | WordPress revisions (*Revisions* in the editor). City content records are revisioned too |
| Pairing copy | Edit it again in the treat's *Occasion pairings* box. The full-restore route above also works |

## Rolling back a release

| What went wrong | Rollback | Limits |
| --- | --- | --- |
| New code breaks pages, no database migration ran | Put back the previous `themes/cater-ice-cream`, `plugins/cater-ice-cream-core` and `mu-plugins` from the backup or the previous package, then `wp cache flush` | None. Data is untouched |
| New code ran a schema migration | Restore code **and** the pre-deployment database backup | Any enquiries received after the backup would be lost. Export them first (*Enquiries → Export CSV* and a database export of the Flamingo rows) and re-enter them after the rollback. Migrations are additive (new tables or columns), so older code usually keeps working with a newer schema. Check with a developer before restoring the database |
| Bad coverage import | Restore the location tables only (see above) | Manual coverage decisions made after the backup must be redone |
| Wrong settings | Restore the setting (Design System import, or re-enter it) | — |

Plugin uninstall is **not** a rollback. Deactivating or deleting the core plugin keeps all data (`uninstall.php` deliberately removes nothing), and the catalogue guard must stay in `mu-plugins` at all times.

## Restore drill (rehearsal)

**Always on a separate disposable installation and database. Never overwrite the working site.**

The rc1 drill was performed locally on 2026-10-20 with `bash dev/tools/release/release.sh restore-drill`. The disposable install was at `C:\laragon\release\catericecream\work\site`, with database `cic_drill` and environment `staging`. The tools refuse to run unless the disposable configuration is loaded and the database name starts with `cic_`.

1. **Live-like state.** A synthetic enquiry was created with `@example.test` data and no mail. It had status *Contacted*, a follow-up date, a private note and a coordination row, and was fingerprinted: SHA-256 over the message, its 26 meta rows (including `_cic_type`, `_cic_context`, `_cic_source`, `_cic_status`, `_cic_follow_up`, `_cic_notes`) and its `wp_cic_submissions` row. Signatures were also taken of the pairings, products and design settings, and of one media file.
2. **Backup.** Database export plus a file archive, with SHA256SUMS verified.
3. **Incident.** The enquiry was deleted permanently, a pairing deleted, the design settings changed, a media file deleted, and a broken theme deployed, which made the home page return HTTP 500.
4. **Restore.** Files first, then the database.
5. **Result: PASS.** The enquiry fingerprint, pairing/product/design signatures and media checksum were identical to step 1, and the home page returned HTTP 200. Afterwards, deployment checks passed 44/44 and smoke checks 33/33.

The disposable install and databases were deleted afterwards. The working site's content tables were unchanged (checksums compared before and after).

**Still required on the real host [host]:** repeat this drill with the host's own backup tool and destination, on a staging copy, before launch. Then record the date, the backup used, the time taken and the result here.
