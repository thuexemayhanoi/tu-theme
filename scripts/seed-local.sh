#!/usr/bin/env bash
#
# Dev helper (LOCAL only): fresh WordPress env → activate plugin + theme → seed the five languages
# with the Reading settings applied → print the language home URLs.
#
# Usage: WP_ENV_DIR=/home/user/wpenv WP_PORT=8080 scripts/seed-local.sh [--serve] [--no-reset] [seed flags…]
#   --serve     start the PHP built-in server afterwards (scripts/dev-env.sh serve)
#   --no-reset  keep the existing database (only re-link, activate and seed)
#   seed flags  passed through to `wp mrtu seed` (e.g. --dry-run, --update, --lang=en)
#
# Never points at production: the env is the SQLite install under $WP_ENV_DIR.
set -euo pipefail

export WP_ENV_DIR="${WP_ENV_DIR:-/home/user/wpenv}"
export WP_PORT="${WP_PORT:-8080}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP=(php -d memory_limit=1024M "$WP_ENV_DIR/bin/wp-cli.phar" --allow-root --path="$WP_ENV_DIR/wordpress")

SERVE=0
RESET=1
SEED_FLAGS=()
for arg in "$@"; do
	case "$arg" in
		--serve) SERVE=1 ;;
		--no-reset) RESET=0 ;;
		--help | -h)
			sed -n '2,12p' "$0"
			exit 0
			;;
		*) SEED_FLAGS+=("$arg") ;;
	esac
done

if [ "$RESET" -eq 1 ]; then
	echo "== Resetting $WP_ENV_DIR (port $WP_PORT)"
	bash "$ROOT/scripts/dev-env.sh" reset
else
	echo "== Linking $ROOT into $WP_ENV_DIR"
	bash "$ROOT/scripts/dev-env.sh" link
fi

if [ ! -f "$WP_ENV_DIR/bin/wp-cli.phar" ] || [ ! -d "$WP_ENV_DIR/wordpress" ]; then
	echo "No WordPress env at $WP_ENV_DIR" >&2
	exit 2
fi

echo "== Activating plugin and theme"
"${WP[@]}" plugin activate mr-tu-multilang-seo
"${WP[@]}" theme activate mr-tu

echo "== Seeding content (wp mrtu seed --set-front ${SEED_FLAGS[*]:-})"
"${WP[@]}" mrtu seed --set-front "${SEED_FLAGS[@]}"

echo "== Language home pages"
"${WP[@]}" eval '
foreach ( mrtu_languages( true ) as $code => $lang ) {
	printf( "  %s  %s\n", $code, mrtu_home_url( "", $code ) );
}
'

if [ "$SERVE" -eq 1 ]; then
	bash "$ROOT/scripts/dev-env.sh" serve
fi
