#!/usr/bin/env bash
#
# HTTP assertions for a seeded five-language site (LOCAL servers only).
# Runs `wp mrtu seed --set-front` (idempotent) and then checks, for vi/en/de/fr/es: the home page with the
# right <html lang>, the pillar / pricing / guide-faq / contact pages with their block markers, one post, the
# category archive, 404s for unknown and foreign-language slugs, the /en → /en/ redirect and the switcher.
#
# Usage: tests/http/test-seeded-site.sh [BASE_URL] [WP_ENV_DIR] [--no-seed]
#   BASE_URL   default $BASE_URL or http://localhost:8080 (must be localhost/127.0.0.1)
#   WP_ENV_DIR default $WP_ENV_DIR or /home/user/wpenv (used to seed and to look up URLs through WP-CLI)
set -uo pipefail

SEED=1
ARGS=()
for arg in "$@"; do
	case "$arg" in
		--no-seed) SEED=0 ;;
		*) ARGS+=("$arg") ;;
	esac
done
BASE="${ARGS[0]:-${BASE_URL:-http://localhost:8080}}"
BASE="${BASE%/}"
ENV_DIR="${ARGS[1]:-${WP_ENV_DIR:-/home/user/wpenv}}"
WP=(php -d memory_limit=1024M "$ENV_DIR/bin/wp-cli.phar" --allow-root --path="$ENV_DIR/wordpress")

case "$BASE" in
	http://localhost:* | http://localhost | http://127.0.0.1:* | http://127.0.0.1) ;;
	*)
		echo "Refusing to run against $BASE: local servers only." >&2
		exit 2
		;;
esac
if [ ! -f "$ENV_DIR/bin/wp-cli.phar" ] || [ ! -d "$ENV_DIR/wordpress" ]; then
	echo "No WordPress env at $ENV_DIR" >&2
	exit 2
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

passed=0
failed=0
pass() { passed=$((passed + 1)); echo "  PASS: $1"; }
fail() { failed=$((failed + 1)); echo "  FAIL: $1"; }

STATUS=''
LOCATION=''

# fetch PATH → STATUS, LOCATION, body in $TMP/body
fetch() {
	STATUS="$(curl -s -o "$TMP/body" -D "$TMP/headers" -w '%{http_code}' "$BASE$1")"
	LOCATION="$(grep -i '^location:' "$TMP/headers" | tail -1 | cut -d' ' -f2- | tr -d '\r')"
}

html_lang() {
	grep -o '<html[^>]*' "$TMP/body" | head -1 | grep -o ' lang="[^"]*"' | head -1 | cut -d'"' -f2
}

has() { grep -qF -- "$1" "$TMP/body"; }

# check_page PATH STATUS LANG [MUST_CONTAIN]
check_page() {
	local path="$1" want="$2" lang="$3" yes="${4:-}"
	if [ -z "$path" ]; then
		fail "missing URL for: $want $lang $yes"
		return
	fi
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
	pass "$label${yes:+ [has $yes]}"
}

echo "== HTTP seeded site ($BASE) =="

if ! curl -s -o /dev/null "$BASE/"; then
	echo "Server not reachable at $BASE" >&2
	exit 2
fi

if [ "$SEED" -eq 1 ]; then
	if ! "${WP[@]}" mrtu seed --set-front >"$TMP/seed" 2>&1; then
		cat "$TMP/seed"
		echo "Seeding failed" >&2
		exit 1
	fi
	grep -E '^(Success|Error)' "$TMP/seed" | head -2 | sed 's/^/  /'
fi

# One line per language and key: "<lang> <key>=<root-relative path>" (looked up through the plugin API, not guessed).
"${WP[@]}" eval '
foreach ( mrtu_languages( true ) as $code => $lang ) {
	echo $code, " hreflang=", $lang["hreflang"], "\n";
	foreach ( array( "home", "pillar", "pricing", "guide-faq", "contact" ) as $role ) {
		$url = MrTu\Multilang\Roles::role_url( $role, $code );
		echo $code, " ", $role, "=", "" !== $url ? wp_make_link_relative( $url ) : "", "\n";
	}
	$posts = get_posts( array( "post_type" => "post", "post_status" => "publish", "numberposts" => 1, "orderby" => "ID", "order" => "ASC", "suppress_filters" => true, "mrtu_lang" => $code, "meta_key" => "_mrtu_seed_id" ) );
	echo $code, " post=", $posts ? wp_make_link_relative( get_permalink( $posts[0] ) ) : "", "\n";
	$cat = "";
	foreach ( get_terms( array( "taxonomy" => "category", "hide_empty" => false, "meta_key" => "_mrtu_seed_id" ) ) as $term ) {
		if ( MrTu\Multilang\Translations::get_term_language( $term->term_id ) === $code ) {
			$cat = wp_make_link_relative( get_term_link( $term ) );
			break;
		}
	}
	echo $code, " category=", $cat, "\n";
	echo $code, " pillar_slug=", get_post_field( "post_name", MrTu\Multilang\Roles::get_page_id_by_role( "pillar", $code ) ), "\n";
}
' >"$TMP/urls" 2>"$TMP/urls.err" || {
	cat "$TMP/urls.err"
	echo "URL lookup failed" >&2
	exit 1
}
u() { grep "^$1 $2=" "$TMP/urls" | head -1 | cut -d= -f2-; }

LANGS="vi en de fr es"
for code in $LANGS; do
	echo
	echo "# $code"
	hl="$(u "$code" hreflang)"
	prefix=""
	[ "$code" != vi ] && prefix="/$code"
	check_page "$(u "$code" home)" 200 "$hl" 'class="mrtu-lang__link'
	check_page "$(u "$code" pillar)" 200 "$hl" 'class="mrtu-lang__link'
	check_page "$(u "$code" pricing)" 200 "$hl" mrtu-pricing
	check_page "$(u "$code" guide-faq)" 200 "$hl" mrtu-toc
	check_page "$(u "$code" contact)" 200 "$hl" mrtu-contact-card
	check_page "$(u "$code" post)" 200 "$hl"
	check_page "$(u "$code" category)" 200 "$hl"
	check_page "$prefix/does-not-exist/" 404 ''
	if [ "$code" != vi ]; then
		check_page "$prefix/$(u vi pillar_slug)/" 404 ''
	fi
done

echo
echo "# Redirects"
fetch /en
if [ "$STATUS" = 301 ] && [ "$LOCATION" = "$BASE/en/" ]; then
	pass "/en → 301 /en/"
else
	fail "/en → 301 /en/ (got HTTP $STATUS → ${LOCATION:-none})"
fi
check_page "/en/$(u vi pillar_slug)/" 404 ''

echo
echo "# Switcher"
fetch "$(u en pillar)"
count="$(grep -o '<a class="mrtu-lang__link[^>]*hreflang="[^"]*"' "$TMP/body" | grep -o 'hreflang="[^"]*"' | sort -u | wc -l | tr -d ' ')"
if [ "$count" = 5 ]; then
	pass "switcher on the EN pillar links the 5 languages"
else
	fail "switcher on the EN pillar links $count languages (want 5)"
fi
for code in $LANGS; do
	target="$(u "$code" pillar)"
	if grep -qF "href=\"$BASE$target\"" "$TMP/body"; then
		pass "switcher → $code pillar ($target)"
	else
		fail "switcher → $code pillar ($target) missing"
	fi
done

echo
echo "RESULT: $passed passed, $failed failed"
[ "$failed" -eq 0 ]
