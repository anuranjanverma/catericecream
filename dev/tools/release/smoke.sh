#!/usr/bin/env bash
# HTTP smoke checks for a site deployed from the release package (staging rehearsal or staging).
# Read-only: GET requests plus blocked purchase attempts that must create nothing.
#   bash dev/tools/release/smoke.sh http://127.0.0.1:8090 [product-id]
# Expects a staging copy: search engines discouraged, no fixture coverage (city pages 404).
set -u
BASE="${1:?base URL}"
PRODUCT="${2:-}"
UA='cic-release-smoke/1.0'
fails=0
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

pass() { echo "PASS $1"; }
fail() { echo "FAIL $1"; fails=$((fails + 1)); }
code() { curl -s -k -o "$TMP/body" -D "$TMP/head" -w '%{http_code}' -A "$UA" "$@"; }

echo "== Pages ($BASE)"
for path in / /events/ /events/birthday/ /events/wedding/ /events/anniversary/ /events/corporate/ \
	/treats/ /treats/dippin-dots/ /treats/dipn-bananas/ /treats/classic-ice-cream/ /treats/frozen-yogurt/ \
	/service-areas/ /service-areas/california/ /about/ /contact/ /get-a-quote/; do
	c=$(code "$BASE$path")
	if [ "$c" = 200 ] && ! grep -q 'catericecream\.test' "$TMP/body"; then pass "$path 200, no local URL"; else fail "$path -> $c"; fi
done
for path in /no-such-page-release/ /service-areas/california/pasadena/; do
	c=$(code "$BASE$path")
	[ "$c" = 404 ] && pass "$path 404 (no page / no approved coverage)" || fail "$path -> $c (expected 404)"
done

echo "== Indexing and metadata"
code "$BASE/" >/dev/null
grep -qiE "<meta name=['\"]robots['\"] content=['\"][^'\"]*noindex" "$TMP/body" && pass "home carries robots noindex (staging)" || fail "home robots noindex missing"
grep -q '<title>' "$TMP/body" && pass "home has a <title>" || fail "home <title> missing"
grep -q 'href="tel:' "$TMP/body" && pass "home has a tel: link" || fail "home tel link missing"
if grep -qE 'woocommerce(\.min)?\.js|sourcebuster|order-attribution|add_to_cart_button' "$TMP/body"; then fail "commerce scripts/controls on home"; else pass "no commerce scripts or controls on home"; fi
c=$(code "$BASE/wp-sitemap.xml")
[ "$c" != 200 ] && pass "core sitemap disabled while search engines are discouraged ($c)" || fail "sitemap served on a discouraged site"
c=$(code "$BASE/robots.txt")
[ "$c" = 200 ] && grep -qi 'Disallow: /' "$TMP/body" && pass "robots.txt disallows crawling" || fail "robots.txt -> $c"

echo "== Forms"
code "$BASE/get-a-quote/" >/dev/null
grep -q 'wpcf7' "$TMP/body" && grep -q 'name="event"' "$TMP/body" && pass "quote page renders the CF7 quote form" || fail "quote form missing"
code "$BASE/contact/" >/dev/null
grep -q 'wpcf7' "$TMP/body" && pass "contact page renders the CF7 contact form" || fail "contact form missing"

echo "== Catalogue protection"
if [ -n "$PRODUCT" ]; then
	c=$(code "$BASE/?add-to-cart=$PRODUCT")
	if grep -qi 'woocommerce_items_in_cart\|woocommerce_cart_hash' "$TMP/head"; then fail "add-to-cart URL set a cart cookie"; else pass "add-to-cart URL creates no cart ($c)"; fi
fi
c=$(code -X POST -H 'Content-Type: application/json' -d '{"id":1,"quantity":1}' "$BASE/wp-json/wc/store/v1/cart/add-item")
[ "$c" -ge 400 ] && pass "Store API add-item refused ($c)" || fail "Store API add-item -> $c"
c=$(code -X POST -H 'Content-Type: application/json' -d '{}' "$BASE/wp-json/wc/store/v1/checkout")
[ "$c" -ge 400 ] && pass "Store API checkout refused ($c)" || fail "Store API checkout -> $c"
c=$(code "$BASE/wp-json/")
if grep -qi '"flamingo' "$TMP/body"; then fail "Flamingo namespace exposed in REST index"; else pass "no Flamingo/enquiry namespace in the REST index ($c)"; fi

echo "== Assets"
c=$(code "$BASE/wp-content/themes/cater-ice-cream/assets/fonts/dm-sans/dmsans-roman-latin.woff2")
[ "$c" = 200 ] && pass "bundled font served" || fail "font -> $c"
img=$(curl -s -k -A "$UA" "$BASE/" | grep -oE '/wp-content/uploads/[^"'"'"' ]+\.webp' | head -1)
if [ -n "$img" ]; then c=$(code "$BASE$img"); [ "$c" = 200 ] && pass "uploaded image served ($img)" || fail "image $img -> $c"; else fail "no uploaded image referenced on home"; fi

echo
if [ "$fails" -eq 0 ]; then echo "SMOKE OK"; else echo "SMOKE FAILED: $fails"; exit 1; fi
