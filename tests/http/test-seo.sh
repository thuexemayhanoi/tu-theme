#!/usr/bin/env bash
#
# HTTP SEO assertions (LOCAL servers only): one canonical / description / robots per page, hreflang on
# home and pillar pages but not on search / 404, sitemap index + children, robots.txt, a managed 301,
# JSON-LD that parses. Creates the SEO fixtures (tests/php/fixtures-seo.php) through WP-CLI first.
#
# Usage: tests/http/test-seo.sh [BASE_URL] [WP_ENV_DIR]
#   BASE_URL   default $BASE_URL or http://localhost:8080 (must be localhost/127.0.0.1)
#   WP_ENV_DIR default $WP_ENV_DIR or /home/user/wpenv
set -uo pipefail

BASE="${1:-${BASE_URL:-http://localhost:8080}}"
BASE="${BASE%/}"
ENV_DIR="${2:-${WP_ENV_DIR:-/home/user/wpenv}}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WP=(php -d memory_limit=1024M "$ENV_DIR/bin/wp-cli.phar" --allow-root --path="$ENV_DIR/wordpress")

case "$BASE" in
	http://localhost:* | http://localhost | http://127.0.0.1:* | http://127.0.0.1) ;;
	*)
		echo "Refusing to run against $BASE: local servers only." >&2
		exit 2
		;;
esac

TMP="$(mktemp -d)"
STAMP="$(date +%s)"
trap 'rm -rf "$TMP"; "${WP[@]}" eval "\MrTu\Multilang\Redirects::remove( \"/fx-seo-old-'"$STAMP"'/\" );" >/dev/null 2>&1 || true; "${WP[@]}" eval-file "$ROOT/tests/php/fixtures-seo.php" cleanup >/dev/null 2>&1 || true' EXIT

passed=0
failed=0
pass() { passed=$((passed + 1)); echo "  PASS: $1"; }
fail() { failed=$((failed + 1)); echo "  FAIL: $1"; }

STATUS=''
LOCATION=''
CTYPE=''

# fetch PATH [curl args…] → STATUS, LOCATION, CTYPE, body in $TMP/body
fetch() {
	local path="$1"
	shift
	STATUS="$(curl -s -o "$TMP/body" -D "$TMP/headers" -w '%{http_code}' "$@" "$BASE$path")"
	LOCATION="$(grep -i '^location:' "$TMP/headers" | tail -1 | cut -d' ' -f2- | tr -d '\r')"
	CTYPE="$(grep -i '^content-type:' "$TMP/headers" | tail -1 | cut -d' ' -f2- | tr -d '\r')"
}

# count REGEX → number of matching tags in the body (case-insensitive, one per match)
count() { grep -oiE -- "$1" "$TMP/body" | wc -l | tr -d ' '; }

canonicals() { count '<link[^>]+rel=["'"'"']canonical["'"'"']'; }
descriptions() { count '<meta[^>]+name=["'"'"']description["'"'"']'; }
robots() { count '<meta[^>]+name=["'"'"']robots["'"'"']'; }
hreflangs() { count '<link[^>]+hreflang=["'"'"']'; }
canonical_href() { grep -oiE '<link[^>]+rel=["'"'"']canonical["'"'"'][^>]*' "$TMP/body" | grep -oE 'href=["'"'"'][^"'"'"']*' | head -1 | cut -c7-; }

echo "== SEO HTTP tests against $BASE =="
echo "# Fixtures"
if ! "${WP[@]}" eval-file "$ROOT/tests/php/fixtures-seo.php" > "$TMP/fx" 2>"$TMP/fx.err"; then
	echo "  FAIL: fixtures-seo.php failed:"
	cat "$TMP/fx" "$TMP/fx.err"
	echo "RESULT: 0 passed, 1 failed"
	exit 1
fi
fx() { grep "^$1=" "$TMP/fx" | head -1 | cut -d= -f2-; }
PILLAR_EN="$(fx FX_SEO_PILLAR_EN_PATH)"
PILLAR_VI="$(fx FX_SEO_PILLAR_VI_PATH)"
FAQ_EN="$(fx FX_SEO_FAQ_EN_PATH)"
if [ -z "$PILLAR_EN" ] || [ -z "$FAQ_EN" ]; then
	echo "  FAIL: fixture paths missing"
	cat "$TMP/fx"
	echo "RESULT: 0 passed, 1 failed"
	exit 1
fi
"${WP[@]}" eval "\MrTu\Multilang\Redirects::add( \"/fx-seo-old-$STAMP/\", \"$PILLAR_EN\" );" >/dev/null 2>&1
MODE="$("${WP[@]}" eval 'echo mrtu_seo_mode();' 2>/dev/null | tail -1)"
pass "fixtures ready (mode: $MODE)"
if [ "$MODE" != "standalone" ]; then
	echo "  (Yoast is active in this env: head/sitemap assertions apply to standalone mode only)"
fi

# check_head PATH LANG MIN_HREFLANG
check_head() {
	local path="$1" lang="$2" min="$3"
	fetch "$path"
	if [ "$STATUS" != 200 ]; then
		fail "$path → HTTP $STATUS${LOCATION:+ → $LOCATION}"
		return
	fi
	pass "$path → 200"
	[ "$(canonicals)" = 1 ] && pass "$path: exactly one canonical" || fail "$path: $(canonicals) canonical tags"
	[ "$(descriptions)" = 1 ] && pass "$path: exactly one description" || fail "$path: $(descriptions) description tags"
	[ "$(robots)" = 1 ] && pass "$path: exactly one robots" || fail "$path: $(robots) robots tags"
	if [ "$(hreflangs)" -ge "$min" ]; then pass "$path: $(hreflangs) hreflang links (≥ $min)"; else fail "$path: $(hreflangs) hreflang links (< $min)"; fi
	grep -qiE 'hreflang=["'"'"']x-default' "$TMP/body" && pass "$path: x-default present" || fail "$path: no x-default"
	grep -qiE 'hreflang=["'"'"']vi-VN' "$TMP/body" && pass "$path: vi-VN alternate" || fail "$path: no vi-VN alternate"
	local lang_count
	lang_count="$(grep -oE '<html[^>]*' "$TMP/body" | head -1 | grep -oE ' lang="[^"]*"' | cut -d'"' -f2)"
	[ "$lang_count" = "$lang" ] && pass "$path: <html lang=\"$lang\">" || fail "$path: <html lang=\"$lang_count\">"
}

echo
echo "# Head: homes and pillar pages"
check_head / vi-VN 4
[ "$(canonical_href)" = "$BASE/" ] && pass "/: canonical = $BASE/" || fail "/: canonical = $(canonical_href)"
check_head /en/ en 4
[ "$(canonical_href)" = "$BASE/en/" ] && pass "/en/: canonical = $BASE/en/" || fail "/en/: canonical = $(canonical_href)"
check_head /de/ de 4
check_head "$PILLAR_EN" en 4
[ "$(canonical_href)" = "$BASE$PILLAR_EN" ] && pass "$PILLAR_EN: canonical = self" || fail "$PILLAR_EN: canonical = $(canonical_href)"
grep -q 'og:locale" content="en_US"' "$TMP/body" && pass "$PILLAR_EN: og:locale en_US" || fail "$PILLAR_EN: og:locale"
check_head "$PILLAR_VI" vi-VN 4
check_head "$FAQ_EN" en 4

echo
echo "# Head: search and 404"
fetch '/en/?s=xe'
[ "$STATUS" = 200 ] && pass "/en/?s=xe → 200" || fail "/en/?s=xe → $STATUS"
[ "$(hreflangs)" = 0 ] && pass "search: no hreflang" || fail "search: $(hreflangs) hreflang links"
[ "$(canonicals)" = 0 ] && pass "search: no canonical" || fail "search: $(canonicals) canonical tags"
grep -qiE '<meta[^>]+name=["'"'"']robots["'"'"'][^>]*noindex' "$TMP/body" && pass "search: robots noindex" || fail "search: robots not noindex"
[ "$(robots)" = 1 ] && pass "search: exactly one robots" || fail "search: $(robots) robots tags"
fetch "/en/no-such-page-$STAMP/"
[ "$STATUS" = 404 ] && pass "/en/no-such-page/ → 404" || fail "/en/no-such-page/ → $STATUS"
[ "$(hreflangs)" = 0 ] && pass "404: no hreflang" || fail "404: $(hreflangs) hreflang links"
[ "$(canonicals)" = 0 ] && pass "404: no canonical" || fail "404: $(canonicals) canonical tags"
grep -qiE '<meta[^>]+name=["'"'"']robots["'"'"'][^>]*noindex' "$TMP/body" && pass "404: robots noindex" || fail "404: robots not noindex"

echo
echo "# Sitemaps and robots.txt"
fetch /mrtu-sitemap-index.xml
if [ "$STATUS" = 200 ] && echo "$CTYPE" | grep -qi xml; then pass "/mrtu-sitemap-index.xml → 200 XML"; else fail "/mrtu-sitemap-index.xml → $STATUS ($CTYPE)"; fi
if php -r '$x = @simplexml_load_file( $argv[1] ); exit( $x && "sitemapindex" === $x->getName() ? 0 : 1 );' "$TMP/body"; then pass "sitemap index parses (sitemapindex)"; else fail "sitemap index does not parse"; fi
CHILDREN="$(grep -oE '<loc>[^<]+</loc>' "$TMP/body" | sed -e 's/<loc>//' -e 's#</loc>##')"
[ "$(echo "$CHILDREN" | grep -c .)" -ge 3 ] && pass "index lists $(echo "$CHILDREN" | grep -c .) language sitemaps" || fail "index lists too few children"
for child in $CHILDREN; do
	path="${child#"$BASE"}"
	fetch "$path"
	if [ "$STATUS" = 200 ] && php -r '$x = @simplexml_load_file( $argv[1] ); exit( $x && "urlset" === $x->getName() ? 0 : 1 );' "$TMP/body"; then pass "$path → 200, valid urlset"; else fail "$path → $STATUS / invalid XML"; fi
done
fetch /mrtu-sitemap-en.xml
grep -q "<loc>$BASE$PILLAR_EN</loc>" "$TMP/body" && pass "EN sitemap lists the pillar" || fail "EN sitemap lacks the pillar"
grep -q "<loc>$BASE$PILLAR_VI</loc>" "$TMP/body" && fail "EN sitemap leaks a VI page" || pass "EN sitemap has no VI pages"
grep -q 'xhtml:link rel="alternate" hreflang="vi-VN"' "$TMP/body" && pass "EN sitemap carries xhtml:link alternates" || fail "EN sitemap without alternates"
fetch /mrtu-sitemap-zz.xml
[ "$STATUS" = 404 ] && pass "/mrtu-sitemap-zz.xml → 404" || fail "/mrtu-sitemap-zz.xml → $STATUS"
fetch /robots.txt
grep -q "^Sitemap: $BASE/mrtu-sitemap-index.xml" "$TMP/body" && pass "robots.txt advertises the sitemap index" || fail "robots.txt without Sitemap line"
fetch /wp-sitemap.xml
[ "$STATUS" = 404 ] && pass "/wp-sitemap.xml → 404 (core sitemap off)" || fail "/wp-sitemap.xml → $STATUS"

echo
echo "# Redirects"
fetch "/fx-seo-old-$STAMP/"
if [ "$STATUS" = 301 ] && [ "$LOCATION" = "$BASE$PILLAR_EN" ]; then pass "/fx-seo-old-$STAMP/ → 301 $PILLAR_EN"; else fail "/fx-seo-old-$STAMP/ → $STATUS ${LOCATION:-}"; fi
fetch "/fx-seo-old-$STAMP?utm=1"
if [ "$STATUS" = 301 ] && [ "$LOCATION" = "$BASE$PILLAR_EN?utm=1" ]; then pass "query string carried over, slash-less source matched"; else fail "query string / slash variant → $STATUS ${LOCATION:-}"; fi
HITS="$("${WP[@]}" eval "echo (int) \MrTu\Multilang\Redirects::all()['/fx-seo-old-$STAMP/']['hits'];" 2>/dev/null | tail -1)"
[ "${HITS:-0}" -ge 2 ] && pass "hit counter = $HITS" || fail "hit counter = ${HITS:-?}"
fetch "$PILLAR_EN"
[ "$STATUS" = 200 ] && pass "redirect target itself serves 200 (no loop)" || fail "target → $STATUS"

echo
echo "# JSON-LD"
jsonld() {
	php -r '
		$html = file_get_contents( $argv[1] );
		if ( ! preg_match( "~<script type=\"application/ld\+json\" class=\"mrtu-schema\">(.*?)</script>~s", $html, $m ) ) { exit( 2 ); }
		$data = json_decode( $m[1], true );
		if ( ! is_array( $data ) || empty( $data["@graph"] ) ) { exit( 3 ); }
		$types = array();
		foreach ( $data["@graph"] as $node ) { foreach ( (array) $node["@type"] as $t ) { $types[ $t ] = isset( $types[ $t ] ) ? $types[ $t ] + 1 : 1; } }
		$page = null; foreach ( $data["@graph"] as $node ) { if ( in_array( "WebPage", (array) $node["@type"], true ) ) { $page = $node; } }
		echo "WebSite=" . ( isset( $types["WebSite"] ) ? $types["WebSite"] : 0 ) . " FAQPage=" . ( isset( $types["FAQPage"] ) ? $types["FAQPage"] : 0 ) . " AutoRental=" . ( isset( $types["AutoRental"] ) ? $types["AutoRental"] : 0 ) . " inLanguage=" . ( $page ? $page["inLanguage"] : "-" ) . " url=" . ( $page ? $page["url"] : "-" ) . "\n";
	' "$TMP/body"
}
fetch /en/
INFO="$(jsonld)"; RC=$?
[ "$RC" = 0 ] && pass "/en/: JSON-LD parses ($INFO)" || fail "/en/: JSON-LD missing/invalid (rc $RC)"
echo "$INFO" | grep -q 'WebSite=1 ' && pass "/en/: exactly one WebSite node" || fail "/en/: WebSite count ($INFO)"
echo "$INFO" | grep -q 'inLanguage=en ' && pass "/en/: WebPage inLanguage en" || fail "/en/: inLanguage ($INFO)"
echo "$INFO" | grep -q "url=$BASE/en/" && pass "/en/: WebPage url = /en/" || fail "/en/: WebPage url ($INFO)"
fetch "$FAQ_EN"
INFO="$(jsonld)"; RC=$?
[ "$RC" = 0 ] && pass "$FAQ_EN: JSON-LD parses" || fail "$FAQ_EN: JSON-LD missing/invalid (rc $RC)"
echo "$INFO" | grep -q 'FAQPage=1 ' && pass "$FAQ_EN: FAQPage present on the guide" || fail "$FAQ_EN: FAQPage ($INFO)"
fetch "$PILLAR_EN"
INFO="$(jsonld)"
echo "$INFO" | grep -q 'FAQPage=0 ' && pass "$PILLAR_EN: no FAQPage on the pillar" || fail "$PILLAR_EN: FAQPage ($INFO)"
echo "$INFO" | grep -q 'WebSite=1 ' && pass "$PILLAR_EN: one WebSite node" || fail "$PILLAR_EN: WebSite ($INFO)"
fetch /
INFO="$(jsonld)"
echo "$INFO" | grep -q 'inLanguage=vi-VN ' && pass "/: WebPage inLanguage vi-VN" || fail "/: inLanguage ($INFO)"
[ "$(grep -c 'class="mrtu-schema"' "$TMP/body")" = 1 ] && pass "/: exactly one JSON-LD block" || fail "/: $(grep -c 'class="mrtu-schema"' "$TMP/body") JSON-LD blocks"

echo
echo "RESULT: $passed passed, $failed failed"
[ "$failed" -eq 0 ]
