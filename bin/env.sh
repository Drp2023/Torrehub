#!/usr/bin/env bash
# Recreates the LocalWP "Open site shell" environment for Git Bash.
# Usage: source bin/env.sh
# Override any value via env before sourcing (e.g. LOCAL_SITE_ID=xxx).

: "${LOCAL_SITE_ID:=1K5Hwz2sd}"
: "${LOCAL_SITE_DIR:=/c/Users/Miklos/Local Sites/torrehub-website}"
: "${LOCAL_SERVICES:=/c/Users/Miklos/AppData/Roaming/Local/lightning-services}"
: "${LOCAL_PHP_VER:=php-8.2.27+1}"
: "${LOCAL_MYSQL_VER:=mysql-8.0.35+4}"
: "${LOCAL_WPCLI:=/c/Users/Miklos/AppData/Local/Programs/Local/resources/extraResources/bin/wp-cli/wp-cli.phar}"

export REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export WP_PATH="$LOCAL_SITE_DIR/app/public"
export LOCAL_RUN="/c/Users/Miklos/AppData/Roaming/Local/run/$LOCAL_SITE_ID"
# Native Windows binaries need Windows-style paths in env vars (MSYS only converts argv).
if command -v cygpath >/dev/null 2>&1; then
  export MYSQL_HOME="$(cygpath -w "$LOCAL_RUN/conf/mysql")"
else
  export MYSQL_HOME="$LOCAL_RUN/conf/mysql"
fi
export PHPRC="$LOCAL_RUN/conf/php"
export PATH="$LOCAL_SERVICES/$LOCAL_PHP_VER/bin/win64:$LOCAL_SERVICES/$LOCAL_MYSQL_VER/bin/win64/bin:$PATH"
export LOCAL_MYSQL_ARGS="--defaults-file=$LOCAL_RUN/conf/mysql/my.cnf"

wp() {
  # imagick.dll is missing in Local's PHP build; silence the harmless startup warning.
  php -d display_startup_errors=0 -c "$PHPRC/php.ini" "$LOCAL_WPCLI" --path="$WP_PATH" "$@"
}
export -f wp
