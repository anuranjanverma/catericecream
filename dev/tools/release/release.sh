#!/usr/bin/env bash
# Release candidate builder and DISPOSABLE staging/restore rehearsal (Phase 10). Git Bash, local only.
#
#   bash dev/tools/release/release.sh <stage> [...]
#
# Stages (run in this order; each logs to $OUT/logs/<stage>.log):
#   backup         Backup of the working site (DB dump, wp-config.php, .htaccess, project files,
#                  uploads) to C:\laragon\backup\catericecream\pre-phase10-<ts>\ + table checksums.
#                  Read-only for the working site.
#   site           Disposable WordPress install outside the web root ($OUT/work/site) with its own
#                  wp-config.php (database cic_*, environment "staging", fresh salts).
#   build-db       Imports the backup into the disposable database cic_rc_build, sanitises it,
#                  exports the package SQL, drops cic_rc_build.
#   assemble       Package folder + zip: code from CODE_COMMIT (git archive), docs from DOCS_COMMIT,
#                  media, sanitised SQL, VERSION.json, INVENTORY.txt, SHA256SUMS; hygiene checks.
#   deploy-drill   Follows DEPLOYMENT.md on the disposable install: package code/media, database
#                  cic_drill, URL replacement, rewrite flush.
#   restore-drill  Synthetic enquiry -> backup -> simulated loss/bad deploy -> restore -> compare.
#   teardown       Drops cic_drill/cic_rc_build and deletes the disposable install (package kept).
#   checksum-after Compares the working site's table checksums with the backup-stage snapshot.
#
# The HTTP checks need the disposable site served separately:
#   php -S 127.0.0.1:8090 -t "$OUT/work/site" dev/tools/release/router.php
#
# Never touches the working database except to read it. Destructive commands are limited to the
# hard-coded disposable databases and to folders under C:\laragon\release\catericecream.
set -euo pipefail

STAGE="${1:?stage}"
REPO=/c/laragon/www/catericecream
WP=/c/laragon/bin/wp-cli/wp
PHP=/c/laragon/bin/php/php-8.5.1-nts-Win32-vs17-x64/php.exe
export PATH="/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin:$PATH"
OUT=/c/laragon/release/catericecream
RC="${RC:-rc1}"
URL="${DRILL_URL:-http://127.0.0.1:8090}"
SITE="$OUT/work/site"
PKG="$OUT/cater-ice-cream-$RC"
STATE="$OUT/work/state.env"
BUILD_DB=cic_rc_build
DRILL_DB=cic_drill
TOOLS="$REPO/dev/tools/release"
win() { cygpath -m "$1"; }
mkdir -p "$OUT/logs" "$OUT/work"
exec > >(tee -a "$OUT/logs/$STAGE.log") 2>&1
echo "### $STAGE $(date '+%Y-%m-%d %H:%M:%S')"
[ -f "$STATE" ] && . "$STATE"

drill_wp() { ( cd "$SITE" && CIC_DRILL_DB="$1" "$WP" "${@:2}" ); }
checksums() {
	( cd "$REPO" && "$WP" db query "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name" --skip-column-names 2>/dev/null ) | tr -d '\r' | grep -v '^$' > "$OUT/work/tables.txt"
	( cd "$REPO" && "$WP" db query "CHECKSUM TABLE $(paste -sd, "$OUT/work/tables.txt")" --skip-column-names 2>/dev/null )
}

case "$STAGE" in
backup)
	TS="$(date +%Y%m%d-%H%M%S)"
	BACKUP="/c/laragon/backup/catericecream/pre-phase10-$TS"
	mkdir -p "$BACKUP"
	( cd "$REPO" && "$WP" db export "$(win "$BACKUP/catericecream.sql")" --single-transaction --quiet )
	cp "$REPO/wp-config.php" "$BACKUP/wp-config.php"
	[ -f "$REPO/.htaccess" ] && cp "$REPO/.htaccess" "$BACKUP/.htaccess"
	tar -C "$REPO" -cf "$BACKUP/project-files.tar" wp-content/themes/cater-ice-cream wp-content/plugins/cater-ice-cream-core wp-content/mu-plugins wp-content/uploads
	( cd "$BACKUP" && sha256sum catericecream.sql wp-config.php project-files.tar $( [ -f .htaccess ] && echo .htaccess ) > SHA256SUMS )
	checksums > "$OUT/work/checksum-before.txt"
	printf 'BACKUP=%q\nTS=%q\n' "$BACKUP" "$TS" > "$STATE"
	echo "Backup: $BACKUP ($(du -sh "$BACKUP" | cut -f1)); table checksums recorded ($(wc -l < "$OUT/work/checksum-before.txt") tables)."
	;;

site)
	rm -rf "$SITE"
	mkdir -p "$SITE/wp-content/plugins" "$SITE/wp-content/themes" "$SITE/wp-content/mu-plugins" "$SITE/wp-content/uploads"
	# WordPress core (same version as local; on a host: wp core download --version=<WP>).
	cp -r "$REPO/wp-admin" "$REPO/wp-includes" "$SITE/"
	for f in "$REPO"/*.php; do [ "$(basename "$f")" = wp-config.php ] || cp "$f" "$SITE/"; done
	cp "$REPO/wp-content/index.php" "$SITE/wp-content/"
	# Third-party plugins at the pinned versions (on a host: wp plugin install <slug> --version=<v>).
	for p in woocommerce advanced-custom-fields contact-form-7 flamingo; do cp -r "$REPO/wp-content/plugins/$p" "$SITE/wp-content/plugins/"; done
	"$PHP" "$(win "$TOOLS/make-disposable-config.php")" "$(win "$REPO/wp-config.php")" "$(win "$SITE")" "$DRILL_DB" "$URL" "$(win "$OUT/logs/disposable-debug.log")"
	echo "Disposable install: $SITE ($(du -sh "$SITE" | cut -f1))."
	;;

build-db)
	: "${BACKUP:?run backup first}"
	drill_wp "$BUILD_DB" db drop --yes 2>/dev/null || true
	drill_wp "$BUILD_DB" db create
	drill_wp "$BUILD_DB" db import "$(win "$BACKUP/catericecream.sql")"
	drill_wp "$BUILD_DB" eval-file "$(win "$TOOLS/sanitize.php")" --skip-plugins --skip-themes
	drill_wp "$BUILD_DB" db export "$(win "$OUT/work/package.sql")" --single-transaction --quiet
	drill_wp "$BUILD_DB" db drop --yes
	echo "Package SQL: $(du -h "$OUT/work/package.sql" | cut -f1). Disposable build database dropped."
	;;

assemble)
	: "${CODE_COMMIT:?set CODE_COMMIT}"
	DOCS_COMMIT="${DOCS_COMMIT:-$CODE_COMMIT}"
	# Re-assembly after teardown reuses the package's own sanitised SQL.
	[ -f "$OUT/work/package.sql" ] || cp "$PKG/database/cater-ice-cream-$RC.sql" "$OUT/work/package.sql"
	rm -rf "$PKG" "$PKG.zip"
	mkdir -p "$PKG/code" "$PKG/media/uploads" "$PKG/database" "$PKG/docs"
	git -C "$REPO" archive "$CODE_COMMIT" wp-content/themes/cater-ice-cream wp-content/plugins/cater-ice-cream-core wp-content/mu-plugins/cic-catalog-guard.php wp-content/mu-plugins/cic-staging-mail-guard.php | tar -x -C "$PKG/code"
	if git -C "$REPO" cat-file -e "$DOCS_COMMIT:docs/release" 2>/dev/null; then
		git -C "$REPO" archive "$DOCS_COMMIT" docs/release | tar -x -C "$PKG/docs" --strip-components=2
	else
		echo "WARN docs/release not in $DOCS_COMMIT yet; package built without docs (re-assemble after the docs commit)."
	fi
	export RC CODE_COMMIT_FULL="$(git -C "$REPO" rev-parse "$CODE_COMMIT")" DOCS_COMMIT_FULL="$(git -C "$REPO" rev-parse "$DOCS_COMMIT")"
	cp -r "$REPO/wp-content/uploads/2026" "$PKG/media/uploads/"
	cp "$REPO"/wp-content/uploads/woocommerce-placeholder*.webp "$PKG/media/uploads/"
	cp "$OUT/work/package.sql" "$PKG/database/cater-ice-cream-$RC.sql"
	( cd "$REPO" && "$WP" eval '
		require_once ABSPATH . "wp-admin/includes/plugin.php";
		$v = array( "release" => getenv( "RC" ), "built_at" => gmdate( "c" ), "code_commit" => getenv( "CODE_COMMIT_FULL" ), "docs_commit" => getenv( "DOCS_COMMIT_FULL" ),
			"wordpress" => get_bloginfo( "version" ), "php_tested" => PHP_VERSION, "mysql_tested" => $GLOBALS["wpdb"]->db_version(),
			"plugins" => array(), "theme" => array( "cater-ice-cream" => wp_get_theme( "cater-ice-cream" )->get( "Version" ) ),
			"core_schema_version" => (int) get_option( "cic_core_schema_version" ), "table_prefix" => $GLOBALS["wpdb"]->prefix, "charset" => $GLOBALS["wpdb"]->charset, "collate" => $GLOBALS["wpdb"]->collate );
		foreach ( array( "woocommerce/woocommerce.php", "advanced-custom-fields/acf.php", "contact-form-7/wp-contact-form-7.php", "flamingo/flamingo.php", "cater-ice-cream-core/cater-ice-cream-core.php" ) as $f ) { $v["plugins"][ dirname( $f ) ] = get_plugin_data( WP_PLUGIN_DIR . "/" . $f, false, false )["Version"]; }
		$v["mu_plugins"] = array( "cic-catalog-guard" => "1.0.0", "cic-staging-mail-guard" => "1.0.0" );
		echo wp_json_encode( $v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );' ) > "$PKG/VERSION.json"
	cp "$PKG/docs/README.md" "$PKG/README.md" 2>/dev/null || true
	# Inventory (path, bytes, sha256) and checksums.
	( cd "$PKG" && find . -type f ! -name INVENTORY.txt ! -name SHA256SUMS | sed 's#^\./##' | LC_ALL=C sort | while read -r f; do printf '%s\t%s\t%s\n' "$f" "$(stat -c %s "$f")" "$(sha256sum "$f" | cut -d' ' -f1)"; done > INVENTORY.txt )
	( cd "$PKG" && cut -f1,3 INVENTORY.txt | awk -F'\t' '{print $2"  "$1}' > SHA256SUMS )
	echo "== Hygiene"
	bad=0
	for pattern in 'wp-config*.php' '*.log' '.env*' '*.key' '*.pem' 'cic-local-mail.php' 'node_modules' '.git*' '*.bak' '*.tar'; do
		hits="$(find "$PKG" -name "$pattern" | head -3)"; if [ -n "$hits" ]; then echo "FAIL found $pattern: $hits"; bad=1; else echo "PASS no $pattern"; fi
	done
	[ "$(find "$PKG" -name '*.sql' | wc -l)" = 1 ] && echo "PASS exactly one SQL file (database/)" || { echo "FAIL unexpected SQL files"; bad=1; }
	[ -z "$(find "$PKG" -path '*/dev/*' -o -path '*/tests/*' -o -path '*/handoff/*' | head -1)" ] && echo "PASS no dev/tests/handoff folders" || { echo "FAIL test tooling present"; bad=1; }
	[ -z "$(find "$PKG" -path '*wpcf7_uploads*' -o -path '*wc-logs*' | head -1)" ] && echo "PASS no form uploads or WooCommerce logs" || { echo "FAIL private upload/log folders present"; bad=1; }
	grep -rIl -E "DB_PASSWORD|AUTH_KEY'|define\( *'DB_" "$PKG" >/dev/null && { echo "FAIL credential-like text found"; bad=1; } || echo "PASS no credential definitions"
	grep -q 'flamingo_inbound' "$PKG/database/cater-ice-cream-$RC.sql" && echo "INFO 'flamingo_inbound' appears in SQL (table/option names only expected)"
	echo "Email addresses in the package SQL (expected: specification recipient, public business email, placeholders, WordPress/plugin defaults):"
	grep -oE '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' "$PKG/database/cater-ice-cream-$RC.sql" | sort | uniq -c | sort -rn | head -20
	[ "$bad" = 0 ] || { echo "Hygiene failed"; exit 1; }
	/c/Windows/System32/tar.exe -a -c -f "$(win "$PKG.zip")" -C "$(win "$OUT")" "cater-ice-cream-$RC"
	( cd "$OUT" && sha256sum "cater-ice-cream-$RC.zip" > "cater-ice-cream-$RC.zip.sha256" )
	echo "Package: $PKG ($(du -sh "$PKG" | cut -f1), $(wc -l < "$PKG/INVENTORY.txt") files); zip $(du -h "$PKG.zip" | cut -f1)."
	;;

deploy-drill)
	: "${BACKUP:?run backup first}"
	# 1. Code and media from the package (never from the working tree).
	rm -rf "$SITE/wp-content/themes/cater-ice-cream" "$SITE/wp-content/plugins/cater-ice-cream-core" "$SITE/wp-content/mu-plugins" "$SITE/wp-content/uploads"
	cp -r "$PKG/code/wp-content/themes/cater-ice-cream" "$SITE/wp-content/themes/"
	cp -r "$PKG/code/wp-content/plugins/cater-ice-cream-core" "$SITE/wp-content/plugins/"
	cp -r "$PKG/code/wp-content/mu-plugins" "$SITE/wp-content/"
	cp -r "$PKG/media/uploads" "$SITE/wp-content/"
	# 2. Database.
	drill_wp "$DRILL_DB" db drop --yes 2>/dev/null || true
	drill_wp "$DRILL_DB" db create
	drill_wp "$DRILL_DB" db import "$(win "$PKG/database/cater-ice-cream-$RC.sql")"
	# 3. URL replacement (plain and JSON-escaped forms), rewrite rules, caches.
	drill_wp "$DRILL_DB" search-replace 'https://catericecream.test' "$URL" --all-tables-with-prefix --precise --report-changed-only
	drill_wp "$DRILL_DB" search-replace 'https:\/\/catericecream.test' "${URL//\//\\/}" --all-tables-with-prefix --precise --report-changed-only
	drill_wp "$DRILL_DB" rewrite flush
	drill_wp "$DRILL_DB" cache flush
	drill_wp "$DRILL_DB" cic enquiries status
	drill_wp "$DRILL_DB" cic locations status
	drill_wp "$DRILL_DB" eval-file "$(win "$TOOLS/verify-deploy.php")" "$URL"
	;;

restore-drill)
	D="$OUT/work/restore"
	rm -rf "$D" && mkdir -p "$D"
	echo "== 1. Live-like state: synthetic enquiry with workflow metadata"
	drill_wp "$DRILL_DB" eval-file "$(win "$TOOLS/enquiry-drill.php")" create
	before="$(drill_wp "$DRILL_DB" eval-file "$(win "$TOOLS/enquiry-drill.php")" fingerprint)"; echo "$before"
	data_sig() { drill_wp "$DRILL_DB" db query "SELECT (SELECT COUNT(*) FROM wp_cic_product_events), (SELECT COUNT(*) FROM wp_posts WHERE post_type='product' AND post_status='publish'), (SELECT MD5(option_value) FROM wp_options WHERE option_name='cic_design_settings'), (SELECT MD5(GROUP_CONCAT(description ORDER BY id)) FROM wp_cic_product_events)" --skip-column-names; }
	sig_before="$(data_sig)"; echo "data signature: $sig_before"
	media="wp-content/uploads/2026/$(ls "$SITE/wp-content/uploads/2026" | head -1)"
	media_file="$(find "$SITE/$media" -type f | head -1)"; media_hash="$(sha256sum "$media_file" | cut -d' ' -f1)"
	echo "== 2. Backup (database + files)"
	drill_wp "$DRILL_DB" db export "$(win "$D/db.sql")" --single-transaction --quiet
	tar -C "$SITE" -cf "$D/files.tar" wp-content/themes/cater-ice-cream wp-content/plugins/cater-ice-cream-core wp-content/mu-plugins wp-content/uploads
	( cd "$D" && sha256sum db.sql files.tar > SHA256SUMS && sha256sum -c SHA256SUMS )
	echo "== 3. Simulated incident: lost enquiry, deleted pairing, changed design, deleted media, broken theme"
	drill_wp "$DRILL_DB" eval-file "$(win "$TOOLS/enquiry-drill.php")" delete
	drill_wp "$DRILL_DB" db query "DELETE FROM wp_cic_product_events ORDER BY id LIMIT 1; UPDATE wp_options SET option_value = REPLACE(option_value, 'BC1F64', '000000') WHERE option_name = 'cic_design_settings';"
	rm -f "$media_file"
	echo '<?php throw new Error( "drill: broken deployment" );' > "$SITE/wp-content/themes/cater-ice-cream/functions.php"
	drill_wp "$DRILL_DB" eval-file "$(win "$TOOLS/enquiry-drill.php")" fingerprint --skip-themes
	echo "data signature after incident: $(data_sig)"
	echo "home page after incident: HTTP $(curl -s -o /dev/null -w '%{http_code}' "$URL/")"
	echo "== 4. Restore (files first, then database)"
	rm -rf "$SITE/wp-content/themes/cater-ice-cream" "$SITE/wp-content/plugins/cater-ice-cream-core" "$SITE/wp-content/mu-plugins" "$SITE/wp-content/uploads"
	tar -C "$SITE" -xf "$D/files.tar"
	drill_wp "$DRILL_DB" db import "$(win "$D/db.sql")"
	drill_wp "$DRILL_DB" cache flush
	echo "== 5. Compare"
	after="$(drill_wp "$DRILL_DB" eval-file "$(win "$TOOLS/enquiry-drill.php")" fingerprint)"; echo "$after"
	sig_after="$(data_sig)"; echo "data signature: $sig_after"
	[ "$before" = "$after" ] && echo "PASS enquiry, workflow meta and coordination row identical after restore" || { echo "FAIL enquiry fingerprint differs"; exit 1; }
	[ "$sig_before" = "$sig_after" ] && echo "PASS pairings, products and design settings identical after restore" || { echo "FAIL data signature differs"; exit 1; }
	[ "$(sha256sum "$media_file" | cut -d' ' -f1)" = "$media_hash" ] && echo "PASS media file restored byte-identical" || { echo "FAIL media file differs"; exit 1; }
	echo "home page after restore: HTTP $(curl -s -o /dev/null -w '%{http_code}' "$URL/")"
	drill_wp "$DRILL_DB" eval-file "$(win "$TOOLS/enquiry-drill.php")" delete
	;;

teardown)
	for db in "$DRILL_DB" "$BUILD_DB"; do drill_wp "$db" db drop --yes 2>/dev/null && echo "dropped $db" || echo "$db not present"; done
	rm -rf "$SITE" "$OUT/work/restore" "$OUT/work/package.sql"
	echo "Disposable install and databases removed. Package kept: $PKG(.zip)"
	;;

checksum-after)
	checksums > "$OUT/work/checksum-after.txt"
	if diff "$OUT/work/checksum-before.txt" "$OUT/work/checksum-after.txt" > "$OUT/work/checksum-diff.txt"; then
		echo "PASS working database unchanged: all $(wc -l < "$OUT/work/checksum-after.txt") table checksums identical"
	else
		echo "Changed tables (expected only for WordPress/WooCommerce runtime tables such as options transients):"
		grep '^>' "$OUT/work/checksum-diff.txt" | awk '{print $2}'
	fi
	;;

*)
	echo "Unknown stage $STAGE"; exit 2 ;;
esac
