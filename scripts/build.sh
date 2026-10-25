#!/usr/bin/env bash
#
# Builds installable ZIPs (WordPress Admin → Upload) into dist/:
#   dist/mr-tu-theme.zip            root folder: mr-tu/
#   dist/mr-tu-multilang-seo.zip    root folder: mr-tu-multilang-seo/ (+ data/ business seed, seed/ content)
# and, when the private assistant source exists locally:
#   dist-private/mr-tu-local-assistant.zip (git-ignored — deliver privately)
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$REPO/build"
cd "$REPO"

rm -rf "$BUILD" && mkdir -p "$BUILD" dist

if [ -d node_modules/lightningcss ]; then
	node scripts/build-css.mjs
fi

copy_tree() { # src dest — copies without dev-only files
	mkdir -p "$2"
	tar -C "$1" \
		--exclude='./tests' --exclude='./node_modules' --exclude='./vendor' \
		--exclude='./assets/css/src' --exclude='./assets/css/editor-src' \
		--exclude='*.map' --exclude='.DS_Store' --exclude='./phpcs.xml*' --exclude='./.git*' \
		-cf - . | tar -C "$2" -xf -
}

# Theme
copy_tree theme/mr-tu "$BUILD/mr-tu"
(cd "$BUILD" && rm -f "$REPO/dist/mr-tu-theme.zip" && zip -qr -X "$REPO/dist/mr-tu-theme.zip" mr-tu)

# Multilang SEO plugin (+ business seed + content seed)
copy_tree plugin/mr-tu-multilang-seo "$BUILD/mr-tu-multilang-seo"
mkdir -p "$BUILD/mr-tu-multilang-seo/data"
cp config/mr-tu-business.json "$BUILD/mr-tu-multilang-seo/data/mr-tu-business.json"
if [ -d content ] && [ -n "$(ls -A content 2>/dev/null)" ]; then
	mkdir -p "$BUILD/mr-tu-multilang-seo/seed"
	cp -r content/. "$BUILD/mr-tu-multilang-seo/seed/"
fi
(cd "$BUILD" && rm -f "$REPO/dist/mr-tu-multilang-seo.zip" && zip -qr -X "$REPO/dist/mr-tu-multilang-seo.zip" mr-tu-multilang-seo)

# Private assistant (never committed)
if [ -d private/mr-tu-local-assistant ]; then
	mkdir -p dist-private
	copy_tree private/mr-tu-local-assistant "$BUILD/mr-tu-local-assistant"
	(cd "$BUILD" && rm -f "$REPO/dist-private/mr-tu-local-assistant.zip" && zip -qr -X "$REPO/dist-private/mr-tu-local-assistant.zip" mr-tu-local-assistant)
fi

# Sanity: every PHP file in the packages lints, every ZIP has exactly one root folder.
fail=0
while IFS= read -r -d '' f; do
	php -l "$f" > /dev/null || { echo "LINT FAIL: $f"; fail=1; }
done < <(find "$BUILD" -name '*.php' -print0)
for z in dist/*.zip dist-private/*.zip; do
	[ -f "$z" ] || continue
	roots=$(unzip -Z1 "$z" | cut -d/ -f1 | sort -u | wc -l)
	[ "$roots" = "1" ] || { echo "ZIP ROOT FAIL: $z"; fail=1; }
	printf '%-45s %8s bytes  %s files\n' "$z" "$(stat -c%s "$z")" "$(unzip -Z1 "$z" | wc -l)"
done
rm -rf "$BUILD"
exit $fail
