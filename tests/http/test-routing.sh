#!/usr/bin/env bash
#
# HTTP routing assertions for the Mr Tu multilingual router (LOCAL servers only).
# Checks status codes, redirects, <html lang>, language-scoped listings and the switcher shortcode.
# Canonical/hreflang tags belong to the SEO module and are not asserted here.
#
# Usage: tests/http/test-routing.sh [BASE_URL] [WP_ENV_DIR]
#   BASE_URL   default $BASE_URL or http://localhost:8080 (must be localhost/127.0.0.1)
#   WP_ENV_DIR default $WP_ENV_DIR or /home/user/wpenv (used to create fixtures through WP-CLI)
#
# The site must use the twentytwentyfive theme (or any theme printing language_attributes()).
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
trap 'rm -rf "$TMP"; "${WP[@]}" eval-file "$ROOT/tests/php/fixtures-core.php" enable es >/dev/null 2>&1 || true' EXIT

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

html_lang() {
	grep -o '<html[^>]*' "$TMP/body" | head -1 | grep -o ' lang="[^"]*"' | head -1 | cut -d'"' -f2
}

has() { grep -qF -- "$1" "$TMP/body"; }

# check_page PATH STATUS LANG [MUST_CONTAIN] [MUST_NOT_CONTAIN]
check_page() {
	local path="$1" want="$2" lang="$3" yes="${4:-}" no="${5:-}"
	fetch "$path"
	local label="$path → $want"
	[ -n "$lang" ] && label="$label, lang=$lang"
	if [ "$STATUS" != "$want" ]; then
		fail "$label (got HTTP $STATUS${LOCATION:+ → $LOCATION})"
		return
	fi
	if [ -n "$lang" ] && [ "$(html_lang)" != "$lang" ]; then
		fail "$label (got <html lang=\"$(html_lang)\">)"
		return
	fi
	if [ -n "$yes" ] && ! has "$yes"; then
		fail "$label (missing \"$yes\")"
		return
	fi
	if [ -n "$no" ] && has "$no"; then
		fail "$label (unexpected \"$no\")"
		return
	fi
	pass "$label${yes:+ [has $yes]}${no:+ [not $no]}"
}

# check_redirect PATH CODE TARGET_PATH
check_redirect() {
	local path="$1" code="$2" target="$BASE$3"
	fetch "$path"
	if [ "$STATUS" = "$code" ] && [ "$LOCATION" = "$target" ]; then
		pass "$path → $code $3"
	else
		fail "$path → $code $3 (got HTTP $STATUS → ${LOCATION:-none})"
	fi
}

echo "== HTTP routing ($BASE) =="

if ! curl -s -o /dev/null "$BASE/"; then
	echo "Server not reachable at $BASE" >&2
	exit 2
fi

"${WP[@]}" eval-file "$ROOT/tests/php/fixtures-core.php" >"$TMP/fx" 2>&1 || {
	cat "$TMP/fx"
	echo "Fixture creation failed" >&2
	exit 1
}
fx() { grep "^$1=" "$TMP/fx" | head -1 | cut -d= -f2-; }

echo
echo "# Homes and <html lang>"
check_page / 200 vi-VN FX-HOME-VI
check_page /en/ 200 en FX-HOME-EN FX-HOME-VI
check_page /de/ 200 de FX-HOME-DE
check_page /fr/ 200 fr FX-HOME-FR
check_page /es/ 200 es FX-HOME-ES
check_redirect /en 301 /en/
check_redirect '/en?utm_source=test' 301 '/en/?utm_source=test'
check_redirect "/en/$(fx FX_EN_HOME_SLUG)/" 301 /en/

echo
echo "# Pages per language"
check_page "/de/$(fx FX_DE_ONLY_SLUG)/" 200 de FX-DE-ONLY
check_page "/$(fx FX_DE_ONLY_SLUG)/" 404 vi-VN
check_page "/en/$(fx FX_DE_ONLY_SLUG)/" 404 en
check_page "/$(fx FX_VI_ONLY_SLUG)/" 200 vi-VN FX-VI-ONLY
check_page "/en/$(fx FX_VI_ONLY_SLUG)/" 404 en
check_page "/fr/$(fx FX_FR_FAQ_SLUG)/" 200 fr FX-FAQ-FR FX-FAQ-EN
check_page "/en/$(fx FX_EN_FAQ_SLUG)/" 200 en FX-FAQ-EN FX-FAQ-FR
check_page "/de/$(fx FX_DE_FAQ_SLUG)/" 200 de FX-FAQ-DE FX-FAQ-EN
check_page "/$(fx FX_EN_FAQ_SLUG)/" 404 vi-VN
check_redirect /en/faq 301 /en/faq/
check_page /en/does-not-exist/ 404 en

echo
echo "# Language switcher (shortcode on the FAQ pages)"
fetch /en/faq/
if has "href=\"$BASE/fr/faq/\" hreflang=\"fr\" lang=\"fr\"" && has "href=\"$BASE/cau-hoi-thuong-gap/\" hreflang=\"vi-VN\" lang=\"vi-VN\"" && has 'aria-current="page"' && has "href=\"$BASE/es/\" hreflang=\"es\""; then
	pass "/en/faq/ switcher links translations, current page and the ES home fallback"
else
	fail "/en/faq/ switcher markup"
fi

echo
echo "# Search, posts page, pagination, categories, feeds"
check_page '/en/?s=test' 200 en 'FX-EN-POST 1 English test article' FX-VI-POST
check_page '/?s=test' 200 vi-VN 'FX-VI-POST' FX-EN-POST
check_page "/en/$(fx FX_EN_BLOG_SLUG)/" 200 en FX-EN-POST FX-VI-POST
check_page "/en/$(fx FX_EN_BLOG_SLUG)/page/2/" 200 en FX-EN-POST FX-VI-POST
check_page "/en/$(fx FX_EN_BLOG_SLUG)/page/3/" 404 en
check_page "/en/category/$(fx FX_EN_CATEGORY)/" 200 en FX-EN-POST FX-VI-POST
check_page "/category/$(fx FX_EN_CATEGORY)/" 404 vi-VN
check_page /category/meo-thue-xe/ 200 vi-VN FX-VI-POST FX-EN-POST
fetch /en/feed/
if [ "$STATUS" = 200 ] && echo "$CTYPE" | grep -qi xml && has '<item>' && has FX-EN-POST && ! has FX-VI-POST; then
	pass "/en/feed/ → 200 XML with EN items only"
else
	fail "/en/feed/ (HTTP $STATUS, $CTYPE)"
fi

echo
echo "# Canonical redirects stay correct"
check_redirect "/?p=$(fx FX_VI_POST_ID)" 301 /fx-meo-1/
check_redirect "/en/?p=$(fx FX_VI_POST_ID)" 301 /fx-meo-1/

echo
echo "# Disabled language"
"${WP[@]}" eval-file "$ROOT/tests/php/fixtures-core.php" disable es >/dev/null
check_page /es/ 404 vi-VN
check_page /es/inicio/ 404 vi-VN
"${WP[@]}" eval-file "$ROOT/tests/php/fixtures-core.php" enable es >/dev/null
check_page /es/ 200 es FX-HOME-ES

echo
echo "# Core endpoints unaffected"
fetch /wp-json/
if [ "$STATUS" = 200 ] && echo "$CTYPE" | grep -qi json; then pass "/wp-json/ → 200 JSON"; else fail "/wp-json/ (HTTP $STATUS, $CTYPE)"; fi
fetch '/?rest_route=/'
if [ "$STATUS" = 200 ] && echo "$CTYPE" | grep -qi json; then pass "/?rest_route=/ → 200 JSON"; else fail "/?rest_route=/ (HTTP $STATUS)"; fi
fetch /wp-admin/
if [ "$STATUS" = 302 ] && echo "$LOCATION" | grep -q 'wp-login.php'; then pass "/wp-admin/ → 302 login"; else fail "/wp-admin/ (HTTP $STATUS → $LOCATION)"; fi
fetch /wp-login.php
if [ "$STATUS" = 200 ]; then pass "/wp-login.php → 200"; else fail "/wp-login.php (HTTP $STATUS)"; fi

echo
echo "# Preview of an EN draft"
PREVIEW="$(fx FX_EN_DRAFT_PREVIEW)"
check_page "$PREVIEW" 404 ''
COOKIE="$("${WP[@]}" eval-file "$ROOT/tests/php/fixtures-core.php" cookie | grep '^FX_COOKIE=' | cut -d= -f2-)"
fetch "$PREVIEW" -H "Cookie: $COOKIE"
if [ "$STATUS" = 200 ] && [ "$(html_lang)" = en ] && has FX-EN-DRAFT-BODY; then
	pass "$PREVIEW as admin → 200, lang=en, draft content"
else
	fail "$PREVIEW as admin (HTTP $STATUS, lang=$(html_lang))"
fi

echo
echo "RESULT: $passed passed, $failed failed"
[ "$failed" -eq 0 ]
