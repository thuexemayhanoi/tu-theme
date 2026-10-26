#!/usr/bin/env bash
#
# Runs every tests/php/test-*.php through `wp eval-file` against a LOCAL WordPress env.
# Prints each assertion, per-file and total PASS/FAIL counts; exits 1 when anything fails
# (a failed assertion, a PHP warning/notice/fatal, or a file that did not finish).
#
# Usage: tests/run-php.sh [WP_ENV_DIR]   (default: $WP_ENV_DIR or /home/user/wpenv)
set -uo pipefail

ENV_DIR="${1:-${WP_ENV_DIR:-/home/user/wpenv}}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP=(php -d memory_limit=1024M "$ENV_DIR/bin/wp-cli.phar" --allow-root --path="$ENV_DIR/wordpress")

if [ ! -f "$ENV_DIR/bin/wp-cli.phar" ] || [ ! -d "$ENV_DIR/wordpress" ]; then
	echo "No WordPress test env at $ENV_DIR" >&2
	exit 2
fi

total_pass=0
total_fail=0
broken=0

# test-seo-yoast.php needs WPSEO_VERSION defined before plugins_loaded: the stub is installed as a
# must-use plugin for that single run and removed again (also on abort).
YOAST_STUB="$ROOT/tests/php/yoast-stub.php"
MU_STUB="$ENV_DIR/wordpress/wp-content/mu-plugins/zz-mrtu-yoast-stub.php"
trap 'rm -f "$MU_STUB"' EXIT

for file in "$ROOT"/tests/php/test-*.php; do
	[ -e "$file" ] || continue
	name="$(basename "$file")"
	if [ "$name" = "test-seo-yoast.php" ]; then
		mkdir -p "$(dirname "$MU_STUB")"
		cp "$YOAST_STUB" "$MU_STUB"
	fi
	output="$("${WP[@]}" eval-file "$file" 2>&1)"
	status=$?
	rm -f "$MU_STUB"
	echo "$output"

	pass=$(printf '%s\n' "$output" | grep -cE '^[[:space:]]*PASS:')
	fail=$(printf '%s\n' "$output" | grep -cE '^[[:space:]]*FAIL:')
	php_errors=$(printf '%s\n' "$output" | grep -cE '^(PHP )?(Warning|Notice|Deprecated|Fatal error|Parse error)')
	if [ "$php_errors" -gt 0 ]; then
		echo "  FAIL: $name emitted $php_errors PHP warning/notice/error line(s)"
		fail=$((fail + php_errors))
	fi
	if [ "$status" -ne 0 ] && [ "$fail" -eq 0 ]; then
		echo "  FAIL: $name exited with status $status"
		broken=$((broken + 1))
	fi
	if ! printf '%s\n' "$output" | grep -q '^RESULT:'; then
		echo "  FAIL: $name did not reach the end"
		broken=$((broken + 1))
	fi
	echo "-- $name: $pass passed, $fail failed"
	total_pass=$((total_pass + pass))
	total_fail=$((total_fail + fail))
done

total_fail=$((total_fail + broken))
echo
echo "TOTAL: $total_pass passed, $total_fail failed"
[ "$total_fail" -eq 0 ]
