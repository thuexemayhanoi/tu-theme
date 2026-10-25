#!/usr/bin/env bash
#
# Local QA gate. Run before every commit:
#   scripts/qa.sh [--env /home/user/wpenv] [--skip-tests]
# Steps: php -l · PHPCS WordPress (errors fail) · PHPCompatibilityWP 7.4+ · integration tests · build.
set -uo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
ENV_DIR="/home/user/wpenv"
SKIP_TESTS=0
while [ $# -gt 0 ]; do
	case "$1" in
		--env) ENV_DIR="$2"; shift 2 ;;
		--skip-tests) SKIP_TESTS=1; shift ;;
		*) shift ;;
	esac
done
PHPCS="${PHPCS:-/home/user/qa-tools/vendor/bin/phpcs}"
TARGETS=( "$REPO/theme/mr-tu" "$REPO/plugin/mr-tu-multilang-seo" )
[ -d "$REPO/private/mr-tu-local-assistant" ] && TARGETS+=( "$REPO/private/mr-tu-local-assistant" )
status=0

echo "== php -l"
while IFS= read -r -d '' f; do
	php -l "$f" > /dev/null 2>&1 || { php -l "$f"; status=1; }
done < <(find "${TARGETS[@]}" -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0)

if [ -x "$PHPCS" ]; then
	echo "== PHPCS WordPress (errors)"
	"$PHPCS" --standard="$REPO/phpcs.xml.dist" -n --report=summary "${TARGETS[@]}" || status=1
	echo "== PHPCompatibilityWP 7.4-"
	"$PHPCS" --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- -n --extensions=php --report=summary "${TARGETS[@]}" || status=1
else
	echo "!! phpcs not found at $PHPCS (skipped)"
fi

if [ "$SKIP_TESTS" = "0" ] && [ -x "$REPO/tests/run-php.sh" ]; then
	echo "== integration tests ($ENV_DIR)"
	"$REPO/tests/run-php.sh" "$ENV_DIR" || status=1
fi

echo "== build"
bash "$REPO/scripts/build.sh" || status=1

[ $status = 0 ] && echo "QA: PASS" || echo "QA: FAIL"
exit $status
