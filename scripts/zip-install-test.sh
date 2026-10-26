#!/usr/bin/env bash
#
# Clean-install test for the release ZIPs (never production).
# Resets a throw-away local WordPress, removes the repo symlinks, installs dist/mr-tu-theme.zip and
# dist/mr-tu-multilang-seo.zip exactly like "Upload Plugin/Theme" would, activates them, seeds the five
# languages from the ZIP's own bundled content and runs smoke checks. Usage:
#   WP_ENV_DIR=/home/user/wpenv-theme WP_PORT=8081 scripts/zip-install-test.sh
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
WP_ENV_DIR="${WP_ENV_DIR:-/home/user/wpenv-theme}"
WP_PORT="${WP_PORT:-8081}"
WP_DIR="$WP_ENV_DIR/wordpress"
URL="http://localhost:$WP_PORT"
WP="php -d memory_limit=1024M $WP_ENV_DIR/bin/wp-cli.phar --allow-root --path=$WP_DIR"
fail=0
ok()   { printf 'PASS: %s\n' "$1"; }
# PHP log lines that are environment noise (no outbound network in the test container), never product code.
phperr(){ grep -E 'PHP (Fatal|Warning|Notice|Deprecated)' "$WP_ENV_DIR/debug.log" | grep -v -i 'wordpress.org' || true; }
bad()  { printf 'FAIL: %s\n' "$1"; fail=1; }
check(){ if eval "$2"; then ok "$1"; else bad "$1"; fi; }
# "echo big-html | grep -q" is unreliable under pipefail (grep exits early → SIGPIPE on echo); count instead.
has()  { [ "$(printf '%s' "$1" | grep -c -- "$2")" -gt 0 ]; }

[ -f "$REPO/dist/mr-tu-theme.zip" ] && [ -f "$REPO/dist/mr-tu-multilang-seo.zip" ] || { echo "Build the ZIPs first: bash scripts/build.sh"; exit 1; }

echo "== reset $WP_ENV_DIR ($URL)"
# Drop whatever a previous run left (repo symlinks or real ZIP-installed folders; rm -rf on a symlink removes only the link).
unlink_ours() { for d in "$WP_DIR/wp-content/themes/mr-tu" "$WP_DIR/wp-content/plugins/mr-tu-multilang-seo" "$WP_DIR/wp-content/plugins/mr-tu-local-assistant"; do [ -e "$d" ] || [ -L "$d" ] && rm -rf "$d"; done; return 0; }
unlink_ours
WP_ENV_DIR="$WP_ENV_DIR" WP_PORT="$WP_PORT" bash "$REPO/scripts/dev-env.sh" reset > /dev/null
# No repo symlinks and no repo seed constants: the ZIPs must be self-contained.
unlink_ours
sed -i "/MRTU_SEED_DIR/d" "$WP_DIR/wp-config.php"
: > "$WP_ENV_DIR/debug.log"
WP_ENV_DIR="$WP_ENV_DIR" WP_PORT="$WP_PORT" bash "$REPO/scripts/dev-env.sh" serve > /dev/null
sleep 1

echo "== install from ZIPs"
$WP plugin install "$REPO/dist/mr-tu-multilang-seo.zip" --activate --quiet
$WP theme install "$REPO/dist/mr-tu-theme.zip" --activate --quiet
check "plugin active"  "$WP plugin list --status=active --field=name | grep -qx mr-tu-multilang-seo"
check "theme active"   "[ \"\$($WP theme list --status=active --field=name)\" = mr-tu ]"
check "no PHP errors after activation" "[ -z \"\$(phperr)\" ]"

echo "== seed from the bundled content"
$WP mrtu seed --set-front > "$WP_ENV_DIR/seed.log" 2>&1 || true
tail -3 "$WP_ENV_DIR/seed.log"
check "seed created pages in 5 languages" "[ \"\$($WP post list --post_type=page --post_status=publish --format=count)\" -ge 50 ]"
check "no PHP errors after seeding" "[ -z \"\$(phperr)\" ]"

echo "== HTTP smoke"
for path in / /en/ /de/ /fr/ /es/ /mrtu-sitemap-index.xml /robots.txt; do
	code=$(curl -s -o /dev/null -w '%{http_code}' "$URL$path")
	[ "$code" = 200 ] && ok "GET $path → 200" || bad "GET $path → $code"
done
check "/en redirects to /en/" "[ \"\$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' $URL/en)\" = '301 $URL/en/' ]"
for lang in vi en de fr es; do
	p=/; [ "$lang" != vi ] && p="/$lang/"
	html=$(curl -s "$URL$p")
	has "$html" "<html lang=\"$lang" && ok "html lang $lang" || bad "html lang $lang"
	has "$html" 'hreflang="x-default"' && ok "x-default hreflang on $p" || bad "x-default hreflang on $p"
	[ "$(printf '%s' "$html" | grep -c 'rel="canonical"')" = 1 ] && ok "one canonical on $p" || bad "canonical count on $p"
done
html=$(curl -s "$URL/")
has "$html" '0942 467 674\|Nguyễn Văn Cừ' && bad "wrong NAP leaked" || ok "no Nguyễn Tú Long Biên NAP"
has "$html" 'mr-tu/assets/css/main.css' && ok "theme CSS enqueued" || bad "theme CSS enqueued"
has "$html" 'application/ld+json' && ok "JSON-LD present" || bad "JSON-LD present"
check "health check has no errors (wp mrtu health exits 0)" "$WP mrtu health > $WP_ENV_DIR/health.log 2>&1"
check "no PHP errors after smoke" "[ -z \"\$(phperr)\" ]"

echo "== deactivate / reactivate (rollback path)"
$WP plugin deactivate mr-tu-multilang-seo --quiet
check "site still 200 with plugin off" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $URL/)\" = 200 ]"
$WP plugin activate mr-tu-multilang-seo --quiet
check "site 200 after reactivation" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $URL/en/)\" = 200 ]"
$WP theme activate twentytwentyfive --quiet 2>/dev/null || true
check "site 200 with default theme (plugin only)" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $URL/en/)\" = 200 ]"
$WP theme activate mr-tu --quiet
check "no PHP errors overall" "[ -z \"\$(phperr)\" ]"
phperr | head -5

[ "$fail" = 0 ] && echo "ZIP INSTALL TEST: PASS" || { echo "ZIP INSTALL TEST: FAIL"; exit 1; }
