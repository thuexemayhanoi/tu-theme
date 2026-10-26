#!/usr/bin/env bash
#
# HTTP check (curl) of the four business blocks on a real page of the LOCAL dev server.
# Creates the page with `wp post create` (block markup), fetches it with curl, checks the HTML,
# then deletes the page and restores the options (fixture teardown).
#
#   WP_URL=http://localhost:8083 \
#   WP_CLI="php -d memory_limit=1024M /home/user/wpenv-business/bin/wp-cli.phar --allow-root --path=/home/user/wpenv-business/wordpress" \
#   bash tests/e2e/business/http-check.sh
#
# Prints PASS/FAIL lines and "RESULT: passed/total"; exits 1 on failure.
set -uo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
WP_URL="${WP_URL:-http://localhost:8083}"
WP_CLI="${WP_CLI:-wp}"
BODY="$(mktemp)"
pass=0
fail=0

ok() {
	if [ "$1" = 1 ]; then pass=$((pass + 1)); echo "PASS: $2"; else fail=$((fail + 1)); echo "FAIL: $2"; fi
}
has() { if grep -qF -- "$1" "$BODY"; then ok 1 "$2"; else ok 0 "$2"; fi; }
lacks() { if grep -qF -- "$1" "$BODY"; then ok 0 "$2"; else ok 1 "$2"; fi; }
fetch() { curl -sSL -o "$BODY" -w '%{http_code}' "$1"; }

# Known state: seed data, reference prices OFF (unverified prices must not appear).
$WP_CLI eval-file "$HERE/fixture.php" state no-reference > /dev/null

CONTENT='<!-- wp:mrtu/pricing-table {"period":"week"} /-->

<!-- wp:mrtu/fleet /-->

<!-- wp:mrtu/contact-card /-->

<!-- wp:mrtu/cta {"variant":"call"} /-->'
ID="$($WP_CLI post create --post_type=page --post_status=publish --post_title='Mr Tu blocks (curl check)' --post_content="$CONTENT" --meta_input='{"_mrtu_e2e":"1"}' --porcelain)"
URL="$WP_URL/?page_id=$ID"

CODE="$(fetch "$URL")"
[ "$CODE" = 200 ] && ok 1 "page $URL returns 200" || ok 0 "page $URL returns 200 (got $CODE)"
has 'data-mrtu-pricing' 'pricing-table rendered (data-mrtu-pricing)'
has 'role="tablist"' 'pricing-table tablist'
if grep -qE 'id="mrtu-pt-[0-9]+-week" aria-controls="mrtu-pp-[0-9]+-week" aria-selected="true"' "$BODY"; then ok 1 'weekly tab selected from the block attribute'; else ok 0 'weekly tab selected from the block attribute'; fi
has 'mrtu-price--on-request' 'unverified prices render as "price on request"'
has 'mrtu-badge--pending' 'price list status badge'
lacks '150.000' 'no unverified daily price text'
lacks '700.000' 'no unverified weekly price text'
lacks '1.200.000' 'no unverified monthly price text'
lacks 'Yamaha Sirius' 'hidden vehicles not rendered'
has 'data-mrtu-fleet' 'fleet rendered'
has 'data-filter="scooter"' 'fleet filter chips'
has 'mrtu-contact-card' 'contact card rendered'
has 'data-mrtu-qc-avoid' 'contact card is a Quick Contact avoid zone'
lacks 'Phúc Tân' 'unverified address not shown'
has 'mrtu-cta--call' 'CTA rendered'
has 'href="tel:+84816659199"' 'verified phone used for call links'
has 'class="mrtu-qc"' 'Quick Contact dock in the footer'
has "classList.add('mrtu-js')" 'mrtu-js flag script in the head'
for asset in assets/css/blocks.css assets/js/pricing.js assets/js/fleet.js assets/css/quick-contact.css assets/js/quick-contact.js; do
	has "$asset" "$asset enqueued"
	ASSET_CODE="$(curl -s -o /dev/null -w '%{http_code}' "$WP_URL/wp-content/plugins/mr-tu-multilang-seo/$asset")"
	[ "$ASSET_CODE" = 200 ] && ok 1 "$asset served (200)" || ok 0 "$asset served (got $ASSET_CODE)"
done

# Reference prices ON: amounts appear, flagged as reference.
$WP_CLI eval-file "$HERE/fixture.php" state > /dev/null
fetch "$URL" > /dev/null
has 'mrtu-price--reference' 'reference state when show_reference_prices is on'
has 'mrtu-pricing__note--reference' 'reference legend'
has '150.000' 'reference amount shown'

$WP_CLI post delete "$ID" --force > /dev/null
$WP_CLI eval-file "$HERE/fixture.php" teardown > /dev/null
rm -f "$BODY"

echo "RESULT: $pass/$((pass + fail))"
[ "$fail" = 0 ]
