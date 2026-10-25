#!/usr/bin/env bash
#
# Reproducible LOCAL test environment for Mr Tu (never touches production).
# WordPress (GitHub mirror tag) + SQLite Database Integration + WP-CLI, with the repo's
# theme/plugins symlinked in. Usage:
#   scripts/dev-env.sh install   # download + install WordPress into $WP_ENV_DIR
#   scripts/dev-env.sh link      # (re)link theme + plugins from this repo
#   scripts/dev-env.sh serve     # start php -S on 127.0.0.1:8080 (background)
#   scripts/dev-env.sh reset     # wipe the SQLite DB and reinstall
set -euo pipefail

REPO="${REPO_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
WP_ENV_DIR="${WP_ENV_DIR:-/home/user/wpenv}"
WP_DIR="$WP_ENV_DIR/wordpress"
WP_TAG="${WP_TAG:-7.1.3}"
PORT="${WP_PORT:-8080}"
URL="${WP_URL:-http://localhost:$PORT}"
WP="php -d memory_limit=1024M $WP_ENV_DIR/bin/wp-cli.phar --allow-root --path=$WP_DIR"

install_wp() {
	mkdir -p "$WP_ENV_DIR/bin" "$WP_ENV_DIR/db"
	[ -f "$WP_ENV_DIR/bin/wp-cli.phar" ] || curl -sSL -o "$WP_ENV_DIR/bin/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	if [ ! -d "$WP_DIR" ] && [ -d /home/user/wpenv/wordpress ] && [ "$WP_DIR" != /home/user/wpenv/wordpress ]; then
		mkdir -p "$WP_DIR" && (cd /home/user/wpenv/wordpress && tar --exclude=./wp-config.php --exclude=./wp-content/themes/mr-tu --exclude=./wp-content/plugins/mr-tu-multilang-seo --exclude=./wp-content/plugins/mr-tu-local-assistant --exclude=./wp-content/plugins/plugin-check --exclude=./wp-content/uploads -cf - .) | (cd "$WP_DIR" && tar -xf -)
	fi
	[ -d "$WP_DIR" ] || GIT_LFS_SKIP_SMUDGE=1 git clone --depth 1 --branch "$WP_TAG" https://github.com/WordPress/WordPress.git "$WP_DIR"
	local S="$WP_ENV_DIR/sqlite-database-integration"
	[ -d "$S" ] || GIT_LFS_SKIP_SMUDGE=1 git clone --depth 1 https://github.com/WordPress/sqlite-database-integration.git "$S"
	local P="$WP_DIR/wp-content/plugins/sqlite-database-integration"
	rm -rf "$P" && cp -r "$S/packages/plugin-sqlite-database-integration" "$P"
	rm -rf "$P/wp-includes/database" && mkdir -p "$P/wp-includes" && cp -r "$S/packages/mysql-on-sqlite/src" "$P/wp-includes/database"
	cp "$P/db.copy" "$WP_DIR/wp-content/db.php"
	sed -i "s#'{SQLITE_IMPLEMENTATION_FOLDER_PATH}'#__DIR__.'/plugins/sqlite-database-integration'#g; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" "$WP_DIR/wp-content/db.php"
	if [ ! -f "$WP_DIR/wp-config.php" ]; then
		cat > "$WP_DIR/wp-config.php" <<EOF
<?php
define( 'DB_NAME', 'wordpress' ); define( 'DB_USER', '' ); define( 'DB_PASSWORD', '' ); define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
define( 'DB_DIR', '$WP_ENV_DIR/db/' ); define( 'DB_FILE', 'wp.sqlite' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as \$k ) { define( \$k, 'local-test-only-' . \$k ); }
\$table_prefix = 'wp_';
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', '$WP_ENV_DIR/debug.log' ); define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' ); define( 'DISABLE_WP_CRON', true );
define( 'MRTU_SEED_DIR', '$REPO/content' ); define( 'MRTU_BUSINESS_SEED', '$REPO/config/mr-tu-business.json' );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
EOF
	fi
	$WP core is-installed 2>/dev/null || $WP core install --url="$URL" --title="Mr Tu" --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email
	$WP option update permalink_structure '/%postname%/'
	$WP option update blogdescription ''
	$WP rewrite flush
}

link_repo() {
	ln -sfn "$REPO/theme/mr-tu" "$WP_DIR/wp-content/themes/mr-tu"
	ln -sfn "$REPO/plugin/mr-tu-multilang-seo" "$WP_DIR/wp-content/plugins/mr-tu-multilang-seo"
	if [ -d "$REPO/private/mr-tu-local-assistant" ]; then
		ln -sfn "$REPO/private/mr-tu-local-assistant" "$WP_DIR/wp-content/plugins/mr-tu-local-assistant"
	fi
}

serve() {
	pkill -f "php -d memory_limit=512M -S 127.0.0.1:$PORT" 2>/dev/null || true
	nohup php -d memory_limit=512M -S 127.0.0.1:$PORT -t "$WP_DIR" "$REPO/scripts/router.php" > "$WP_ENV_DIR/server.log" 2>&1 &
	disown || true
	sleep 1 && echo "Serving $URL (log: $WP_ENV_DIR/server.log)"
}

case "${1:-}" in
	install) install_wp; link_repo ;;
	link) link_repo ;;
	serve) serve ;;
	reset) rm -f "$WP_ENV_DIR/db/wp.sqlite"; install_wp; link_repo ;;
	*) echo "usage: $0 install|link|serve|reset"; exit 1 ;;
esac
